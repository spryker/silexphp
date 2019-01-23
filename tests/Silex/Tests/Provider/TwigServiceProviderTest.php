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

use PHPUnit_Framework_TestCase;
use Silex\Application;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * TwigProvider test cases.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class TwigServiceProviderTest extends PHPUnit_Framework_TestCase
{
    public function testRegisterAndRender()
    {
        $app = new Application(['form.factory' => true]);

        $app->register(new TwigServiceProvider(), [
            'twig.templates' => ['hello' => 'Hello {{ name }}!'],
        ]);

        $app['controllers']->get('/hello/{name}', function ($name) use ($app) {
            return $app['twig']->render('hello', ['name' => $name]);
        });

        $request = Request::create('/hello/john');
        $response = $app->handle($request);
        $this->assertEquals('Hello john!', $response->getContent());
    }

    public function testRenderFunction()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\RequestStack')) {
            $this->markTestSkipped();
        }

        $app = new Application(['form.factory' => true]);

        $app->register(new HttpFragmentServiceProvider());
        $app->register(new TwigServiceProvider(), [
            'twig.templates' => [
                'hello' => '{{ render("/foo") }}',
                'foo' => 'foo',
            ],
        ]);

        $app['controllers']->get('/hello', function () use ($app) {
            return $app['twig']->render('hello');
        });

        $app['controllers']->get('/foo', function () use ($app) {
            return $app['twig']->render('foo');
        });

        $request = Request::create('/hello');
        $response = $app->handle($request);
        $this->assertEquals('foo', $response->getContent());
    }

    public function testLoaderPriority()
    {
        $app = new Application();
        $app->register(new TwigServiceProvider(), [
            'twig.templates' => ['foo' => 'foo'],
        ]);
        $loader = $this->getMockBuilder('\Twig_LoaderInterface')->getMock();
        $loader->expects($this->never())->method('getSourceContext');
        $app['twig.loader.filesystem'] = $app->share(function ($app) use ($loader) {
            return $loader;
        });
        $this->assertEquals('foo', $app['twig.loader']->getSourceContext('foo')->getCode());
    }
}
