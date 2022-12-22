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

use Spryker\Service\Container\ContainerInterface;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

/**
 * Loads Twig extension runtimes via Pimple.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class RuntimeLoader implements RuntimeLoaderInterface
{
    /**
     * @var \Spryker\Service\Container\ContainerInterface
     */
    private $container;

    /**
     * @var array
     */
    private $mapping;

    /**
     * @param \Spryker\Service\Container\ContainerInterface $container
     * @param array $mapping
     */
    public function __construct(ContainerInterface $container, array $mapping)
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

        return null;
    }
}
