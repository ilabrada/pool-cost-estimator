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

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.0+ with PDO |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Frontend | HTML5, CSS3 (responsive), Vanilla JavaScript |
| PDF | html2pdf.js (client-side, no server library needed) |
| Icons | Google Material Icons (CDN) |
| Fonts | Inter (Google Fonts CDN) |

## Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite (standard on Hostinger)

## Installation

### 1. Create Database

In your hosting panel (e.g., Hostinger hPanel):
- Go to **Databases → MySQL Databases**
- Create a new database (e.g., `pool_estimator`)
- Note the database name, username, and password

### 2. Configure Database Credentials

Edit `includes/config.php` and update:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
```

Also set your timezone:
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

### 6. Login

Visit `https://yourdomain.com/` and enter your PIN.

## Local Development with MAMP

MAMP is the easiest way to run this app on your Mac before uploading to Hostinger.

### 1. Install MAMP

Download and install **MAMP Free** from [mamp.info](https://www.mamp.info/). No paid version needed.

### 2. Point MAMP to Your Project Folder *(recommended)*

Instead of copying files, point MAMP directly to your repository so any edits in VS Code are served immediately — no syncing needed:

1. Open **MAMP** → Click **Preferences** → Click the **Web Server** tab
2. Change **Document Root** to your project folder:
   ```
   /Users/ivan/Documents/repository/pool-cost-estimator
   ```
3. Click **OK**

> **Alternative — Symbolic Link:** If you use MAMP for multiple projects and prefer to keep its default root, run this once in Terminal:
> ```bash
> ln -s /Users/ivan/Documents/repository/pool-cost-estimator /Applications/MAMP/htdocs/pool-cost-estimator
> ```
> Then access the app at `http://localhost:8888/pool-cost-estimator/` instead of `http://localhost:8888/`.

### 3. Start MAMP Servers

Open the MAMP application and click **Start Servers**. Both the Apache and MySQL indicators should turn green.

### 4. Create the Database

1. Open **phpMyAdmin** at [http://localhost:8888/phpmyadmin](http://localhost:8888/phpmyadmin)
2. Log in with username `root` and password `root`
3. Click **New** in the left sidebar
4. Name the database `pool_estimator` and set collation to `utf8mb4_unicode_ci`
5. Click **Create**

### 5. Update `includes/config.php`

Change the database block to use MAMP's default credentials and port:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '8889');        // MAMP uses port 8889 for MySQL
define('DB_NAME', 'pool_estimator');
define('DB_USER', 'root');
define('DB_PASS', 'root');        // MAMP default password
```

Also enable error display when developing locally:

```php
ini_set('display_errors', '1');
```

### 6. Run the Installer

Visit [http://localhost:8888/pool-cost-estimator/install.php](http://localhost:8888/pool-cost-estimator/install.php) and complete the 3 steps.

### 7. Access the App

Go to [http://localhost:8888/pool-cost-estimator/](http://localhost:8888/pool-cost-estimator/) and log in with the PIN you just set.

> **Before deploying to Hostinger**, revert `DB_PORT` to `3306`, update the credentials to your Hostinger DB values, and set `display_errors` back to `'0'`.

---

## Project Structure

```
├── index.php                 # Login page
├── install.php               # Installation wizard (delete after setup)
├── dashboard.php             # Main dashboard with estimates list
├── estimate.php              # Create/edit estimate form
├── clients.php               # Client management (list, add, edit, view)
├── settings.php              # Business info, pricing, PIN change
├── print-estimate.php        # Print-friendly estimate / PDF download
├── api.php                   # AJAX API (client search, calculations)
├── logout.php                # Logout
├── manifest.json             # PWA manifest (add to home screen)
├── .htaccess                 # Security & caching rules
├── includes/
│   ├── config.php            # Database & app configuration
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

## Security Notes

- PIN is hashed with `password_hash()` (bcrypt)
- Sessions are HTTP-only with SameSite=Strict
- CSRF protection on all forms
- SQL injection prevented via PDO prepared statements
- `.htaccess` blocks access to sensitive files
- Delete `install.php` after setup

## License

Private use. All rights reserved.
