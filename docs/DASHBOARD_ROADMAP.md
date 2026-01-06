# Dashboard Enhancement Roadmap

> Last updated: 2026-01-06

This document outlines the planned enhancements for the Laravel Coolify dashboard, organized into implementation phases.

## API Capabilities Research

Based on comprehensive research of the Coolify API (v4), here's what IS and ISN'T possible:

### What Works via API
- Application CRUD and lifecycle (start/stop/restart/deploy)
- Database CRUD and lifecycle
- Database backups (trigger and list)
- Deployment history and logs (polling, not streaming)
- Environment variables CRUD
- Rollback to previous deployments (local Docker images)
- Server and project management
- Security keys management

### What Doesn't Work via API
- Real-time log streaming (SSE/WebSocket) - dashboard only
- Container command execution - SSH only
- Metrics/monitoring (CPU, memory, disk) - Sentinel/dashboard only
- SSL certificate management - automatic or manual file upload
- Individual process/worker status - not exposed
- Webhook management - GitHub OAuth only

### Known Limitations
- Rollback to specific Git commits is buggy (#1976)
- Database restore only works for standalone databases
- No programmatic terminal access

---

## Implementation Phases

### Phase 1: Quick Wins (Free)

High-value, low-effort improvements using existing API capabilities.

| Feature | Description | API Support | Complexity |
|---------|-------------|-------------|------------|
| **One-Click Rollback** | Rollback button on each deployment in history | `rollback()` exists | Low |
| **Database Backup UI** | Trigger backups, view backup history | `backup()`, `backups()` exist | Medium |
| **Connection String Copy** | Quick copy buttons for DATABASE_URL, REDIS_URL | Data already fetched | Low |
| **Deploy with Tag/Branch** | Input field to deploy specific tag or branch | `deployTag()` exists | Low |
| **Keyboard Shortcuts** | D=deploy, R=restart, Esc=close modals | UI only | Low |

### Phase 2: Enhanced UX (Free)

Improved user experience and workflow features.

| Feature | Description | Implementation |
|---------|-------------|----------------|
| **Smart Log Polling** | Auto-refresh logs during active deployment, progress indicator | Polling with status detection |
| **Environment Diff** | Show env var changes before deploying | Compare current vs staged |
| **Bulk Env Import** | Paste .env format to import multiple vars | Parse and batch create |
| **Quick Actions Menu** | Dropdown with deploy, restart, rollback, logs | UI component |
| **Deploy Sound Effects** | Optional audio feedback on success/failure | JS Audio API, localStorage toggle |
| **Dark/Light Mode** | Theme toggle with system preference detection | Tailwind dark mode |

### Phase 3: Pro Features (Paid)

Premium features that provide significant value for teams and production use.

| Feature | Description | Value Proposition |
|---------|-------------|-------------------|
| **Deployment Analytics** | Track success rate, avg deploy time, trends | Data-driven deployment insights |
| **Multi-App Dashboard** | View and manage multiple applications | Single pane of glass |
| **Scheduled Deployments** | Deploy at specific times (maintenance windows) | Controlled release timing |
| **Deployment Approvals** | Require approval before production deploy | Team governance |
| **Team Activity Log** | Audit trail of who did what when | Compliance and debugging |
| **Custom Health Checks** | HTTP checks beyond Coolify's /up | Application-specific monitoring |
| **Deployment Webhooks** | Notify external systems (Slack, Discord, etc.) | Integration flexibility |
| **AI Failure Analysis** | Analyze logs and suggest fixes when deploy fails | Faster incident resolution |

### Phase 4: Differentiators (Weird/Innovative)

Features that make the package memorable and delightful.

| Feature | Description | Wow Factor |
|---------|-------------|------------|
| **Deployment Streaks** | Gamification - track consecutive successful deploys | Fun, engagement |
| **Cost Tracking** | Manual input to track hosting costs per app | Budget visibility |
| **Dependency Scanner** | Parse lock files, flag outdated/vulnerable packages | Security awareness |
| **Performance Budgets** | Set thresholds (deploy time < 5min), alert on breach | Quality gates |
| **Deploy Confidence Score** | Heuristic score based on tests, time of day, history | Risk assessment |

---

## Database Features Detail

The Resources tab should be significantly enhanced for database management:

### Current State
- View database status
- Start/stop/restart controls
- Basic info display (type, status, image)

### Planned Enhancements

#### Backup Management
```
┌─────────────────────────────────────────────────────────┐
│ Database Backups                          [Backup Now]  │
├─────────────────────────────────────────────────────────┤
│ 2026-01-06 14:30  │ 245 MB  │ Completed  │ [Download]  │
│ 2026-01-05 14:30  │ 243 MB  │ Completed  │ [Download]  │
│ 2026-01-04 14:30  │ 241 MB  │ Completed  │ [Download]  │
└─────────────────────────────────────────────────────────┘
```

#### Connection Strings
```
┌─────────────────────────────────────────────────────────┐
│ Connection Strings                                      │
├─────────────────────────────────────────────────────────┤
│ Internal URL     │ postgres://...         │ [Copy]     │
│ DATABASE_URL     │ postgres://...         │ [Copy]     │
│ Laravel Config   │ 'pgsql' => [...]       │ [Copy]     │
└─────────────────────────────────────────────────────────┘
```

---

## Pro Edition Considerations

### Licensing Model Options
1. **License Key** - Simple key validation, annual renewal
2. **Seats-Based** - Per-developer pricing
3. **Feature Flags** - Unlock specific features
4. **Usage-Based** - Free up to N deploys/month

### Potential Pro-Only Features
- Multi-app dashboard (> 1 app)
- Deployment analytics and trends
- Scheduled deployments
- Approval workflows
- AI-powered features
- Priority support

### Implementation Approach
- Use Laravel's built-in encryption for license validation
- Feature flags via config/database
- Graceful degradation (pro features hidden, not broken)
- Clear upgrade prompts in UI

---

## Technical Notes

### Testing Strategy
- Use `@dev` version in northranger for real-world testing
- Mock API responses for unit tests
- Feature tests for dashboard interactions

### Performance Considerations
- Lazy-load tabs (don't fetch all data upfront)
- Debounce log polling during deploys
- Cache deployment history locally
- Paginate long lists

### Accessibility
- Keyboard navigation throughout
- ARIA labels on interactive elements
- Color-blind friendly status indicators
- Screen reader announcements for async actions

---

## Changelog

### 2026-01-06
- Initial roadmap created
- API capabilities researched and documented
- Four implementation phases defined
- Pro edition considerations outlined
