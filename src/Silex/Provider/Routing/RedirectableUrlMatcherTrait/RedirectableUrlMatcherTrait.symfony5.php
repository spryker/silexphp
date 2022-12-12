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

use Silex\Provider\Routing\RedirectableUrlMatcherTrait\RedirectableUrlMatcherTraitCommon;

trait RedirectableUrlMatcherTrait
{
    use RedirectableUrlMatcherTraitCommon;

    /**
     * @param $path
     * @param $route
     * @param $scheme
     *
     * @return array
     */
    public function redirect($path, $route, $scheme = null)
    {
        return $this->executeRedirect($path, $route, $scheme);
    }
}
