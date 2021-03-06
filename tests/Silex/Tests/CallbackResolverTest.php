<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Tests;

use PHPUnit\Framework\TestCase;
use Pimple;
use stdClass;
use Silex\CallbackResolver;

class CallbackResolverTest extends TestCase
{
    private $app;
    private $resolver;

    /**
     * @return void
     */
    public function setup(): void
    {
        $this->app = new Pimple();
        $this->resolver = new CallbackResolver($this->app);
    }

    public function testShouldResolveCallback()
    {
        $this->app['some_service'] = function () { return new stdClass(); };

        $this->assertTrue($this->resolver->isValid('some_service:methodName'));
        $this->assertEquals(
            array($this->app['some_service'], 'methodName'),
            $this->resolver->convertCallback('some_service:methodName')
        );
    }

    public function testNonStringsAreNotValid()
    {
        $this->assertFalse($this->resolver->isValid(null));
        $this->assertFalse($this->resolver->isValid('some_service::methodName'));
    }

    public function testShouldThrowAnExceptionIfServiceIsMissing()
    {
        $this->expectException('InvalidArgumentException');
        $this->expectExceptionMessage('Service "some_service" does not exist.');
        $this->resolver->convertCallback('some_service:methodName');
    }
}
