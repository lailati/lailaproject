# Progress — Web-Based Talent Mapping System for FSTP Lecturers

**Project folder:** `C:\VR Box\UPNM\projectlaila`
**Student:** Lailati binti Mohd (2241073) · UPNM FSTP · PSM TSK 3306
**Last updated:** 13 October 2026

---

## 1. Current status

| # | Item | Status | Notes |
|---|---|---|---|
| 1 | Proposal reviewed | ✅ Done | `..\PSM\Proposal PSM_2241073_LAILATI MOHD.pdf` — 3 objectives, roles Admin + Pensyarah, stack HTML/CSS/Bootstrap/JS + PHP + MySQL (XAMPP) |
| 2 | UI mockups reviewed | ✅ Done | `FSTP Talent Mapping UI Collage.png` (12 screens), `FSTP Talent Map Dashboard Mockup.png` (11 forms), `ChatGPT Image Oct 5 2026.png` (system overview diagrams) |
| 3 | Development environment | ✅ Done | XAMPP 8.2.12 installed to `C:\xampp`; PHP 8.2.12 + MariaDB 10.4.32 running on ports 80 / 3306 |
| 4 | ERD | ✅ Done | `docs/erd.mmd` (Mermaid source) + `docs/erd.svg`, `docs/erd.png`, `docs/erd.html` viewer |
| 5 | System requirements (SRS) | ✅ Done | `docs/system-requirements.md` — 55 FRs, 10 NFRs, roles matrix, environment, data dictionary |
| 6 | Database schema + sample data | ✅ Done | `app/database/fstp_talent_map.sql` — 14 tables, imported & verified (12 lecturers, 12 categories, 0 orphan rows) |
| 7 | Application scaffold | ✅ Done | `app/` — config, includes, auth guard, Bootstrap 5 layout, vendored assets (works offline) |
| 8 | Authentication | ✅ Done | Login (both roles), logout, forgot password, forced password change, CSRF, bcrypt |
| 9 | Dashboards | ✅ Done | Pentadbir: live KPIs + 2 charts + activity feed. Pensyarah: own counts, profile completion, activity, top expertise |
| 10 | Manage Users (Pentadbir) | ✅ Done | Create / activate / deactivate / reset password / delete, search and filters |
| 11 | Admin modules | ✅ Done | Lecturers + full profile view, Expertise Categories (CRUD), Talent Mapping search, 7 record browsers, Statistics, Reports |
| 12 | Lecturer modules | ✅ Done | My Profile (photo upload), My Expertise (M:N with level + years), and 7 record modules with add / edit / delete and file uploads |
| 13 | User acceptance test | ⬜ Next | Against `docs/system-requirements.md` §8 |

**Verification:** `tools/smoke_test.py` — **92 / 92 checks pass** against the live stack: both roles logging in, role separation, record CRUD, cross-lecturer access blocked, upload validation, every page rendering clean, and every CSV export. The test cleans up the records, files and accounts it creates.

---

## 2. Environment facts

```
XAMPP        8.2.12      C:\xampp            installer md5 c09aeaef58e6a7614563303636ec3810
PHP          8.2.12      C:\xampp\php\php.exe        mysqli, pdo_mysql, mbstring, gd, zip, intl
MariaDB      10.4.32     C:\xampp\mysql             port 3306, user root (no password, local only)
Apache       2.4.58      C:\xampp\apache            port 80
phpMyAdmin   5.2.1       C:\xampp\phpMyAdmin
Web root                 C:\xampp\htdocs
Database name            fstp_talent_map            (imported)
App URL                  http://localhost/projectlaila/
Front-end libs           vendored locally: Bootstrap 5.3.3, Bootstrap Icons 1.11.3, Chart.js 4.4.3
```

**Safety note:** the existing web root was backed up to `C:\xampp-htdocs-backup` (22,107 files) before XAMPP was installed, and verified intact afterwards. `php.ini` backup: `C:\xampp\php\php.ini.bak-20261013`.

**Starting the stack:** open `C:\xampp\xampp-control.exe` → Start Apache and MySQL. The app is then at `http://localhost/projectlaila/`.

**Code location:** the application lives in this project folder at `app\`.
`C:\xampp\htdocs\projectlaila` is a **directory junction** to `app\`, so Apache serves it
at `http://localhost/projectlaila/` with no file copying and no Apache config changes.
Recreate it with:

```powershell
New-Item -ItemType Junction -Path 'C:\xampp\htdocs\projectlaila' -Target 'C:\VR Box\UPNM\projectlaila\app'
```

---

## 3. Schema summary (14 tables)

`users` · `lecturers` · `departments` · `expertise_categories` · `expertise` · `lecturer_expertise` · `research` · `publications` · `certifications` · `training` · `consultancy` · `achievements` · `documents` · `activity_log`

Core rule: `users` 1:1 `lecturers`; `lecturers` ↔ `expertise` is many-to-many through `lecturer_expertise` (which also stores expertise level, years of experience and research interest).

---

## 4. Decisions log

| Date | Decision | Reason |
|---|---|---|
| 13 Oct 2026 | Use XAMPP (full install) rather than a portable stack | Matches the proposal scope and the existing `C:\xampp\htdocs` web root |
| 13 Oct 2026 | Added `expertise_categories`, `departments`, `activity_log` tables | Required by mockup screens (category counts, department filter/chart, Recent Activities) but missing from the first draft ERD |
| 13 Oct 2026 | Keep one login for both roles, single role guard | Simpler for coursework; role read from `users.role` |
| 13 Oct 2026 | ERD kept in Mermaid (`docs/erd.mmd`) as the source of truth | Easier to edit and re-render than a drawing tool |

---

## 5. Demo logins

| Role | Username | Password |
|---|---|---|
| Pentadbir | `admin` | `Admin@123` |
| Pensyarah | `aisyah`, `harun`, `farnad`, `ahmad`, `nurul`, `zulkifli`, `sitiaminah`, `firdaus`, `norhayati`, `khairul`, `rahimah`, `syafiq` | `Pensyarah@123` |

Login also accepts the email address (e.g. `aisyah@upnm.edu.my`).
**Change these before handing over any demonstration.**

---

## 6. Bugs found and fixed during verification

| Bug | Cause | Fix |
|---|---|---|
| Every login crashed with `SQLSTATE[HY093]` | A named PDO placeholder (`:identity`) was used twice in one statement while emulated prepares are off | Split into `:identity_user` / `:identity_email` |
| Both admin charts rendered blank | `dashboard-admin.js` was emitted *before* `chart.umd.min.js`, so `typeof Chart` was undefined and the script exited silently | Queue both in `$extra_js`, library first |
| Manage Users role filter showed "Admin" by default | `includes/header.php` set `$role` for the logged-in user, overwriting the page's own filter variable | Header now uses `$__role` / `$__isAdmin`; the page filter is `$filterRole` / `$filterStatus` |
| All 7 record browsers died with "Call to undefined function record_type()" | `record-browser.php` validated the record type *before* requiring `auth.php`, which is what loads `functions.php` → `record-types.php` | `auth.php` is now required first; the type is validated after the guard |
| `admin/statistics.php` failed to parse | A `;` where an array element needed a `,` in the `$totals` array | Comma fixed (caught by `php -l` before it ever ran) |
| Year filter would have thrown SQL errors on 3 record types | The year facet query ran unconditionally, but `training`, `certifications` and `documents` have no `year` column | The facet is now queried and rendered only when the type declares a year column |
| Record form headers read "Add New Research project" | `ucfirst()` applied to a lowercase multi-word label | The three multi-word `singular` labels are now properly cased ("Research Project") |

### Architecture note

The seven record modules share **one** definition (`includes/record-types.php`), **one**
set of form fields (`includes/record-fields.php`) and **one** CRUD engine
(`includes/record-manager.php`). The pages are thin wrappers that declare which record
type they are:

```php
// lecturer/research.php
$manager_type = 'research';
require __DIR__ . '/../includes/record-manager.php';
```

`admin/lecturers-view.php` and `lecturer/profile.php` build their tabbed views from the
same definitions, so a column or a field is described in exactly one place across the
admin browsers, the admin profile view, the lecturer modules, the lecturer's own profile,
Statistics and Reports. Adding a record type means adding one entry to each of the two
definition files — no new page logic.

Display and editing are deliberately separate files: `record-types.php` describes table
columns, `record-fields.php` describes form inputs and their validation rules.

### Access control (the part to demo to your supervisor)

Ownership is enforced in the **SQL WHERE clause**, never in a hidden form field:

```sql
DELETE FROM research WHERE research_id = :id AND lecturer_id = :lecturer_id
```

The smoke test proves this by having a second lecturer (harun) POST aisyah's `record_id`
to the delete action: the record survives, because the id alone is never enough.
The same test confirms a Pensyarah is bounced off every admin page by URL.

Uploads are validated on **content, not filename**: a file called `fake.pdf` whose bytes
are plain text is rejected (`finfo` reports `text/plain`), while a real PDF is accepted.
Stored names are generated (`20261013_a1b2c3d4e5f6a7b8.pdf`) so a crafted filename can
never escape the upload directory, and `assets/uploads/.htaccess` refuses to serve
anything executable from there.

---

## 7. Next actions

The build is functionally complete — both roles have their full module set. What remains:

1. **User acceptance test** with target users against `docs/system-requirements.md` §8 (the six acceptance criteria), logged in a UAT table with pass/fail per criterion.
2. **Document uploads already block PHP execution**, but add a `.htaccess`-level `Content-Disposition: attachment` so PDFs open in the browser rather than downloading (cosmetic).
3. **Pagination** on the record browsers and lecturer lists if the dataset grows past a few hundred rows.
4. **A "review queue"** marker on records changed since the Pentadbir last looked — the `updated_at` column already carries what is needed.
5. Consider whitelisting `curl`-friendly PDF export alongside the existing CSV export in Reports.
6. Change all demo passwords before the final demonstration, and set `APP_DEBUG` to `false` in `config/config.php` for the submitted build.
