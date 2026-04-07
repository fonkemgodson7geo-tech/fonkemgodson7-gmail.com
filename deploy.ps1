#!/usr/bin/env powershell
# Automated deployment script for cmdonsdesoins.com
# This script commits and deploys all auth fixes to Render

Write-Host "==================================" -ForegroundColor Cyan
Write-Host "DEPLOYING TO cmdonsdesoins.com" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""

# Navigate to project
cd "C:\Users\NGOUNOU DORCAS\Desktop\AWCD"
Write-Host "✓ In project folder: $(Get-Location)" -ForegroundColor Green

# Check git status
Write-Host ""
Write-Host "Checking git status..." -ForegroundColor Yellow
git status

# Stage files
Write-Host ""
Write-Host "Staging changes..." -ForegroundColor Yellow
git add admin/login.php includes/auth.php doctor/register.php api/reset_admin.php
Write-Host "✓ Files staged" -ForegroundColor Green

# Commit
Write-Host ""
Write-Host "Creating commit..." -ForegroundColor Yellow
git commit -m "Fix admin login, doctor registration, and add password reset tool"
Write-Host "✓ Commit created" -ForegroundColor Green

# Push to Render
Write-Host ""
Write-Host "Pushing to remote (Render)..." -ForegroundColor Yellow
git push origin main
Write-Host "✓ Code pushed to Render!" -ForegroundColor Green

Write-Host ""
Write-Host "==================================" -ForegroundColor Cyan
Write-Host "DEPLOYMENT IN PROGRESS" -ForegroundColor Cyan
Write-Host "==================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Render is rebuilding your app..." -ForegroundColor Yellow
Write-Host ""
Write-Host "Next steps:" -ForegroundColor Cyan
Write-Host "1. Wait 2-3 minutes for Render to finish"
Write-Host "2. Go to: https://cmdonsdesoins.com/api/reset_admin.php" -ForegroundColor Magenta
Write-Host "3. Click the button to reset admin password"
Write-Host "4. Then login at: https://cmdonsdesoins.com/admin/login.php" -ForegroundColor Magenta
Write-Host "   Username: admie"
Write-Host "   Password: awc_DDS2019"
Write-Host ""
Write-Host "Done! Press Enter to close." -ForegroundColor Green
Read-Host
