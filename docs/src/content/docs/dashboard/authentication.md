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
