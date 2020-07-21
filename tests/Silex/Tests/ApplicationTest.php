<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Silex\Tests;

use ArrayObject;
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Silex\AppArgumentValueResolver;
use Silex\Application;
use Silex\Controller;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Silex\ControllerResolver;
use Silex\Provider\MonologServiceProvider;
use Silex\Route;
use Silex\ServiceProviderInterface;
use stdClass;
use Symfony\Component\Debug\ErrorHandler;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadataFactory;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Application test cases.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class ApplicationTest extends TestCase
{
    /**
     * @return void
     */
    public function testMatchReturnValue()
    {
        $app = new Application();

        $returnValue = $app->match('/foo', function () {
        });
        $this->assertInstanceOf(Controller::class, $returnValue);
        $this->assertInstanceOf('Silex\Controller', $returnValue);

        $returnValue = $app['controllers']->get('/foo', function () {
        });
        $this->assertInstanceOf(Controller::class, $returnValue);

        $returnValue = $app->post('/foo', function () {
        });
        $this->assertInstanceOf(Controller::class, $returnValue);

        $returnValue = $app->put('/foo', function () {
        });
        $this->assertInstanceOf(Controller::class, $returnValue);

        $returnValue = $app->patch('/foo', function () {
        });
        $this->assertInstanceOf(Controller::class, $returnValue);

        $returnValue = $app->delete('/foo', function () {
        });
        $this->assertInstanceOf(Controller::class, $returnValue);
    }

    /**
     * @return void
     */
    public function testConstructorInjection()
    {
        // inject a custom parameter
        $params = ['param' => 'value'];
        $app = new Application($params);
        $this->assertSame($params['param'], $app['param']);

        // inject an existing parameter
        $params = ['locale' => 'value'];
        $app = new Application($params);
        $this->assertSame($params['locale'], $app['locale']);
    }

    public function testGetRequest()
    {
        $request = Request::create('/');

        $app = new Application();
        $app['controllers']->get('/', function (Request $req) use ($request) {
            return $request === $req ? 'ok' : 'ko';
        });

        $this->assertEquals('ok', $app->handle($request)->getContent());
    }

    /**
     * @return void
     */
    public function testGetRoutesWithNoRoutes()
    {
        $app = new Application();

        $routes = $app['routes'];
        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertEquals(0, count($routes->all()));
    }

    public function testGetRoutesWithRoutes()
    {
        $app = new Application();

        $app['controllers']->get('/foo', function () {
            return 'foo';
        });

        $app['controllers']->get('/bar')->run(function () {
            return 'bar';
        });

        $routes = $app['routes'];
        $this->assertInstanceOf(RouteCollection::class, $routes);
        $this->assertEquals(0, count($routes->all()));
        $app->flush();
        $this->assertEquals(2, count($routes->all()));
    }

    public function testOnCoreController()
    {
        $app = new Application();

        $app['controllers']->get('/foo/{foo}', function (ArrayObject $foo) {
            return $foo['foo'];
        })->convert('foo', function ($foo) {
            return new ArrayObject(['foo' => $foo]);
        });

        $response = $app->handle(Request::create('/foo/bar'));
        $this->assertEquals('bar', $response->getContent());

        $app['controllers']->get('/foo/{foo}/{bar}', function (ArrayObject $foo) {
            return $foo['foo'];
        })->convert('foo', function ($foo, Request $request) {
            return new ArrayObject(['foo' => $foo . $request->attributes->get('bar')]);
        });

        $response = $app->handle(Request::create('/foo/foo/bar'));
        $this->assertEquals('foobar', $response->getContent());
    }

    /**
     * @return void
     */
    public function testOn()
    {
        $app = new Application();
        $app['pass'] = false;

        $callback = $this->getCallback($app);
        $app->on('test', $callback);

        $app['dispatcher']->dispatch(new KernelEvent($app, Request::createFromGlobals(), HttpKernelInterface::MASTER_REQUEST), 'test');

        $this->assertTrue($app['pass']);
    }

    /**
     * @param $app
     *
     * @return callable
     */
    protected function getCallback($app): callable
    {
        if (class_exists(Event::class)) {
            return function (Event $e) use ($app) {
                $app['pass'] = true;
            };
        }

        return function (\Symfony\Contracts\EventDispatcher\Event $e) use ($app) {
            $app['pass'] = true;
        };
    }

    /**
     * @return void
     */
    public function testAbort()
    {
        $app = new Application();

        try {
            $app->abort(404);
            $this->fail();
        } catch (HttpException $e) {
            $this->assertEquals(404, $e->getStatusCode());
        }
    }

    /**
     * @dataProvider escapeProvider
     *
     * @return void
     */
    public function testEscape($expected, $text)
    {
        $app = new Application();

        $this->assertEquals($expected, $app->escape($text));
    }

    public function escapeProvider()
    {
        return [
            ['&lt;', '<'],
            ['&gt;', '>'],
            ['&quot;', '"'],
            ["'", "'"],
            ['abc', 'abc'],
        ];
    }

    public function testControllersAsMethods()
    {
        $app = new Application();

        $app['controller-resolver'] = function () use ($app) {
            return new ControllerResolver($app);
        };
        $app['argument_metadata_factory'] = function () {
            return new ArgumentMetadataFactory();
        };
        $app['argument_value_resolvers'] = function () use ($app) {
            return array_merge([new AppArgumentValueResolver($app)], ArgumentResolver::getDefaultArgumentValueResolvers());
        };
        $app['argument-resolver'] = function ($app) {
            return new ArgumentResolver($app['argument_metadata_factory'], $app['argument_value_resolvers']);
        };

        $app['controllers']->get('/{name}', 'Silex\Tests\FooController::barAction');

        $this->assertEquals('Hello Fabien', $app->handle(Request::create('/Fabien'))->getContent());
    }

    public function testHttpSpec()
    {
        $app = new Application();
        $app['charset'] = 'ISO-8859-1';

        $app['controllers']->get('/', function () {
            return 'hello';
        });

        // content is empty for HEAD requests
        $response = $app->handle(Request::create('/', 'HEAD'));
        $this->assertEquals('', $response->getContent());

        // charset is appended to Content-Type
        $response = $app->handle(Request::create('/'));

        $this->assertEquals('text/html; charset=ISO-8859-1', $response->headers->get('Content-Type'));
    }

    public function testRoutesMiddlewares()
    {
        $app = new Application();

        $test = $this;

        $middlewareTarget = [];
        $beforeMiddleware1 = function (Request $request) use (&$middlewareTarget, $test) {
            $test->assertEquals('/reached', $request->getRequestUri());
            $middlewareTarget[] = 'before_middleware1_triggered';
        };
        $beforeMiddleware2 = function (Request $request) use (&$middlewareTarget, $test) {
            $test->assertEquals('/reached', $request->getRequestUri());
            $middlewareTarget[] = 'before_middleware2_triggered';
        };
        $beforeMiddleware3 = function (Request $request) use (&$middlewareTarget, $test) {
            throw new Exception('This middleware shouldn\'t run!');
        };

        $afterMiddleware1 = function (Request $request, Response $response) use (&$middlewareTarget, $test) {
            $test->assertEquals('/reached', $request->getRequestUri());
            $middlewareTarget[] = 'after_middleware1_triggered';
        };
        $afterMiddleware2 = function (Request $request, Response $response) use (&$middlewareTarget, $test) {
            $test->assertEquals('/reached', $request->getRequestUri());
            $middlewareTarget[] = 'after_middleware2_triggered';
        };
        $afterMiddleware3 = function (Request $request, Response $response) use (&$middlewareTarget, $test) {
            throw new Exception('This middleware shouldn\'t run!');
        };

        $app['controllers']->get('/reached', function () use (&$middlewareTarget) {
            $middlewareTarget[] = 'route_triggered';

            return 'hello';
        })
        ->before($beforeMiddleware1)
        ->before($beforeMiddleware2)
        ->after($afterMiddleware1)
        ->after($afterMiddleware2);

        $app['controllers']->get('/never-reached', function () use (&$middlewareTarget) {
            throw new Exception('This route shouldn\'t run!');
        })
        ->before($beforeMiddleware3)
        ->after($afterMiddleware3);

        $result = $app->handle(Request::create('/reached'));

        $this->assertSame(['before_middleware1_triggered', 'before_middleware2_triggered', 'route_triggered', 'after_middleware1_triggered', 'after_middleware2_triggered'], $middlewareTarget);
        $this->assertEquals('hello', $result->getContent());
    }

    public function testRoutesBeforeMiddlewaresWithResponseObject()
    {
        $app = new Application();

        $app['controllers']->get('/foo', function () {
            throw new Exception('This route shouldn\'t run!');
        })
        ->before(function () {
            return new Response('foo');
        });

        $request = Request::create('/foo');
        $result = $app->handle($request);

        $this->assertEquals('foo', $result->getContent());
    }

    public function testRoutesAfterMiddlewaresWithResponseObject()
    {
        $app = new Application();

        $app['controllers']->get('/foo', function () {
            return new Response('foo');
        })
        ->after(function () {
            return new Response('bar');
        });

        $request = Request::create('/foo');
        $result = $app->handle($request);

        $this->assertEquals('bar', $result->getContent());
    }

    public function testRoutesBeforeMiddlewaresWithRedirectResponseObject()
    {
        $app = new Application();

        $app['controllers']->get('/foo', function () {
            throw new Exception('This route shouldn\'t run!');
        })
        ->before(function () use ($app) {
            return $app->redirect('/bar');
        });

        $request = Request::create('/foo');
        $result = $app->handle($request);

        $this->assertInstanceOf(RedirectResponse::class, $result);
        $this->assertEquals('/bar', $result->getTargetUrl());
    }

    public function testRoutesBeforeMiddlewaresTriggeredAfterSilexBeforeFilters()
    {
        $app = new Application();

        $middlewareTarget = [];
        $middleware = function (Request $request) use (&$middlewareTarget) {
            $middlewareTarget[] = 'middleware_triggered';
        };

        $app['controllers']->get('/foo', function () use (&$middlewareTarget) {
            $middlewareTarget[] = 'route_triggered';

            return true;
        })
        ->before($middleware);

        $app->before(function () use (&$middlewareTarget) {
            $middlewareTarget[] = 'before_triggered';
        });

        $app->handle(Request::create('/foo'));

        $this->assertSame(['before_triggered', 'middleware_triggered', 'route_triggered'], $middlewareTarget);
    }

    public function testRoutesAfterMiddlewaresTriggeredBeforeSilexAfterFilters()
    {
        $app = new Application();

        $middlewareTarget = [];
        $middleware = function (Request $request) use (&$middlewareTarget) {
            $middlewareTarget[] = 'middleware_triggered';
        };

        $app['controllers']->get('/foo', function () use (&$middlewareTarget) {
            $middlewareTarget[] = 'route_triggered';

            return true;
        })
        ->after($middleware);

        $app->after(function () use (&$middlewareTarget) {
            $middlewareTarget[] = 'after_triggered';
        });

        $app->handle(Request::create('/foo'));

        $this->assertSame(['route_triggered', 'middleware_triggered', 'after_triggered'], $middlewareTarget);
    }

    public function testFinishFilter()
    {
        $containerTarget = [];

        $app = new Application();

        $app->finish(function () use (&$containerTarget) {
            $containerTarget[] = '4_filterFinish';
        });

        $app['controllers']->get('/foo', function () use (&$containerTarget) {
            $containerTarget[] = '1_routeTriggered';

            return new StreamedResponse(function () use (&$containerTarget) {
                $containerTarget[] = '3_responseSent';
            });
        });

        $app->after(function () use (&$containerTarget) {
            $containerTarget[] = '2_filterAfter';
        });

        $app->run(Request::create('/foo'));

        $this->assertSame(['1_routeTriggered', '2_filterAfter', '3_responseSent', '4_filterFinish'], $containerTarget);
    }

    public function testNonResponseAndNonNullReturnFromRouteBeforeMiddlewareShouldThrowRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        $app = new Application();

        $middleware = function (Request $request) {
            return 'string return';
        };

        $app['controllers']->get('/', function () {
            return 'hello';
        })
        ->before($middleware);

        $app->handle(Request::create('/'), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testNonResponseAndNonNullReturnFromRouteAfterMiddlewareShouldThrowRuntimeException()
    {
        $this->expectException(RuntimeException::class);
        $app = new Application();

        $middleware = function (Request $request) {
            return 'string return';
        };

        $app['controllers']->get('/', function () {
            return 'hello';
        })
        ->after($middleware);

        $app->handle(Request::create('/'), HttpKernelInterface::MASTER_REQUEST, false);
    }

    public function testSubRequest()
    {
        $app = new Application();
        $app['controllers']->get('/sub', function (Request $request) {
            return new Response('foo');
        });
        $app['controllers']->get('/', function (Request $request) use ($app) {
            return $app->handle(Request::create('/sub'), HttpKernelInterface::SUB_REQUEST);
        });

        $this->assertEquals('foo', $app->handle(Request::create('/'))->getContent());
    }

    public function testSubRequestDoesNotReplaceMainRequestAfterHandling()
    {
        $mainRequest = Request::create('/');
        $subRequest = Request::create('/sub');

        $app = new Application();
        $app['controllers']->get('/sub', function (Request $request) {
            return new Response('foo');
        });
        $app['controllers']->get('/', function (Request $request) use ($subRequest, $app) {
            $response = $app->handle($subRequest, HttpKernelInterface::SUB_REQUEST);

            // request in app must be the main request here
            $response->setContent($response->getContent() . ' ' . $app['request']->getPathInfo());

            return $response;
        });

        $this->assertEquals('foo /', $app->handle($mainRequest)->getContent());
    }

    /**
     * @return void
     */
    public function testRegisterShouldReturnSelf()
    {
        $app = new Application();
        $provider = $this->getMockBuilder(ServiceProviderInterface::class)->getMock();

        $this->assertSame($app, $app->register($provider));
    }

    public function testMountShouldReturnSelf()
    {
        $app = new Application();
        $mounted = new ControllerCollection(new Route());
        $mounted->get('/{name}', function ($name) {
            return new Response($name);
        });

        $this->assertSame($app, $app->mount('/hello', $mounted));
    }

    /**
     * @return void
     */
    public function testMountPreservesOrder()
    {
        $app = new Application();
        $mounted = new ControllerCollection(new Route());
        $mounted->get('/mounted')->bind('second');

        $app['controllers']->get('/before')->bind('first');
        $app->mount('/', $mounted);
        $app['controllers']->get('/after')->bind('third');
        $app->flush();

        $this->assertEquals(['first', 'second', 'third'], array_keys(iterator_to_array($app['routes'])));
    }

    /**
     * @return void
     */
    public function testMountNullException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "mount" method takes either a "ControllerCollection" or a "ControllerProviderInterface" instance.');
        $app = new Application();
        $app->mount('/exception', null);
    }

    /**
     * @return void
     */
    public function testMountWrongConnectReturnValueException()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The method "Silex\Tests\IncorrectControllerCollection::connect" must return a "ControllerCollection" instance. Got: "NULL"');
        $app = new Application();
        $app->mount('/exception', new IncorrectControllerCollection());
    }

    /**
     * @return void
     */
    public function testSendFile()
    {
        $app = new Application();

        $response = $app->sendFile(__FILE__, 200, ['Content-Type: application/php']);
        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertEquals(__FILE__, (string)$response->getFile());
    }

    /**
     * @return void
     */
    public function testGetRouteCollectionWithRouteWithoutController()
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The "homepage" route must have code to run when it matches.');
        $app = new Application();
        unset($app['exception_handler']);
        $app->match('/')->bind('homepage');
        $app->handle(Request::create('/'));
    }

    public function testRedirectDoesNotRaisePHPNoticesWhenMonologIsRegistered()
    {
        $app = new Application();

        ErrorHandler::register(null, false);
        $app['monolog.logfile'] = 'php://memory';
        $app->register(new MonologServiceProvider());
        $app['controllers']->get('/foo/', function () {
            return 'ok';
        });

        $response = $app->handle(Request::create('/foo'));
        $this->assertEquals(301, $response->getStatusCode());
    }

    public function testBeforeFilterOnMountedControllerGroupIsolatedToGroup()
    {
        $app = new Application();
        $app->match('/', function () {
            return new Response('ok');
        });
        $mounted = $app['controllers_factory'];
        $mounted->before(function () {
            return new Response('not ok');
        });
        $app->mount('/group', $mounted);

        $response = $app->handle(Request::create('/'));
        $this->assertEquals('ok', $response->getContent());
    }

    public function testViewListenerWithPrimitive()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return 123;
        });
        $app->view(function ($view, Request $request) {
            return new Response($view);
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('123', $response->getContent());
    }

    public function testViewListenerWithArrayTypeHint()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return ['ok'];
        });
        $app->view(function (array $view) {
            return new Response($view[0]);
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('ok', $response->getContent());
    }

    public function testViewListenerWithObjectTypeHint()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return (object)['name' => 'world'];
        });
        $app->view(function (stdClass $view) {
            return new Response('Hello ' . $view->name);
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('Hello world', $response->getContent());
    }

    /**
     * @requires PHP 5.4
     */
    public function testViewListenerWithCallableTypeHint()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return function () {
                return 'world';
            };
        });
        $app->view(function (callable $view) {
            return new Response('Hello ' . $view());
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('Hello world', $response->getContent());
    }

    public function testViewListenersCanBeChained()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return (object)['name' => 'world'];
        });

        $app->view(function (stdClass $view) {
            return ['msg' => 'Hello ' . $view->name];
        });

        $app->view(function (array $view) {
            return $view['msg'];
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('Hello world', $response->getContent());
    }

    public function testViewListenersAreIgnoredIfNotSuitable()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return 'Hello world';
        });

        $app->view(function (stdClass $view) {
            throw new Exception('View listener was called');
        });

        $app->view(function (array $view) {
            throw new Exception('View listener was called');
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('Hello world', $response->getContent());
    }

    public function testViewListenersResponsesAreNotUsedIfNull()
    {
        $app = new Application();
        $app['controllers']->get('/foo', function () {
            return 'Hello world';
        });

        $app->view(function ($view) {
            return 'Hello view listener';
        });

        $app->view(function ($view) {
            return;
        });

        $response = $app->handle(Request::create('/foo'));

        $this->assertEquals('Hello view listener', $response->getContent());
    }
}

class FooController
{
    public function barAction(Application $app, $name)
    {
        return 'Hello ' . $app->escape($name);
    }
}

class IncorrectControllerCollection implements ControllerProviderInterface
{
    /**
     * @return void
     */
    public function connect(Application $app)
    {
        return;
    }
}
