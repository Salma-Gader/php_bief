# Sunset Brunch: table booking

One-page booking site for a Sunday brunch in Tanger, with a PHP back-office.
Plain PHP 8 + MySQL, vanilla HTML/CSS/JS, no framework.

> Work in progress: Phase 1 (scaffold and database) is done.

## Requirements

- PHP 8.1+ (developed on **PHP 8.4**) with the `pdo_mysql` extension
- MySQL 8.0.16+ or MariaDB 10.4+ (XAMPP's bundled MariaDB works)

## Run locally

```bash
# 1. Create the database and tables
mysql -u root -p < database/schema.sql

# 2. Create a dedicated low-privilege database user (do not run the app as root)
mysql -u root -p -e "CREATE USER 'sunset_app'@'localhost' IDENTIFIED BY 'a-strong-password';
  CREATE USER 'sunset_app'@'127.0.0.1' IDENTIFIED BY 'a-strong-password';
  GRANT SELECT, INSERT, UPDATE, DELETE ON sunset_brunch.* TO 'sunset_app'@'localhost';
  GRANT SELECT, INSERT, UPDATE, DELETE ON sunset_brunch.* TO 'sunset_app'@'127.0.0.1';"

# 3. Configure the app
cp config/config.example.php config/config.php
#    then edit config/config.php: database password, and a random ip_salt:
php -r "echo bin2hex(random_bytes(32));"

# 4. Check the setup
php bin/check-db.php

# 5. Create the back-office admin (the password is typed at a prompt, only its hash is stored)
php bin/create-admin.php admin

# 6. Start the site
php -S 127.0.0.1:8000 -t public
```

Open http://127.0.0.1:8000.

**XAMPP/Apache instead of `php -S`:** point a virtual host's `DocumentRoot` at the `public/` folder.
If the whole project is copied into `htdocs`, the root `.htaccess` still blocks the private folders.

## Project structure

```
public/            Web root: the only folder the browser can reach
src/bootstrap.php  Loaded first by every entry point: config, timezone, errors, autoload, security headers
src/Database.php   One shared PDO connection per request (prepared statements, strict SQL mode)
src/helpers.php    config() and e() (HTML escaping)
config/            booking.php = business rules (committed); config.php = secrets (git-ignored)
database/          schema.sql
bin/               CLI tools: check-db.php, create-admin.php
storage/logs/      PHP error log (git-ignored)
```

## Assumptions

- **Capacity:** active guests (status `new` + `confirmed`) + the new booking must never exceed 40 per date and slot.
- **Booking window:** bookings are accepted at most 90 days ahead (`booking_window_days` in `config/booking.php`).
- **Same-day bookings:** when today is Sunday, today can be booked for slots that have not started yet.
- **Timezone:** all dates and times use `Africa/Casablanca`, in both PHP and MySQL.
- **Content:** the restaurant is fictional; the six dishes and prices in `config/menu.php` are sample content.

## Credits

- Hero illustration and zellige tile: original SVGs made for this project.
- Font: [Josefin Sans](https://fonts.google.com/specimen/Josefin+Sans) (SIL Open Font License), self-hosted from `@fontsource-variable/josefin-sans@5.3.0`.
