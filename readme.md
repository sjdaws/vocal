# Vocal

[![Build Status](https://travis-ci.org/lakedawson/vocal.png)](https://travis-ci.org/lakedawson/vocal) [![License](https://poser.pugx.org/lakedawson/vocal/license.png)](https://packagist.org/packages/lakedawson/vocal) [![Latest Stable Version](https://poser.pugx.org/lakedawson/vocal/version.png)](https://packagist.org/packages/lakedawson/vocal)

Extended functionality for Eloquent in Laravel 4.

A big chunk of Vocal is based on [Ardent](https://github.com/laravelbook/ardent) for Laravel 4 by Max Ehsan.

Copyright (c) 2014 Lake Dawson Software <[https://lakedawson.com/](https://lakedawson.com/)>


## Documentation

* [Background](#background)
* [Installation](#install)
* [Getting Started](#start)
* [Recursive Operation](#recursion)
* [Auto Hydration](#hydration)
* [Simple Validation](#simple)
* [Extended Validation](#extended)
* [Retrieving Validation Errors](#errors)
* [i8n Custom Validation Messages](#i8n)
* [Overriding Validation](#override)
* [Hooks](#hooks)
* [Secure Text Attributes](#secure)
* [Random Word Generator](#random)


<a name="background"></a>
## Background

We primarily build large scale, multi tenant, single page applications based on [AngularJS](http://angularjs.org) and [Laravel 4](http://laravel.com). We are big on user experience and want to make sure our applications are simple to use and make sense.

We've always found that validating user input when dealing with nested relationships was a hassle, even with the brilliant [Ardent](https://github.com/laravelbook/ardent) by Max Ehsan. We want to minimise user frustration so we can't validate one record at a time since the user could give up if they think they've resolved the only error on the page and are given another error for a nested relationship that was invalid from the start.

This led to large inflexible controllers. We had to validate each model, keep track of any errors and make sure the method failed for any error encountered. If we wanted to add another relationship to our model this would mean we also needed to change our store and update methods and hard code in the new relationship so it could be validated. What a drama.

> **This is where Vocal comes in.**

Vocal is a recursive, self-validating extension for Eloquent. It works much the same way as Ardent, but supports nested relationships automatically. This will help you significantly reduce the amount of code you need to write leading to more free time so you can watch the [best cat videos](https://www.youtube.com/watch?v=cbP2N1BQdYc) on the internet.


<a name="install"></a>
## Installation

The first thing you need to do is add `lakedawson/vocal` as a requirement to `composer.json`:

```javascript
{
    "require": {
        "lakedawson/vocal": "0.1.*"
    }
}
```

Update your packages with `composer update` and you're ready to go.


<a name="start"></a>
## Getting Started

Vocal extends the Eloquent base class, so your models are still fully compatible with Eloquent. Vocal simply intercepts some methods such as `validate` and `save` before they're passed to Eloquent.

To create a new Vocal model, simply make your model class extend the Vocal base class:

```php
use LakeDawson\Vocal\Vocal;

class User extends Vocal {}
```

Alternatively you could just create a single Base model that extends Vocal and make everything else extend your Base model. This makes it easier to switch between Vocal, Eloquent and Ardent and doesn't require a `use` statement inside each model.

```php
use LakeDawson\Vocal\Vocal;

class Base extends Vocal {}
```

```php
class User extends Base {}
```

> **Note:** If you don't require Vocal functionality across all your models you can simply make some models extend Vocal and others extend Eloquent or Ardent.


<a name="recursion"></a>
## Recursive Operation

One of the primary features of Vocal is recursive validation and recursive saving via relationships. This makes it possible to validate and save entire record sets in one go.

This means we can work on a single page without involving multiple controllers.

For example, if we had an address book for each of our users on our site, and we want to give them an page so they can add/edit/delete addresses we would probably fetch the record(s) for the address book page like so:

```php
public function get()
{
    $user = User::with('addresses')->findorFail(Auth::user()->id);

    return View::make('addressbook')->with('user', $user);
}
```

If a user wants to edit an address, we would traditionally need to do this all over again and involve a `UserAddressController` and `UserAddress` model:

```php
public function get($id)
{
    $address = UserAddress::where('user_id', Auth::user()->id)->findOrFail($id);

    return View::make('editaddress')->with('address', $address);
}
```

Then when the user saves the address we would invoke the `update` function, which would validate the record, and either return the errors or save the record and send them back to the initial address book page. That seems like a lot of work. Wouldn't it be better to show one page, and allow them to change everything they want then save it all in one function?

> **Enter Vocal**

In this example we use AngularJS. We can load the `$user` into  `$scope.user`, and manipulate the `$scope.user.addresses` object without any server interaction. This also means if the user changes 10 records and decides that not what they wanted to do, they can easily abort without any of the records being updated.

When the user is finished we simply send the equivalent of the `$user` object back to the server and the magic happens.

The first major advantage of this is you don't need to create a user before they can add addresses to their address book, it can all be done in one go. This means you can ask the user for their address, or multiple addresses on a sign up page, and add it straight to their address book without having to work across multiple controllers or models.

The second advantage is we don't need separate `store` and `update` methods for `UserController` and then again for `UserAddressController`, as Vocal will handle all of this internally and detect whether it's working with an existing record or a whether it should create a new record automatically.

The third advantage is our new `save` method (to replace store and update in the `UserController`) can be just three lines long:

```php
public function save()
{
    $user = User::find(Input::get('id')) ?: new User;
    $result = $user->saveRecursive();

    return $user;
}
```

This will validate and update the user and all their addresses in one swoop. If the validation was unsuccessful for the `$user` object, or any of the addresses, we will have all the <a href="#errors">errors stored our `$user` object</a> and `$result` will be false. If everything was successfully validated and updated `Vocal->errors()` will be empty and `$result` will be true.


<a name="hydration"></a>
## Auto Hydration

To save you from having to update your models manually each time the user saves something, Vocal uses auto-hydration.

Take this update function as an example:

```php
public function update($id)
{
    $user = User::find($id) ?: new User;
    $user->name  = Input::get('name');
    $user->email = Input::get('email');
    $user->phone = Input::get('phone');

    $result = $user->save();
}
```

Boring...

To prevent boredom, Vocal will automatically hydrate models for you from `Input::all()` by default when calling `Vocal->save()` or `Vocal->saveRecursive()`. To make sure auto hydration only assigns the fields you want, you will need to define the `fillable` and/or `guarded` variables on the model based on Laravel's [mass assignment rules](http://laravel.com/docs/eloquent#mass-assignment).

The automatic hydration of models will happen on both new records and when updating records and all relationships will automatically be hydrated also.

> **Note:** If you don't want your model to be hydrated automatically, you can disabled it on a per model basis by setting the `fillFromInput` variable in your model to `false`.

```php
class User extends Vocal
{
    protected $fillFromInput = false;
}
```


<a name="simple"></a>
## Simple Validation

Vocal models use Laravel's built-in [Validator class](http://laravel.com/docs/validation). Defining validation rules for a model is simple and is typically done in your model class as a protected `$rules` variable:

```php
class User extends Vocal
{
    protected $rules = array(
      'name'  => array('required', 'unique'),
      'email' => array('required', 'email')
    );
}
```
You can use either pipe delimited or array syntax validation rules. See Laravel's [validation usage](http://laravel.com/docs/validation#basic-usage) for more information.

Vocal models validate themselves automatically when `Vocal->save()` or `Vocal->saveRecursive()` is called.

```php
$user        = new User;
$user->name  = 'John doe';
$user->email = 'john@doe.com';

$result = $user->save(); // <-- This will return false if model is invalid
```

> **Note:** You can also validate a model prior to saving by using the `Vocal->validate()` or `Vocal->validateRecursive()` methods.


<a name="extended"></a>
## Extended Validation

Some rules, such as Laravel's [unique rule](http://laravel.com/docs/validation#rule-unique) will fail if you attempt to update a record without updating the unique field, as it will see the field as not unique because it's already in the database. Laravel gets around this by allowing you to set a special parameter to ignore the current record, but since we define rules at a model level it's not easy to tell it automatically which record you want to exclude, which means we end up doing this at a controller level... which gives us even more redundant code. But never fear, Vocal will sort all this out for you.

Vocal handles these situations for you automatically in two ways:

#### Always skip current record

If you define a rule as just `unique` without any parameters Vocal will automatically fill in the parameters for you. You don't even need to put the name of the table, which is the minimum requirement by default. This makes it super simple to keep records of the same kind unique within the table.

For the following example, our primary key is id, but this can be overridden by setting the `primaryKey` variable on the model, and the id of the current record is 3:

```php
class User extends Vocal
{
    protected $rules = array(
      'name'  => array('required', 'unique'), // <-- This will automatically change to 'unique:users,name,3,id'
      'email' => array('required', 'email')
    );
}
```

#### User defined variables

You can also define variables at a model level. Variables are prefixed with a tilde (~), and matching attributes will be replaced.

This makes unique where clauses easier to implement. For example if we want to keep name unique to everyone in a specific group, but duplicates are allowed in the table itself providing they're in different groups, we can set some variables on our rule to achieve this. Any attribute on the current model can be used as a variable by specifying the attribute name.

For the following example, our primary key is id, the id of the current record is 3, and the group_id is 1:

```php
class User extends Vocal
{
    protected $rules = array(
      'name'  => array('required', 'unique:~table,~field,~id,id,group_id,~group_id'), // <-- This will automatically change to 'unique:users,name,3,id,group_id,1'
      'email' => array('required', 'email')
    );
}
```

> **Note:** There are some caveats with the variable system:

> * `~table` will be replaced with the table name *except* if there is a attribute in the database named table.
> * `~field` will be replaced with the field/column/attribute name *except* if there is a attribute named field.

> It's best to treat these as reserved words and not use `table` or `field` as a column/attribute on any model.


<a name="errors"></a>
## Retrieving Validation Errors

When a Vocal model fails to validate, there are two ways to retrieve errors.

Upon validation a nested `Illuminate\Support\MessageBag` object is attached to the Vocal object that contains validation failure messages.

If you are saving or validating a single record and want to retrieve errors directly from `MessageBag`, you can use `Vocal->errorBag()`. This works in the same way normal validation errors do, you can use `Vocal->errorBag()->all()` to get all messages and `Vocal->errorBag()->get('attribute')` to retrieve errors just for a single attribute.

> **Note:** Using the `MessageBag` with nested errors returned from recursive validation can cause irratic behaviour. Calling `Vocal->errorBag()->all()` will return an empty set if a record validates successfully but has a nested relationship with an error.

Errors can be retrieved as an array using the `Vocal->errors()` method. This is the recommended method when using `Vocal->saveRecursive()` or `Vocal->validateRecursive()`.

For example let's say our input looks like this:

```php
array(
    'description' => '',
    'options' => array(
        array(
            'description'   => 'Option 1',
            'somethingelse' => true
        ),
        array(
            'description'   => 'Option 2',
            'somethingelse' => true,
            'anotherlevel'  => array(
                'description' => '',
                'helloiama'   => 'childofoptions'
            )
        ),
        array(
            'description'   => 'Option 1',
            'somethingelse' => true
        )
    )
)
```

And our rules are like this on every model (`Record`, `RecordOptions`, `RecordOptionsAnotherlevel`):

```php
protected $rules = array(
    'description' => array('required')
);
```

Calling `Vocal->errors()` would give us this array in return:

```php
Array
(
    [description] => The description field is required.
    [options] => Array
        (
            [1] => Array
                (
                    [anotherlevel] => Array
                        (
                            [description] => The description field is required.
                        )

                )

        )

)
```

We could filter this further but calling `Vocal->errors($key)`. In this example we called `Vocal->errors('options.1.anotherlevel')`:

```php
Array
(
    [description] => You must specify a name (ChildChild)
)
```


<a name="i8n"></a>
## i8n Custom Validation Messages

Currently if you want to define [custom error messages](http://laravel.com/docs/validation#custom-error-messages) for a model, you must change the default language file or pass them from the controller. Ardent improves on this by allowing you to define them within the model itself.

All of these solutions have a flaw:

* Changing the default language file means your error messages need to be generic, this is especially bad for regex errors
* Passing error messages from the controller means you need to use `Lang::get()` and define all your error messages over and over again, which is more redundant code especially if you have the same validator for `save` and `update` methods
* The Ardent method is an improvement but is not i8n friendly. You're stuck with error messages for a single language

Vocal handles i8n custom messages for you. Simply create a folder called 'validation' in your `lang/locale` directory and add language files with the same name as the model. Vocal will automatically search for a validation language file prior to validating. If no matching language file is found there will be a fallback to the default validation file.

For example, if we have a user, we might want a better error message for why we need a valid email address. Our model would have these rules:

```php
class User extends Vocal
{
    protected $rules = array(
      'email' => array('required', 'email')
    );
}
```

For English, we would then have a file saved in `lang/en/validation/User.php` with the following:

```php
return array(
    'email' => array(
        'email'    => "That email address doesn't seem valid, are you sure you've typed it in correctly?",
        'required' => 'We need a valid email address so we can send you your ticket'
    )
);
```

If validation failed for either `email.required` or `email.email` the custom language file would be used. We could of course just specify one (or none) custom messages and then the default language from `lang/en/validation.php` would be used.


<a name="override"></a>
## Overriding Validation

You can override Vocal validation (and custom error messages) for a single call by passing `$rules` and/or `$messages` parameters to `Vocal->validate()`, `Vocal->validateRecursive()`, `Vocal->save()`, or `Vocal->saveRecursive()`.

All functions take two parameters:

- First parameter is `$rules`. This must be an array of Validator rules in the same form as they would be [defined in the model](#simple)
- Second parameter is `$messages`. This must be an array of [custom validation messages](http://laravel.com/docs/validation#custom-error-messages)

An array that is **not empty** will override the rules or custom error messages specified by the class for that instance of the method only.

> **Note:** The default value for `$rules` and `$messages` is an empty `array()`. If you pass an empty  `array()` nothing will be overriden.


<a name="hooks"></a>
## Hooks

> Hooks have been taken directly from [Ardent](https://github.com/laravelbook/ardent). If you only require hook functionality Ardent may be a more polished and better fit for your application.

Vocal provides hooks before and after any model changes.

Here's the complete list of available hooks:

- `before`/`afterCreate()`
- `before`/`afterSave()`
- `before`/`afterUpdate()`
- `before`/`afterDelete()`
- `before`/`afterValidate()`

All `before` hooks, when returning `false` (specifically boolean, not simply "falsy" values) will halt the operation. So, for example, if you want to stop saving if something goes wrong in a `beforeSave` method, just `return false` and the save will not happen - and obviously `afterSave` won't be called as well. As validation is run prior to each save, returning false from `afterValidate()` will also stop processing and stop the record from saving.

For example, you may use `afterCreate` to send a welcome email to a newly registered user:

```php
public function afterCreate()
{
    // User was created successfully, send welcome email
    if ( ! $this->emailSent) $this->sendWelcomeEmail();
}
```

The above example is especially useful for situations where users may be able to register themselves, but you also have an administration panel where you can add users manually. Instead of putting the code in each controller, you can place it in the model and it will be fired for each registration.

#### Additional beforeSave and afterSave

`beforeSave` and `afterSave` can be included at run-time. Simply pass in closures with the model as argument to the `save()` (or `forceSave()`) method.

```php
$user->save(array(), array(), array(), function ($model)
    {
        // Before save Closure
        Log::info("Saving user...");

        return true; // <-- You could return false here to stop the save from happening
    },
    function ($model)
    {
        // After save Closure
        Log::info("User saved successfully");
    }
);
```

> **Note:** the closures should have one parameter as it will be passed a reference to the model being saved.


<a name="secure"></a>
## Secure Text Attributes

> Secure text attributes have been taken directly from [Ardent](https://github.com/laravelbook/ardent). If you only require this functionality Ardent may be a more polished and better fit for your application.

Vocal can automatically hash attributes for you before saving them to the database. This is perfect for passwords or any other data you want to encrypt using Laravel's hash function.

If you want to automatically hash an attribute, add the field to the `hashAttributes` array to your model class:

```php
class User extends Vocal
{
    protected $hashAttributes = array('password');
}
```

Vocal will automatically replace the plain-text attribute with secure hash checksum and save it to database. It uses Laravel's `Hash::make()` method internally to generate the hash.


<a name="random"></a>
## Random Word Generator

Sometimes you just want to create a random sentence from dictionary words.

Vocal contains a built in dictionary of 1949 English words and can help you by creating a random sentence via the `Vocal->randomSentence($length)` method, where `$length` is the number of words to randomly select and put into a sentence.

```php
User::randomSentence(3); // Returns something like 'Human Bridge Indicate'
```
