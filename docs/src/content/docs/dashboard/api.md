---
title: Dashboard API
description: JSON API endpoints for programmatic access
---

## Base URL

All endpoints are prefixed with `/coolify/api` (or your configured path).

## Applications

```
GET    /api/applications/{uuid}              # Get application details
POST   /api/applications/{uuid}/deploy       # Trigger deployment
POST   /api/applications/{uuid}/restart      # Restart application
POST   /api/applications/{uuid}/stop         # Stop application
POST   /api/applications/{uuid}/start        # Start application
GET    /api/applications/{uuid}/logs         # Get application logs
GET    /api/applications/{uuid}/envs         # List environment variables
POST   /api/applications/{uuid}/envs         # Create environment variable
PATCH  /api/applications/{uuid}/envs/{env}   # Update environment variable
DELETE /api/applications/{uuid}/envs/{env}   # Delete environment variable
```

## Deployments

```
GET    /api/applications/{uuid}/deployments  # List deployments
GET    /api/deployments/{uuid}               # Get deployment details
GET    /api/deployments/{uuid}/logs          # Get deployment logs
POST   /api/deployments/{uuid}/cancel        # Cancel deployment
```

## Databases

```
GET    /api/databases                        # List databases
GET    /api/databases/{uuid}                 # Get database details
POST   /api/databases/{uuid}/start           # Start database
POST   /api/databases/{uuid}/stop            # Stop database
POST   /api/databases/{uuid}/restart         # Restart database
POST   /api/databases/{uuid}/backup          # Trigger backup
GET    /api/databases/{uuid}/backups         # List backups
```

## Servers

```
GET    /api/servers                          # List servers
GET    /api/servers/{uuid}                   # Get server details
GET    /api/servers/{uuid}/resources         # List server resources
GET    /api/servers/{uuid}/domains           # List server domains
POST   /api/servers/{uuid}/validate          # Validate server connection
```

## Services

```
GET    /api/services                         # List services
GET    /api/services/{uuid}                  # Get service details
POST   /api/services/{uuid}/start            # Start service
POST   /api/services/{uuid}/stop             # Stop service
POST   /api/services/{uuid}/restart          # Restart service
```

## Projects

```
GET    /api/projects                         # List projects
GET    /api/projects/{uuid}                  # Get project details
GET    /api/projects/{uuid}/environments     # List environments
```

## Dashboard Stats

```
GET    /api/stats                            # Aggregated dashboard data
```

## Kick Integration

These endpoints proxy requests to Laravel Kick on your deployed applications.

```
GET    /api/kick/{appUuid}/status            # Check if Kick is configured
GET    /api/kick/{appUuid}/health            # Health check results
GET    /api/kick/{appUuid}/stats             # System stats (CPU, memory, disk)
GET    /api/kick/{appUuid}/logs              # List log files
GET    /api/kick/{appUuid}/logs/{file}       # Read log entries
GET    /api/kick/{appUuid}/queue             # Queue status
GET    /api/kick/{appUuid}/queue/failed      # Failed jobs list
GET    /api/kick/{appUuid}/artisan           # List available commands
POST   /api/kick/{appUuid}/artisan           # Execute artisan command
```

### Query Parameters

Log reading supports:

- `level` - Filter by log level (DEBUG, INFO, WARNING, ERROR, etc.)
- `search` - Full-text search
- `lines` - Number of lines to return (default: 100)
