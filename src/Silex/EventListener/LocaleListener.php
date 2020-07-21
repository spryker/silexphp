<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\EventListener;

use Silex\Application;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RequestContextAwareInterface;

/**
 * @deprecated Will be removed without replacement.
 *
 * Initializes the locale based on the current request.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class LocaleListener implements EventSubscriberInterface
{
    /**
     * @var \Silex\Application
     */
    protected $app;

    /**
     * @var \Symfony\Component\Routing\RequestContextAwareInterface|null
     */
    private $router;

    /**
     * @var string
     */
    private $defaultLocale;

    /**
     * @var \Symfony\Component\HttpFoundation\RequestStack|null
     */
    private $requestStack;

    public function __construct(Application $app, ?RequestContextAwareInterface $router = null, ?RequestStack $requestStack = null)
    {
        $this->app = $app;

        $this->defaultLocale = $app['locale'];
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    /**
     * @return void
     */
    public function setDefaultLocale(KernelEvent $event)
    {
        $event->getRequest()->setDefaultLocale($this->defaultLocale);
    }

    /**
     * @return void
     */
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        $this->setLocale($request);
        $this->setRouterContext($request);

        $this->app['locale'] = $event->getRequest()->getLocale();
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\FinishRequestEvent $event
     *
     * @return void
     */
    public function onKernelFinishRequest(FinishRequestEvent $event)
    {
        $parentRequest = $this->requestStack->getParentRequest();

        if ($parentRequest !== null) {
            $this->setRouterContext($parentRequest);
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return void
     */
    private function setLocale(Request $request)
    {
        $locale = $request->attributes->get('_locale');

        if ($locale) {
            $request->setLocale($locale);
        }
    }

    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return void
     */
    private function setRouterContext(Request $request)
    {
        if ($this->router !== null) {
            $this->router->getContext()->setParameter('_locale', $request->getLocale());
        }
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['setDefaultLocale', 100],
                // must be registered after the Router to have access to the _locale
                ['onKernelRequest', 16],
            ],
            KernelEvents::FINISH_REQUEST => [['onKernelFinishRequest', 0]],
        ];
    }
}
