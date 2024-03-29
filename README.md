# Silexphp

[![Build Status](https://travis-ci.org/spryker/silexphp.svg?branch=master)](https://travis-ci.org/spryker/silexphp)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/github/license/spryker/silexphp.svg)](https://github.com/spryker/silexphp/)

The micro framework Silex will no longer be maintained. With this release we introduce a copy of Silex to be able to refactor out Silex and replace it with a Spryker solution. This release will not change any behavior.


## Installation

```
composer require spryker/silexphp
```

The module `spryker/silex` is now requiring `spryker/silexphp` instead of `silex/silexphp` - as refactored version of it.
You do not need to install this manually. Please consider to update `spryker/silex` instead.

## Documentation

[Spryker Documentation](https://documentation.spryker.com/)


**WARNING** Silex 1.x is not maintained anymore. This is a copy of `silex/silexphp` we use to refactor out Silex from Spryker.


License
-------

Silex is licensed under the MIT license.

.. _Symfony components: http://symfony.com
.. _Composer:           http://getcomposer.org
.. _PHPUnit:            https://phpunit.de
.. _silex.zip:          http://silex.sensiolabs.org/download
.. _documentation:      http://silex.sensiolabs.org/documentation
