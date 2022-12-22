<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Silex\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Exception;
use Swift_Message;
use Silex\Application;
use Silex\Provider\SwiftmailerServiceProvider;
use Symfony\Component\HttpFoundation\Request;

class SwiftmailerServiceProviderTest extends TestCase
{
    public function testSwiftMailerServiceIsSwiftMailer()
    {
        $app = new Application();

        $app->register(new SwiftmailerServiceProvider());
        $app->boot();

        $this->assertInstanceOf('Swift_Mailer', $app['mailer']);
    }

    public function testSwiftMailerIgnoresSpoolIfDisabled()
    {
        $app = new Application();

        $app->register(new SwiftmailerServiceProvider());
        $app->boot();

        $app['swiftmailer.use_spool'] = false;

        $app['swiftmailer.spooltransport'] = function () {
            throw new Exception('Should not be instantiated');
        };

        $this->assertInstanceOf('Swift_Mailer', $app['mailer']);
    }

    public function testSwiftMailerSendsMailsOnFinish()
    {
        // $this->markTestSkipped('Issue with prefer-lowest jobs and Swiftmailer send message.');

        $app = new Application();

        $app->register(new SwiftmailerServiceProvider());
        $app->boot();

        $app['swiftmailer.spool'] = $app->share(function () {
            return new SpoolStub();
        });

        $app['controllers']->get('/', function () use ($app) {
            if (method_exists(Swift_Message::class, 'newInstance')) {
                $app['mailer']->send(Swift_Message::newInstance());
                return true;
            }


            $app['mailer']->send((new Swift_Message()));

            return true;
        });

        $this->assertCount(0, $app['swiftmailer.spool']->getMessages());

        $request = Request::create('/');
        $response = $app->handle($request);
        $this->assertCount(1, $app['swiftmailer.spool']->getMessages());

        $app->terminate($request, $response);
        $this->assertTrue($app['swiftmailer.spool']->hasFlushed);
        $this->assertCount(0, $app['swiftmailer.spool']->getMessages());
    }

    public function testSwiftMailerAvoidsFlushesIfMailerIsUnused()
    {
        $app = new Application();

        $app->register(new SwiftmailerServiceProvider());
        $app->boot();

        $app['swiftmailer.spool'] = $app->share(function () {
            return new SpoolStub();
        });

        $app['controllers']->get('/', function () use ($app) { return true; });

        $request = Request::create('/');
        $response = $app->handle($request);
        $this->assertCount(0, $app['swiftmailer.spool']->getMessages());

        $app->terminate($request, $response);
        $this->assertFalse($app['swiftmailer.spool']->hasFlushed);
    }
}
