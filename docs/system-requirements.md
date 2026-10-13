# System Requirements Specification (SRS)

**Project:** Web-Based Talent Mapping System for FSTP Lecturers
**Institution:** Universiti Pertahanan Nasional Malaysia (UPNM) — Fakulti Sains dan Teknologi Pertahanan (FSTP)
**Course:** TSK 3306 Projek (PSM) · Semester I, Sesi Akademik 2026/2027
**Student:** Lailati binti Mohd (2241073)
**Supervisor:** Prof. Madya Dr. Nor Azliana Akmal binti Jamaludin
**Database:** `fstp_talent_map` · **Version:** 1.0 · **Date:** 13 October 2026

---

## 1. Introduction

### 1.1 Purpose
This document specifies the functional and non-functional requirements for the Web-Based Talent Mapping System for FSTP Lecturers. It is the reference for design, implementation and user acceptance testing.

### 1.2 Scope
A centralised web application that records, updates and maps FSTP lecturer expertise so faculty management can identify subject-matter experts quickly for project supervision, research collaboration, evaluation panels, training and external engagement. Replaces the current practice of scattered CVs, department files and individual records.

### 1.3 Definitions

| Term | Meaning |
|---|---|
| Pentadbir (Admin) | System administrator: manages users, expertise catalogue, categories and reports |
| Pensyarah (Lecturer) | Faculty member who maintains their own professional profile |
| Talent Mapping | Matching/seeking lecturers by expertise, department or expertise level |
| Expertise Level | Beginner / Intermediate / Advanced / Expert |
| Profile Completion | Percentage of mandatory profile fields the lecturer has filled (target ≥ 85%) |

### 1.4 Requirement priority
**M** = Mandatory (must be present in the final system) · **S** = Should have · **C** = Could have (nice to have)

---

## 2. Users and Access Rights

| Module / Action | Pentadbir (Admin) | Pensyarah (Lecturer) |
|---|:--:|:--:|
| Login / Logout | ✔ | ✔ |
| View own dashboard | Admin Dashboard | Lecturer Dashboard |
| Manage user accounts (create/edit/deactivate/reset password) | ✔ | ✘ |
| Manage master list of lecturers | ✔ | ✘ (own record via profile) |
| Manage expertise categories *(add/edit/delete)* | ✔ | ✘ |
| Manage expertise catalogue items | ✔ | ✘ |
| Update own profile | ✔ | ✔ |
| Add / edit own expertise | ✘ (view only) | ✔ |
| Add / edit research, publications, certifications, training, consultancy, achievements | view/search only | ✔ |
| Upload documents | ✔ | ✔ |
| Search & filter lecturers (talent mapping) | ✔ | ✔ |
| View statistics | ✔ | ✘ |
| Generate & export reports | ✔ | ✘ |

**Single login for both roles**; role is read from `users.role` and enforced by one shared session/role guard (`includes/auth.php`). Admin-only pages are unreachable to a lecturer by direct URL.

---

## 3. Functional Requirements

### 3.1 Authentication & Account Management (AUTH)
| ID | Requirement | Priority |
|---|---|---|
| FR-01 | The system shall authenticate a user by username **or** email plus password. | M |
| FR-02 | Passwords shall be stored using `password_hash()` (bcrypt) and verified with `password_verify()`; no plain-text passwords. | M |
| FR-03 | Login failures shall show a generic error and shall not reveal whether the account exists. | M |
| FR-04 | The system shall support "Remember me" and a "Forgot Password?" entry point. | S |
| FR-05 | Every page shall require an authenticated session; unauthenticated access shall redirect to the login page. | M |
| FR-06 | Logout shall destroy the session completely. | M |
| FR-07 | Admin shall create user accounts with Username, Full Name, Email, Role, Department, Status and Initial Password. | M |
| FR-08 | Admin shall activate/deactivate accounts (Status = Active/Inactive); inactive accounts cannot log in. | M |
| FR-09 | Admin shall reset a user's password; the initial password is displayed once and must be changed on first login. | S |
| FR-10 | The system shall record `last_login` per user. | C |

### 3.2 Lecturer Profile (PROF)
| ID | Requirement | Priority |
|---|---|---|
| FR-11 | A lecturer shall view and edit: Staff ID, Full Name, Email, Department, Position, Phone Number, Location and Profile Photo. | M |
| FR-12 | A lecturer shall upload a profile photo (JPG/PNG, max 2 MB) with a "Change Photo" action. | M |
| FR-13 | The dashboard shall display a Profile Completion percentage, calculated from mandatory profile fields. | S |
| FR-14 | The profile page shall show tabs for Expertise, Publications, Certifications, Training, Consultancy, Achievements and Documents with a live count on each. | S |
| FR-15 | Edits to a profile shall be recorded in the activity log. | S |

### 3.3 Expertise Management (EXP)
| ID | Requirement | Priority |
|---|---|---|
| FR-16 | A lecturer shall add an expertise entry with Expertise Category, Expertise Area, Expertise Level, Years of Experience, optional Research Interest and an optional supporting document. | M |
| FR-17 | The system shall list a lecturer's expertise in a table with expertise name, category, level, years and Edit/Delete actions. | M |
| FR-18 | Expertise Level shall be one of Beginner, Intermediate, Advanced, Expert. | M |
| FR-19 | One lecturer may hold many expertise areas, and one expertise area may belong to many lecturers (many-to-many). | M |
| FR-20 | Admin shall add, edit and delete expertise *categories* and catalogue items; a category in use shall not be deletable without confirmation. | M |
| FR-21 | Each category shall display the number of lecturers tagged to it. | S |
| FR-22 | A lecturer shall not be able to create duplicate expertise entries. | S |

### 3.4 Professional Records (REC)
| ID | Requirement | Priority |
|---|---|---|
| FR-23 | A lecturer shall add/edit/delete **Research**: title, research area, research category, year, status (Ongoing/Completed), description and supporting document. | M |
| FR-24 | A lecturer shall add/edit/delete **Publications**: title, publication type, journal/publisher, year, status (Published/In Press/Submitted), DOI/link and uploaded file. | M |
| FR-25 | A lecturer shall add/edit/delete **Certifications**: certification name, issuing body, issue date, expiry date, optional certificate number and supporting document. | M |
| FR-26 | A lecturer shall add/edit/delete **Training**: training/course name, organizer, date, status, duration in days, optional description and supporting document. | M |
| FR-27 | A lecturer shall add/edit/delete **Consultancy**: project/consultancy name, organization/client, role, year, status, optional description and supporting document. | M |
| FR-28 | A lecturer shall add/edit/delete **Achievements**: achievement name, category, year, optional description and supporting document. | M |
| FR-29 | All record forms shall provide Cancel and Save actions, with required fields validated before saving. | M |
| FR-30 | A lecturer shall see only their own records for editing, and shall only ever see their own dashboard totals. | M |
| FR-31 | The system shall flag certifications expiring within 90 days. | C |

### 3.5 Document Management (DOC)
| ID | Requirement | Priority |
|---|---|---|
| FR-32 | A lecturer shall upload documents with Document Type, Title/Description and File. | M |
| FR-33 | Allowed types shall be PDF, JPG, PNG; maximum 10 MB per file (5 MB for form attachments). | M |
| FR-34 | Uploaded files shall be stored outside the web-executable path where possible and never served by a raw path an unauthenticated user can guess. | M |
| FR-35 | The document list shall show No., Document Name, Type, Upload Date and Available Actions (download, delete). | M |
| FR-36 | Admin shall be able to upload documents on behalf of a lecturer. | S |
| FR-37 | Replacing or deleting a document shall remove the stored file, not just the database row. | S |

### 3.6 Talent Mapping & Search (MAP)
| ID | Requirement | Priority |
|---|---|---|
| FR-38 | Any logged-in user shall search lecturers by name or expertise keyword. | M |
| FR-39 | The search shall be filterable by Department and by Expertise Level, with a Search action and a "Sort by: Latest" ordering. | M |
| FR-40 | Results shall display, per lecturer: photo, name, expertise level badge, department, years of experience, main expertise area and a "View Profile" link. | M |
| FR-41 | The page shall list expertise categories with the count of lecturers in each. | S |
| FR-42 | Search shall be case-insensitive and match partial keywords. | S |
| FR-43 | An authorised user shall be able to export search results (CSV/PDF). | C |

### 3.7 Dashboards (DASH)
| ID | Requirement | Priority |
|---|---|---|
| FR-44 | The **Admin dashboard** shall show total counts of Lecturers, Expertise Categories, Research Publications and Certifications, each with a "View Details" link. | M |
| FR-45 | The Admin dashboard shall show an Expertise Distribution chart (bar) filterable by department. | S |
| FR-46 | The Admin dashboard shall show a Lecturers by Department chart (donut) with percentages. | S |
| FR-47 | The **Lecturer dashboard** shall show the lecturer's own counts of Expertise, Research, Publications and Certifications. | M |
| FR-48 | The Lecturer dashboard shall show Profile Completion and a "Recent Activities" list. | S |
| FR-49 | The Lecturer dashboard shall show the lecturer's top 5 expertise areas with levels. | S |

### 3.8 Statistics & Reports (RPT)
| ID | Requirement | Priority |
|---|---|---|
| FR-50 | Admin shall view statistics mapping expertise across the faculty and departments. | M |
| FR-51 | Admin shall generate reports of lecturers by expertise, category or department. | M |
| FR-52 | Reports shall be printable and exportable to PDF and/or Excel/CSV. | S |
| FR-53 | Admin shall be able to review information last updated by lecturers (review queue). | S |

### 3.9 Activity Log (LOG)
| ID | Requirement | Priority |
|---|---|---|
| FR-54 | The system shall log create/update/delete/upload/login events with user, entity, description and timestamp. | S |
| FR-55 | The activity log shall feed the "Recent Activities" panel on the lecturer dashboard. | S |

---

## 4. Non-Functional Requirements

| ID | Category | Requirement |
|---|---|---|
| NFR-01 | Usability | Bootstrap 5 responsive layout; a first-time lecturer shall complete a profile entry without training; labels in Bahasa Melayu with English technical terms as per mockups. |
| NFR-02 | Performance | Any page shall load in ≤ 3 s on localhost with 100 lecturers × 20 records each; the mapping search shall return in ≤ 2 s. |
| NFR-03 | Security | Bcrypt password hashing; prepared statements (PDO/mysqli) on **every** query; server-side validation on every form; `htmlspecialchars()` on all output; session ID regenerated on login. |
| NFR-04 | Upload safety | Extension **and** MIME type checked; randomised stored filenames; PHP execution disabled in the upload directory. |
| NFR-05 | Compatibility | Chrome, Edge and Firefox (latest 2 versions); minimum viewport 1366 × 768. |
| NFR-06 | Maintainability | One `config/db.php` for credentials; one shared header/footer; one role guard; a versioned `database/fstp_talent_map.sql` kept in the repository. |
| NFR-07 | Data integrity | Foreign keys with `ON DELETE CASCADE` for child records; `ON DELETE RESTRICT` for lookup tables still in use; unique indexes prevent duplicate accounts and duplicate expertise. |
| NFR-08 | Backup | Database exportable via phpMyAdmin; uploads folder copyable independently of the database. |
| NFR-09 | Language | UI copy in Bahasa Melayu (Pentadbir, Pensyarah, Kepakaran) per proposal and mockups. |
| NFR-10 | Accessibility | Form fields paired with visible labels; colour contrast sufficient for the level badges. |

---

## 5. Environment Requirements

### 5.1 Development / local deployment (as installed and verified)

| Component | Requirement | Verified on this machine |
|---|---|---|
| Operating system | Windows 10/11 64-bit | Windows 11 ✔ |
| Web server | Apache 2.4.58 (XAMPP) | ✔ port 80 |
| PHP | PHP 8.2.12 with `mysqli`, `pdo_mysql`, `mbstring`, `gd`, `zip`, `fileinfo`, `openssl`, `curl` | ✔ PHP 8.2.12 |
| Database | MariaDB 10.4.32 (MySQL-compatible) | ✔ port 3306 |
| DB admin tool | phpMyAdmin 5.2.1 | ✔ |
| Front end | HTML5, CSS3, Bootstrap 5, JavaScript | — |
| Editor | Visual Studio Code | ✔ |
| RAM / disk | 8 GB RAM / 20 GB free disk | — |
| URL | `http://localhost/<project>/` | ✔ |

**XAMPP:** 8.2.12 · installer md5 `c09aeaef58e6a7614563303636ec3810`

### 5.2 Client requirements
Modern browser (Chrome / Edge / Firefox), screen ≥ 1366 × 768, network access to the server; no plugin or installation required.

### 5.3 Credentials (local development)
MariaDB user `root` with an empty password on localhost (XAMPP default). **Must be changed before any shared or production deployment.**

---

## 6. Traceability to PSM Objectives

| Proposal objective | Requirements that satisfy it |
|---|---|
| 1. Design a centralised database storing lecturer expertise systematically | FR-16…FR-28, NFR-07, §7 data dictionary (`fstp_talent_map`, 14 tables) |
| 2. Develop a web-based system letting lecturers manage profile, expertise, research, publications and achievements online | FR-11…FR-37, FR-47…FR-49 |
| 3. Validate usability and functionality together with target users | NFR-01, NFR-02, §2 access-rights matrix, UAT against §3 |

---

## 7. Data Dictionary (summary)

14 tables in `fstp_talent_map` — full ERD in `docs/erd.mmd` / `erd.svg`.

| Table | Purpose | Key relationships |
|---|---|---|
| `users` | Login accounts and role | 1:1 `lecturers`; FK `department_id` |
| `lecturers` | Lecturer profile | FK `user_id`, `department_id` |
| `departments` | Department lookup (filter + charts) | 1:M `lecturers`, `users` |
| `expertise_categories` | Expertise category master | 1:M `expertise` |
| `expertise` | Expertise catalogue items | FK `category_id` |
| `lecturer_expertise` | **M:N** lecturer ↔ expertise, with level + years | FK `lecturer_id`, `expertise_id` |
| `research` | Research projects | FK `lecturer_id` |
| `publications` | Publications | FK `lecturer_id` |
| `certifications` | Professional certifications | FK `lecturer_id` |
| `training` | Training / courses attended | FK `lecturer_id` |
| `consultancy` | Consultancy projects | FK `lecturer_id` |
| `achievements` | Awards and achievements | FK `lecturer_id` |
| `documents` | Uploaded supporting documents | FK `lecturer_id`, `uploaded_by` |
| `activity_log` | Audit trail / Recent Activities | FK `user_id` |

**Deviations from the first draft ERD (deliberate, to match the approved mockups):**
1. Added `expertise_categories` — the mockups have a whole *Manage Expertise Categories* module with per-category counts, so category cannot be a free-text column.
2. Added `departments` — "All Departments" is a filter, a chart dimension and a form dropdown.
3. Added `activity_log` — required by the Lecturer Dashboard "Recent Activities" panel.
4. Kept `lecturer_expertise` as the M:N junction holding level, years and research interest, exactly as the original ERD intended.
5. `documents.uploaded_by` added so Admin uploads on behalf of a lecturer are attributable.

---

## 8. Acceptance Criteria

The system is accepted when, on host `localhost`:
1. An Admin can create a lecturer account and log in as that lecturer.
2. The lecturer can complete a profile, add at least one expertise, one research, one publication and one certification, and upload one document.
3. The Admin dashboard totals and both charts reflect those records.
4. Talent Mapping returns the lecturer for a keyword search and for a Department/Level filter.
5. A lecturer cannot reach any Admin-only page or another lecturer's records by URL manipulation or parameter tampering.
6. All uploaded files remain private to authenticated users.
