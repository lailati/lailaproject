#!/usr/bin/env python
"""
Smoke test for the FSTP Talent Map login and user-setup phase.

Runs against the live local stack (Apache + MariaDB) and checks the things that
are easy to get subtly wrong:

  * the front controller routes by session state
  * a wrong password is rejected and a right one is accepted for BOTH roles
  * each role lands on its own dashboard
  * a Pensyarah cannot reach an admin page by typing the URL
  * the admin user-creation form actually writes to the database
  * config/, includes/, database/ and uploads/ are not readable over HTTP

Usage (Apache and MySQL must be running):
    python tools/smoke_test.py
    python tools/smoke_test.py http://localhost/projectlaila
"""

import http.cookiejar
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost/projectlaila").rstrip("/")

PASSED, FAILED = [], []


# --------------------------------------------------------------------------- #
# tiny HTTP client
# --------------------------------------------------------------------------- #
class NoRedirect(urllib.request.HTTPRedirectHandler):
    """Surface 3xx responses instead of silently following them."""

    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def session(follow=True):
    jar = http.cookiejar.CookieJar()
    handlers = [urllib.request.HTTPCookieProcessor(jar)]
    if not follow:
        handlers.append(NoRedirect)
    return urllib.request.build_opener(*handlers)


def call(opener, path, data=None):
    """Return (status, body, location). Never raises on 4xx/5xx."""
    url = path if path.startswith("http") else BASE + "/" + path.lstrip("/")
    body = urllib.parse.urlencode(data).encode() if data else None
    req = urllib.request.Request(url, data=body, method="POST" if data else "GET")
    try:
        with opener.open(req, timeout=30) as resp:
            return resp.status, resp.read().decode("utf-8", "replace"), resp.headers.get("Location")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), e.headers.get("Location")
    except Exception as e:  # connection refused, DNS, ...
        return 0, f"<transport error: {e}>", None


def csrf(html):
    m = re.search(r'name="csrf_token"\s+value="([^"]+)"', html)
    return m.group(1) if m else None


def check(name, condition, detail=""):
    if condition:
        PASSED.append(name)
        print(f"  PASS  {name}")
    else:
        FAILED.append(f"{name} :: {detail}")
        print(f"  FAIL  {name}  {detail}")


def login(username, password):
    """Log in and return (opener, status, location, body)."""
    op = session(follow=False)
    status, html, _ = call(op, "login.php")
    token = csrf(html)
    if not token:
        return op, 0, "no csrf token on login page", html
    status, body, location = call(op, "login.php", {
        "csrf_token": token, "identity": username, "password": password,
    })
    return op, status, location, body


# --------------------------------------------------------------------------- #
print(f"Target: {BASE}\n")

print("[1] Front controller and login page")
anon = session(follow=False)
status, _, location = call(anon, "/")
check("GET / redirects an anonymous visitor to login.php", status == 302 and location and "login.php" in location,
      f"status={status} location={location}")

status, html, _ = call(anon, "login.php")
check("GET login.php returns 200", status == 200, f"status={status}")
check("login page shows the FSTP TALENT MAP branding", "FSTP TALENT MAP" in html)
check("login page shows the Welcome panel", "Welcome" in html and "Login" in html)
check("login page has a CSRF token", csrf(html) is not None)

print("\n[2] Authentication")
op, status, location, body = login("admin", "definitely-wrong")
check("wrong password is rejected", status == 200 and "tidak sah" in body, f"status={status}")
check("wrong password shows the generic error, no account disclosure",
      "tidak sah" in body and "tidak ditemui" not in body)

op_admin, status, location, _ = login("admin", "Admin@123")
check("Pentadbir login succeeds", status == 302, f"status={status}")
check("Pentadbir is sent to the admin dashboard",
      bool(location) and "admin/dashboard.php" in location, f"location={location}")

op_lect, status, location, _ = login("aisyah", "Pensyarah@123")
check("Pensyarah login succeeds", status == 302, f"status={status}")
check("Pensyarah is sent to the lecturer dashboard",
      bool(location) and "lecturer/dashboard.php" in location, f"location={location}")

op_lect2, status, _, _ = login("aisyah@upnm.edu.my", "Pensyarah@123")
check("login also accepts the email address", status == 302, f"status={status}")

# a second lecturer, used later to prove records are not shared
op_harun, status, _, _ = login("harun", "Pensyarah@123")
check("a second Pensyarah can log in", status == 302, f"status={status}")

print("\n[3] Dashboards render live data")
status, html, _ = call(op_admin, "admin/dashboard.php")
check("admin dashboard returns 200", status == 200, f"status={status}")
check("admin dashboard has the 4 KPI tiles",
      all(label in html for label in ["Lecturers", "Expertise Categories", "Research Publications", "Certifications"]))
check("admin dashboard renders both charts",
      'id="chartExpertise"' in html and 'id="chartDepartment"' in html)
check("admin dashboard queries reached the database",
      bool(re.search(r'kpi-value">\s*1[0-9]\s*<', html)), "expected a two-digit lecturer count")

status, html, _ = call(op_lect, "lecturer/dashboard.php")
check("lecturer dashboard returns 200", status == 200, f"status={status}")
check("lecturer dashboard shows Profile Completion", "Profile Completion" in html)
check("lecturer dashboard shows Recent Activities", "Recent Activities" in html)
check("lecturer dashboard names the logged-in user", "Aisyah" in html)

print("\n[4] Role separation")
status, _, location = call(op_lect, "admin/dashboard.php")
check("Pensyarah is bounced off the admin dashboard",
      status == 302 and location and "lecturer/dashboard.php" in location,
      f"status={status} location={location}")

status, _, location = call(op_lect, "admin/users.php")
check("Pensyarah is bounced off Manage Users",
      status == 302 and location and "lecturer/dashboard.php" in location,
      f"status={status} location={location}")

status, html, _ = call(op_admin, "admin/users.php")
check("Pentadbir can open Manage Users", status == 200 and "Add User Account" in html)
check("Manage Users lists existing accounts", "aisyah" in html)

status, _, location = call(anon, "admin/dashboard.php")
check("anonymous visitor is bounced to login",
      status == 302 and location and "login.php" in location,
      f"status={status} location={location}")

print("\n[5] Admin creates a user account")
status, html, _ = call(op_admin, "admin/users.php")
token = csrf(html)
new_user = {
    "csrf_token": token,
    "action": "create",
    "full_name": "Dr. Smoke Test",
    "username": "smoketest",
    "email": "smoketest@upnm.edu.my",
    "role": "lecturer",
    "department_id": "1",
    "status": "active",
    "password": "Test@12345",
    "staff_no": "",
    "position": "Lecturer",
    "phone": "+60 12-000 0000",
}
status, _, location = call(op_admin, "admin/users.php", new_user)
check("create-user POST redirects back to the list",
      status == 302 and location and "users.php" in location, f"status={status} location={location}")

status, html, _ = call(op_admin, "admin/users.php")
check("the new account appears in the list", "smoketest" in html)
check("an auto-generated staff number was assigned",
      "UPNM013" in html, "expected UPNM013 for the 13th lecturer record")

status, html, _ = call(op_admin, "admin/users.php?q=smoketest")
check("search finds the new account", "smoketest" in html and "Dr. Smoke Test" in html)

print("\n[6] Files that must never be served over HTTP")
for path in ("includes/auth.php", "includes/functions.php", "config/db.php",
             "config/config.php", "database/fstp_talent_map.sql", "assets/uploads/.htaccess"):
    status, html, _ = call(anon, path)
    check(f"{path} is blocked", status in (403, 404) and "password" not in html.lower(),
          f"status={status}")

print("\n[7] Change own password")
status, html, _ = call(op_lect, "auth/change-password.php")
check("change-password page loads for a lecturer", status == 200 and "Tukar Kata Laluan" in html)
token = csrf(html)
status, _, location = call(op_lect, "auth/change-password.php", {
    "csrf_token": token,
    "current_password": "wrong-one",
    "new_password": "Lect@12345",
    "confirm_password": "Lect@12345",
})
check("a wrong current password is refused",
      status == 200 or "tidak betul" in (location or ""), f"status={status}")

# --------------------------------------------------------------------------- #
print("\n[8] Admin modules")
admin_pages = [
    ("admin/lecturers.php",            "Lecturer Profiles"),
    ("admin/expertise-categories.php", "Add Expertise Category"),
    ("admin/talent-mapping.php",       "Talent Mapping"),
    ("admin/research.php",             "Research Records"),
    ("admin/publications.php",         "Publications Records"),
    ("admin/certifications.php",       "Certifications Records"),
    ("admin/training.php",             "Training Records"),
    ("admin/consultancy.php",          "Consultancy Records"),
    ("admin/achievements.php",         "Achievements Records"),
    ("admin/documents.php",            "Documents Records"),
    ("admin/statistics.php",           "Expertise Distribution"),
    ("admin/reports.php",              "Pilih Laporan"),
]
for path, marker in admin_pages:
    status, html, _ = call(op_admin, path)
    clean = "Fatal error" not in html and "Warning:" not in html and "Notice:" not in html
    check(f"{path} renders cleanly",
          status == 200 and marker in html and clean,
          f"status={status} marker={'y' if marker in html else 'n'} clean={clean}")

status, html, _ = call(op_admin, "admin/lecturers-view.php?id=1")
check("lecturer profile view renders all tabs",
      status == 200 and "Aisyah" in html and "Expertise" in html and "Fatal error" not in html,
      f"status={status}")

status, html, _ = call(op_admin, "admin/lecturers-view.php?id=99999")
check("unknown lecturer id is handled, not fatal",
      status in (200, 302) and "Fatal error" not in html, f"status={status}")

print("\n[9] Admin search, filters and exports")
status, html, _ = call(op_admin, "admin/talent-mapping.php?q=forensics")
check("talent mapping keyword search finds a match", status == 200 and "Farnad" in html, f"status={status}")

status, html, _ = call(op_admin, "admin/talent-mapping.php?level=Expert")
check("talent mapping level filter returns experts", status == 200 and "Expert" in html, f"status={status}")

status, html, _ = call(op_admin, "admin/talent-mapping.php?category=1")
check("talent mapping category shortcut works", status == 200 and "Cybersecurity Experts" in html, f"status={status}")

status, html, _ = call(op_admin, "admin/lecturers.php?q=aisyah")
check("lecturer search finds Aisyah", status == 200 and "Aisyah" in html, f"status={status}")

status, html, _ = call(op_admin, "admin/research.php?q=iot")
check("research search filters rows", status == 200 and "IoT" in html, f"status={status}")

status, html, _ = call(op_admin, "admin/research.php?export=csv")
check("research CSV export produced a header row",
      status == 200 and "Research Title" in html and "Aisyah" in html, f"status={status}")

for rpt, marker in [("expertise", "Bidang Kepakaran"), ("department", "Jabatan"),
                    ("certification", "Pensijilan"), ("publication", "Penerbitan")]:
    status, html, _ = call(op_admin, f"admin/reports.php?report={rpt}&generate=1")
    check(f"{rpt} report generates",
          status == 200 and marker in html and "Fatal error" not in html, f"status={status}")

status, html, _ = call(op_admin, "admin/reports.php?report=expertise&generate=1&export=csv")
check("report CSV export produced a header row",
      status == 200 and "Staff No" in html, f"status={status}")

print("\n[10] New admin pages are protected from Pensyarah")
for path in ("admin/talent-mapping.php", "admin/lecturers.php",
             "admin/expertise-categories.php", "admin/statistics.php", "admin/reports.php"):
    status, _, location = call(op_lect, path)
    check(f"Pensyarah bounced off {path}",
          status == 302 and location and "lecturer/dashboard.php" in location,
          f"status={status} location={location}")

# --------------------------------------------------------------------------- #
# Lecturer section
# --------------------------------------------------------------------------- #
import uuid


def post_raw(opener, path, body, content_type):
    """POST a hand-built body (used for multipart file uploads)."""
    url = BASE + "/" + path.lstrip("/")
    req = urllib.request.Request(url, data=body, method="POST")
    req.add_header("Content-Type", content_type)
    try:
        with opener.open(req, timeout=30) as resp:
            return resp.status, resp.read().decode("utf-8", "replace"), resp.headers.get("Location")
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode("utf-8", "replace"), e.headers.get("Location")


def multipart(fields, files=None):
    """Build a multipart/form-data body. files: {name: (filename, bytes, mime)}."""
    boundary = "----FSTP" + uuid.uuid4().hex
    out = b""
    for key, value in fields.items():
        out += (f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n'
                f'{value}\r\n').encode()
    for key, (filename, content, mime) in (files or {}).items():
        out += (f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"; '
                f'filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n').encode()
        out += content + b"\r\n"
    out += f"--{boundary}--\r\n".encode()
    return out, f"multipart/form-data; boundary={boundary}"


MINIMAL_PDF = (b"%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
               b"2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
               b"3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
               b"trailer<</Root 1 0 R>>\n%%EOF\n")

print("\n[11] Lecturer modules render")
lecturer_pages = [
    ("lecturer/profile.php",        "Edit Profile"),
    ("lecturer/expertise.php",      "My Expertise"),
    ("lecturer/research.php",       "Research Saya"),
    ("lecturer/publications.php",   "Publications Saya"),
    ("lecturer/certifications.php", "Certifications Saya"),
    ("lecturer/training.php",       "Training Saya"),
    ("lecturer/consultancy.php",    "Consultancy Saya"),
    ("lecturer/achievements.php",   "Achievements Saya"),
    ("lecturer/documents.php",      "Documents Saya"),
]
for path, marker in lecturer_pages:
    status, html, _ = call(op_lect, path)
    clean = "Fatal error" not in html and "Warning:" not in html
    check(f"{path} renders cleanly",
          status == 200 and marker in html and clean,
          f"status={status} marker={'y' if marker in html else 'n'} clean={clean}")

print("\n[12] Lecturer creates, edits and deletes a research record")
status, html, _ = call(op_lect, "lecturer/research.php")
token = csrf(html)
body = {
    "csrf_token": token, "action": "create",
    "title": "SMOKE TEST Research Title",
    "research_area": "Cybersecurity",
    "research_category": "Applied Research",
    "year": "2026", "status": "Ongoing",
    "description": "Created by the automated smoke test.",
}
status, _, location = call(op_lect, "lecturer/research.php", body)
check("create research redirects back to the list",
      status == 302 and location and "research.php" in location, f"status={status}")

status, html, _ = call(op_lect, "lecturer/research.php")
check("the new research record appears", "SMOKE TEST Research Title" in html)
new_id = re.search(r"lecturer/research\.php\?edit=(\d+)", html)
# the newest record is first in the list, so the first edit link is ours
check("the new record has an edit link", new_id is not None)

if new_id:
    rid = new_id.group(1)
    status, html, _ = call(op_lect, f"lecturer/research.php?edit={rid}")
    check("edit form loads with the record values",
          status == 200 and "SMOKE TEST Research Title" in html, f"status={status}")

    token = csrf(html)
    status, _, location = call(op_lect, "lecturer/research.php", {
        "csrf_token": token, "action": "update", "record_id": rid,
        "title": "SMOKE TEST Research Updated",
        "research_area": "Digital Forensics", "research_category": "Basic Research",
        "year": "2025", "status": "Completed", "description": "Updated by the smoke test.",
    })
    check("update redirects back to the list",
          status == 302 and location and "research.php" in location, f"status={status}")

    status, html, _ = call(op_lect, "lecturer/research.php")
    check("the edit was saved", "SMOKE TEST Research Updated" in html
          and "SMOKE TEST Research Title" not in html)

    print("\n[13] A lecturer cannot touch another lecturer's record")
    status, _, location = call(op_harun, f"lecturer/research.php?edit={rid}")
    check("another lecturer cannot open the edit form",
          status == 302 and not (location and f"edit={rid}" in location),
          f"status={status} location={location}")

    status, html, _ = call(op_harun, "lecturer/research.php")
    check("the record is not visible in the other lecturer's list",
          "SMOKE TEST Research Updated" not in html)

    # Harun tries to delete Aisyah's row by posting her record id directly.
    token = csrf(html)
    call(op_harun, "lecturer/research.php", {
        "csrf_token": token, "action": "delete", "record_id": rid,
    })
    status, html, _ = call(op_lect, "lecturer/research.php")
    check("the forged delete did NOT remove the record",
          "SMOKE TEST Research Updated" in html,
          "a lecturer deleted another lecturer's record")

    # now the owner deletes it, which must work
    token = csrf(html)
    status, _, _ = call(op_lect, "lecturer/research.php", {
        "csrf_token": token, "action": "delete", "record_id": rid,
    })
    status, html, _ = call(op_lect, "lecturer/research.php")
    check("the owner can delete their own record",
          "SMOKE TEST Research Updated" not in html, "owner delete failed")

print("\n[14] Validation and upload safety")
status, html, _ = call(op_lect, "lecturer/research.php")
record_count_before = len(re.findall(r"lecturer/research\.php\?edit=", html))
token = csrf(html)

status, html, _ = call(op_lect, "lecturer/research.php", {
    "csrf_token": token, "action": "create", "title": "", "research_area": "",
    "status": "Ongoing",
})
check("a missing required field is refused with a message",
      status == 200 and "diperlukan" in html, f"status={status}")

status, html, _ = call(op_lect, "lecturer/research.php")
record_count_after = len(re.findall(r"lecturer/research\.php\?edit=", html))
check("the invalid submission wrote no record",
      record_count_after == record_count_before,
      f"record count went {record_count_before} -> {record_count_after}")

# a file named .pdf whose CONTENT is plain text must be rejected (MIME check)
status, html, _ = call(op_lect, "lecturer/documents.php")
token = csrf(html)
fields = {"csrf_token": token, "action": "create", "document_type": "CV",
          "title": "SMOKE TEST fake pdf"}
body, ctype = multipart(fields, {"file": ("fake.pdf", b"this is plainly not a pdf", "application/pdf")})
status, html, _ = post_raw(op_lect, "lecturer/documents.php", body, ctype)
check("a .pdf whose content is not a PDF is rejected",
      status == 200 and "tidak sepadan" in html, f"status={status}")

# a real PDF must be accepted
status, html, _ = call(op_lect, "lecturer/documents.php")
token = csrf(html)
fields = {"csrf_token": token, "action": "create", "document_type": "CV",
          "title": "SMOKE TEST real pdf"}
body, ctype = multipart(fields, {"file": ("cv.pdf", MINIMAL_PDF, "application/pdf")})
status, _, location = post_raw(op_lect, "lecturer/documents.php", body, ctype)
check("a real PDF is accepted",
      status == 302 and location and "documents.php" in location, f"status={status}")

status, html, _ = call(op_lect, "lecturer/documents.php")
check("the uploaded document is listed", "SMOKE TEST real pdf" in html)

# tidy up the uploaded document
doc_id = re.search(r"lecturer/documents\.php\?edit=(\d+)", html)
if doc_id:
    token = csrf(html)
    call(op_lect, "lecturer/documents.php", {
        "csrf_token": token, "action": "delete", "record_id": doc_id.group(1),
    })
    status, html, _ = call(op_lect, "lecturer/documents.php")
    print(f"  cleanup: uploaded document removed = {'SMOKE TEST real pdf' not in html}")

# --------------------------------------------------------------------------- #
status, html, _ = call(op_admin, "admin/users.php?q=smoketest")
row = re.search(r"<tr>.*?smoketest.*?</tr>", html, re.S)
uid = re.search(r'name="user_id" value="(\d+)"', row.group(0)) if row else None
if uid:
    call(op_admin, "admin/users.php", {
        "csrf_token": csrf(html) or "",
        "action": "delete",
        "user_id": uid.group(1),
    })
    status, html, _ = call(op_admin, "admin/users.php?q=smoketest")
    # the search box echoes the query string, so look for the display name row
    print(f"  cleanup: smoketest account removed = {'Dr. Smoke Test' not in html}")

print("\n" + "=" * 68)
print(f"PASSED {len(PASSED)}   FAILED {len(FAILED)}")
if FAILED:
    print("\nFailures:")
    for f in FAILED:
        print("  - " + f)
sys.exit(1 if FAILED else 0)
