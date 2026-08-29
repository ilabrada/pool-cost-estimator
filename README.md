# 🏊 Pool Cost Estimator

A mobile-first web application for estimating swimming pool construction costs. Built with PHP + MySQL for easy deployment on budget hosting (Hostinger Premium, etc.).

## Features

- **Cost Estimation** — Auto-calculate pool costs based on dimensions, materials, features
- **Client Management** — Save clients and associate estimates to them
- **PDF/Print** — Generate professional PDF estimates or print directly
- **Mobile Optimized** — Responsive design works great on tablets and phones (PWA-ready)
- **PIN Authentication** — Simple single-user PIN access
- **Configurable Pricing** — Customize all unit prices from Settings
- **Real-time Calculations** — Costs update live as you fill in the form
- **Estimate Management** — Create, edit, duplicate, delete, track status
- **Audit Log** — Tracks who saved/modified estimates and clients, with user role, IP address, timestamp, and browser info

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3 with PDO |
| Database | MariaDB 11.8.x (matching Hostinger) |
| Frontend | HTML5, CSS3 (responsive), Vanilla JavaScript |
| PDF | html2pdf.js (client-side, no server library needed) |
| Icons | Google Material Icons (CDN) |
| Fonts | Inter (Google Fonts CDN) |

## Requirements

- PHP 8.3
- MariaDB 11.8.x
- Web server support for PHP (Herd Basic provides the local runtime)
- Apache with mod_rewrite is standard on Hostinger

## Installation

### 1. Create Database

In your hosting panel (e.g., Hostinger hPanel):
- Go to **Databases → MySQL Databases**
- Create a new database (e.g., `pool_estimator`)
- Note the database name, username, and password

### 2. Configure Database Credentials

**Do not edit `includes/config.php` directly.** It contains `{{TOKEN}}` placeholders that are replaced automatically by the CI/CD pipeline on deployment (see [CI/CD & Token Replacement](#cicd--token-replacement)).

For local development, create `includes/config.local.php` instead — see [Local Development with Herd + MariaDB](#local-development-with-herd--mariadb) below.

For production credentials, store them as [GitHub Actions secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets) — never commit them to the repository.

### Hostinger Hosting (Example Values)

Example values for a Hostinger MySQL database setup (store the actual credentials in GitHub secrets or your hosting panel, not in the repo):

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pool_estimator');
define('DB_USER', 'u123456789_pool_estimator');
define('DB_PASS', 'your-real-db-password');
```

Also confirm the timezone:
```php
date_default_timezone_set('America/New_York');
```

### 3. Upload Files

Upload all files to your hosting's `public_html/` directory (or a subdirectory).

### 4. Run Installation

Visit `https://yourdomain.com/install.php` in your browser:

1. **Step 1** — Tests database connection
2. **Step 2** — Creates tables and default data
3. **Step 3** — Set your business name and access PIN

### 5. Secure Installation

After setup, **delete `install.php`** from your server for security.

> **Future migrations:** Use `migrate.php` (requires admin login) to apply new SQL scripts without going through the full installation wizard again. See [Running Migrations](#running-migrations).

### 6. Login

Visit `https://yourdomain.com/` and enter your PIN.

## Local Development with Homebrew PHP + MariaDB

This project is designed to match your hosting environment: **PHP 8.3** and **MariaDB 11.8.x**.

### 1. Install the local stack

Install the following on your Mac:

1. **PHP 8.3**
   - Install PHP with Homebrew:
     ```bash
     brew install php@8.3
     ```
   - Make sure the PHP binary resolves in your shell:
     ```bash
     php -v
     ```
   - If needed, add the Homebrew PHP bin directory to your PATH.

2. **Homebrew MariaDB 11.8.x**
   - Install MariaDB with Homebrew:
     ```bash
     brew install mariadb@11.8
     brew services start mariadb@11.8
     ```
   - Start the MariaDB service locally

Example commands:

```bash
php -v
mariadb --version
```

You should see:
- PHP 8.3.x
- MariaDB 11.8.x

### 2. Create the local database

Open the MariaDB shell:

```bash
mariadb
```

Create the database:

```sql
CREATE DATABASE pool_estimator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Create the app user and grant access. Use the same password you plan to put in `DB_PASS` in the local config file:

```sql
CREATE USER 'pool_estimator'@'localhost' IDENTIFIED BY 'your_local_password';
GRANT ALL PRIVILEGES ON pool_estimator.* TO 'pool_estimator'@'localhost';
FLUSH PRIVILEGES;
```

> The password in `IDENTIFIED BY 'your_local_password'` must match the value used in `DB_PASS` in `includes/config.local.php`.

### 3. Configure the local app connection

`config.php` contains CI/CD token placeholders (`{{DB_HOST}}` etc.) and must not be edited. Instead, create `includes/config.local.php` with your local credentials — this file is gitignored and loaded automatically when present:

```php
<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'pool_estimator');
define('DB_USER', 'pool_estimator');
define('DB_PASS', 'your_local_password');
```

The password above must match the password used in the MariaDB `CREATE USER` statement exactly.

The constants defined here take priority over the token placeholders in `config.php` via `defined() || define()` guards, so the `{{TOKEN}}` strings are never evaluated.

### 4. Serve the app locally

You do not need Herd or any special local server manager. Use the PHP built-in web server from your Homebrew PHP 8.3 install:

```bash
cd /path/to/pool-cost-estimator
php -S localhost:8000
```

Then open the app in your browser:

```text
http://localhost:8000
```

If you prefer, you can also point any local web server at the project folder instead.

### 5. Run the installer

Visit the app URL and open:

```text
http://localhost:8000/install.php
```

Complete the 3-step setup wizard:

1. **Step 1** — tests the database connection
2. **Step 2** — creates the tables and default data
3. **Step 3** — sets the business name and access PIN

### 6. Access the app

After installation, visit the project URL and log in with the PIN you set during setup.

> No manual revert needed before deploying. `config.local.php` only exists on your machine; the CI/CD pipeline works directly from the token placeholders in `config.php`.

---

## CI/CD & Token Replacement

This project uses GitHub Actions to deploy to cPanel via FTP. Credentials are stored as [repository secrets](https://docs.github.com/en/actions/security-guides/encrypted-secrets) and injected at deploy time using `sed`:

| Branch | Workflow | Destination |
|--------|----------|-------------|
| `main` | `.github/workflows/deploy.yml` | Production (`/`) |
| `development` | `.github/workflows/deploy-dev.yml` | QA (`/qa/`) |

The deploy step replaces the `{{TOKEN}}` placeholders in `includes/config.php` with the real values before uploading:

```yaml
- name: Replace config tokens
  run: |
    sed -i 's/{{DB_HOST}}/${{ secrets.PROD_DB_HOST }}/g' includes/config.php
    sed -i 's/{{DB_NAME}}/${{ secrets.PROD_DB_NAME }}/g' includes/config.php
    sed -i 's/{{DB_USER}}/${{ secrets.PROD_DB_USERNAME }}/g' includes/config.php
    sed -i 's/{{DB_PASS}}/${{ secrets.PROD_DB_PASS }}/g' includes/config.php
    sed -i 's/{{DB_PORT}}/${{ secrets.PROD_DB_PORT }}/g' includes/config.php
```

This means:
- `config.php` in the repository always contains only placeholders — **never real credentials**
- Local development uses `config.local.php` (gitignored) to supply the same constants before the placeholders are evaluated
- Production/QA environments receive a fully resolved `config.php` only during the deploy run

### Setting up GitHub Secrets

Go to your repository → **Settings → Secrets and variables → Actions** and add:

| Secret name | Description |
|---|---|
| `PROD_DB_HOST` | Production DB host |
| `PROD_DB_NAME` | Production DB name |
| `PROD_DB_USERNAME` | Production DB user |
| `PROD_DB_PASS` | Production DB password |
| `PROD_DB_PORT` | Production DB port |
| `HOSTINGER_DB_HOST` | Dev/QA DB host |
| `HOSTINGER_DB_NAME` | Dev/QA DB name |
| `HOSTINGER_DB_USERNAME` | Dev/QA DB user |
| `HOSTINGER_DB_PASS` | Dev/QA DB password |
| `HOSTINGER_DB_PORT` | Dev/QA DB port |
| `HOSTINGER_FTP_SERVER` | FTP host |
| `HOSTINGER_FTP_USERNAME` | FTP username |
| `HOSTINGER_FTP_PASSWORD` | FTP password |

---

## Project Structure

```
├── index.php                 # Login page
├── install.php               # Installation wizard (delete after setup)
├── migrate.php               # Migration runner for existing installs (requires login)
├── dashboard.php             # Main dashboard with estimates list
├── estimate.php              # Create/edit estimate form
├── clients.php               # Client management (list, add, edit, view)
├── settings.php              # Business info, pricing, PIN change
├── audit-log.php             # Audit log viewer (activity history)
├── print-estimate.php        # Print-friendly estimate / PDF download
├── api.php                   # AJAX API (client search, calculations)
├── logout.php                # Logout
├── manifest.json             # PWA manifest (add to home screen)
├── .htaccess                 # Security & caching rules
├── includes/
│   ├── config.php            # Config with CI/CD token placeholders (committed)
│   ├── config.local.php      # Local credentials override — gitignored, never committed
│   ├── db.php                # PDO database connection
│   ├── functions.php         # All helper functions
│   ├── auth.php              # Session authentication
│   ├── header.php            # HTML header template
│   └── footer.php            # HTML footer template
├── assets/
│   ├── css/style.css         # All styles (mobile-first responsive)
│   └── js/app.js             # All JavaScript (calculations, UI)
└── sql/
    └── schema.sql            # Database schema & default data
```

## Usage

### Creating an Estimate

1. Click **New Estimate** from the dashboard
2. Search for an existing client or type a new name
3. Enter pool dimensions (length, width, depths)
4. Select pool shape and material
5. Toggle features (jacuzzi, lighting, heating, etc.)
6. Add deck and fencing options if needed
7. Add any custom line items
8. Review the live cost summary on the right
9. Click **Save Estimate**

### Printing / PDF

- Open a saved estimate → click **Print / PDF**
- Use the **Print** button for your browser's print dialog
- Use the **Download PDF** button to save as a PDF file

### Configuring Prices

Go to **Settings → Pricing** to adjust all unit prices. Changes apply to new estimates.

## Adding to Home Screen (Mobile App Experience)

On your tablet or phone:
- **iOS Safari**: Tap Share → "Add to Home Screen"
- **Android Chrome**: Tap menu → "Add to Home Screen"

This gives you an app-like experience without downloading from an app store.

## Hosting on Hostinger Premium

This app is designed to work perfectly on Hostinger Premium:
- Uses PHP + MySQL (both included)
- No Composer or Node.js required
- All dependencies load from CDN
- `.htaccess` handles security and caching
- Lightweight — minimal server resources needed

## Audit Log

Every create, update, delete, and duplicate action on estimates and clients is automatically logged. Settings changes are logged too. Each entry records:

- **User role** — admin or estimator
- **Date & time** — when the action occurred
- **IP address** — where the request came from
- **Browser (User Agent)** — which device/browser was used
- **Details** — contextual info (estimate number, client name, totals, etc.)

Access the log from the **Audit Log** link in the sidebar. Filter by entity type (estimates, clients, settings) and paginate through history.

## Security Notes

- PIN is hashed with `password_hash()` (bcrypt)
- Sessions are HTTP-only with SameSite=Strict
- CSRF protection on all forms
- SQL injection prevented via PDO prepared statements
- `.htaccess` blocks access to sensitive files
- Delete `install.php` after setup
- `migrate.php` is protected by admin session authentication; no credentials are stored in the file itself

## Running Migrations

After the initial installation, apply new SQL migration scripts without re-entering business information or your admin PIN.

### Using the Web UI (recommended)

1. Log in to the app as an admin.
2. Navigate to `https://yourdomain.com/migrate.php`.
3. Click **Run Pending Migrations**.
4. The page shows each file in `sql/migrations/` with its status:
   - **Applied** — the script ran successfully and was recorded.
   - **Already applied** — skipped because it was already recorded in the `migrations` table.
   - **Error** — the script failed; details are shown inline.
5. Your business information and admin PIN are **never touched** by this process.

### What counts as a pending migration?

Any `.sql` file in `sql/migrations/` whose filename does **not** appear in the `migrations` database table. Files are applied in sorted (alphabetical/numeric) order.

### Adding a new migration

1. Create a new file in `sql/migrations/` following the naming convention: `NNN_description.sql` (e.g. `003_add_promo_codes.sql`).
2. Write idempotent SQL where possible (use `IF NOT EXISTS`, `IF EXISTS`, etc.).
3. Deploy the file to the server and run `migrate.php`.

### What about `install.php`?

`install.php` is the **first-time** setup wizard. On an already-configured database, Step 2 now auto-detects the existing setup and redirects directly to the completion screen — it will **never** overwrite your business info or PIN. You can still use `install.php` to reapply the base schema + any pending migrations during first-time setup, then delete it afterward for security.

## License

Private use. All rights reserved. 
