#!/usr/bin/env bash
# AWCD Deployment Verification Script
# Run this after deployment to verify everything is working

echo "🚀 AWCD Deployment Verification"
echo "================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

PASSED=0
FAILED=0

# Function to check result
check_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ PASSED${NC}: $2"
        ((PASSED++))
    else
        echo -e "${RED}❌ FAILED${NC}: $2"
        ((FAILED++))
    fi
}

echo "1. DNS Resolution"
echo "-----------------"
nslookup awcd.onrender.com > /dev/null 2>&1
check_result $? "awcd.onrender.com DNS resolution"

nslookup www.cmdonsdesoins.com > /dev/null 2>&1
check_result $? "www.cmdonsdesoins.com DNS resolution"

echo ""
echo "2. HTTP Connectivity"
echo "--------------------"
curl -s --max-time 10 -o /dev/null -w "%{http_code}" https://awcd.onrender.com/api/health.php | grep -q "200"
check_result $? "awcd.onrender.com health endpoint (HTTP 200)"

curl -s --max-time 10 -o /dev/null -w "%{http_code}" https://www.cmdonsdesoins.com/api/health.php | grep -q "200"
check_result $? "www.cmdonsdesoins.com health endpoint (HTTP 200)"

echo ""
echo "3. Application Health"
echo "---------------------"
HEALTH_RESPONSE=$(curl -s --max-time 10 https://awcd.onrender.com/api/health.php)
if echo "$HEALTH_RESPONSE" | grep -q '"status":"operational"'; then
    check_result 0 "Application status: operational"
else
    check_result 1 "Application status check"
fi

if echo "$HEALTH_RESPONSE" | grep -q '"database":"up"'; then
    check_result 0 "Database connectivity"
else
    check_result 1 "Database connectivity"
fi

echo ""
echo "4. Key Pages Accessibility"
echo "--------------------------"
curl -s --max-time 10 -o /dev/null -w "%{http_code}" https://www.cmdonsdesoins.com/index.php | grep -q "200"
check_result $? "Main index page"

curl -s --max-time 10 -o /dev/null -w "%{http_code}" https://www.cmdonsdesoins.com/admin/login.php | grep -q "200"
check_result $? "Admin login page"

curl -s --max-time 10 -o /dev/null -w "%{http_code}" https://www.cmdonsdesoins.com/patient/login.php | grep -q "200"
check_result $? "Patient login page"

echo ""
echo "5. Security Headers"
echo "-------------------"
SECURITY_HEADERS=$(curl -s -I --max-time 10 https://awcd.onrender.com/api/health.php | grep -i "content-type\|x-")
if echo "$SECURITY_HEADERS" | grep -q "application/json"; then
    check_result 0 "Proper content-type headers"
else
    check_result 1 "Content-type headers"
fi

echo ""
echo "📊 DEPLOYMENT SUMMARY"
echo "====================="
echo "✅ Passed: $PASSED"
echo "❌ Failed: $FAILED"

if [ $FAILED -eq 0 ]; then
    echo ""
    echo -e "${GREEN}🎉 DEPLOYMENT SUCCESSFUL!${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Run user training sessions using USER_GUIDE.md"
    echo "2. Set up automated monitoring with monitor.php"
    echo "3. Configure backup procedures"
    echo "4. Monitor system logs for 24-48 hours"
    exit 0
else
    echo ""
    echo -e "${RED}⚠️  DEPLOYMENT ISSUES DETECTED${NC}"
    echo ""
    echo "Troubleshooting steps:"
    echo "1. Check Render deployment logs"
    echo "2. Verify environment variables"
    echo "3. Test database connectivity"
    echo "4. Review application logs"
    exit 1
fi</content>
<parameter name="filePath">c:\Users\TECHWAVE\Desktop\AWCD\verify_deployment.sh