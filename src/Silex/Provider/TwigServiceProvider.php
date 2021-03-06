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

use ArrayObject;
use ReflectionClass;
use Silex\Application;
use Silex\Provider\Twig\RuntimeLoader;
use Silex\ServiceProviderInterface;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelExtension;
use Symfony\Bridge\Twig\Extension\HttpKernelRuntime;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Bridge\Twig\Extension\SecurityExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\HttpKernel\Fragment\HIncludeFragmentRenderer;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * @deprecated Use `\Spryker\Yves\Twig\Plugin\Application\TwigApplicationPlugin` for Yves instead.
 * @deprecated Use `\Spryker\Zed\Twig\Communication\Plugin\Application\TwigApplicationPlugin` for Zed instead.
 *
 * Twig integration for Silex.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['twig.options'] = new ArrayObject();
        $app['twig.form.templates'] = ['form_div_layout.html.twig'];
        $app['twig.path'] = [];
        $app['twig.templates'] = [];

        $app['twig'] = $app->share(function (Application $app) {

            $twigOptions = (array)$app['twig.options'];
            $globalOptions = [
                'charset' => $app['charset'],
                'debug' => $app['debug'],
                'strict_variables' => $app['debug'],
            ];

            $twigOptions = array_replace($globalOptions, $twigOptions);

            $twig = new Environment($app['twig.loader'], $twigOptions);
            $twig->addGlobal('app', $app);

            if ($app['debug']) {
                $twig->addExtension(new DebugExtension());
            }

            if (class_exists('Symfony\Bridge\Twig\Extension\RoutingExtension')) {
                if (isset($app['url_generator'])) {
                    $twig->addExtension(new RoutingExtension($app['url_generator']));
                }

                if (isset($app['translator'])) {
                    $twig->addExtension(new TranslationExtension($app['translator']));
                }

                if (isset($app['security.authorization_checker'])) {
                    $twig->addExtension(new SecurityExtension($app['security.authorization_checker']));
                }

                if (isset($app['fragment.handler'])) {
                    if (method_exists(HIncludeFragmentRenderer::class, 'setTemplating')) {
                        $app['fragment.renderer.hinclude']->setTemplating($twig);
                    }

                    $twig->addExtension(new HttpKernelExtension());
                }

                if (isset($app['form.factory'])) {
                    $app['twig.form.engine'] = $app->share(function ($app) {
                        return new TwigRendererEngine($app['twig.form.templates'], $app['twig']);
                    });

                    $app['twig.form.renderer'] = $app->share(function ($app) {
                        return new FormRenderer($app['twig.form.engine'], $app['form.csrf_provider']);
                    });

                    $twig->addExtension(new FormExtension());

                    // add loader for Symfony built-in form templates
                    $reflected = new ReflectionClass('Symfony\Bridge\Twig\Extension\FormExtension');
                    $path = dirname($reflected->getFileName()) . '/../Resources/views/Form';
                    $app['twig.loader']->addLoader(new FilesystemLoader($path));
                }
            }

            if (class_exists(HttpKernelRuntime::class)) {
                $twig->addRuntimeLoader($app['twig.runtime_loader']);
            }

            return $twig;
        });

        $app['twig.loader.filesystem'] = $app->share(function ($app) {
            return new FilesystemLoader($app['twig.path']);
        });

        $app['twig.loader.array'] = $app->share(function ($app) {
            return new ArrayLoader($app['twig.templates']);
        });

        $app['twig.loader'] = $app->share(function ($app) {
            return new ChainLoader([
                $app['twig.loader.array'],
                $app['twig.loader.filesystem'],
            ]);
        });

        $app['twig.runtime.httpkernel'] = function ($app) {
            return new HttpKernelRuntime($app['fragment.handler']);
        };

        $app['twig.runtimes'] = function ($app) {
            $runtimes = [
                HttpKernelRuntime::class => 'twig.runtime.httpkernel',
                FormRenderer::class => 'twig.form.renderer',
            ];

            return $runtimes;
        };
        $app['twig.runtime_loader'] = function ($app) {
            return new RuntimeLoader($app, $app['twig.runtimes']);
        };
    }

    /**
     * @return void
     */
    public function boot(Application $app)
    {
    }
}
