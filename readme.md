# Vocal

[![Build Status](https://travis-ci.org/sjdaws/vocal.png)](https://travis-ci.org/sjdaws/vocal) [![License](https://poser.pugx.org/sjdaws/vocal/license.png)](https://packagist.org/packages/sjdaws/vocal) [![Latest Stable Version](https://poser.pugx.org/sjdaws/vocal/version.png)](https://packagist.org/packages/sjdaws/vocal)

Extended functionality for Eloquent in Laravel 4 and 5.

Some parts of Vocal are based on [Ardent](https://github.com/laravelbook/ardent) for Laravel 4 by Max Ehsan.

Copyright (c) 2014-2015 [Scott Dawson](https://github.com/sjdaws).

## Documentation

* [What is Vocal](#background)
* [Installation](#install)
* [Getting Started](#start)
* [Usage](#usage)

<a name="background"></a>
## What is Vocal

Vocal makes working with nested relationships easier. This is especially helpful if you're displaying multiple models to a user at once, such as a user profile with an address book, and want them to be able to change their name and update their address in one go.


<a name="install"></a>
## Installation

The first thing you need to do is add `sjdaws/vocal` as a requirement to `composer.json`:

```javascript
{
    "require": {
        "sjdaws/vocal": "2.0.*"
    }
}
```

Update your packages with `composer update` and you're ready to go.

<a name="start"></a>
## Getting Started

Vocal extends the Eloquent base class, so your models are still fully compatible with Eloquent. Vocal simply intercepts some methods such as `validate` and `save` before they're passed to Eloquent.

To create a new Vocal model, simply make your model class extend the Vocal base class:

```php
use Sjdaws\Vocal\Vocal;

class User extends Vocal {}
```

There is no need to add any Facades or Service Providers.

<a name="usage"></a>
## Usage

Vocal provides several settings and methods:

Variable | Access Modifier | Type
-----|-----|-----
`$allowHydrationFromInput` | protected | boolean
`$hashable` | protected | array
`$languageFolder` | protected | string
`$languageKey` | protected | string
`$messages` | protected | array
`$rules` | public | array
`$validateBeforeSave` | protected | boolean

Method | Parameters
-----|-----
create | `$data`, `$rules`, `$messages`
forceCreate | `$data`
forceSave | `$data`
forceSaveAndDelete | `$data`
forceSaveRecursive | `$data`
getErrors | `$filter`
getErrorBag |
hydrateModel | `$data`
save | `$data`, `$rules`, `$messages`
saveAndDelete | `$data`, `$rules`, `$messages`
saveRecursive | `$data`, `$rules`, `$messages`
timestamp | `$value`
validate | `$data`, `$rules`, `$messages`
validateRecursive | `$data`, `$rules`, `$messages`
