<div>
    {{-- Valid keys --}}
    <h1>{{ __('messages.welcome') }}</h1>
    <p>{{ trans('auth.failed') }}</p>

    {{-- Invalid keys --}}
    <div>{{ __('users.nonexistent') }}</div>
    <div>@lang('invalid.key')</div>
</div>
