<?php
// OnlineNIC API Configuration

// API Credentials
define('ONLINENIC_USER', '10578');
define('ONLINENIC_PASSWORD', '654123');
define('ONLINENIC_APIKEY', '{![Xic=GAUlWXEI_');

// API Endpoints
define('ONLINENIC_TEST_URL', 'https://ote.onlinenic.com:5999');
define('ONLINENIC_LIVE_URL', 'https://api.onlinenic.com');

// Environment: 'test', 'live', or 'demo'
define('ONLINENIC_ENV', 'demo');

// Enable demo mode for development
define('DEMO_MODE', true);

// Get current API base URL
function getOnlineNICBaseURL()
{
  return ONLINENIC_ENV === 'live' ? ONLINENIC_LIVE_URL : ONLINENIC_TEST_URL;
}

// Default nameservers
define('DEFAULT_DNS1', 'ns1.onlinenic.com');
define('DEFAULT_DNS2', 'ns2.onlinenic.com');

// Cache settings
define('CACHE_DOMAIN_CHECK', 300); // 5 minutes
define('CACHE_PRICING', 3600); // 1 hour
?>