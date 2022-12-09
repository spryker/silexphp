<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Provider\Routing;

use Symfony\Component\HttpKernel\Kernel;

// @phpstan-ignore-next-line
if (Kernel::MAJOR_VERSION < 6) {
    require 'RedirectableUrlMatcherTrait.symfony5.php';

    return;
}

trait RedirectableUrlMatcherTrait
{
    /**
     * @param string $path
     * @param string $route
     * @param string|null $scheme
     *
     * @return array
     */
    public function redirect(string $path, string $route, string $scheme = null): array
    {
        return $this->executeRedirect($path, $route, $scheme);
    }

    /**
     * @param string $path
     * @param string $route
     * @param string|null $scheme
     *
     * @return array
     */
    abstract protected function executeRedirect(string $path, string $route, ?string $scheme = null): array;
}