<!DOCTYPE html>
<html>
<head>
    <title>{{ __('users.title') }}</title>
</head>
<body>
    <h1>{{ __('users.title') }}</h1>
    <p>{{ trans('users.description') }}</p>

    @if($showWelcome)
        <div class="alert">
            @lang('messages.welcome')
        </div>
    @endif

    <footer>
        {{ __('messages.goodbye') }}
    </footer>
</body>
</html>
