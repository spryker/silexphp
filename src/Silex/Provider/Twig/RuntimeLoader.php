<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Provider\Twig;

use Pimple;

/**
 * Loads Twig extension runtimes via Pimple.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RuntimeLoader implements \Twig_RuntimeLoaderInterface
{
    private $container;
    private $mapping;

    public function __construct(Pimple $container, array $mapping)
    {
        $this->container = $container;
        $this->mapping = $mapping;
    }
    /**
     * {@inheritdoc}
     */
    public function load($class)
    {
        if (isset($this->mapping[$class])) {
            return $this->container[$this->mapping[$class]];
        }
    }
}
