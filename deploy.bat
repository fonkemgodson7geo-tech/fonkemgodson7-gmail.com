@echo off
cd /d "C:\Users\NGOUNOU DORCAS\Desktop\AWCD"
git add admin/login.php includes/auth.php doctor/register.php
git commit -m "Fix admin login and doctor registration with schema self-healing"
git push origin main
pause
