#!/bin/bash

# Script to test all email methods and templates
# Usage: ./test-all-emails.sh [admin-email]

echo "═══════════════════════════════════════════════════════"
echo "  📧 Testing All Email Methods and Templates"
echo "═══════════════════════════════════════════════════════"
echo ""

# Get admin email from argument or use default from .env
ADMIN_EMAIL=${1:-"art@mikhaleff.art"}

echo "📬 Sending test emails to: $ADMIN_EMAIL"
echo ""

# Run the artisan command
php artisan email:test-all --admin-email="$ADMIN_EMAIL"

echo ""
echo "═══════════════════════════════════════════════════════"
echo "✅ Test completed! Check your inbox at: $ADMIN_EMAIL"
echo "═══════════════════════════════════════════════════════"
