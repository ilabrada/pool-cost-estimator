# Pool Cost Estimator — Feature Suggestions

## Context

The app is a production-ready PHP/MySQL pool cost estimator on shared hosting (iWebFusion via FTP CI/CD). No Composer, no Node.js, no framework — only native PHP + PDO + vanilla JS + CDN libs. All new features must work within those constraints: pure PHP cURL for any external API calls, no build step, no server-side package manager.

---

## Current Stack Summary

| Layer | Detail |
|---|---|
| Backend | PHP 8.0+, PDO, no framework |
| DB | MySQL (InnoDB, 7 tables) |
| Frontend | Vanilla JS, CSS custom properties, no build step |
| Hosting | Shared hosting (FTP deploy via GitHub Actions) |
| Auth | PIN-based, bcrypt, session cookies |
| External APIs | None currently |
| File handling | None (no uploads exist today) |

---

## Feature 1: Database Backup

### Recommended Approach (3 tiers)

#### Tier 1 — Local File Download ✅ (implement first, easiest)

- New admin page `backup.php` — admin-only (`requireAdmin()`)
- PHP iterates all tables using PDO (`SHOW TABLES`, then `SELECT *` per table)
- Generates a `.sql` file with `CREATE TABLE IF NOT EXISTS` + `INSERT` statements in PHP (no shell access needed — pure PHP, no `exec('mysqldump')` which is often blocked on shared hosts)
- Returns as a browser download: `Content-Type: application/sql`, `Content-Disposition: attachment; filename="backup-YYYY-MM-DD.sql"`
- Also offer CSV export per table as an alternative format
- Store nothing on server — stream directly to the browser response
- Add a "Restore from file" feature: file upload → parse SQL → execute statements in a transaction

#### Tier 2 — Google Drive ✅ (recommended cloud option)

- Use **Google Drive REST API v3** with a **Service Account** (simpler than OAuth for a single-business app — no user login flow needed)
- Admin downloads a service account JSON key from Google Cloud Console and pastes credentials into the Settings page (stored in the `settings` table as `gdrive_service_account_json`)
- PHP backend: generate the backup SQL in memory → POST to `https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart` using PHP `curl` with a JWT-signed Bearer token
- JWT signing is ~30 lines of PHP using `hash_hmac` + base64url encode (no library needed)
- Add a `gdrive_folder_id` setting so backups go to a specific Drive folder
- Files named `pool-backup-YYYY-MM-DD-HH-MM.sql`
- Show last backup date/status in the backup UI

#### Tier 3 — OneDrive ✅ (optional, similar effort to GDrive)

- Use **Microsoft Graph API** with an **App Registration** (client credentials flow — also service-account-like, no user login)
- Admin stores `onedrive_client_id`, `onedrive_client_secret`, `onedrive_tenant_id` in settings
- PHP: POST to `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token` for access token → PUT file to `https://graph.microsoft.com/v1.0/drives/{drive-id}/items/root:/{filename}:/content`
- All via cURL, no SDK

#### iCloud — Not recommended ❌

Apple does not provide a public programmatic API for iCloud Drive for third-party server apps. The only option is CloudKit JS (browser-side, not server-side). **Recommendation**: skip iCloud automation; the "Local File Download" backup naturally works with iCloud — the user downloads it and saves it to their iCloud Drive folder on their Mac.

### New Files
- `backup.php` — admin page with backup/restore UI
- `includes/backup_functions.php` — `generateSQLDump()`, `uploadToGDrive()`, `uploadToOneDrive()`

### DB Changes
- New settings keys: `gdrive_service_account_json`, `gdrive_folder_id`, `onedrive_client_id`, `onedrive_client_secret`, `onedrive_tenant_id`, `last_backup_date`, `last_backup_target`

### Integration Points
- `settings.php` — add new "Cloud Backup" section for API credential input
- `includes/functions.php` — add `getSetting()`/`setSetting()` calls (already exists, no changes needed)
- `.htaccess` — already blocks `/includes/` directory listing; no changes needed

---

## Feature 2: AI Pool Visualizer

### What It Does
User uploads a backyard photo → selects pool type and key parameters (from or matching the estimate) → AI returns a photorealistic image of what the pool would look like in that exact backyard.

### Recommended API: Stability AI (stability.ai) ✅

**Why Stability AI over others:**
- REST API, pure cURL — no SDK, works on shared hosting
- **img2img endpoint**: takes an existing photo + text prompt → generates a modified image. This is exactly the use case.
- **Inpainting endpoint**: user draws a mask over where the pool should go → more precise placement
- Affordable pay-per-use ($0.002–0.01 per image)
- Returns base64-encoded image directly in the JSON response — no need to follow redirect URLs

**Alternative APIs (if Stability AI is not preferred):**
| Service | Notes |
|---|---|
| **Replicate.com** | Hosts hundreds of models including SDXL img2img; REST API; slightly more complex async flow (poll for result) |
| **OpenAI gpt-image-1** | Image editing endpoint supports reference images; higher cost (~$0.04/image); simpler API |
| **Fal.ai** | Fast FLUX models; async REST; similar to Replicate |

### Workflow Design

```
User: uploads backyard photo + selects pool style → 
  PHP: validates file (type, size) → resizes to 1024x1024 → 
  PHP: constructs prompt from estimate data → 
  PHP: POST to Stability AI img2img via cURL → 
  PHP: decodes base64 image → saves to /assets/generated/{uuid}.png → 
  Frontend: shows before/after comparison slider
```

**Prompt construction** (uses estimate data automatically):
```
"A beautiful inground {shell_type} swimming pool {features_list}, 
professional pool installation, photorealistic, 4K, residential backyard, 
natural lighting, clean water, landscaping"
```

### New Files
- `ai-visualizer.php` — new page with upload form + result display
- `includes/ai_functions.php` — `callStabilityAI()`, `buildPoolPrompt()`, `resizeImage()`, `saveGeneratedImage()`
- `assets/generated/` — directory for generated images (add to `.gitignore`)

### Frontend Additions
- File upload input with client-side preview
- Pool style selector (ties into existing estimate parameters)
- "Generate Visualization" button with loading spinner
- Before/after image comparison slider (pure CSS/JS, no library needed)
- "Attach to Estimate" button to save the generated image to an estimate record

### DB Changes
- New settings key: `stability_api_key`
- New column on `estimates` table: `visualization_image` (VARCHAR 255, path to generated image) — via migration `003_add_visualization_to_estimates.sql`

### Hosting Constraints to Address
- `php.ini` / `.htaccess`: increase `upload_max_filesize = 10M`, `post_max_size = 12M`, `max_execution_time = 60` (API call can take 5–15 seconds)
- Image resizing: use PHP's built-in `GD` library (available on virtually all shared hosts) — no ImageMagick needed
- Generated image storage: `/assets/generated/` — add cleanup cron or manual purge in admin UI (shared hosting has disk limits)
- cURL with SSL: already available on modern shared hosts; need `CURLOPT_SSL_VERIFYPEER = true`

### Settings Integration
- Add "AI Visualizer" section to `settings.php` with Stability AI API key input field

---

## Implementation Order

1. **Local file backup** (1–2 days) — highest value, zero external dependencies
2. **AI Visualizer with Stability AI** (2–3 days) — highest wow-factor feature
3. **Google Drive backup** (1–2 days) — requires Google Cloud Console setup
4. **OneDrive backup** (1 day) — same pattern as Google Drive

---

## Verification

- **Backup**: trigger download from `backup.php`; open `.sql` file; verify all 7 tables present with data; restore into a fresh DB and confirm data integrity
- **Google Drive**: trigger upload; check target folder in Google Drive console; verify file name and content
- **AI Visualizer**: upload a test backyard JPEG; confirm generated image appears; verify image saved to `/assets/generated/`; verify estimate attachment flow
- **Hosting limits**: test with a 5MB photo upload on the actual hosted environment; verify no 413/timeout errors
