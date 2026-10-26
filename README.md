# CAMPUSPHERE for ANHS

A unified school information system for **Aurora National High School**, built on the **LavaLust 4.7 MVC framework** (PHP + MySQL/MariaDB). It turns the CampuSphere prototype into one working system: gate-driven attendance, enrollment, academic records, activities, student services, guidance, forecasting and role-based access, all on one database.

| Role | Home | What they do |
|---|---|---|
| Administrator | `/dashboard` | Everything: enrollment, attendance resolution, devices, grades overview, services, users, audit log, forecast, reports |
| Teacher | `/teacher` | Class attendance, grades, activities and submissions for their own sections; announcements to their sections |
| Student | `/student` | Own grades, attendance, activities (upload work), service requests, notices |
| Guidance | `/guidance` | Standing review, notes and intervention plans, insights |

## Quick start

Requirements: PHP 8.1+ (`pdo_mysql`, `mbstring`, `fileinfo`), MySQL 8 or MariaDB 10.4+.

```bash
cp .env.example .env                 # set DB_USER / DB_PASSWORD / APP_KEY
php database/install.php --demo      # creates the database, tables, admin and a demo school
php -S 127.0.0.1:8080 -t public public/router.php
```

Open <http://127.0.0.1:8080>. **Laragon / XAMPP / Apache:** point the virtual host's document root at `public/` (the included `.htaccess` files handle routing and block `app/`, `scheme/`, `runtime/`).

Installer options: `--fresh --yes` (drop everything first), `--admin-user=`, `--admin-password=`. Without `--demo` it creates only reference data and an administrator with a random one-time password (printed once; change on first sign-in is enforced).

### Demo accounts (password `Campus@2026`)
`admin` · `teacher` (Paolo Dela Cruz) · `student` (Sofia Mae Reyes) · `guidance` (Rosalie Andrada). Every learner also signs in with their student number, e.g. `anhs-2026-002`. **Never use `--demo` in production**; set `APP_ENV=production` to hide the demo box on the login page.

## Feature map

- **Attendance**: the gate reader posts scans to `POST /api/gate/scan` (bearer token); teachers record class attendance; the system flags learners who entered the gate but have no classroom record (discrepancies). Every correction needs a reason and is kept in an audit trail.
- **Enrollment**: application → document verification → approval (creates the learner record and a student account in one transaction) or rejection with reason.
- **Academics**: quarter grades per subject, final grade and remarks computed server-side; activities with deadlines, file submissions (validated and stored outside the web root), grading with feedback.
- **Student standing ("AI Insights")**: *Good Standing / Needs Review / Recommended Support* from general average, attendance, missing requirements, falling quarter grades, subjects below 80 and consecutive absences. This is **rule-based decision support**, not machine learning: thresholds are in `app/config/campus.php`, each result lists its reasons, and it never changes a record.
- **Guidance**: notes, commendations, parent consultations and support cases, plus intervention plans and a quarterly trend.
- **Forecast**: cohort-survival projection of next year's headcount, sections and estimated teachers needed, with a low/high band (method in `docs/ARCHITECTURE.md`).
- **Services, notifications, reports**: certificate/record requests with status history, audience-targeted notices, filtered report previews (grade, section, status, date range) with CSV export (formula-injection safe).
- **Security**: bcrypt, session regeneration, login throttling, CSRF on every form, path ACL + row-level scopes, CSP and security headers, audit log, hashed device tokens.
- **Offline-friendly**: no CDN, no web fonts, charts are server-side SVG. Works on a school LAN.

## Gate scanner API

```bash
curl -X POST http://HOST/api/gate/scan \
  -H "Authorization: Bearer <device token>" -H "Content-Type: application/json" \
  -d '{"student_no":"ANHS-2026-001"}'          # optional "event": "Time-in" | "Time-out"
```
Create devices and tokens under **Attendance → Devices** (the token is shown once; only its SHA-256 hash is stored). The demo installer prints a demo token.

## Tests

```bash
php tests/run.php          # unit tests: Acl, Standing, Grading, Forecast
./tests/smoke.sh           # every page, every role, no PHP errors, ACL 403s
php tests/flows.php        # 80 end-to-end checks (login, CSRF, lockout, enrollment, attendance,
                           # gate API, grades, uploads, services, guidance, users, exports)
```
The HTTP tests expect the app on `127.0.0.1:8099` and a freshly seeded demo database (`php database/install.php --fresh --yes --demo`).

## Notes on the framework

LavaLust is vendored in `scheme/`. One small patch was needed: `scheme/kernel/Security.php` validated the CSRF token twice per request (the second pass always failed), so it now validates once. Input is read from `$_GET`/`$_POST` directly because `Io::get()/post()` raise warnings on missing keys. See `docs/LAVALUST.md` for the framework's own readme.

### Upgrading an existing database
Run `database/migrations/001_guidance_note_kind.sql` (adds the note type column used by commendations and consultations).
