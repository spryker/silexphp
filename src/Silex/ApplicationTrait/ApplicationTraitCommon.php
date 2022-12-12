<?php

namespace Silex\ApplicationTrait;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

trait ApplicationTraitCommon
{
    /**
     * @param \Symfony\Component\HttpFoundation\Request $request
     * @param int $type
     * @param bool $catch
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    abstract protected function executeHandle(Request $request, int $type = HttpKernelInterface::MASTER_REQUEST, bool $catch = true): Response;
}
