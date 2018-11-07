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

/**
 * @deprecated This interface will be removed soon, please check deprecation messages at method level for further
 * information.
 *
 * Interface that all Silex service providers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
interface ServiceProviderInterface
{
    /**
     * @deprecated Please use `Spryker\Shared\ApplicationExtension\Provider\ServiceInterface::register()` instead.
     *
     * Registers services on the given app.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     */
    public function register(Application $app);

    /**
     * @deprecated Please use `Spryker\Shared\ApplicationExtension\Provider\BootableServiceInterface::boot()` instead if
     * your service needs to be configured dynamically.
     *
     * Bootstraps the application.
     *
     * This method is called after all services are registered
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app);
}
