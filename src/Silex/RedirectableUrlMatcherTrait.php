<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex;

//  symfony/routing: <6.0.0
// @phpstan-ignore-next-line
if (class_exists('\Symfony\Component\Routing\RouteCollectionBuilder')) {
    require 'RedirectableUrlMatcherTrait/RedirectableUrlMatcherTrait.symfony5.php';
} else {
    require 'RedirectableUrlMatcherTrait/RedirectableUrlMatcherTrait.latest.php';
}
