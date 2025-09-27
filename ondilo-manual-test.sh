#!/bin/bash

# Ondilo Manual OAuth Test Script
echo "🔐 Ondilo OAuth Manual Test"
echo "=========================="

# Configuration
ONDILO_BASE="https://interop.ondilo.com"
CLIENT_ID="customer_api"
REDIRECT_URI="https://canvas39.vercel.app/auth/callback"
EMAIL="suvallop@gmail.com"
PASSWORD="kencen2007"
COOKIE_JAR="/tmp/ondilo_cookies.txt"

# Clean up previous cookies
rm -f $COOKIE_JAR

echo "📋 Step 1: Getting authorization page..."
AUTH_URL="${ONDILO_BASE}/oauth2/authorize?client_id=${CLIENT_ID}&response_type=code&redirect_uri=${REDIRECT_URI}&scope=api&state=test123"

# Get authorization page and extract CSRF token
AUTH_RESPONSE=$(curl -s -c $COOKIE_JAR \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  "$AUTH_URL")

# Extract CSRF token
CSRF_TOKEN=$(echo "$AUTH_RESPONSE" | grep -o 'name="_token" value="[^"]*"' | sed 's/name="_token" value="//;s/"//')

if [ -z "$CSRF_TOKEN" ]; then
    echo "❌ Failed to extract CSRF token"
    echo "Response preview:"
    echo "$AUTH_RESPONSE" | head -5
    exit 1
fi

echo "✅ CSRF Token extracted: ${CSRF_TOKEN:0:20}..."

echo "📋 Step 2: Submitting login form..."

# Submit login form
LOGIN_RESPONSE=$(curl -s -b $COOKIE_JAR -c $COOKIE_JAR \
  -X POST \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -H "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8" \
  -H "Referer: $AUTH_URL" \
  -D /tmp/ondilo_headers.txt \
  --data-urlencode "_token=$CSRF_TOKEN" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=$PASSWORD" \
  --data-urlencode "locale=en" \
  --data-urlencode "proceed=Authorize" \
  "$AUTH_URL")

# Check for redirect
REDIRECT_URL=$(grep -i "location:" /tmp/ondilo_headers.txt | sed 's/[Ll]ocation: //;s/\r//')

if [ -n "$REDIRECT_URL" ]; then
    echo "✅ Login successful! Redirect URL:"
    echo "$REDIRECT_URL"
    
    # Extract authorization code
    AUTH_CODE=$(echo "$REDIRECT_URL" | grep -o 'code=[^&]*' | sed 's/code=//')
    
    if [ -n "$AUTH_CODE" ]; then
        echo "✅ Authorization code extracted: ${AUTH_CODE:0:20}..."
        
        echo "📋 Step 3: Exchanging code for access token..."
        
        # Try different client secrets
        for SECRET in "customer_api" "customer_secret" "api_secret" ""; do
            echo "🔑 Trying client_secret: '$SECRET'"
            
            if [ -z "$SECRET" ]; then
                TOKEN_RESPONSE=$(curl -s -X POST \
                  -H "Content-Type: application/x-www-form-urlencoded" \
                  -H "Accept: application/json" \
                  --data-urlencode "grant_type=authorization_code" \
                  --data-urlencode "client_id=$CLIENT_ID" \
                  --data-urlencode "code=$AUTH_CODE" \
                  --data-urlencode "redirect_uri=$REDIRECT_URI" \
                  "${ONDILO_BASE}/oauth2/token")
            else
                TOKEN_RESPONSE=$(curl -s -X POST \
                  -H "Content-Type: application/x-www-form-urlencoded" \
                  -H "Accept: application/json" \
                  --data-urlencode "grant_type=authorization_code" \
                  --data-urlencode "client_id=$CLIENT_ID" \
                  --data-urlencode "client_secret=$SECRET" \
                  --data-urlencode "code=$AUTH_CODE" \
                  --data-urlencode "redirect_uri=$REDIRECT_URI" \
                  "${ONDILO_BASE}/oauth2/token")
            fi
            
            echo "Response: $TOKEN_RESPONSE"
            
            # Check if we got access token
            ACCESS_TOKEN=$(echo "$TOKEN_RESPONSE" | grep -o '"access_token":"[^"]*"' | sed 's/"access_token":"//;s/"//')
            
            if [ -n "$ACCESS_TOKEN" ]; then
                echo "🎉 SUCCESS! Access token obtained: ${ACCESS_TOKEN:0:30}..."
                
                echo "📋 Step 4: Testing API call..."
                
                # Test API call
                API_RESPONSE=$(curl -s \
                  -H "Authorization: Bearer $ACCESS_TOKEN" \
                  -H "Accept: application/json" \
                  -H "Accept-Charset: utf-8" \
                  -H "Content-Type: application/json" \
                  "${ONDILO_BASE}/api/customer/v1/pools")
                
                echo "🏊 Pool data:"
                echo "$API_RESPONSE" | head -20
                
                # Save token for later use
                echo "$ACCESS_TOKEN" > /tmp/ondilo_access_token.txt
                echo "💾 Access token saved to /tmp/ondilo_access_token.txt"
                
                break
            fi
        done
        
        if [ -z "$ACCESS_TOKEN" ]; then
            echo "❌ Failed to get access token with any client secret"
        fi
        
    else
        echo "❌ No authorization code found in redirect URL"
    fi
    
else
    echo "❌ No redirect found. Login may have failed."
    echo "Response preview:"
    echo "$LOGIN_RESPONSE" | head -10
    
    # Check for error messages
    if echo "$LOGIN_RESPONSE" | grep -i "error\|invalid\|wrong\|incorrect" > /dev/null; then
        echo "🚨 Possible authentication error detected"
    fi
fi

# Clean up
rm -f $COOKIE_JAR /tmp/ondilo_headers.txt

echo "🏁 Test completed"