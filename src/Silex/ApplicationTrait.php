<?php

namespace Silex;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;

if (Kernel::MAJOR_VERSION >= 6) {
    trait ApplicationTrait
    {
        /**
         * {@inheritDoc}
         *
         * If you call this method directly instead of run(), you must call the
         * terminate() method yourself if you want the finish filters to be run.
         */
        public function handle(Request $request, int $type = HttpKernelInterface::MASTER_REQUEST, bool $catch = true): Response
        {
            if (!$this->booted) {
                $this->boot();
            }

            $current = $type === HttpKernelInterface::SUB_REQUEST ? $this['request'] : $this['request_error'];

            $this['request'] = $request;

            $this->flush();

            $response = $this['kernel']->handle($request, $type, $catch);

            $this['request'] = $current;

            return $response;
        }
    }

} else {
    trait ApplicationTrait
    {
        /**
         * {@inheritDoc}
         *
         * If you call this method directly instead of run(), you must call the
         * terminate() method yourself if you want the finish filters to be run.
         */
        public function handle(Request $request, $type = HttpKernelInterface::MASTER_REQUEST, $catch = true): Response
        {
            if (!$this->booted) {
                $this->boot();
            }

            $current = $type === HttpKernelInterface::SUB_REQUEST ? $this['request'] : $this['request_error'];

            $this['request'] = $request;

            $this->flush();

            $response = $this['kernel']->handle($request, $type, $catch);

            $this['request'] = $current;

            return $response;
        }
    }

}



