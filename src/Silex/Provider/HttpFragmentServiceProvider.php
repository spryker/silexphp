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

use LogicException;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpKernel\Fragment\FragmentHandler;
use Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer;
use Symfony\Component\HttpKernel\Fragment\EsiFragmentRenderer;
use Symfony\Component\HttpKernel\Fragment\HIncludeFragmentRenderer;
use Symfony\Component\HttpKernel\EventListener\FragmentListener;
use Symfony\Component\HttpKernel\UriSigner;

/**
 * @deprecated Use Http module plugins instead.
 *
 * HttpKernel Fragment integration for Silex.
 *
 * This service provider requires Symfony 2.4+.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class HttpFragmentServiceProvider implements ServiceProviderInterface
{
    protected $uriSignerSecret;

    public function __construct(?string $uriSignerSecret = null)
    {
        $this->uriSignerSecret = $uriSignerSecret;
    }

    public function register(Application $app)
    {
        if (!class_exists('Symfony\Component\HttpFoundation\RequestStack')) {
            throw new LogicException('The HTTP Fragment service provider only works with Symfony 2.4+.');
        }

        $app['fragment.handler'] = $app->share(function ($app) {
            return new FragmentHandler($app['request_stack'], $app['fragment.renderers'], $app['debug']);
        });

        $app['fragment.renderer.inline'] = $app->share(function ($app) {
            $renderer = new InlineFragmentRenderer($app['kernel'], $app['dispatcher']);
            $renderer->setFragmentPath($app['fragment.path']);

            return $renderer;
        });

        $app['fragment.renderer.hinclude'] = $app->share(function ($app) {
            $renderer = new HIncludeFragmentRenderer(null, $app['uri_signer'], $app['fragment.renderer.hinclude.global_template'], $app['charset']);
            $renderer->setFragmentPath($app['fragment.path']);

            return $renderer;
        });

        $app['fragment.renderer.esi'] = $app->share(function ($app) {
            $renderer = new EsiFragmentRenderer($app['http_cache.esi'], $app['fragment.renderer.inline']);
            $renderer->setFragmentPath($app['fragment.path']);

            return $renderer;
        });

        $app['fragment.listener'] = $app->share(function ($app) {
            return new FragmentListener($app['uri_signer'], $app['fragment.path']);
        });

        $app['uri_signer'] = $app->share(function ($app) {
            return new UriSigner($app['uri_signer.secret']);
        });

        $app['uri_signer.secret'] = $this->getUriSignerSecret();
        $app['fragment.path'] = '/_fragment';
        $app['fragment.renderer.hinclude.global_template'] = null;
        $app['fragment.renderers'] = $app->share(function ($app) {
            $renderers = array($app['fragment.renderer.inline'], $app['fragment.renderer.hinclude']);

            if (isset($app['http_cache.esi'])) {
                $renderers[] = $app['fragment.renderer.esi'];
            }

            return $renderers;
        });
    }

    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber($app['fragment.listener']);
    }

    /**
     * @return string
     */
    protected function getUriSignerSecret(): string
    {
        if ($this->uriSignerSecret) {
            return $this->uriSignerSecret;
        }

        $this->uriSignerSecret = getenv('SPRYKER_ZED_REQUEST_TOKEN') ?: null;

        if (!$this->uriSignerSecret) {
            trigger_error(
                'Environment variable `SPRYKER_ZED_REQUEST_TOKEN` must be defined.',
                E_USER_ERROR,
            );
        }

        return $this->uriSignerSecret;
    }
}
