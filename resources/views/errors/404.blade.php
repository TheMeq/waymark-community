<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('errors.404.title') }} | {{ config('app.name') }}</title>
</head>
<body>
    <main>
        <h1>{{ __('errors.404.title') }}</h1>
        <p>{{ __('errors.404.message') }}</p>
        <a href="{{ url('/') }}">{{ __('common.actions.back_home') }}</a>
    </main>
</body>
</html>
