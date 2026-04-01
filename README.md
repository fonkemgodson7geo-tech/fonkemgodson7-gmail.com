# fonkemgodson7-gmail.com

## Production Go-Live Checklist

Use this checklist for every release to reduce deployment risk.

### 1. Pre-Deploy

- Confirm you are on the correct branch and the working tree is clean.
- Pull latest `main` and ensure `dev` is rebased or merged cleanly.
- Verify secrets exist in production `.env` and are not committed:
	- `ENCRYPTION_KEY`
	- Database credentials (`DB_TYPE`, `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`)
	- External service keys (`CINERPAY_API_KEY`, `SMTP_*`)
- Ensure writable paths exist in production:
	- `uploads/`
	- SQLite file path (if `DB_TYPE=sqlite`)

### 2. Deploy

- Deploy from `main` only.
- Confirm dependency install/build steps complete successfully.
- If database schema changed, run migration/setup scripts in the approved order.

### 3. Immediate Verification (Smoke Test)

- Check health endpoint `/api/health.php`:
	- Response is valid JSON
	- `status` is `operational`
	- `checks.database` is `up`
- Validate role-based login and dashboard load:
	- Admin
	- Doctor
	- Patient
- Test one critical write flow end-to-end (create/update record).
- Test logout for each role.

### 4. Rollback Readiness

- Keep previous deployment artifact/version available.
- Ensure recent database backup exists before release.
- Define rollback trigger: repeated 5xx errors, failed login flow, or DB failures.
- Document who executes rollback and how to verify recovery.

### 5. Post-Deploy (First 24 Hours)

- Monitor uptime, response time, and 5xx rate.
- Review application and server logs for errors.
- Confirm backups continue running on schedule.
- Record any incidents and update this checklist if a gap is found.

## Branch Strategy

- Develop on `dev`.
- Validate in `dev`, then merge/rebase to `main` for production.
- Tag releases after successful production verification.

## Branding Setup (Logo)

The landing page top bar supports a configurable logo via environment variable.

1. Set `SITE_LOGO_URL` in your production `.env`.
2. Use either an absolute URL (`https://.../logo.png`) or a project-relative path (`assets/img/logo.png`).
3. Redeploy or restart PHP so environment changes are loaded.

Example:

```env
SITE_LOGO_URL=https://your-domain.com/assets/logo.png
```

If `SITE_LOGO_URL` is empty, the UI shows a built-in `LOGO` placeholder box.

## How To Cut A Release

Run these commands in order.

```bash
# 1) Update local refs
git fetch origin

# 2) Update dev with latest remote work
git checkout dev
git pull --rebase origin dev

# 3) Sync main to remote and merge dev
git checkout main
git pull --rebase origin main
git merge dev

# 4) Push release commit(s) to main
git push origin main

# 5) Tag release and push tag
git tag -a vX.Y.Z -m "Release vX.Y.Z"
git push origin vX.Y.Z

# 6) Return to dev for next cycle
git checkout dev
```

If merge conflicts occur, resolve them, run smoke tests again, then continue and push.

## Search Visibility Checklist (Google, Bing, Others)

Use this after each production deployment so the site is discoverable.

### 1. Public URL And Domain

- Confirm the site is reachable from the public internet (no auth wall on home page).
- Set `SITE_URL` in production to your canonical domain (for example, `https://awcd.onrender.com` or your custom domain).
- If using a custom domain, configure DNS records in your registrar and verify HTTPS is active.

### 2. Crawl And Sitemap

- Confirm `robots.txt` is live at `/robots.txt`.
- Confirm sitemap endpoint is live at `/sitemap.php`.
- Keep private portals non-indexed (already disallowed in `robots.txt`).

### 3. Search Console Setup

- Add your domain in Google Search Console.
- Verify ownership (usually DNS TXT record).
- Submit sitemap URL: `https://your-domain/sitemap.php`.
- Request indexing for the homepage and key public pages.

### 4. Bing And Other Engines

- Add the site to Bing Webmaster Tools.
- Submit the same sitemap URL there.
- Ensure site metadata (title and description) remains accurate after each release.

### 5. Quick Verification Commands

```bash
# Health
curl -s https://your-domain/api/health.php

# Robots
curl -I https://your-domain/robots.txt

# Sitemap
curl -I https://your-domain/sitemap.php
```

Expected result: HTTP `200` for all three endpoints, and valid XML on sitemap output.

## Release Log Template

Copy this block for each release.

```md
### Release YYYY-MM-DD - vX.Y.Z

- Owner: Name
- Branch/Tag: main / vX.Y.Z
- Commit: <short-hash>
- Environment: production

#### Scope

- Summary of features/fixes included.

#### Pre-Deploy Checks

- [ ] Branch clean and up to date
- [ ] Secrets verified in production
- [ ] Backup confirmed

#### Deployment

- Start time (UTC):
- End time (UTC):
- Result: Success / Failed / Rolled back

#### Verification

- [ ] /api/health.php = operational
- [ ] Admin login/dashboard
- [ ] Doctor login/dashboard
- [ ] Patient login/dashboard
- [ ] Critical write flow passed

#### Incidents / Notes

- Any errors, mitigations, or follow-up actions.

#### Rollback (if used)

- Trigger reason:
- Rollback start/end time:
- Recovery verification:
```