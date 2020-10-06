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

use Symfony\Component\Translation\Translator as BaseTranslator;

/**
 * Translator that gets the current locale from the Silex application.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Translator extends BaseTranslator
{
    protected $app;

    public function __construct(Application $app, $selector, $cacheDir = null, $debug = false)
    {
        $this->app = $app;

        parent::__construct($this->app['locale'], $selector, $cacheDir, $debug);
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = [], $domain = null, $locale = null)
    {
        if (method_exists(BaseTranslator::class, 'transChoice')) {
            return parent::transChoice($id, $number, $parameters, $domain, $locale);
        }

        $parameters['%count%'] = $number;

        return $this->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale()
    {
        return $this->app['locale'];
    }

    /**
     * @param string|null $locale
     *
     * @return void
     */
    public function setLocale($locale)
    {
        if (null === $locale) {
            return;
        }

        $this->app['locale'] = $locale;

        parent::setLocale($locale);
    }
}
