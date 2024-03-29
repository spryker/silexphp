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
use RuntimeException;
use Silex\Provider\SecurityServiceProvider;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Silex\Application;
use Silex\Provider\MonologServiceProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * MonologProvider test cases.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class MonologServiceProviderTest extends TestCase
{
    private $currErrorHandler;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->currErrorHandler = set_error_handler('var_dump');
        restore_error_handler();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        set_error_handler($this->currErrorHandler);
    }

    public function testRequestLogging()
    {
        $this->markTestSkipped();
        $app = $this->getApplication();

        $app['controllers']->get('/foo', function () use ($app) {
            return 'foo';
        });

        $this->assertFalse($app['monolog.handler']->hasInfoRecords());

        $request = Request::create('/foo');
        $app->handle($request);

        $this->assertTrue($app['monolog.handler']->hasInfo('> GET /foo'));
        $this->assertTrue($app['monolog.handler']->hasInfo('< 200'));

        $records = $app['monolog.handler']->getRecords();
        $this->assertContains('Matched route "GET_foo"', $records[0]['message']);
    }

    public function testManualLogging()
    {
        $app = $this->getApplication();

        $app['controllers']->get('/log', function () use ($app) {
            /** @var \Symfony\Bridge\Monolog\Logger $monolog */
            $monolog = $app['monolog'];
            if (method_exists(Logger::class, 'addDebug')) {
                $monolog->addDebug('logging a message');

                return true;
            }

            $monolog->debug('logging a message');
            return true;
        });

        $this->assertFalse($app['monolog.handler']->hasDebugRecords());

        $request = Request::create('/log');
        $app->handle($request);

        $this->assertTrue($app['monolog.handler']->hasDebug('logging a message'));
    }

    public function testErrorLogging()
    {
        $app = $this->getApplication();

        $app->error(function (Exception $e) {
            return 'error handled';
        });

        /*
         * Simulate 404, logged to error level
         */
        $this->assertFalse($app['monolog.handler']->hasErrorRecords());

        $request = Request::create('/error');
        $app->handle($request);

        $records = $app['monolog.handler']->getRecords();
        $pattern = "#Symfony\\\\Component\\\\HttpKernel\\\\Exception\\\\NotFoundHttpException#";
        $this->assertMatchingRecord($pattern, Logger::ERROR, $app['monolog.handler']);

        /*
         * Simulate unhandled exception, logged to critical
         */
        $app['controllers']->get('/error', function () {
            throw new RuntimeException('very bad error');
        });

        $this->assertFalse($app['monolog.handler']->hasCriticalRecords());

        $request = Request::create('/error');
        $app->handle($request);

        $pattern = "#RuntimeException: very bad error \(uncaught exception\) at .* line \d+#";
        $this->assertMatchingRecord($pattern, Logger::CRITICAL, $app['monolog.handler']);
    }

    public function testRedirectLogging()
    {
        $app = $this->getApplication();

        $app['controllers']->get('/foo', function () use ($app) {
            return new RedirectResponse('/bar', 302);
        });

        $this->assertFalse($app['monolog.handler']->hasInfoRecords());

        $request = Request::create('/foo');
        $app->handle($request);

        $this->assertTrue($app['monolog.handler']->hasInfo('< 302 /bar'));
    }

    public function testErrorLoggingGivesWayToSecurityExceptionHandling()
    {
        $app = $this->getApplication();
        $app['monolog.level'] = Logger::ERROR;

        $app->register(new SecurityServiceProvider(), array(
            'security.firewalls' => array(
                'admin' => array(
                    'pattern' => '^/admin',
                    'http' => true,
                    'users' => array(),
                ),
            ),
        ));

        $app['controllers']->get('/admin', function () {
            return 'SECURE!';
        });

        $request = Request::create('/admin');
        $app->run($request);

        $this->assertEmpty($app['monolog.handler']->getRecords(), 'Expected no logging to occur');
    }

    public function testStringErrorLevel()
    {
        $app = $this->getApplication();
        $app['monolog.level'] = 'info';

        $this->assertSame(Logger::INFO, $app['monolog.handler']->getLevel());
    }

    public function testNonExistentStringErrorLevel()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Provided logging level \'foo\' does not exist. Must be a valid monolog logging level.');
        $app = $this->getApplication();
        $app['monolog.level'] = 'foo';

        $app['monolog.handler']->getLevel();
    }

    public function testDisableListener()
    {
        $app = $this->getApplication();
        unset($app['monolog.listener']);

        $app->handle(Request::create('/404'));

        $this->assertEmpty($app['monolog.handler']->getRecords(), 'Expected no logging to occur');
    }

    protected function assertMatchingRecord($pattern, $level, $handler)
    {
        $found = false;
        $records = $handler->getRecords();
        foreach ($records as $record) {
            if (preg_match($pattern, $record['message']) && $record['level'] == $level) {
                $found = true;
                continue;
            }
        }
        $this->assertTrue($found, "Trying to find record matching $pattern with level $level");
    }

    protected function getApplication()
    {
        $app = new Application();

        $app->register(new MonologServiceProvider());

        $app['monolog.handler'] = $app->share(function () use ($app) {
            $level = MonologServiceProvider::translateLevel($app['monolog.level']);

            return new TestHandler($level);
        });

        return $app;
    }
}
