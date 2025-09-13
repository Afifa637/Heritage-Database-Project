# Heritage Project — PHP frontend & API

## Quick setup (XAMPP)
1. Copy `heritage_project/` into XAMPP `htdocs/heritage_project/`.
2. Start Apache and MySQL.
3. Open phpMyAdmin and import `sql/db_schema.sql`.
4. Edit `includes/db_connect.php` (or `api/db.php`) if MySQL credentials differ.
5. Run `admin/set_admin_password.php` in your browser once to set the admin password (change password inside the file before running). Delete that file after use.
6. Open `http://localhost/heritage_project/index.php` and `http://localhost/heritage_project/admin/login.php`.

## What to show in the demo
1. Open `index.php` — search & filter sites.
2. Click a site -> `site.php` -> add a review and book a visit (shows transaction & payments created).
3. Login to admin -> `dashboard.php` -> view charts, top sites, export CSV, manage sites.
4. Run example queries from `sql/sql_examples.sql` inside phpMyAdmin to demonstrate joins, views, subqueries, set ops.

## Optional improvements
- Add image upload & storage for site photos (secure validation).
- Add CSRF tokens and improved auth for admin.
- Convert admin to Laravel with migrations & blade templates (I can do this for extra credit).

