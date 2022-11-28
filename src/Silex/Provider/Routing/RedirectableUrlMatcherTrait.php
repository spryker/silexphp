<?php

namespace Silex\Provider\Routing;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;

if (InstalledVersions::satisfies(new VersionParser(), 'symfony/http-kernel', '^6.0.0')) {
    trait RedirectableUrlMatcherTrait
    {
        /**
         * {@inheritdoc}
         */
        public function redirect(string $path, string $route, string $scheme = null): array
        {
            return $this->executeRedirect($path, $route, $scheme);
        }

        /**
         * @param string $path
         * @param string $route
         * @param string|null $scheme
         *
         * @return array
         */
        abstract protected function executeRedirect(string $path, string $route, ?string $scheme = null): array;
    }
} else {
    trait RedirectableUrlMatcherTrait
    {
        /**
         * {@inheritdoc}
         */
        public function redirect($path, $route, $scheme = null)
        {
            return $this->executeRedirect($path, $route, $scheme);
        }

        /**
         * @param string $path
         * @param string $route
         * @param string|null $scheme
         *
         * @return array
         */
        abstract protected function executeRedirect(string $path, string $route, ?string $scheme = null): array;
    }
}
