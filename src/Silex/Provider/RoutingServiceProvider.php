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
use Silex\ControllerCollection;
use Silex\EventListener\LocaleListener;
use Silex\Provider\Routing\LazyRequestMatcher;
use Silex\RedirectableUrlMatcher;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

/**
 * @deprecated Use `\Spryker\Zed\Router\Communication\Plugin\Application\RouterApplicationPlugin` instead.
 * @deprecated Use `\Spryker\Yves\Router\Plugin\Application\RouterApplicationPlugin` instead.
 *
 * Symfony Routing component Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RoutingServiceProvider implements ServiceProviderInterface
{
    /**
     * Added for BC reason only.
     */
    public const BC_FEATURE_FLAG_LOCALE_LISTENER = 'BC_FEATURE_FLAG_LOCALE_LISTENER';

    /**
     * Added for BC reason only.
     */
    public const BC_FEATURE_FLAG_ROUTER_LISTENER = 'BC_FEATURE_FLAG_ROUTER_LISTENER';

    /**
     * @param \Silex\Application $app
     *
     * @return void
     */
    public function register(Application $app)
    {
        $app[static::BC_FEATURE_FLAG_LOCALE_LISTENER] = true;
        $app[static::BC_FEATURE_FLAG_ROUTER_LISTENER] = true;

        $app['route_class'] = 'Silex\\Route';

        $app['route_factory'] = function ($app) {
            return new $app['route_class']();
        };

        $app['routes_factory'] = function () {
            return new RouteCollection();
        };

        $app['routes'] = $app->share(function ($app) {
            return $app['routes_factory'];
        });

        $app['url_generator'] = $app->share(function ($app) {
            return new UrlGenerator($app['routes'], $app['request_context']);
        });

        $app['request_matcher'] = $app->share(function ($app) {
            return new RedirectableUrlMatcher($app['routes'], $app['request_context']);
        });

        $app['request_context'] = $app->share(function ($app) {
            $context = new RequestContext();

            $context->setHttpPort(isset($app['request.http_port']) ? $app['request.http_port'] : 80);
            $context->setHttpsPort(isset($app['request.https_port']) ? $app['request.https_port'] : 443);

            return $context;
        });

        $app['controllers'] = $app->share(function ($app) {
            return $app['controllers_factory'];
        });

        $controllersFactory = function () use ($app, &$controllersFactory) {
            return new ControllerCollection($app['route_factory'], $app['routes_factory'], $controllersFactory);
        };
        $app['controllers_factory'] = $controllersFactory;

        $app['url_matcher'] = function () use ($app) {
            return new RedirectableUrlMatcher($app['routes'], $app['request_context']);
        };

        $app['routing.listener'] = $app->share(function ($app) {
            $urlMatcher = new LazyRequestMatcher(function () use ($app) {
                return $app['url_matcher'];
            });

            return new RouterListener($urlMatcher, $app['request_stack'], $app['request_context'], $app['logger']);
        });
    }

    /**
     * @param \Silex\Application $app
     *
     * @return void
     */
    public function boot(Application $app)
    {
        $dispatcher = $app['dispatcher'];
        if ($app[static::BC_FEATURE_FLAG_ROUTER_LISTENER]) {
            $dispatcher->addSubscriber($app['routing.listener']);
        }
        if ($app[static::BC_FEATURE_FLAG_LOCALE_LISTENER]) {
            $dispatcher->addSubscriber(new LocaleListener($app, $app['url_matcher'], $app['request_stack']));
        }
    }
}
