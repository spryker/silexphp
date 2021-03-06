<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silex\Tests\Application;

use PHPUnit\Framework\TestCase;
use Silex\Provider\SecurityServiceProvider;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * SecurityTrait test cases.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @requires PHP 5.4
 */
class SecurityTraitTest extends TestCase
{
    public function testUser()
    {
        $request = Request::create('/');

        $app = $this->createApplication(array(
            'fabien' => array('ROLE_ADMIN', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
        ));
        $app['controllers']->get('/', function () { return 'foo'; });
        $app->handle($request);
        $this->assertNull($app->user());

        $request->headers->set('PHP_AUTH_USER', 'fabien');
        $request->headers->set('PHP_AUTH_PW', 'foo');
        $app->handle($request);
        $this->assertInstanceOf(UserInterface::class, $app->user());
        $this->assertEquals('fabien', $app->user()->getUsername());
    }

    public function testUserWithNoToken()
    {
        $request = Request::create('/');

        $app = $this->createApplication();
        $app['controllers']->get('/', function () { return 'foo'; });
        $app->handle($request);
        $this->assertNull($app->user());
    }

    public function testUserWithInvalidUser()
    {
        $request = Request::create('/');

        $app = $this->createApplication();
        $app->boot();
        $app['security.token_storage']->setToken(new UsernamePasswordToken('foo', 'foo', 'foo'));

        $app['controllers']->get('/', function () { return 'foo'; });
        $app->handle($request);
        $this->assertNull($app->user());
    }

    public function testEncodePassword()
    {
        $app = $this->createApplication(array(
            'fabien' => array('ROLE_ADMIN', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
        ));

        $user = new User('foo', 'bar');
        $this->assertEquals('5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg==', $app->encodePassword($user, 'foo'));
    }

    public function testIsGrantedWithoutTokenThrowsException()
    {
        $this->expectException(AuthenticationCredentialsNotFoundException::class);
        $app = $this->createApplication();
        $app['controllers']->get('/', function () { return 'foo'; });
        $app->handle(Request::create('/'));
        $app->isGranted('ROLE_ADMIN');
    }

    public function testIsGranted()
    {
        $request = Request::create('/');

        $app = $this->createApplication(array(
            'fabien' => array('ROLE_ADMIN', '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
            'monique' => array('ROLE_USER',  '5FZ2Z8QIkA7UTZ4BYkoC+GsReLf569mSKDsfods6LYQ8t+a8EW9oaircfMpmaLbPBh4FOBiiFyLfuZmTSUwzZg=='),
        ));
        $app['controllers']->get('/', function () { return 'foo'; });

        // User is Monique (ROLE_USER)
        $request->headers->set('PHP_AUTH_USER', 'monique');
        $request->headers->set('PHP_AUTH_PW', 'foo');
        $app->handle($request);
        $this->assertTrue($app->isGranted('ROLE_USER'));
        $this->assertFalse($app->isGranted('ROLE_ADMIN'));

        // User is Fabien (ROLE_ADMIN)
        $request->headers->set('PHP_AUTH_USER', 'fabien');
        $request->headers->set('PHP_AUTH_PW', 'foo');
        $app->handle($request);
        $this->assertFalse($app->isGranted('ROLE_USER'));
        $this->assertTrue($app->isGranted('ROLE_ADMIN'));
    }

    public function createApplication($users = array())
    {
        $app = new SecurityApplication();
        $app->register(new SecurityServiceProvider(), array(
            'security.firewalls' => array(
                'default' => array(
                    'http' => true,
                    'users' => $users,
                ),
            ),
        ));

        return $app;
    }
}
