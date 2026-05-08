# Multi-Tier Escalation Management System

Professional capstone-ready help desk and SLA escalation platform built with native PHP, MySQL, HTML, CSS, and JavaScript.

## Database Structure

The schema is normalized around operational modules:

- `roles`, `users`, `support_levels`, `teams`, `agent_profiles` manage authentication, team ownership, and tiered agent assignments.
- `categories`, `priorities`, `sla_rules`, `settings` define configurable service-desk metadata and escalation thresholds.
- `tickets`, `ticket_assignments`, `ticket_updates`, `resolution_logs` hold the incident lifecycle from creation through closure.
- `notifications`, `escalation_history`, `audit_trails` provide in-app alerts, SLA escalation records, and governance/audit evidence.

## Role Permissions

- Requester: create tickets, view own tickets, receive notifications, confirm closure, or reopen resolved tickets.
- Level 1/2/3 Agents: work tickets at their support tier, add updates, resolve tickets, and escalate to higher tiers with a reason.
- Administrator: manage users, teams, agents, assignments, SLA rules, reports, and audit visibility across all tickets.

## SLA Escalation Logic

- The ticket form collects `problem_intensity`, `affected_user_type`, `number_of_users_affected`, `total_number_of_users`, computed `affected_user_percentage`, and `business_impact`.
- Priority is auto-derived:
  - `P1` when 50%+ users are affected, impact is system-wide/security/major, or intensity is Critical.
  - `P2` when 10% to 49% are affected, the issue disrupts a department, or intensity is High/Medium.
  - `P3` for low-impact and minor issues.
- SLA targets are loaded from `sla_rules` and applied at creation time.
- `cron/auto_escalate.php` marks tickets as `At Risk`, creates warning notifications, and escalates breached tickets to Level 2 or Level 3 based on priority and blast radius.

## Run Locally with XAMPP or Laragon

1. Create a MySQL database named `escalation_system`.
2. Import [schema.sql](/C:/Users/JD/Documents/Codex/2026-05-09/create-a-new-repository-and-name/escalation-system/database/schema.sql) and then [seed.sql](/C:/Users/JD/Documents/Codex/2026-05-09/create-a-new-repository-and-name/escalation-system/database/seed.sql).
3. Place the `escalation-system` folder inside your XAMPP `htdocs` or Laragon `www` directory.
4. Update database credentials in [database.php](/C:/Users/JD/Documents/Codex/2026-05-09/create-a-new-repository-and-name/escalation-system/config/database.php) if needed.
5. Open `http://localhost/escalation-system/login.php`.
6. Demo accounts:
   - `admin@example.com / password123`
   - `level1@example.com / password123`
   - `level2@example.com / password123`
   - `level3@example.com / password123`
   - `user@example.com / password123`
7. For automatic SLA checks, schedule:

```bash
php cron/auto_escalate.php
```

every 5 minutes in Windows Task Scheduler or cron.

## Vercel Deployment Notes

- This repository includes [vercel.json](/C:/Users/JD/Documents/Codex/2026-05-09/create-a-new-repository-and-name/escalation-system/vercel.json) configured for the `vercel-php` community runtime.
- For full functionality on Vercel, configure external MySQL environment variables:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_NAME`
  - `DB_USER`
  - `DB_PASS`
- Because Vercel’s filesystem is read-only at runtime, file uploads are stored as placeholders only in this capstone build unless connected to external object storage.
