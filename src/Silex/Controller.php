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

use BadMethodCallException;
use Silex\Exception\ControllerFrozenException;
use Symfony\Component\Routing\RouteCollection;

/**
 * A wrapper for a controller, mapped to a route.
 *
 * __call() forwards method-calls to Route, but returns instance of Controller
 * listing Route's methods below, so that IDEs know they are valid
 *
 * @method Controller assert(string $variable, string $regexp)
 * @method Controller value(string $variable, mixed $default)
 * @method Controller convert(string $variable, mixed $callback)
 * @method Controller method(string $method)
 * @method Controller requireHttp()
 * @method Controller requireHttps()
 * @method Controller before(mixed $callback)
 * @method Controller after(mixed $callback)
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class Controller
{
    /**
     * @var \Silex\Route
     */
    private $route;

    /**
     * @var string
     */
    private $routeName;

    /**
     * @var bool
     */
    private $isFrozen = false;

    /**
     * @var \Symfony\Component\Routing\RouteCollection
     */
    private $routeCollection;

    /**
     * @param \Silex\Route $route
     * @param \Symfony\Component\Routing\RouteCollection|null $routeCollection
     */
    public function __construct(Route $route, ?RouteCollection $routeCollection = null)
    {
        $this->setRoute($route);
        $this->setRouteCollection($routeCollection);
    }

    /**
     * @param \Silex\Route $route
     */
    public function setRoute(Route $route)
    {
        $this->route = $route;
    }

    /**
     * Gets the controller's route.
     *
     * @return \Silex\Route
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @param \Symfony\Component\Routing\RouteCollection $routeCollection
     */
    public function setRouteCollection(?RouteCollection $routeCollection)
    {
        $this->routeCollection = $routeCollection ?: new RouteCollection();
    }

    /**
     * @return \Symfony\Component\Routing\RouteCollection
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->routeCollection;
    }

    /**
     * Gets the controller's route name.
     *
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * Sets the controller's route.
     *
     * @param string $routeName
     *
     * @return Controller $this The current Controller instance
     */
    public function bind($routeName)
    {
        if ($this->isFrozen) {
            throw new ControllerFrozenException(sprintf('Calling %s on frozen %s instance.', __METHOD__, __CLASS__));
        }

        $this->routeName = $routeName;

        $this->getRouteCollection()->add($routeName, $this->getRoute());

        return $this;
    }

    public function __call($method, $arguments)
    {
        if (!method_exists($this->route, $method)) {
            throw new BadMethodCallException(sprintf('Method "%s::%s" does not exist.', get_class($this->route), $method));
        }

        call_user_func_array(array($this->route, $method), $arguments);

        return $this;
    }

    /**
     * Freezes the controller.
     *
     * Once the controller is frozen, you can no longer change the route name
     */
    public function freeze()
    {
        $this->isFrozen = true;
    }

    public function generateRouteName($prefix)
    {
        $methods = implode('_', $this->route->getMethods()).'_';

        $routeName = $methods.$prefix.$this->route->getPath();
        $routeName = str_replace(array('/', ':', '|', '-'), '_', $routeName);
        $routeName = preg_replace('/[^a-z0-9A-Z_.]+/', '', $routeName);

        // Collapse consecutive underscores down into a single underscore.
        $routeName = preg_replace('/_+/', '_', $routeName);

        return $routeName;
    }
}
