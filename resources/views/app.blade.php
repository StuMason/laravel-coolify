<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>Coolify - {{ config('app.name') }}</title>

    {!! Coolify::css() !!}
</head>
<body class="h-full font-sans antialiased">
    @inertia

    {!! Coolify::js() !!}
</body>
</html>
