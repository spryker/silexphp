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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

// @phpstan-ignore-next-line
if (Kernel::MAJOR_VERSION < 6) {
    require 'ApplicationTrait/ApplicationTrait.symfony5.php';
} else {
    require 'ApplicationTrait/ApplicationTrait.latest.php';
}
    
