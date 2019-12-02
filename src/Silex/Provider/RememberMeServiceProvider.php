<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Silex\Provider;

use LogicException;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider;
use Symfony\Component\Security\Http\Firewall\RememberMeListener;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;
use Symfony\Component\Security\Http\RememberMe\TokenBasedRememberMeServices;

/**
 * @deprecated Use `\Spryker\Yves\Security\Plugin\Security\RememberMeSecurityPlugin` instead.
 *
 * @see \Spryker\Yves\Security\Plugin\Security\RememberMeSecurityPlugin
 *
 * Remember-me authentication for the SecurityServiceProvider.
 *
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
class RememberMeServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['security.remember_me.response_listener'] = $app->share(function () {
            return new ResponseListener();
        });

        $app['security.authentication_listener.factory.remember_me'] = $app->protect(function ($name, $options) use ($app) {
            if (empty($options['key'])) {
                $options['key'] = $name;
            }

            if (!isset($app['security.remember_me.service.' . $name])) {
                $app['security.remember_me.service.' . $name] = $app['security.remember_me.service._proto']($name, $options);
            }

            if (!isset($app['security.authentication_listener.' . $name . '.remember_me'])) {
                $app['security.authentication_listener.' . $name . '.remember_me'] = $app['security.authentication_listener.remember_me._proto']($name, $options);
            }

            if (!isset($app['security.authentication_provider.' . $name . '.remember_me'])) {
                $app['security.authentication_provider.' . $name . '.remember_me'] = $app['security.authentication_provider.remember_me._proto']($name, $options);
            }

            return [
                'security.authentication_provider.' . $name . '.remember_me',
                'security.authentication_listener.' . $name . '.remember_me',
                null, // entry point
                'remember_me',
            ];
        });

        $app['security.remember_me.service._proto'] = $app->protect(function ($providerKey, $options) use ($app) {
            return $app->share(function () use ($providerKey, $options, $app) {
                $options = array_replace([
                    'name' => 'REMEMBERME',
                    'lifetime' => 31536000,
                    'path' => '/',
                    'domain' => null,
                    'secure' => false,
                    'httponly' => true,
                    'always_remember_me' => false,
                    'remember_me_parameter' => '_remember_me',
                ], $options);

                return new TokenBasedRememberMeServices([$app['security.user_provider.' . $providerKey]], $options['key'], $providerKey, $options, $app['logger']);
            });
        });

        $app['security.authentication_listener.remember_me._proto'] = $app->protect(function ($providerKey) use ($app) {
            return $app->share(function () use ($app, $providerKey) {
                $listener = new RememberMeListener(
                    $app['security.token_storage'],
                    $app['security.remember_me.service.' . $providerKey],
                    $app['security.authentication_manager'],
                    $app['logger'],
                    $app['dispatcher']
                );

                return $listener;
            });
        });

        $app['security.authentication_provider.remember_me._proto'] = $app->protect(function ($name, $options) use ($app) {
            return $app->share(function () use ($app, $name, $options) {
                return new RememberMeAuthenticationProvider($app['security.user_checker'], $options['key'], $name);
            });
        });
    }

    /**
     * @param \Silex\Application $app
     *
     * @throws \LogicException
     *
     * @return void
     */
    public function boot(Application $app)
    {
        if (!isset($app['security'])) {
            throw new LogicException('You must register the SecurityServiceProvider to use the RememberMeServiceProvider');
        }

        $app['dispatcher']->addSubscriber($app['security.remember_me.response_listener']);
    }
}
