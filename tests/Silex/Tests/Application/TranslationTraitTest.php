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
use Silex\Provider\TranslationServiceProvider;
use Symfony\Component\Translation\Translator;

/**
 * TranslationTrait test cases.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @requires PHP 5.4
 */
class TranslationTraitTest extends TestCase
{
    /**
     * @doesNotPerformAssertion
     */
    public function testTrans()
    {
        $app = $this->createApplication();
        $app['translator'] = $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')->disableOriginalConstructor()->getMock();
        $translator->expects($this->once())->method('trans');
        $app->trans('foo');
    }

    /**
     * @doesNotPerformAssertion
     */
    public function testTransChoice()
    {
        $app = $this->createApplication();
        $app['translator'] = $translator = $this->getMockBuilder('Symfony\Component\Translation\Translator')->disableOriginalConstructor()->getMock();
        $transChoiceExists = method_exists(Translator::class, 'transChoice');
        if ($transChoiceExists) {
            $translator->expects($this->once())->method('transChoice');
            $app->transChoice('foo', 2);

            return;
        }
        $translator->expects($this->once())->method('trans');
        $app->transChoice('foo', 2);

    }

    public function createApplication()
    {
        $app = new TranslationApplication();
        $app->register(new TranslationServiceProvider());

        return $app;
    }
}
