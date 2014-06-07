# Vocal without AngularJS

Even though Vocal was designed to be used with AngularJS, you can use Vocal for resursive validation and saving with your regular blade templates. This can significantly reduce the amount of Controllers you need to have in order to use basic CRUD actions.

For this example we will have a users, and each user will have an address book.

> **Note:** This example is in no way complete, and requires using the `database` driver in `app/config/auth.php` out of the box as our User model doesn't implement `UserInterface` and `RemindableInterface`.

## Adding and Deleting Addresses

> This example does not have methods for adding an address to the form or deleting an address from the form. You will need to write these functions yourself.

### Adding an Address

To add an address, you just need to add fields to the form. Each address consists of these fields:

```php
<h2>Address {{ $address->id }}</h2>
{{ Form::label('address', 'Address') }} {{ Form::text('addresses[' . $address->id . '][address]', Input::old('address[' . $address->id . '][address]', $address->address)) }}<br>
{{ Form::label('city', 'City') }} {{ Form::text('addresses[' . $address->id . '][city]', Input::old('address[' . $address->id . '][city]', $address->city)) }}<br>
{{ Form::hidden('addresses[' . $address->id . '][id]', $address->id) }}
```

A new address will be exactly the same without ids. If an address is passed to the `save` method without an id it will automatically be created and attached to the user.

```php
<h2>New address</h2>
{{ Form::label('address', 'Address') }} {{ Form::text('addresses[][address]') }}<br>
{{ Form::label('city', 'City') }} {{ Form::text('addresses[][city]') }}<br>
```

You will also need some functionality to reinsert these fields into the DOM if there are errors. They will be passed back via `Input::old('addresses')` in the same way as normal.

### Deleting an Address

Deleting an address will require some modification to the save function to remove an existing address which wasn't passed in input.

```php
foreach ($user->addresses as $address)
{
    if ( ! Input::has('addresses.' . $address->id)) $address->delete();
}
```
