<!DOCTYPE html>
<html lang="en" class="dark h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Coolify - {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    <script>
        window.Coolify = {
            path: '{{ Str::start(config("coolify.path"), "/") }}',
            pollInterval: {{ (int) config('coolify.polling_interval', 5) * 1000 }},
        };
    </script>
    @if(file_exists(public_path('vendor/coolify/app.css')))
        <link rel="stylesheet" href="{{ asset('vendor/coolify/app.css') }}">
    @endif
</head>
<body class="h-full bg-zinc-950 text-zinc-100 antialiased">
    <div id="app" class="h-full"></div>
    @if(file_exists(public_path('vendor/coolify/app.js')))
        <script type="module" src="{{ asset('vendor/coolify/app.js') }}"></script>
    @endif
</body>
</html>
