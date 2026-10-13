# FSTP Talent Map

**Web-Based Talent Mapping System for FSTP Lecturers**
Universiti Pertahanan Nasional Malaysia — Fakulti Sains dan Teknologi Pertahanan
PSM TSK 3306 · PSM 2241073 · Lailati binti Mohd
Supervisor: Prof. Madya Dr. Nor Azliana Akmal binti Jamaludin

---

## Running the system

1. Open **`C:\xampp\xampp-control.exe`** and click **Start** next to **Apache** and **MySQL**.
2. Open your browser at:

   ### → **http://localhost/projectlaila/**  (Apache port **80**, MySQL port **3306**)

3. Log in with one of the demo accounts below.

| Role | Username | Password |
|---|---|---|
| Pentadbir (Admin) | `admin` | `Admin@123` |
| Pensyarah (Lecturer) | `aisyah` | `Pensyarah@123` |
| Pensyarah (Lecturer) | `harun`, `farnad`, `ahmad`, `nurul`, `zulkifli`, `sitiaminah`, `firdaus`, `norhayati`, `khairul`, `rahimah`, `syafiq` | `Pensyarah@123` |

> You can also log in with the full email address instead of the username (e.g. `aisyah@upnm.edu.my`).

**Change these passwords before any demonstration you hand over.**

---

## Project layout

```
C:\VR Box\UPNM\projectlaila\
├── app\                          <- the application (linked into htdocs)
│   ├── index.php                 front controller: routes by role
│   ├── login.php  logout.php     single login for both roles
│   ├── config\
│   │   ├── config.php            ALL settings: DB credentials, paths, upload limits
│   │   └── db.php                shared PDO connection (prepared statements only)
│   ├── includes\
│   │   ├── auth.php              session, login/logout, role guards  <-- role checks live ONLY here
│   │   ├── functions.php         escaping, flash, CSRF, activity log, helpers
│   │   ├── header.php            top bar + role-aware sidebar
│   │   └── footer.php
│   ├── admin\                    dashboard.php, users.php
│   ├── lecturer\                 dashboard.php
│   ├── auth\                     change-password.php, forgot-password.php
│   ├── assets\
│   │   ├── css\app.css           the visual identity (Bootstrap 5 does layout)
│   │   ├── js\                   app.js, dashboard-admin.js
│   │   ├── vendor\               Bootstrap 5.3.3, Bootstrap Icons, Chart.js 4.4.3 (local, works offline)
│   │   ├── img\favicon.svg
│   │   └── uploads\              photo\ and documents\ — .htaccess blocks execution
│   └── database\
│       └── fstp_talent_map.sql   full schema (14 tables) + sample data
├── docs\                         ERD (mermaid/svg/png), SRS, screenshots
├── tools\                        fetch_vendor.ps1, smoke_test.py
└── progress.md                   build log

C:\xampp\htdocs\projectlaila\      <- directory junction pointing at app\
```

The code lives in this project folder; `C:\xampp\htdocs\projectlaila` is a **junction**
so Apache can serve it at `http://localhost/projectlaila/` without copying files.
If you move the project, recreate it:

```powershell
New-Item -ItemType Junction -Path 'C:\xampp\htdocs\projectlaila' -Target 'C:\VR Box\UPNM\projectlaila\app'
```

---

## Database

* Database name: **`fstp_talent_map`** · 14 tables · InnoDB · utf8mb4
* Import / reset: phpMyAdmin → Import → `app\database\fstp_talent_map.sql` → Go
  (the file starts with `DROP TABLE`, so **importing it resets everything to the sample data**)

---

## Rebuilding / verifying

| Task | Command |
|---|---|
| Re-download the front-end libraries | `powershell -NoProfile -File tools\fetch_vendor.ps1` |
| Re-render the ERD page from `docs\erd.mmd` | `python docs\build_erd_html.py` |
| Run the full smoke test (needs Apache + MySQL) | `python tools\smoke_test.py` |

The smoke test checks 37 things: redirects, both roles logging in, wrong-password
rejection, role separation by URL, blocked config/includes/database/upload paths,
and the admin create-user flow.

---

## Security notes (implemented)

* Passwords stored with `password_hash()` (bcrypt); the login form is the only place they are read.
* **Every** query uses prepared statements — no string concatenation of user input.
* All output escaped with `htmlspecialchars()` via the `e()` helper.
* CSRF token on every form, verified on every POST.
* Session ID regenerated on login; cookie is HttpOnly + SameSite=Lax.
* `config/`, `includes/`, `database/` and `assets/uploads/` are blocked over HTTP (`.htaccess`).
* A password issued by the Pentadbir is one-off: `must_change_pw` forces the user to set their own on first login.
* A lecturer cannot reach an admin page by typing its URL — the guard redirects them to their own dashboard.

---

## Built so far

- [x] Database: 14 tables + sample data (12 lecturers, 12 expertise categories, research / publications / certifications / training / consultancy / achievements / documents)
- [x] Login, logout, forgot password, forced password change
- [x] Pentadbir dashboard — live KPIs, expertise distribution bar chart, department doughnut, activity feed
- [x] Pensyarah dashboard — own record counts, profile completion, recent activity, top expertise
- [x] Manage Users — create / activate / deactivate / reset password / delete, search and filter
- [ ] My Profile, My Expertise, Research, Publications, Certifications, Training, Consultancy, Achievements, Documents
- [ ] Talent Mapping search, Statistics, Reports

Sidebar items marked **soon** are the modules still to be built.
