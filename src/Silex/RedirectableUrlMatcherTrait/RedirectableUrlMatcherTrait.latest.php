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

use Silex\RedirectableUrlMatcherTrait\RedirectableUrlMatcherTraitCommon;

trait RedirectableUrlMatcherTrait
{
    use RedirectableUrlMatcherTraitCommon;

    /**
     * {@inheritdoc}
     *
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
}
