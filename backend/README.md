# MediCheck — Medicine Availability Checker & Reservation System

A PHP + MySQL (mysqli) application where customers search pharmacy stock and
reserve medicines, pharmacies manage inventory and reservations, and an admin
oversees the whole system.

## Quick Start
Once the app is installed and the demo data is loaded, you can test it by
searching for **Paracetamol**, or by logging in with the demo pharmacy account:

- Email: `abc@pharmacy.local`
- Password: `Pharma@123`

## Requirements
- PHP 7.4+ (8.x recommended) with `mysqli` and `fileinfo` extensions
- MySQL / MariaDB
- A server such as XAMPP, WAMP, MAMP, or PHP's built-in server

## Setup

1. **Copy the project** into your web root, e.g. `htdocs/medicine_checker/`.

2. **Create the database.** Import `database/medicine_checker.sql` via phpMyAdmin,
   or from a terminal:
   ```
   mysql -u root -p < database/medicine_checker.sql
   ```
   This creates the `medicine_checker` database and its four tables
   (`users`, `pharmacies`, `medicines`, `reservations`). No demo data is inserted.

3. **Check the DB credentials** in `database/db.php` (defaults: host `localhost`,
   user `root`, empty password). Adjust if your setup differs.

4. **Create the first administrator.** Open in your browser:
   ```
   http://localhost/medicine_checker/database/create_admin.php
   ```
   Fill in the form, then **delete `database/create_admin.php`** (it refuses to
   run once an admin exists, but delete it anyway for safety).

5. **Sign up.** Register a customer account, and/or a pharmacy account, from
   `register.php`. New pharmacies start as *pending* — log in as admin and
   approve them under **Manage Pharmacies** before their medicines appear in
   search.

6. **Uploads folder.** Medicine images are stored in `uploads/medicine/`. Ensure
   this folder is writable by the web server.

7. **(Optional) Load demo data.** To try search and reservation without entering
   data by hand, visit:
   ```
   http://localhost/medicine_checker/database/seed.php
   ```
   It adds two approved pharmacies (ABC, XYZ) with a mix of in-stock, low-stock
   and out-of-stock medicines, one pending pharmacy (HealthPlus, hidden from
   search), and a demo customer. **Delete `database/seed.php` afterwards.**

   Demo logins — pharmacies use password `Pharma@123`:
   `abc@pharmacy.local`, `xyz@pharmacy.local`, `health@pharmacy.local`;
   customer `rahul@user.local` / `User@123`. Try searching *Paracetamol*.

## Common Setup Issues

**1. MySQL won't start**
If MySQL stays red in the XAMPP Control Panel, another MySQL service is probably
already using port 3306. You'll need to stop that service (or change XAMPP's
MySQL port in `my.ini`) before MySQL can start.

**2. Database connection error**
If a page shows a database connection error, your MySQL `root` account most
likely has a password set. The default setup expects:

```text
Username: root
Password: (blank)
Database: medicine_checker
```

If your MySQL root password is different, put it in the `$DB_PASS` line of
`database/db.php`.

## Roles & flow
- **User:** search → reserve available medicine → track reservations → cancel if pending/confirmed.
- **Pharmacy:** add/edit/deactivate medicines → update stock → confirm / collect / cancel reservations.
- **Admin:** approve/suspend pharmacies → activate/deactivate medicines → view & filter all reservations.

Login redirects by role to `admin/`, `pharmacy/`, or `user/` dashboards.

## Key implementation notes
- **Passwords** are hashed with `password_hash()` and checked with `password_verify()`.
- **SQL injection** is prevented throughout with prepared statements and `bind_param()`.
- **Reservations** decrement stock inside a transaction using `SELECT ... FOR UPDATE`
  so concurrent reservations cannot oversell. Cancelling / expiring a reservation
  returns the stock.
- **Authorization** is enforced server-side via `require_login($role)` on every
  protected page — a pharmacy cannot reach `/admin/*` by typing the URL.
- **CSRF** tokens protect every state-changing POST; **XSS** is mitigated by escaping
  all output with `htmlspecialchars()` (the `e()` helper).
- **File uploads** are validated by real MIME type and size, and stored with random
  names; the `uploads/` folder blocks script execution via `.htaccess`.
- **Soft deletes:** medicines are set to `inactive` rather than physically removed,
  preserving historical reservation data.

## Project structure
```
index.php  login.php  register.php  logout.php  search.php  reserve.php
includes/   functions.php  header.php  footer.php
database/   db.php  medicine_checker.sql  create_admin.php  seed.php
admin/      dashboard.php  pharmacies.php  medicines.php  reservations.php
pharmacy/   dashboard.php  add_medicine.php  edit_medicine.php  delete_medicine.php  medicines.php  reservations.php
user/       dashboard.php  my_reservations.php
css/style.css   js/script.js   uploads/medicine/
```

> Note: uploaded medicine images live in `uploads/medicine/` (kept separate from
> the app code). If you prefer the `images/` name from the original spec, change
> the path in `includes/functions.php` (`handle_image_upload`) and in
> `search.php` / `reserve.php`.
