# CAMPUSPHERE architecture

## Layers (LavaLust MVC)

```
public/index.php → Router (app/config/routes.php) → Middleware → Controller → Model(s) → View
```

- **Routes**: public (`/`, `/api/gate/scan`), `guest` group (login), `secure` group (everything else). Static routes precede `{id}` routes (first match wins).
- **Middleware**: `GuestMiddleware` redirects signed-in users home; `SecureMiddleware` requires a session, forces a password change when flagged, and applies the **path ACL** (`access_map` in `app/config/campus.php`, logic in `libraries/Acl.php`, fail-closed).
- **Controllers** extend `Base_controller` (loads model aliases, input helpers, flash, `render()`, `respond()`, audit shortcut). Admin-only controllers call `require_role()`.
- **Models** extend `Campus_model` (`rows/row/val/run/insert_row/update_row`, `ok()/fail()` result arrays). **Row-level scopes** live here: a teacher only reaches students of their own sections, a student only their own record.
- **Libraries (pure logic, unit-tested)**: `Acl`, `Standing`, `Grading`, `Forecast`; `Auth` handles sessions and throttling.
- **Views** are plain PHP receiving data arrays only; all output goes through `e()`. Shared UI via helpers (`ui_*`, `icon`, `chart_*`).

## Data model (database/schema.sql, InnoDB, utf8mb4)

users → teachers / students (1:1 by `user_id`) · school_years · sections · subjects · class_assignments · enrollments · enrollment_history · grades · gate_devices · gate_logs · class_attendance · attendance_corrections · activities · submissions · service_requests · request_status_log · notices · notice_reads · guidance_notes · interventions · audit_logs · login_attempts · settings. Foreign keys enforce integrity; multi-row writes (enrollment approval, request creation, grading) run in transactions.

## Attendance reconciliation

Gate scans (`gate_logs`, source `Scanner`/`Manual`) are the source of truth for *presence on campus*; `class_attendance` is the teacher's record per subject and day. A **discrepancy** is a learner with a Time-in scan and a started class period with no classroom record. Resolution (`/attendance`) records a status plus mandatory reason in `attendance_corrections`.

## Standing rules (rule-based, explainable)

`Standing::evaluate(average, attendance, missing, rules)` checks two rule sets from config (defaults): *support* if average < 83, attendance < 90 % or ≥ 4 missing; *review* if average < 88, attendance < 93 % or ≥ 1 missing. The highest level hit wins and its reasons are returned for display.

## Forecast method

For grade *g* in year *y*: retention = H[y][g] / H[y-1][g-1], averaged over all years with data (history plus the live headcount). Projected Grade 8–12 = this year's previous grade × mean retention (low/high use min/max ratio). Grade 7 intake = linear trend of past Grade 7 headcounts. Sections = ceil(projected / capacity). Pending applications are not counted.

## Security model

| Concern | Control |
|---|---|
| Authentication | bcrypt (`password_hash`), rehash on login, session id regenerated, user re-read each request (deactivation is immediate) |
| Brute force | 5 failures / 15 min per username+IP, constant-time dummy hash for unknown users, attempts stored |
| CSRF | framework token on all POSTs; only `.*api/gate/scan` is excluded (machine endpoint, bearer-token authenticated) |
| Authorization | path ACL + row-level scopes; 403 page for violations; every state change audited |
| Uploads | extension + magic-byte check, size limit, random filename, stored in `runtime/uploads` (outside web root, served through an authorised controller) |
| Device API | token shown once, SHA-256 stored, per-device enable/disable, `last_seen_at` |
| Output | `e()` everywhere, CSV formula neutralisation, security headers (CSP, frame-deny, nosniff, referrer policy) |
| Secrets | `.env` only; `.htaccess` denies `.env`, `.sql`, `runtime/`, `app/`, `scheme/` |

## Deployment checklist

1. `APP_ENV=production`, strong `APP_KEY`, dedicated DB user with only this schema.
2. Serve over HTTPS; document root `public/`; PHP `display_errors=0`.
3. Run `php database/install.php --admin-password='…'` (no `--demo`).
4. Back up the database and `runtime/uploads/`.
5. Put the gate reader on the school LAN and register it under Attendance → Devices.
