<?php

/*
 * This file is part of the Silex framework.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silex\Exception;

use RuntimeException;
/**
 * Exception, is thrown when a frozen controller is modified.
 *
 * @author Igor Wiedler <igor@wiedler.ch>
 */
class ControllerFrozenException extends RuntimeException
{
}
