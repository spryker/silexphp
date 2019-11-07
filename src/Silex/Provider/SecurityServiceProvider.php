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
use ReflectionMethod;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ServiceProviderInterface;
use Spryker\Service\Container\ContainerInterface;
use Spryker\Shared\Kernel\Store;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\Routing\Loader\ClosureLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;
use Symfony\Component\Security\Core\Authentication\AuthenticationProviderManager;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolver;
use Symfony\Component\Security\Core\Authentication\Provider\AnonymousAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Provider\DaoAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserChecker;
use Symfony\Component\Security\Core\Validator\Constraints\UserPasswordValidator;
use Symfony\Component\Security\Http\AccessMap;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\EntryPoint\BasicAuthenticationEntryPoint;
use Symfony\Component\Security\Http\EntryPoint\FormAuthenticationEntryPoint;
use Symfony\Component\Security\Http\EntryPoint\RetryAuthenticationEntryPoint;
use Symfony\Component\Security\Http\Firewall;
use Symfony\Component\Security\Http\Firewall\AbstractAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\AnonymousAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\BasicAuthenticationListener;
use Symfony\Component\Security\Http\Firewall\ChannelListener;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;
use Symfony\Component\Security\Http\Firewall\SwitchUserListener;
use Symfony\Component\Security\Http\FirewallMap;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Logout\DefaultLogoutSuccessHandler;
use Symfony\Component\Security\Http\Logout\SessionLogoutHandler;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategy;

/**
 * Symfony Security component Provider.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class SecurityServiceProvider implements ServiceProviderInterface
{
    protected $fakeRoutes;

    public function register(Application $app)
    {
        // used to register routes for login_check and logout
        $this->fakeRoutes = [];

        $that = $this;

        $app['security.role_hierarchy'] = [];
        $app['security.access_rules'] = [];
        $app['security.hide_user_not_found'] = true;

        $r = new ReflectionMethod('Symfony\Component\Security\Http\Firewall\ContextListener', '__construct');
        $params = $r->getParameters();
        if ($params[0]->getClass()->getName() === 'Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface') {
            $app['security.authorization_checker'] = $app->share(function ($app) {
                return new AuthorizationChecker($app['security.token_storage'], $app['security.authentication_manager'], $app['security.access_manager']);
            });

            $app['security.token_storage'] = $app->share(function ($app) {
                return new TokenStorage();
            });

            $app['security'] = $app->share(function ($app) {
                // Deprecated, to be removed in 2.0
                return new SecurityContext($app['security.token_storage'], $app['security.authorization_checker']);
            });
        } else {
            $app['security.token_storage'] = $app['security.authorization_checker'] = $app->share(function ($app) {
                return $app['security'];
            });
        }

        $app['security'] = $app->share(function ($app) {
            // Deprecated, to be removed in 2.0
            return new SecurityContext($app['security.authentication_manager'], $app['security.access_manager']);
        });

        $app['user'] = function ($app) {
            if (null === $token = $app['security.token_storage']->getToken()) {
                return;
            }

            if (!is_object($user = $token->getUser())) {
                return;
            }

            return $user;
        };

        $app['security.authentication_manager'] = $app->share(function ($app) {
            $manager = new AuthenticationProviderManager($app['security.authentication_providers']);
            $manager->setEventDispatcher($app['dispatcher']);

            return $manager;
        });

        // by default, all users use the digest encoder
        $app['security.encoder_factory'] = $app->share(function ($app) {
            return new EncoderFactory([
                'Symfony\Component\Security\Core\User\UserInterface' => $app['security.encoder.digest'],
            ]);
        });

        $app['security.encoder.digest'] = $app->share(function ($app) {
            return new MessageDigestPasswordEncoder();
        });

        $app['security.user_checker'] = $app->share(function ($app) {
            return new UserChecker();
        });

        $app['security.access_manager'] = $app->share(function ($app) {
            return new AccessDecisionManager($app['security.voters']);
        });

        $app['security.voters'] = $app->share(function ($app) {
            return [
                new RoleHierarchyVoter(new RoleHierarchy($app['security.role_hierarchy'])),
                new AuthenticatedVoter($app['security.trust_resolver']),
            ];
        });

        $app['security.firewall'] = $app->share(function ($app) {
            return new Firewall($app['security.firewall_map'], $app['dispatcher']);
        });

        $app['security.channel_listener'] = $app->share(function ($app) {
            return new ChannelListener(
                $app['security.access_map'],
                new RetryAuthenticationEntryPoint($app['request.http_port'], $app['request.https_port']),
                $app['logger']
            );
        });

        // generate the build-in authentication factories
        foreach (['logout', 'pre_auth', 'form', 'http', 'remember_me', 'anonymous'] as $type) {
            $entryPoint = null;
            if ($type === 'http') {
                $entryPoint = 'http';
            } elseif ($type === 'form') {
                $entryPoint = 'form';
            }

            $app['security.authentication_listener.factory.' . $type] = $app->protect(function ($name, $options) use ($type, $app, $entryPoint) {
                if ($entryPoint && !isset($app['security.entry_point.' . $name . '.' . $entryPoint])) {
                    $app['security.entry_point.' . $name . '.' . $entryPoint] = $app['security.entry_point.' . $entryPoint . '._proto']($name, $options);
                }

                if (!isset($app['security.authentication_listener.' . $name . '.' . $type])) {
                    $app['security.authentication_listener.' . $name . '.' . $type] = $app['security.authentication_listener.' . $type . '._proto']($name, $options);
                }

                $provider = $type === 'anonymous' ? 'anonymous' : 'dao';
                if (!isset($app['security.authentication_provider.' . $name . '.' . $provider])) {
                    $app['security.authentication_provider.' . $name . '.' . $provider] = $app['security.authentication_provider.' . $provider . '._proto']($name);
                }

                return [
                    'security.authentication_provider.' . $name . '.' . $provider,
                    'security.authentication_listener.' . $name . '.' . $type,
                    $entryPoint ? 'security.entry_point.' . $name . '.' . $entryPoint : null,
                    $type,
                ];
            });
        }

        $app['security.firewall_map'] = $app->share(function ($app) {
            $positions = ['logout', 'pre_auth', 'form', 'http', 'remember_me', 'anonymous'];
            $providers = [];
            $configs = [];
            foreach ($app['security.firewalls'] as $name => $firewall) {
                $entryPoint = null;
                $pattern = isset($firewall['pattern']) ? $firewall['pattern'] : null;
                $users = isset($firewall['users']) ? $firewall['users'] : [];
                $security = isset($firewall['security']) ? (bool)$firewall['security'] : true;
                $stateless = isset($firewall['stateless']) ? (bool)$firewall['stateless'] : false;
                $context = isset($firewall['context']) ? $firewall['context'] : $name;
                unset($firewall['pattern'], $firewall['users'], $firewall['security'], $firewall['stateless'], $firewall['context']);

                $protected = $security === false ? false : count($firewall);

                $listeners = ['security.channel_listener'];

                if ($protected) {
                    if (!isset($app['security.context_listener.' . $name])) {
                        if (!isset($app['security.user_provider.' . $name])) {
                            $app['security.user_provider.' . $name] = is_array($users) ? $app['security.user_provider.inmemory._proto']($users) : $users;
                        }

                        $app['security.context_listener.' . $name] = $app['security.context_listener._proto']($name, [$app['security.user_provider.' . $name]]);
                    }

                    if ($stateless === false) {
                        $listeners[] = 'security.context_listener.' . $context;
                    }

                    $factories = [];
                    foreach ($positions as $position) {
                        $factories[$position] = [];
                    }

                    foreach ($firewall as $type => $options) {
                        if ($type === 'switch_user') {
                            continue;
                        }

                        // normalize options
                        if (!is_array($options)) {
                            if (!$options) {
                                continue;
                            }

                            $options = [];
                        }

                        if (!isset($app['security.authentication_listener.factory.' . $type])) {
                            throw new LogicException(sprintf('The "%s" authentication entry is not registered.', $type));
                        }

                        $options['stateless'] = $stateless;

                        [$providerId, $listenerId, $entryPointId, $position] = $app['security.authentication_listener.factory.' . $type]($name, $options);

                        if ($entryPointId !== null) {
                            $entryPoint = $entryPointId;
                        }

                        $factories[$position][] = $listenerId;
                        $providers[] = $providerId;
                    }

                    foreach ($positions as $position) {
                        foreach ($factories[$position] as $listener) {
                            $listeners[] = $listener;
                        }
                    }

                    $listeners[] = 'security.access_listener';

                    if (isset($firewall['switch_user'])) {
                        $app['security.switch_user.' . $name] = $app['security.authentication_listener.switch_user._proto']($name, $firewall['switch_user']);

                        $listeners[] = 'security.switch_user.' . $name;
                    }

                    if (!isset($app['security.exception_listener.' . $name])) {
                        if ($entryPoint == null) {
                            $app[$entryPoint = 'security.entry_point.' . $name . '.form'] = $app['security.entry_point.form._proto']($name, []);
                        }
                        $app['security.exception_listener.' . $name] = $app['security.exception_listener._proto']($entryPoint, $name);
                    }
                }

                $configs[$name] = [$pattern, $listeners, $protected];
            }

            $app['security.authentication_providers'] = array_map(function ($provider) use ($app) {
                return $app[$provider];
            }, array_unique($providers));

            $map = new FirewallMap();
            foreach ($configs as $name => $config) {
                $map->add(
                    is_string($config[0]) ? new RequestMatcher($config[0]) : $config[0],
                    array_map(function ($listenerId) use ($app, $name) {
                        $listener = $app[$listenerId];

                        if (isset($app['security.remember_me.service.' . $name])) {
                            if ($listener instanceof AbstractAuthenticationListener) {
                                $listener->setRememberMeServices($app['security.remember_me.service.' . $name]);
                            }
                            if ($listener instanceof LogoutListener) {
                                $listener->addHandler($app['security.remember_me.service.' . $name]);
                            }
                        }

                        return $listener;
                    }, $config[1]),
                    $config[2] ? $app['security.exception_listener.' . $name] : null
                );
            }

            return $map;
        });

        $app['security.access_listener'] = $app->share(function ($app) {
            return new AccessListener(
                $app['security.token_storage'],
                $app['security.access_manager'],
                $app['security.access_map'],
                $app['security.authentication_manager'],
                $app['logger']
            );
        });

        $app['security.access_map'] = $app->share(function ($app) {
            $map = new AccessMap();

            foreach ($app['security.access_rules'] as $rule) {
                if (is_string($rule[0])) {
                    $rule[0] = new RequestMatcher($rule[0]);
                }

                $map->add($rule[0], (array)$rule[1], isset($rule[2]) ? $rule[2] : null);
            }

            return $map;
        });

        $app['security.trust_resolver'] = $app->share(function () {
            return new AuthenticationTrustResolver('Symfony\Component\Security\Core\Authentication\Token\AnonymousToken', 'Symfony\Component\Security\Core\Authentication\Token\RememberMeToken');
        });

        $app['security.session_strategy'] = $app->share(function () {
            return new SessionAuthenticationStrategy(SessionAuthenticationStrategy::MIGRATE);
        });

        $app['security.http_utils'] = $app->share(function ($app) {
            return new HttpUtils(isset($app['url_generator']) ? $app['url_generator'] : null, $app['url_matcher']);
        });

        $app['security.last_error'] = $app->protect(function (Request $request) {
            if (class_exists('Symfony\Component\Security\Core\Security')) {
                $error = Security::AUTHENTICATION_ERROR;
            } else {
                $error = SecurityContextInterface::AUTHENTICATION_ERROR;
            }
            if ($request->attributes->has($error)) {
                return $request->attributes->get($error)->getMessage();
            }

            $session = $request->getSession();
            if ($session && $session->has($error)) {
                $message = $session->get($error)->getMessage();
                $session->remove($error);

                return $message;
            }
        });

        // prototypes (used by the Firewall Map)

        $app['security.context_listener._proto'] = $app->protect(function ($providerKey, $userProviders) use ($app) {
            return $app->share(function () use ($app, $userProviders, $providerKey) {
                return new ContextListener(
                    $app['security.token_storage'],
                    $userProviders,
                    $providerKey,
                    $app['logger'],
                    $app['dispatcher']
                );
            });
        });

        $app['security.user_provider.inmemory._proto'] = $app->protect(function ($params) use ($app) {
            return $app->share(function () use ($app, $params) {
                $users = [];
                foreach ($params as $name => $user) {
                    $users[$name] = ['roles' => (array)$user[0], 'password' => $user[1]];
                }

                return new InMemoryUserProvider($users);
            });
        });

        $app['security.exception_listener._proto'] = $app->protect(function ($entryPoint, $name) use ($app) {
            return $app->share(function () use ($app, $entryPoint, $name) {
                return new ExceptionListener(
                    $app['security.token_storage'],
                    $app['security.trust_resolver'],
                    $app['security.http_utils'],
                    $name,
                    $app[$entryPoint],
                    null, // errorPage
                    null, // AccessDeniedHandlerInterface
                    $app['logger']
                );
            });
        });

        $app['security.authentication.success_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
            return $app->share(function () use ($name, $options, $app) {
                $handler = new DefaultAuthenticationSuccessHandler(
                    $app['security.http_utils'],
                    $options
                );
                $handler->setProviderKey($name);

                return $handler;
            });
        });

        $app['security.authentication.failure_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
            return $app->share(function () use ($name, $options, $app) {
                return new DefaultAuthenticationFailureHandler(
                    $app,
                    $app['security.http_utils'],
                    $options,
                    $app['logger']
                );
            });
        });

        $app['security.authentication_listener.form._proto'] = $app->protect(function ($name, $options) use ($app, $that) {
            return $app->share(function () use ($app, $name, $options, $that) {
                $that->addFakeRoute(
                    'match',
                    $tmp = isset($options['check_path']) ? $options['check_path'] : '/login_check',
                    str_replace('/', '_', ltrim($tmp, '/'))
                );

                $class = isset($options['listener_class']) ? $options['listener_class'] : 'Symfony\\Component\\Security\\Http\\Firewall\\UsernamePasswordFormAuthenticationListener';

                if (!isset($app['security.authentication.success_handler.' . $name])) {
                    $app['security.authentication.success_handler.' . $name] = $app['security.authentication.success_handler._proto']($name, $options);
                }

                if (!isset($app['security.authentication.failure_handler.' . $name])) {
                    $app['security.authentication.failure_handler.' . $name] = $app['security.authentication.failure_handler._proto']($name, $options);
                }

                return new $class(
                    $app['security.token_storage'],
                    $app['security.authentication_manager'],
                    isset($app['security.session_strategy.' . $name]) ? $app['security.session_strategy.' . $name] : $app['security.session_strategy'],
                    $app['security.http_utils'],
                    $name,
                    $app['security.authentication.success_handler.' . $name],
                    $app['security.authentication.failure_handler.' . $name],
                    $options,
                    $app['logger'],
                    $app['dispatcher'],
                    isset($options['with_csrf']) && $options['with_csrf'] && isset($app['form.csrf_provider']) ? $app['form.csrf_provider'] : null
                );
            });
        });

        $app['security.authentication_listener.http._proto'] = $app->protect(function ($providerKey, $options) use ($app) {
            return $app->share(function () use ($app, $providerKey, $options) {
                return new BasicAuthenticationListener(
                    $app['security.token_storage'],
                    $app['security.authentication_manager'],
                    $providerKey,
                    $app['security.entry_point.' . $providerKey . '.http'],
                    $app['logger']
                );
            });
        });

        $app['security.authentication_listener.anonymous._proto'] = $app->protect(function ($providerKey, $options) use ($app) {
            return $app->share(function () use ($app, $providerKey, $options) {
                return new AnonymousAuthenticationListener(
                    $app['security.token_storage'],
                    $providerKey,
                    $app['logger']
                );
            });
        });

        $app['security.authentication.logout_handler._proto'] = $app->protect(function ($name, $options) use ($app) {
            return $app->share(function () use ($name, $options, $app) {
                return new DefaultLogoutSuccessHandler(
                    $app['security.http_utils'],
                    isset($options['target_url']) ? $options['target_url'] : '/'
                );
            });
        });

        $app['security.authentication_listener.logout._proto'] = $app->protect(function ($name, $options) use ($app, $that) {
            return $app->share(function () use ($app, $name, $options, $that) {
                $that->addFakeRoute(
                    'get',
                    $tmp = isset($options['logout_path']) ? $options['logout_path'] : '/logout',
                    str_replace('/', '_', ltrim($tmp, '/'))
                );

                if (!isset($app['security.authentication.logout_handler.' . $name])) {
                    $app['security.authentication.logout_handler.' . $name] = $app['security.authentication.logout_handler._proto']($name, $options);
                }

                $listener = new LogoutListener(
                    $app['security.token_storage'],
                    $app['security.http_utils'],
                    $app['security.authentication.logout_handler.' . $name],
                    $options,
                    isset($options['with_csrf']) && $options['with_csrf'] && isset($app['form.csrf_provider']) ? $app['form.csrf_provider'] : null
                );

                $invalidateSession = isset($options['invalidate_session']) ? $options['invalidate_session'] : true;
                if ($invalidateSession === true && $options['stateless'] === false) {
                    $listener->addHandler(new SessionLogoutHandler());
                }

                return $listener;
            });
        });

        $app['security.authentication_listener.switch_user._proto'] = $app->protect(function ($name, $options) use ($app, $that) {
            return $app->share(function () use ($app, $name, $options, $that) {
                return new SwitchUserListener(
                    $app['security.token_storage'],
                    $app['security.user_provider.' . $name],
                    $app['security.user_checker'],
                    $name,
                    $app['security.access_manager'],
                    $app['logger'],
                    isset($options['parameter']) ? $options['parameter'] : '_switch_user',
                    isset($options['role']) ? $options['role'] : 'ROLE_ALLOWED_TO_SWITCH',
                    $app['dispatcher']
                );
            });
        });

        $app['security.entry_point.form._proto'] = $app->protect(function ($name, array $options) use ($app) {
            return $app->share(function () use ($app, $options) {
                $loginPath = isset($options['login_path']) ? $options['login_path'] : '/login';
                $useForward = isset($options['use_forward']) ? $options['use_forward'] : false;

                return new FormAuthenticationEntryPoint($app, $app['security.http_utils'], $loginPath, $useForward);
            });
        });

        $app['security.entry_point.http._proto'] = $app->protect(function ($name, array $options) use ($app) {
            return $app->share(function () use ($app, $name, $options) {
                return new BasicAuthenticationEntryPoint(isset($options['real_name']) ? $options['real_name'] : 'Secured');
            });
        });

        $app['security.authentication_provider.dao._proto'] = $app->protect(function ($name) use ($app) {
            return $app->share(function () use ($app, $name) {
                return new DaoAuthenticationProvider(
                    $app['security.user_provider.' . $name],
                    $app['security.user_checker'],
                    $name,
                    $app['security.encoder_factory'],
                    $app['security.hide_user_not_found']
                );
            });
        });

        $app['security.authentication_provider.anonymous._proto'] = $app->protect(function ($name) use ($app) {
            return $app->share(function () use ($app, $name) {
                return new AnonymousAuthenticationProvider($name);
            });
        });

        if (isset($app['validator'])) {
            $app['security.validator.user_password_validator'] = $app->share(function ($app) {
                return new UserPasswordValidator($app['security.token_storage'], $app['security.encoder_factory']);
            });

            $app['validator.validator_service_ids'] = array_merge($app['validator.validator_service_ids'], ['security.validator.user_password' => 'security.validator.user_password_validator']);
        }
    }

    /**
     * @return void
     */
    public function boot(Application $app)
    {
        $app['dispatcher']->addSubscriber($app['security.firewall']);
        $this->addRouter($app);

        foreach ($this->fakeRoutes as $route) {
            [$method, $pattern, $name] = $route;

            $controllerCollection = $this->getControllerCollection($app);
            $controllerCollection->$method($pattern)->run(null)->bind($name);
        }
    }

    /**
     * @param \Spryker\Service\Container\ContainerInterface $container
     *
     * @throws \LogicException
     *
     * @return void
     */
    protected function addRouter(ContainerInterface $container): void
    {
        if (!$container->has('routers')) {
            return;
        }

        $loader = new ClosureLoader();
        $fakeRoutes = $this->fakeRoutes;

        $resource = function () use ($fakeRoutes) {
            $routeCollection = new RouteCollection();
            foreach ($fakeRoutes as $route) {
                [$method, $pattern, $name] = $route;

                $route = new Route($pattern);

                $controller = function (Request $request) {
                    throw new LogicException(sprintf('The "%s" route must have code to run when it matches.', $request->attributes->get('_route')));
                };

                $route->setDefault('_controller', $controller);

                $routeCollection->add($name, $route);
            }

            return $routeCollection;
        };

        $router = new Router($loader, $resource, []);
        $container->get('routers')->add($router, 1);
    }

    /**
     * @param \Silex\Application $app
     *
     * @return \Silex\ControllerCollection
     */
    protected function getControllerCollection(Application $app): ControllerCollection
    {
        return $app['controllers'];
    }

    /**
     * @return void
     */
    public function addFakeRoute($method, $pattern, $name)
    {
        $name = $this->stripLanguage($name);
        $this->fakeRoutes[] = [$method, $pattern, $name];
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function stripLanguage(string $name): string
    {
        if (!class_exists(Store::class)) {
            return $name;
        }

        $language = Store::getInstance()->getCurrentLanguage();
        $length = mb_strlen($language) + 1;
        if (mb_substr($name, 0, $length) === $language . '_') {
            $name = mb_substr($name, $length);
        }

        return $name;
    }
}
