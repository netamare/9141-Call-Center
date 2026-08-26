# Call Center 9141 — Event Escalation and Response Monitoring System

## Changelog (v9 — Real-time video streaming API)

**Real-time Camera Streaming**
- New `cameras` table + helpers (`includes/cameras.php`)
- JSON API: `admin/api_cameras.php` (list / get / save / delete / health)
- Camera Room **Live Streams** tab (HLS via hls.js, HTTP, MJPEG)
- Admin page `cameras_manage.php` to register stream URLs
- Local browser webcam test card
- RTSP → HLS restream recommended (MediaMTX / ffmpeg)

---

## Changelog (v8 — Camera Operator role, AI Detection Model Integration, Oromo-first, solid logo theme)

**New 5th role: Camera / Control Room Operator (`camera_operator`)**
Focused on the four core problem categories (Illegal Acts, Security, Service Delivery, Accident/Disaster). Access to Camera Room, Live Map and monitoring views.

**AI Detection Model Integration**
- Local detector at `ai/detect.py` (Python + Pillow + ffmpeg frame extraction).
- Analyses uploaded videos/images for traffic congestion, vehicles, people, suspicious activity, crowd density.
- Results mapped to the four categories and stored in `ai_detections` table (auto-created on first run).
- Camera Room page (`admin/cameras.php`) shows media grid + “Run AI Detection” button per item.
- Location text improves priors (road → traffic, market/suuq → theft risk).
- Swap-ready: replace `run_detection()` body in `detect.py` with real ONNX/Torch YOLO model; PHP contract unchanged.

**Language & theme**
- Default language is now Afaan Oromoo (English secondary).
- Gradients removed; solid navy palette matched to Adama City emblem.

---

## Changelog (v7 — theme-toggle fix, real emblem, SMS notifications, live incident map)

**Fixed the dark/light theme toggle (it was silently broken):**
`admin/includes/sidebar.php` printed its wiring `<script>` immediately when
included, but the actual button it wires up is only created later on the
page by `render_topbar_controls()`. So `document.getElementById('themeBtn')`
was always `null` at the moment the script ran, `.addEventListener` threw,
and the error silently killed the rest of that script block — including
the notification bell polling. Fixed by wrapping the whole block in
`DOMContentLoaded`, so it now runs after the button exists. No markup
changes needed anywhere else.

**Fixed a database name mismatch that could break the whole app:**
`config.php` was set to connect to a database called `9141`, but
`schema.sql` creates (and every migration targets) `callcenter9141`.
Fixed `config.php` to match `schema.sql`. If you already created a
database literally named `9141`, either rename it to `callcenter9141` or
re-point `DB_NAME` back — just make sure `config.php` and whichever
database you actually imported `schema.sql` into agree.

**Real Adama City Administration emblem:** the placeholder generated SVG
logo is replaced with the actual emblem image (`assets/logo-adama.png`) on
every page — public form, tracking page, admin login, and the admin
sidebar — plus it's now used as the browser tab favicon everywhere too.

**SMS notifications to citizens:** `includes/notifications.php` gained
`send_sms()`, which texts the tracking code the moment a report is
submitted, and a status update once it's marked Solved/Unsolved. No SMS
provider is bundled — same situation as the existing WhatsApp/IVR note
below, since that needs a paid telecom/gateway account this codebase can't
supply on its own — but `admin/settings.php` now has a full "SMS
notifications to citizens" panel where you paste **any** simple HTTP(S)
gateway URL as a template (`{phone}`, `{message}`, `{apikey}`, `{sender}`
placeholders), pick GET or POST, and turn it on. Works with most
REST-style local/bulk SMS resellers without touching code. Leave it
switched off and everything else in the system works exactly as before.

**Live Incident Map:** a new `admin/live_map.php` (+ `admin/api_map_events.php`
JSON endpoint), reachable from a new "Live Map" sidebar item. Shows every
GPS-tagged event as a colour-coded pin — gold for Illegal Acts, blue for
Security, green for Service Delivery, red (and pulsing, for high/critical
priority) for Accident/Disaster — with a popup (tracking code, category,
priority, status, address, department, time) and a legend. Polls every
20 seconds so it stays current without a page reload, with category and
active/all filters. Department officers only ever see their own
department's events, matching the existing Monitoring page's scoping.

---

## Changelog (v6 — required-field validation, phone format, search, report presets, header controls)

**Required fields marked with a star:** on the public report form, Category,
Description, Location, and Phone are now required and shown with a red `*`.

**Ethiopian phone number format enforced:** `includes/security.php` adds
`is_valid_et_phone()` — accepts local (`0912345678`) or international
(`+251912345678`) format only. Enforced server-side wherever a phone number
is captured or edited (public form, New Event, event edit), with a matching
client-side `pattern` attribute for instant feedback.

**Search:** the dashboard event list and CSV export now take a free-text `q`
filter matching tracking code, caller name, phone, location, address, or
description.

**Daily / Weekly / Monthly downloads:** `admin/reports.php` gained one-click
CSV presets for Today / This Week / This Month, plus a custom-export form
with Date From, Date To, Department, Category, Status, and Search —
all passed through to `admin/export_csv.php`, which now supports all of
these filters.

**Notifications, theme toggle, and "System Live" moved to the header:**
these three controls used to live in the sidebar footer; they're now
rendered via `render_topbar_controls()` (defined in
`admin/includes/sidebar.php`) next to the language switcher at the top of
every admin page, so they're visible without scrolling the sidebar.

---

## Changelog (v5 — GPS, geographic heat map, escalating alert, analytics, visual refresh)

**5-minute escalating alert for operators:** any event still sitting in `new`
status (i.e. no operator has escalated or updated it yet) past a configurable
threshold (default 5 min, `admin/settings.php`) fires an urgent notification
to every operator/administrator — reusing the existing 15s notification poll,
so no cron job is needed (`check_stale_new_events()` in
`includes/notifications.php`). The dashboard also shows a red banner when
events are past this threshold, and each "new" row in the event list carries
a live countdown badge that turns amber in the last minute and red once
escalated.

**GPS on events:** `events.latitude` / `events.longitude` (see
`migration_v5.sql`). A shared Leaflet-based location picker
(`includes/maps.php`) — click-to-pin, draggable marker, and a "Use my
location" (`navigator.geolocation`) button — is wired into the public report
form, the operator's New Event form, and the event edit form. The report
detail, citizen tracking, and About pages show a read-only pin when
coordinates are present.

**Heat map is now geographic, centered on Adama:** `admin/heatmap.php` plots
a real heat layer (Leaflet.heat) of GPS-tagged events over an OpenStreetMap
view of Adama city, weighted by priority, with a category filter. The
original day-of-week × hour grid (still useful for staffing) is kept below it
as a second view.

**New Analytics page** (`admin/analytics.php`, admin/supervisor): 30-day
trend line, SLA compliance donut, average response time by department,
a category × priority matrix, and top-reported locations.

**"About 9141" section enriched:** live stats (total reports, resolution
rate) and a small embedded map confirming this deployment's coverage area
(Adama city) are now shown on the public homepage.

**Full visual refresh:** `assets/style.css` got a broader pass — deeper
shadows, glass-blurred header/sidebar, refined button/badge/table states,
plus new components for the escalation banner, countdown badges, and map
cards. All existing class names are unchanged, so no page markup needed
touching beyond the new features above.

**Login for every role was already enforced** (v3) — Administrator, Call
Center Operator, Supervisor, and Department Officer all authenticate through
`admin/login.php`; nothing further was needed there.

**Schema additions:** `events.latitude`/`longitude`, `events.stale_alert_sent`,
new setting `operator_alert_minutes`. Existing installs: run
`migration_v5.sql` (back up first). Fresh installs: just use `schema.sql`.

---

## Changelog (v4 — notifications, ratings, theming, and staff tools)

**New pages:** `admin/performance.php` (operator performance table with resolution-rate bars), `admin/heatmap.php` (call volume by day-of-week × hour, for staffing decisions), `admin/notifications.php` (full notification history), `admin/help.php` (FAQ), `admin/feedback.php` (staff feedback + admin can view all submissions).

**Notifications system:** `includes/notifications.php` fans out a DB row per relevant user when a new event comes in or is escalated (urgent for high/critical priority). The sidebar bell shows a live unread badge, a dropdown of recent notifications (polls every 15s via `admin/api_notifications.php`), and plays a synthesized alarm beep (WebAudio, no audio file needed) for operators/admins when something urgent is unread.

**Light/dark theme toggle:** full CSS variable-based light theme, toggle button in the sidebar, persisted in the browser (localStorage) so it's instant with no server round-trip.

**Share button:** native share sheet on mobile, clipboard-copy fallback on desktop, points at the public report form.

**Description word limit:** configurable in Settings (default 150 words), enforced server-side and shown as a live counter on both the public form and the operator's New Event form.

**Idle-session auto-logout:** every role is required to re-authenticate after a period of inactivity (default 20 min, configurable in Settings) — this is the concrete implementation of "ask login for every role."

**Citizen satisfaction rating:** once an event is marked Solved or Unsolved, the citizen's tracking page (`track.php`) shows a 1–5 star rating + optional comment, stored on the event and rolled up into the Performance page per operator.

**"About 9141" section** added to the public form's homepage explaining the service and its four categories.

**Schema additions:** `notifications`, `feedback`, `user_preferences` tables; `events.satisfaction_rating`/`satisfaction_comment`; `events.channel` (web/phone/whatsapp/sms/webchat — a schema hook, not a live integration); new settings (`description_word_limit`, `session_idle_minutes`). Existing installs: run `migration_v4.sql` (back up first). Fresh installs: just use `schema.sql`.

### On the "Strategic System Expansion" list (IVR, WhatsApp/SMS, AI predictive analytics)
These need paid third-party infrastructure this codebase can't provide on its own:
- **IVR** needs a telephony provider (e.g. Africa's Talking, Twilio) with a webhook that creates events via the API — the `channel='phone'` field is ready to receive that.
- **WhatsApp/SMS intake** needs the WhatsApp Business API or an SMS gateway account; same webhook pattern.
- **AI predictive analytics** needs either a real ML pipeline or, more realistically at this scale, a statistical forecast (e.g. moving average by day-of-week) — the Heat Map page already gives you the raw pattern data that kind of forecast would be built on.

None of these are faked in this build — they're flagged here as a follow-up phase once you have provider accounts, and the schema/architecture is ready to receive them.

---

## Changelog (v3 — aligned to the full project specification document)

**This is a major structural rebuild.** The system now matches the
project document's data model, roles, and site structure.

**Data model renamed to match the spec's ERD:**
- `reports` table → `events` (with `report_attachments` → `event_attachments`, `report_logs` → `event_logs`)
- `severity` column → `priority`
- Status lifecycle: `new → escalated → in_progress → resolved → closed` is now `new → assigned → ongoing → solved → unsolved`
- Added `gender`, `address` (separate from `location`), `response_time_minutes` (auto-calculated when an event is marked solved/unsolved)

**Four user roles**, matching the spec exactly:
- **Administrator** — manages users, departments, settings; sees and can act on everything
- **Call Center Operator** — registers events, escalates, updates status, logs follow-up calls
- **Supervisor** — read-only monitoring, dashboards, reports/exports
- **Department Officer** — sees and updates only the events assigned to their own department

Each role logs in through the same `admin/login.php` and lands on `admin/dashboard.php`, but the dashboard content, sidebar nav, and available actions all adapt to that role's permissions — so it functions as "their own dashboard" while the Administrator retains full oversight of everything.

**New pages (matching the site structure in the spec):**
- `admin/new_event.php` — operator manually registers a phone call
- `admin/monitoring.php` — Ongoing / Solved / Unsolved tabs
- `admin/departments.php` — full department CRUD (admin only)
- `admin/users.php` — full user management: add/edit/deactivate, assign roles and departments (admin only)
- `admin/settings.php` — configurable SLA response-time targets per priority
- `admin/reports.php` — report-generation history + CSV export shortcut
- Follow-up calls are now tracked per event (`followups` table) so operators can log calling the citizen back to confirm resolution

**Dashboard additions (per the spec's requirements):** Today's Events, Monthly Events, Average Response Time, Number of Operators, plus 4 charts (Events by Month, by Department, Solved vs Unsolved, Event Categories) via Chart.js.

**Migration:** `migration_v3.sql` renames/alters an existing installation in place (back up first!). Fresh installs should just use `schema.sql`.

**Known limitation:** the new admin-facing strings (Users, Departments, Settings, Monitoring, follow-ups, etc.) are fully translated in English only so far; the other 6 languages fall back to English for these new sections until translated. The original citizen-facing report/track pages keep their existing translations.

---

## Changelog (v2 — security & monitoring pass)
**Fixed (critical):**
- `config.php` was connecting to database `callcenter_9141`, but `schema.sql` creates `callcenter9141` (no underscore) — this alone would break the entire app with "Database connection failed." Fixed to match.
- `admin/report.php` built a SQL query with a raw variable instead of a prepared statement when looking up a department name — fixed (was low-risk since the value was already cast to int, but inconsistent and worth closing).

**Added (security):**
- CSRF protection on the admin login, escalate, and status-update forms (`includes/security.php`).
- Brute-force lockout on admin login — 5 failed attempts locks the account for 15 minutes (`users.failed_attempts` / `users.locked_until`, new columns — see `migration_v2.sql` if you already have data).
- Honeypot spam-protection field on the public report form.

**Added (monitoring features — the core ask of an "escalation and response monitoring system"):**
- **SLA/overdue tracking**: each severity level has a response-time target (critical: 1h, high: 4h, medium: 24h, low: 72h — tune these in `admin/dashboard.php`'s `SLA_HOURS` constant). Reports past their target show a red "⏰ OVERDUE" badge, there's a dedicated "Overdue" stat card, and an "Overdue only" filter.
- **Department contact info displayed**: `departments.contact_phone` / `contact_email` were collected in the schema but never shown anywhere — now visible on the escalation panel so the operator actually knows who to call.
- **CSV export** (`admin/export_csv.php`) of the current filtered report list, for offline reporting/analysis.
- Database indices on `reports(status)`, `reports(category_id)`, `reports(severity, status)`, `reports(created_at)` for performance as report volume grows.

**Removed (unnecessary):**
- A stray empty `uploads.zip` sitting at the project root.
- Two leftover sample/test images in `uploads/` (identical file size — clearly test artifacts, not real submissions).

---

A PHP + MySQL web system for the Adama 9141 toll-free call center, based on its four
public report categories: illegal acts, security problems, service delivery problems,
and accidents/disasters. Supports multi-language access, media uploads (photo, video,
voice, document), a logo on every page, and a live services dashboard.

## What it does
- **Public form** (`index.php`) — anyone submits a report by category, description,
  location and severity, optionally attaching a photo, video, voice note, or document,
  and gets a tracking code back.
- **Tracking page** (`track.php`) — the public checks a report's status with their
  tracking code, including any attached media.
- **Operator/admin dashboard** (`admin/dashboard.php`) — login-protected view with a
  **Services panel** showing all 4 categories, their live report counts, and thumbnails
  of the most recently uploaded photos for each — plus overall stats and a filterable
  report list.
- **Report management** (`admin/report.php`) — escalate a report to a department,
  change its status, add a note, view attached media inline (image/video/audio
  players, document links), and see the full audit trail.
- **7-language interface** — English, Afaan Oromoo, አማርኛ (Amharic), العربية (Arabic,
  right-to-left), ትግርኛ (Tigrinya), Soomaali, and Qafar Af (Afar). A language switcher
  appears in the header of every page and remembers the choice for the session.
- **Logo** — the 9141 emblem (`assets/logo.svg`) appears in the header of every page.

## Setup (XAMPP / WAMP / LAMP)
1. Create the database: import `schema.sql` into MySQL — e.g. in phpMyAdmin, or:
   ```
   mysql -u root -p < schema.sql
   ```
2. Edit `config.php` with your DB host/user/password if different from defaults
   (`DB_NAME` must be `callcenter9141` to match `schema.sql`).
3. Copy the whole `callcenter9141/` folder into your web root
   (e.g. `htdocs/` for XAMPP) — **extract the full zip, don't copy loose files**, so
   the `admin/`, `includes/`, `assets/`, `lang/`, and `uploads/` subfolders land
   in the right place relative to `config.php`.
4. Make sure the `uploads/` folder is writable by the web server
   (Apache/PHP needs write access to save attached photos, videos, voice notes, and
   documents). On Windows/XAMPP this is usually fine by default.
5. Visit `http://localhost/callcenter9141/` for the public form,
   and `http://localhost/callcenter9141/admin/login.php` for the dashboard.
6. Default login: **admin / admin123** — change the password (or add real users)
   right after your first login by updating the `users` table.

## File uploads
- Accepted types: images (jpg, png, gif, webp), video (mp4, mov, 3gp, webm),
  audio/voice notes (mp3, wav, m4a, ogg, amr), and documents (pdf, doc, docx).
- Up to 5 files per report, 25MB each (edit `MAX_UPLOAD_BYTES` /
  `MAX_FILES_PER_REPORT` in `config.php` to change these limits).
- Files are renamed to random hex names on save and stored in `uploads/`, which has
  a `.htaccess` rule blocking PHP execution for security. Metadata (original name,
  type, size) is recorded in the `report_attachments` table.
- On mobile browsers, the file picker offers the camera/microphone directly
  (`capture` attribute on the upload input), so a citizen can snap a photo or record
  a voice note on the spot.

## Languages
- Language files live in `lang/` (`en.php`, `om.php`, `am.php`, `ar.php`, `ti.php`,
  `so.php`, `aa.php`) as simple `key => translated string` arrays.
- `includes/lang.php` loads the active language (via `?lang=xx` or the session),
  and the `t('key')` helper prints the translated, HTML-escaped string.
- To add or edit a language: copy `lang/en.php`, translate the values (keep the keys
  the same), add the language code and native name to `SUPPORTED_LANGS` in
  `includes/lang.php`.

## Database structure
- `categories` — the 4 services from the 9141 poster, each with an icon and
  description used on the dashboard's Services panel.
- `departments` — where a report gets escalated (police, fire, revenue bureau, etc).
- `reports` — every submitted event, its status, severity, and assigned department.
- `report_attachments` — uploaded photos/videos/voice notes/documents per report.
- `report_logs` — audit trail of every escalation/status change (this is what makes
  it a *monitoring* system, not just a form).
- `users` — operator/admin accounts.

## How to present/build this as a school or defense project
1. **Problem statement** — manual call logging (like Adama's current Avaya-based
   system) makes it hard to track escalation and measure resolution rates in real time.
2. **Objectives** — digitize intake (including photo/video/voice evidence), categorize
   automatically, route to the right department, monitor status/turnaround with an
   audit trail, and serve citizens in their own language.
3. **System design** — categories → reports → departments, reports → report_logs,
   reports → report_attachments; escalation flow (caller → call center → categorize →
   escalate → resolve).
4. **Implementation** — this PHP/MySQL codebase (form-based intake with file uploads,
   session-based admin auth, PDO prepared statements, a small i18n layer).
5. **Testing** — submit sample reports in each of the 4 categories with attachments,
   escalate them, change statuses, and confirm the audit log, dashboard counts, and
   service-card photo previews update correctly.
6. **Results/evaluation** — you can reuse Adama's real published numbers (95% drop in
   illegalities, 91% drop in service-delivery complaints over 11 months) as your
   benchmark/motivation.
7. **Slides (PPT) structure** — Title → Problem → Objectives → Literature/Case study
   (Adama 9141 stats) → System architecture diagram → ER diagram → Screenshots of the
   public form (in a couple of languages), dashboard services panel, and report
   management page → Results/testing → Conclusion and future work (SMS integration,
   CCTV/AI integration, mobile app).
