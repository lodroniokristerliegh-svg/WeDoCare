# WeDoCare — Backend Setup (PHP + MySQL)

This is the real, deployable server-side implementation of the full
WeDoCare system, built with the exact stack listed in your capstone
(HTML, CSS, PHP, JavaScript, MySQL). It is meant to be run on a local
server such as **XAMPP**, **WAMP**, or **Laragon**, and it uses the
same professional visual design as the `wedocare.html` prototype —
same sidebar, same cards, same tables — but every page here reads and
writes real data in MySQL.

## What's included and working
- **Authentication** — Login, Register (patient sign-up), Logout, with
  hashed passwords (`password_hash`/`password_verify`) and PHP sessions.
- **Doctor side** — Dashboard (live stats/charts), Appointments
  (search/filter/add/mark done/delete, quick-access sidebar button),
  Patients (search/filter/register/view/delete), E-Prescription
  (multi-drug Rx form, view any past prescription, clean print layout),
  Lab Referrals (checklist form, view any past referral, clean print
  layout), Online Consults (Google Meet link management per appointment),
  Payments (record/verify/reject, view uploaded proof of payment),
  Reports (real aggregated statistics), Settings (editable profile photo,
  credentials — license no., specialization, clinic address, contact —
  and password).
- **Patient side** — My Dashboard, My Appointments (view + book new,
  quick-access sidebar button, Join Meet button when online), My
  Prescriptions (read-only), Lab Referrals (read-only), My Payments
  (view + upload GCash/Maya screenshot as proof of payment), My Profile
  (edit info, change password).

### New in this update
- **Public homepage** (`home.php`) — the site now opens to a public,
  portfolio-style landing page featuring Dr. Deguito (photo, bio,
  credentials, clinic address) with "Sign In" / "Register" buttons —
  matching how real clinic/telehealth sites present themselves before
  visitors log in. Fill in your photo and bio under Settings and they
  appear here automatically.
- **GCash / Maya QR codes** — the doctor can upload their GCash and
  Maya QR code images and numbers under Settings → "GCash / Maya
  Payment Details". These now appear automatically inside the
  patient's payment modal (switching based on the selected method) so
  patients know exactly where to send their payment before uploading
  proof.
- **Patient lab result upload** — matching the capstone's Appointment
  Management objective, patients can now upload their laboratory
  result file (image/PDF) against any referral issued to them; the
  doctor sees a "View Result" link and the patient's note directly on
  the Lab Referrals page.
- Removed duplicate "New Appointment"/"Book Appointment" buttons —
  there is now exactly **one** entry point, the button pinned to the
  top of the left sidebar, for both doctor and patient accounts.
- Patient **My Payments**: patients can now self-submit a GCash/Maya
  payment (method, reference/OR number, sender name, amount, date paid,
  screenshot upload with preview and a "Remove & Choose Another" option)
  without waiting for the doctor to record a bill first.
- Doctor gets a **notification bell** (badge count + dropdown) and an
  automatic **popup alert** on every page when a patient submits a new
  payment proof, so it's impossible to miss.
- Doctor **Payments** page has a "View Proof" modal showing the full
  screenshot, reference number, and sender name, with quick Verify/Reject.
- E-Prescription and Lab Referral: click **View** on any past record to
  see the filled document, then **Print** — printing now hides the
  sidebar/topbar/buttons so only the clean document prints.
- Lab Referral test checklist redesigned as a clean plain list instead
  of boxed buttons.
- Doctor **Settings** page now includes an editable profile photo
  (shown in the sidebar and on the homepage) and clinic credentials
  (PRC license number, specialization, clinic address, contact number,
  bio) — these appear automatically in the E-Prescription and Lab
  Referral document headers, and on the public homepage.
- **If you already have an existing database**, run the four
  `migrate_*.php` scripts once in your browser to add the new columns —
  see step 7 below.

The single-file `wedocare.html` (outside this folder) is the
click-and-go **prototype** — the same UI running on simulated
in-browser data, useful for a quick demo with zero setup. This
`backend/` folder is the **real, database-backed implementation** —
this is what you deploy and actually give to Dr. Deguito.

## 1. Install requirements
- PHP 7.4+ (with the `pdo_mysql` extension enabled)
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx (XAMPP bundles all of this)

## 2. Import the database
1. Open **phpMyAdmin** (or the MySQL CLI).
2. Create/Import using `database/schema.sql`. This creates the
   `wedocare_db` database and all tables (`users`, `patients`,
   `appointments`, `prescriptions`, `prescription_items`,
   `lab_referrals`, `lab_referral_tests`, `payments`).

## 3. Configure the connection
Edit `config.php` if your MySQL username/password differs from the
XAMPP default (`root` / empty password).

## 4. Place the files
Copy this whole `backend/` folder into your server's web root, e.g.:
```
C:\xampp\htdocs\wedocare\backend\
```

## 5. Create the doctor's account (one time only)
Visit in your browser:
```
http://localhost/wedocare/backend/setup_admin.php
```
This safely hashes and stores the doctor's password using PHP's
`password_hash()` — never a plain-text password. **Delete
`setup_admin.php` after running it once.**

Default doctor login (change immediately after first sign-in, using
the Settings page):
```
Email:    dr.deguito@wedocare.com
Password: Doctor@123
```

## 6. Visit your site
```
http://localhost/wedocare/backend/
```
This now opens the **public homepage** first. From there, click
**Sign In** or **Register**.
- **Doctor** → full clinic dashboard and every module listed above.
- **Patient** → register via `register.php`, then sign in for the
  patient portal.

## 7. If you already had this system installed before this update
If you imported `schema.sql` before these features existed, run these
once in your browser to safely add the new columns without losing any
data:
```
http://localhost/wedocare/backend/migrate_doctor_profile.php
http://localhost/wedocare/backend/migrate_payment_fields.php
http://localhost/wedocare/backend/migrate_lab_results.php
http://localhost/wedocare/backend/migrate_payment_qr.php
```
Then delete all four files. After that, go to **Settings** as the
doctor to:
- Upload a profile photo and fill in your bio, license number,
  specialization, clinic address, and contact number (these appear on
  the public homepage and on E-Prescription/Lab Referral headers).
- Upload your GCash and Maya QR codes and numbers under "GCash / Maya
  Payment Details" (these appear to patients when they pay).

## How the pieces fit together
- `config.php` — shared PDO database connection (`$pdo`) + starts the
  PHP session, included by every page.
- `includes/auth_guard.php` — `require_login()` / `require_role()`
  guards; redirect unauthenticated or wrong-role visitors to `login.php`.
- `includes/layout_top.php` / `layout_bottom.php` — the shared sidebar,
  topbar, and page shell (identical design to the prototype), used by
  every protected page. Live nav badge counts are pulled from the
  database on every page load.
- `includes/helpers.php` — PHP ports of the prototype's JS formatting
  helpers (`fmt_date`, `fmt_time`, `calc_age`, `badge_class`,
  `avatar_html`, etc.) so both versions render identically.
- `css/app.css` — the exact stylesheet from the prototype, extracted
  so both versions look identical. `css/auth.css` styles the
  login/register pages specifically.
- Each `*_doctor.php` / `*_patient.php` page: guards the route, runs
  its own MySQL queries via `$pdo`, and renders using the shared layout.

## File map
```
backend/
├── index.php                    → redirects to login.php
├── login.php                    → sign in (doctor or patient)
├── register.php                 → patient self-registration
├── logout.php                   → destroys the session
├── setup_admin.php              → one-time doctor account creation
├── config.php                   → database connection + session start
├── dashboard_doctor.php         → doctor dashboard
├── appointments_doctor.php      → manage appointments
├── patients_doctor.php          → manage patient records
├── prescriptions_doctor.php     → issue e-prescriptions
├── lab_referrals_doctor.php     → issue lab referrals
├── consult_doctor.php           → Google Meet link management
├── payments_doctor.php          → verify/reject payments
├── reports_doctor.php           → statistics and analytics
├── settings_doctor.php          → doctor profile & password
├── dashboard_patient.php        → patient dashboard
├── appointments_patient.php     → view/book appointments
├── prescriptions_patient.php    → view own prescriptions
├── labs_patient.php             → view own lab referrals
├── payments_patient.php         → view payments + upload proof
├── profile_patient.php          → patient profile & password
├── includes/
│   ├── auth_guard.php           → route protection
│   ├── helpers.php              → formatting helper functions
│   ├── layout_top.php           → shared sidebar/topbar (head)
│   └── layout_bottom.php        → shared layout (foot)
├── css/
│   ├── app.css                  → main stylesheet (matches prototype)
│   └── auth.css                 → login/register page stylesheet
├── database/
│   └── schema.sql                → full MySQL schema
└── uploads/proofs/               → uploaded payment proof files
```

## Security notes
- All forms use prepared statements (`$pdo->prepare(...)->execute(...)`)
  to prevent SQL injection, and all output is escaped with `e()`
  (a `htmlspecialchars()` wrapper) to prevent XSS.
- Passwords are never stored in plain text — `password_hash()` /
  `password_verify()` (bcrypt) are used throughout.
- Sessions are regenerated on login to prevent session fixation.
- File uploads for payment proof are validated by MIME type and size.

## Extending further
To add more features (e.g. printable PDF prescriptions, SMS/email
notifications, GCash/Maya API integration), follow the same pattern
used throughout: guard the page with `auth_guard.php`, query/insert
via `$pdo`, and render with `includes/layout_top.php` /
`includes/layout_bottom.php` so new pages stay visually consistent
with the rest of the system.


## Public homepage and doctor schedule update

The public `home.php` landing page now includes a responsive weekly consultation schedule section.
The schedule is intentionally not prefilled with assumed clinic hours. After deploying:

1. Sign in with the doctor account.
2. Open **Settings** and scroll to **Weekly Consultation Schedule**.
3. Enter Dr. Deguito's actual hours for each available day, choose online/walk-in availability, and click **Save & Publish Schedule**.
4. The published hours will appear on the public homepage under **Doctor Schedule**.

The doctor schedule table is created automatically when Settings is opened if the hosting database user permits table creation. Alternatively, run `database/doctor_schedule.sql` while the correct WeDoCare database is selected in phpMyAdmin. The migration creates the table only; it does not invent or prefill clinic hours. Booking requests still require clinic confirmation.
