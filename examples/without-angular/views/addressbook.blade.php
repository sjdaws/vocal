<html>
<body>
@if (count($errors))
    <h2>Errors</h2>
    @foreach ($errors as $relationship => $error)
        @if (is_array($error))
            @foreach ($error as $index => $message)
                {{ $relationship }} #{{ $index }}: 
                @foreach ($message as $field => $description)
                    {{ $field }} -> {{ $description }}
                @endforeach
            @endforeach
        @else
            {{ $error }}<br>
        @endif
    @endforeach
    <br><br>
@endif

@if (isset($success))
    <h2>Save successful!</h2>
@endif

{{ Form::model($user, array('route' => array('user.save', $user->id))) }}
    
    {{ Form::hidden('id', $user->id) }}
    {{ Form::label('username', 'Username') }} {{ Form::text('username') }}<br>
    {{ Form::label('email', 'Email') }} {{ Form::text('email') }}<br>

    @foreach ($user->addresses as $address)
        <h2>Address {{ $address->id }}</h2>
        {{ Form::label('address', 'Address') }} {{ Form::text('addresses[' . $address->id . '][address]', Input::old('address[' . $address->id . '][address]', $address->address)) }}<br>
        {{ Form::label('city', 'City') }} {{ Form::text('addresses[' . $address->id . '][city]', Input::old('address[' . $address->id . '][city]', $address->city)) }}<br>
        {{ Form::hidden('addresses[' . $address->id . '][id]', $address->id) }}
    @endforeach

    {{ Form::submit() }}

{{ Form::close() }}
</body>
</html>
