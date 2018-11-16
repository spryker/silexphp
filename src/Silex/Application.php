<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex;

use Pimple;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Silex\EventListener\ConverterListener;
use Silex\EventListener\MiddlewareListener;
use Silex\EventListener\StringToResponseListener;
use Silex\Provider\RoutingServiceProvider;
use Spryker\Shared\ApplicationExtension\Provider\BootableServiceInterface;
use Spryker\Shared\ApplicationExtension\Provider\EventSubscriberInterface;
use Spryker\Shared\ApplicationExtension\Provider\ServiceProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * The Silex framework class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends Pimple implements HttpKernelInterface, TerminableInterface, ContainerInterface
{
    public const VERSION = '1.3.6';

    public const EARLY_EVENT = 512;
    public const LATE_EVENT = -512;

    protected $providers = [];

    protected $eventSubscribers = [];

    protected $booted = false;

    /**
     * Instantiate a new Application.
     *
     * Objects and parameters can be passed as argument to the constructor.
     *
     * @param array $services The parameters or objects.
     *
     * @throws \RuntimeException
     */
    public function __construct(array $services = [])
    {
        parent::__construct();

        $app = $this;

        $this['logger'] = null;

        $this->register(new RoutingServiceProvider());

        $this['exception_handler'] = $this->share(function () use ($app) {
            return new ExceptionHandler($app['debug']);
        });

        $this['dispatcher_class'] = 'Symfony\\Component\\EventDispatcher\\EventDispatcher';
        $this['dispatcher'] = $this->share(function () use ($app) {
            /*
             * @var EventDispatcherInterface
             */
            $dispatcher = new $app['dispatcher_class']();

            $urlMatcher = $this->getLazyUrlMatcher($app);

            $dispatcher->addSubscriber(new RouterListener($urlMatcher, $app['request_stack'], $app['request_context'], $app['logger']));

            if (isset($app['exception_handler'])) {
                $dispatcher->addSubscriber($app['exception_handler']);
            }
            $dispatcher->addSubscriber(new ResponseListener($app['charset']));
            $dispatcher->addSubscriber(new MiddlewareListener($app));
            $dispatcher->addSubscriber(new ConverterListener($app['routes'], $app['callback_resolver']));
            $dispatcher->addSubscriber(new StringToResponseListener());

            return $dispatcher;
        });

        $this['callback_resolver'] = $this->share(function () use ($app) {
            return new CallbackResolver($app);
        });

        $this['resolver'] = $this->share(function () use ($app) {
            return new ControllerResolver($app, $app['logger']);
        });

        $this['kernel'] = $this->share(function () use ($app) {
            return new HttpKernel($app['dispatcher'], $app['resolver'], $app['request_stack']);
        });

        $this['request_stack'] = $this->share(function () use ($app) {
            if (class_exists('Symfony\Component\HttpFoundation\RequestStack')) {
                return new RequestStack();
            }
        });

        $this['request_context'] = $this->share(function () use ($app) {
            $context = new RequestContext();

            $context->setHttpPort($app['request.http_port']);
            $context->setHttpsPort($app['request.https_port']);

            return $context;
        });

        $this['url_matcher'] = $this->share(function () use ($app) {
            return new RedirectableUrlMatcher($app['routes'], $app['request_context']);
        });

        $this['request_error'] = $this->protect(function () {
            throw new RuntimeException('Accessed request service outside of request scope. Try moving that call to a before handler or controller.');
        });

        $this['request'] = $this['request_error'];

        $this['request.http_port'] = 80;
        $this['request.https_port'] = 443;
        $this['debug'] = false;
        $this['charset'] = 'UTF-8';
        $this['locale'] = 'en';

        foreach ($services as $key => $value) {
            $this[$key] = $value;
        }
    }

    /**
     * @param ContainerInterface $container
     *
     * @return UrlMatcherInterface
     */
    protected function getLazyUrlMatcher(ContainerInterface $container)
    {
        if ($container->has('lazy_url_matcher')) {
            return $container->get('lazy_url_matcher');
        }

        $urlMatcher = new LazyUrlMatcher(function () use ($container) {
            return $container->get('url_matcher');
        });

        return $urlMatcher;
    }

    /**
     * @param \Silex\ServiceProviderInterface|\Spryker\Shared\ApplicationExtension\Provider\ServiceProviderInterface|\Spryker\Shared\ApplicationExtension\Provider\BootableServiceInterface|\Spryker\Shared\ApplicationExtension\Provider\EventSubscriberInterface $provider
     * @param array $values An array of values that customizes the provider
     *
     * @return $this
     */
    public function register($provider, array $values = [])
    {
        $this->providers[] = $provider;

        $provider->register($this);

        foreach ($values as $key => $value) {
            $this[$key] = $value;
        }

        return $this;
    }

    /**
     * Boots all (bootable) service providers.
     *
     * This method is automatically called by handle(), but you can use it
     * to boot all service providers when not handling a request.
     *
     * @return void
     */
    public function boot()
    {
        if (!$this->booted) {
            $this->booted = true;

            foreach ($this->providers as $provider) {
                $provider->boot($this);
            }
        }
    }

    /**
     * Maps a pattern to a callable.
     *
     * You can optionally specify HTTP methods that should be matched.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
    public function match($pattern, $to = null)
    {
        return $this['controllers']->match($pattern, $to);
    }

    /**
     * @internal To get entries form the ContainerInterface, this is not used ATM and the method will be removed from the Application at all.
     *
     * Maps a GET request to a callable.
     *
     * @deprecated This method should not be used anymore from the outside.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
//    public function get($pattern, $to = null)
//    {
//        return $this['controllers']->get($pattern, $to);
//    }

    /**
     * Maps a POST request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
    public function post($pattern, $to = null)
    {
        return $this['controllers']->post($pattern, $to);
    }

    /**
     * Maps a PUT request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
    public function put($pattern, $to = null)
    {
        return $this['controllers']->put($pattern, $to);
    }

    /**
     * Maps a DELETE request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
    public function delete($pattern, $to = null)
    {
        return $this['controllers']->delete($pattern, $to);
    }

    /**
     * Maps an OPTIONS request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
    public function options($pattern, $to = null)
    {
        return $this['controllers']->options($pattern, $to);
    }

    /**
     * Maps a PATCH request to a callable.
     *
     * @param string $pattern Matched route pattern
     * @param mixed $to Callback that returns the response when matched
     *
     * @return \Silex\Controller
     */
    public function patch($pattern, $to = null)
    {
        return $this['controllers']->patch($pattern, $to);
    }

    /**
     * Adds an event listener that listens on the specified events.
     *
     * @param string $eventName The event to listen on
     * @param callable $callback The listener
     * @param int $priority The higher this value, the earlier an event
     *                            listener will be triggered in the chain (defaults to 0)
     */
    public function on($eventName, $callback, $priority = 0)
    {
        if ($this->booted) {
            $this['dispatcher']->addListener($eventName, $this['callback_resolver']->resolveCallback($callback), $priority);

            return;
        }

        $this['dispatcher'] = $this->share($this->extend('dispatcher', function (EventDispatcherInterface $dispatcher, $app) use ($callback, $priority, $eventName) {
            $dispatcher->addListener($eventName, $app['callback_resolver']->resolveCallback($callback), $priority);

            return $dispatcher;
        }));
    }

    /**
     * Registers a before filter.
     *
     * Before filters are run before any route has been matched.
     *
     * @param mixed $callback Before filter callback
     * @param int $priority The higher this value, the earlier an event
     *                        listener will be triggered in the chain (defaults to 0)
     *
     * @return void
     */
    public function before($callback, $priority = 0)
    {
        $app = $this;

        $this->on(KernelEvents::REQUEST, function (GetResponseEvent $event) use ($callback, $app) {
            if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
                return;
            }

            $ret = call_user_func($app['callback_resolver']->resolveCallback($callback), $event->getRequest(), $app);

            if ($ret instanceof Response) {
                $event->setResponse($ret);
            }
        }, $priority);
    }

    /**
     * Registers an after filter.
     *
     * After filters are run after the controller has been executed.
     *
     * @param mixed $callback After filter callback
     * @param int $priority The higher this value, the earlier an event
     *                        listener will be triggered in the chain (defaults to 0)
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    public function after($callback, $priority = 0)
    {
        $app = $this;

        $this->on(KernelEvents::RESPONSE, function (FilterResponseEvent $event) use ($callback, $app) {
            if ($event->getRequestType() !== HttpKernelInterface::MASTER_REQUEST) {
                return;
            }

            $response = call_user_func($app['callback_resolver']->resolveCallback($callback), $event->getRequest(), $event->getResponse(), $app);
            if ($response instanceof Response) {
                $event->setResponse($response);
            } elseif ($response !== null) {
                throw new RuntimeException('An after middleware returned an invalid response value. Must return null or an instance of Response.');
            }
        }, $priority);
    }

    /**
     * Registers a finish filter.
     *
     * Finish filters are run after the response has been sent.
     *
     * @param mixed $callback Finish filter callback
     * @param int $priority The higher this value, the earlier an event
     *                        listener will be triggered in the chain (defaults to 0)
     *
     * @return void
     */
    public function finish($callback, $priority = 0)
    {
        $app = $this;

        $this->on(KernelEvents::TERMINATE, function (PostResponseEvent $event) use ($callback, $app) {
            call_user_func($app['callback_resolver']->resolveCallback($callback), $event->getRequest(), $event->getResponse(), $app);
        }, $priority);
    }

    /**
     * Aborts the current request by sending a proper HTTP error.
     *
     * @param int $statusCode The HTTP status code
     * @param string $message The status message
     * @param array $headers An array of HTTP headers
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     *
     * @return void
     */
    public function abort($statusCode, $message = '', array $headers = [])
    {
        throw new HttpException($statusCode, $message, null, $headers);
    }

    /**
     * Registers an error handler.
     *
     * Error handlers are simple callables which take a single Exception
     * as an argument. If a controller throws an exception, an error handler
     * can return a specific response.
     *
     * When an exception occurs, all handlers will be called, until one returns
     * something (a string or a Response object), at which point that will be
     * returned to the client.
     *
     * For this reason you should add logging handlers before output handlers.
     *
     * @param mixed $callback Error handler callback, takes an Exception argument
     * @param int $priority The higher this value, the earlier an event
     *                        listener will be triggered in the chain (defaults to -8)
     *
     * @return void
     */
    public function error($callback, $priority = -8)
    {
        $this->on(KernelEvents::EXCEPTION, new ExceptionListenerWrapper($this, $callback), $priority);
    }

    /**
     * Registers a view handler.
     *
     * View handlers are simple callables which take a controller result and the
     * request as arguments, whenever a controller returns a value that is not
     * an instance of Response. When this occurs, all suitable handlers will be
     * called, until one returns a Response object.
     *
     * @param mixed $callback View handler callback
     * @param int $priority The higher this value, the earlier an event
     *                        listener will be triggered in the chain (defaults to 0)
     *
     * @return void
     */
    public function view($callback, $priority = 0)
    {
        $this->on(KernelEvents::VIEW, new ViewListenerWrapper($this, $callback), $priority);
    }

    /**
     * Flushes the controller collection.
     *
     * @param string $prefix The route prefix
     *
     * @return void
     */
    public function flush($prefix = '')
    {
        $this['routes']->addCollection($this['controllers']->flush($prefix));
    }

    /**
     * Redirects the user to another URL.
     *
     * @param string $url The URL to redirect to
     * @param int $status The status code (302 by default)
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirect($url, $status = 302)
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * Creates a streaming response.
     *
     * @param mixed $callback A valid PHP callback
     * @param int $status The response status code
     * @param array $headers An array of response headers
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function stream($callback = null, $status = 200, array $headers = [])
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    /**
     * Escapes a text for HTML.
     *
     * @param string $text The input text to be escaped
     * @param int $flags The flags (@see htmlspecialchars)
     * @param string|null $charset The charset
     * @param bool $doubleEncode Whether to try to avoid double escaping or not
     *
     * @return string Escaped text
     */
    public function escape($text, $flags = ENT_COMPAT, $charset = null, $doubleEncode = true)
    {
        return htmlspecialchars($text, $flags, $charset ?: $this['charset'], $doubleEncode);
    }

    /**
     * Convert some data into a JSON response.
     *
     * @param mixed $data The response data
     * @param int $status The response status code
     * @param array $headers An array of response headers
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function json($data = [], $status = 200, array $headers = [])
    {
        return new JsonResponse($data, $status, $headers);
    }

    /**
     * Sends a file.
     *
     * @param \SplFileInfo|string $file The file to stream
     * @param int $status The response status code
     * @param array $headers An array of response headers
     * @param string|null $contentDisposition The type of Content-Disposition to set automatically with the filename
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function sendFile($file, $status = 200, array $headers = [], $contentDisposition = null)
    {
        return new BinaryFileResponse($file, $status, $headers, true, $contentDisposition);
    }

    /**
     * Mounts controllers under the given route prefix.
     *
     * @param string $prefix The route prefix
     * @param \Silex\ControllerCollection|\Silex\ControllerProviderInterface $controllers A ControllerCollection or a ControllerProviderInterface instance
     *
     * @throws \LogicException
     *
     * @return $this
     */
    public function mount($prefix, $controllers)
    {
        if ($controllers instanceof ControllerProviderInterface) {
            $connectedControllers = $controllers->connect($this);

            if (!$connectedControllers instanceof ControllerCollection) {
                throw new LogicException(sprintf('The method "%s::connect" must return a "ControllerCollection" instance. Got: "%s"', get_class($controllers), is_object($connectedControllers) ? get_class($connectedControllers) : gettype($connectedControllers)));
            }

            $controllers = $connectedControllers;
        } elseif (!$controllers instanceof ControllerCollection) {
            throw new LogicException('The "mount" method takes either a "ControllerCollection" or a "ControllerProviderInterface" instance.');
        }

        $this['controllers']->mount($prefix, $controllers);

        return $this;
    }

    /**
     * Handles the request and delivers the response.
     *
     * @param \Symfony\Component\HttpFoundation\Request|null $request Request to process
     *
     * @return void
     */
    public function run(?Request $request = null)
    {
        if ($request === null) {
            $request = Request::createFromGlobals();
        }

        $response = $this->handle($request);
        $response->send();
        $this->terminate($request, $response);
    }

    /**
     * {@inheritdoc}
     *
     * If you call this method directly instead of run(), you must call the
     * terminate() method yourself if you want the finish filters to be run.
     */
    public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true)
    {
        if (!$this->booted) {
            $this->boot();
        }

        $current = $type === HttpKernelInterface::SUB_REQUEST ? $this['request'] : $this['request_error'];

        $this['request'] = $request;

        $this->flush();

        $response = $this['kernel']->handle($request, $type, $catch);

        $this['request'] = $current;

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    public function terminate(Request $request, Response $response)
    {
        $this['kernel']->terminate($request, $response);
    }
}
