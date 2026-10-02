---
title: Dashboard Authentication
description: Secure the Coolify dashboard
---

## Default Behavior

Local access only:

```php
// Default gate
Coolify::auth(function ($request) {
    return app()->environment('local');
});
```

:::caution
The default gate allows everyone when `APP_ENV=local`. If a deployed app ever runs with `APP_ENV=local`, the dashboard, with its deploy, restart and environment-variable controls, is open to anyone who can reach it. Set `APP_ENV=production` on deployed apps and define a `Coolify::auth()` gate.
:::

## Custom Authentication

In `AppServiceProvider::boot()`:

```php
use Stumason\Coolify\Coolify;

// Allow authenticated admins
Coolify::auth(function ($request) {
    return $request->user()?->isAdmin();
});
```

```php
// Allow specific users by email
Coolify::auth(function ($request) {
    return in_array($request->user()?->email, [
        'admin@example.com',
        'devops@example.com',
    ]);
});
```

```php
// Allow any authenticated user
Coolify::auth(function ($request) {
    return $request->user() !== null;
});
```

## Middleware

Add custom middleware via config:

```php
// config/coolify.php
'middleware' => ['web', 'auth', 'admin'],
```

Or via environment:

```bash
COOLIFY_MIDDLEWARE=web,auth,admin
```
