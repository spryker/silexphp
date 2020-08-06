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

use Symfony\Component\Debug\ExceptionHandler as DebugExceptionHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Defaults exception handler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExceptionHandler implements EventSubscriberInterface
{
    protected $debug;
    protected $enabled;

    public function __construct($debug)
    {
        $this->debug = $debug;
        $this->enabled = true;
    }

    /**
     * @deprecated since 1.3, to be removed in 2.0
     */
    public function disable()
    {
        $this->enabled = false;
    }

    public function onSilexError(ExceptionEvent $event)
    {
        if (!$this->enabled) {
            return;
        }

        $handler = new DebugExceptionHandler($this->debug);

        if (method_exists($handler, 'getHtml')) {
            $exception = $event->getThrowable();
            if (!$exception instanceof FlattenException) {
                $exception = $this->flattenException($exception);
            }

            $response = Response::create($handler->getHtml($exception), $exception->getStatusCode(), $exception->getHeaders())->setCharset(ini_get('default_charset'));
        } else {
            // BC with Symfony < 2.8
            $response = $handler->createResponse($event->getThrowable());
        }

        $event->setResponse($response);
    }

    /**
     * @param \Exception|\Throwable $exception
     *
     * @return FlattenException
     */
    protected function flattenException($exception): FlattenException
    {
        if ($exception instanceof \Exception) {
            return FlattenException::create($exception);
        }

        return FlattenException::createFromThrowable($exception);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(KernelEvents::EXCEPTION => array('onSilexError', -255));
    }
}
