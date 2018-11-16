<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Provider;

use Silex\Application;
use Silex\Provider\Routing\LazyRequestMatcher;
use Silex\RedirectableUrlMatcher;
use Silex\ServiceProviderInterface;
use Silex\ControllerCollection;
use Spryker\Service\Container\ContainerInterface;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Spryker\Shared\Routing\Plugin\Provider\RoutingServiceProvider as SprykerRoutingServiceProvider;

/**
 * Symfony Routing component Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoutingServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['route_class'] = 'Silex\\Route';

        $app['route_factory'] = $app->factory(function ($app) {
            return new $app['route_class']();
        });

        $app['routes_factory'] = $app->factory(function (ContainerInterface $container) {
            if ($container->has(SprykerRoutingServiceProvider::ROUTE_COLLECTION)) {
                return $container->get(SprykerRoutingServiceProvider::ROUTE_COLLECTION);
            }

            return new RouteCollection();
        });

        $app['routes'] = function ($app) {
            return $app['routes_factory'];
        };

        $app['url_generator'] = function ($app) {
            return new UrlGenerator($app['routes'], $app['request_context']);
        };

        $app['request_matcher'] = function ($app) {
            return new RedirectableUrlMatcher($app['routes'], $app['request_context']);
        };

        $app['request_context'] = function ($app) {
            $context = new RequestContext();

            $context->setHttpPort(isset($app['request.http_port']) ? $app['request.http_port'] : 80);
            $context->setHttpsPort(isset($app['request.https_port']) ? $app['request.https_port'] : 443);

            return $context;
        };

        $app['controllers'] = function ($app) {
            return $app['controllers_factory'];
        };

        $controllersFactory = function () use ($app, &$controllersFactory) {
            return new ControllerCollection($app['route_factory'], $app['routes_factory'], $controllersFactory);
        };
        $app['controllers_factory'] = $app->factory($controllersFactory);

        $app['routing.listener'] = function ($app) {
            $urlMatcher = new LazyRequestMatcher(function () use ($app) {
                return $app['request_matcher'];
            });

            return new RouterListener($urlMatcher, $app['request_stack'], $app['request_context'], $app['logger']);
        };
    }

    /**
     * @param Application $app
     *
     * @return void
     */
    public function boot(Application $app)
    {
    }

    public function subscribe(ContainerInterface $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app->get('routing.listener'));
    }
}
