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