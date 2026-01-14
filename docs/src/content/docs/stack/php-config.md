---
title: PHP Configuration
description: Production PHP settings
---

## OPcache Settings

```ini
[opcache]
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=30000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.jit_buffer_size=256M
opcache.jit=1255
```

`validate_timestamps=0` disables file modification checks. Config/route/view caches must be rebuilt on deploy.

## Memory Settings

```ini
memory_limit=512M
post_max_size=100M
upload_max_filesize=100M
max_execution_time=60
max_input_time=60
```

## PHP-FPM Pool

```ini
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

## Process Manager Modes

| Mode | Behavior |
|------|----------|
| static | Fixed number of children |
| dynamic | Scale between min/max |
| ondemand | Spawn on request |

`dynamic` balances memory usage and response time.

## Recommended Tuning

For production, adjust based on available memory:

```
max_children = (Total RAM - OS overhead) / Average PHP process size
```

Typical PHP process: 30-50MB. On a 4GB server:

```
max_children = (4000 - 500) / 40 = ~87
```

Start conservative and monitor memory usage.
