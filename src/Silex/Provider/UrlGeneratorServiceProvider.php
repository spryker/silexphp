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
use Silex\ServiceProviderInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;

/**
 * @deprecated Use `\Spryker\Zed\Router\Communication\Plugin\Application\RouterApplicationPlugin` for Zed instead.
 * @deprecated Use `\Spryker\Yves\Router\Plugin\Application\RouterApplicationPlugin` for Yves instead.
 *
 * Symfony Routing component Provider for URL generation.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UrlGeneratorServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['url_generator'] = $app->share(function ($app) {
            $app->flush();

            return new UrlGenerator($app['routes'], $app['request_context']);
        });
    }

    public function boot(Application $app)
    {
    }
}
