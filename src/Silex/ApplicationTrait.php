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
            return $this->executeHandle($request, $type, $catch);
        }

        /**
         * @param \Symfony\Component\HttpFoundation\Request $request
         * @param int $type
         * @param bool $catch
         *
         * @return \Symfony\Component\HttpFoundation\Response
         */
        abstract protected function executeHandle(Request $request, int $type = HttpKernelInterface::MASTER_REQUEST, bool $catch = true): Response;
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
            return $this->executeHandle($request, $type, $catch);
        }

        /**
         * @param \Symfony\Component\HttpFoundation\Request $request
         * @param int $type
         * @param bool $catch
         *
         * @return \Symfony\Component\HttpFoundation\Response
         */
        abstract protected function executeHandle(Request $request, int $type = HttpKernelInterface::MASTER_REQUEST, bool $catch = true): Response;
    }

}



