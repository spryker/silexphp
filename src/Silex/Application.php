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
use RuntimeException;
use Silex\EventListener\ConverterListener;
use Silex\EventListener\MiddlewareListener;
use Silex\EventListener\StringToResponseListener;
use Silex\Provider\RoutingServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\EventListener\ResponseListener;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\RequestContext;

/**
 * The Silex framework class.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Application extends Pimple implements HttpKernelInterface, TerminableInterface
{
    public const VERSION = '1.3.6';

    public const EARLY_EVENT = 512;
    public const LATE_EVENT = -512;

    protected $providers = [];

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

        $this['exception_handler'] = $this->share(function () use ($app) {
            return new ExceptionHandler($app['debug']);
        });

        $this->register(new RoutingServiceProvider());

        $this['dispatcher_class'] = 'Symfony\\Component\\EventDispatcher\\EventDispatcher';
        $this['dispatcher'] = $this->share(function () use ($app) {
            /*
             * @var EventDispatcherInterface
             */
            $dispatcher = new $app['dispatcher_class']();

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
     * @param \Silex\ServiceProviderInterface $provider
     * @param array $values An array of values that customizes the provider
     *
     * @return $this
     */
    public function register(ServiceProviderInterface $provider, array $values = [])
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
