# Domain Portal - Mock API Setup Guide

## What We Did ✅

You now have a **Mock API** that works perfectly for development and doesn't depend on external services.

### Files Created/Modified:

1. **`api/mock_domains.php`** - Full-featured mock API endpoint
2. **`api_tester.html`** - Beautiful UI for testing all API endpoints
3. **`config/onlinenic.php`** - Enabled DEMO_MODE
4. **`pages/domain_search.php`** - Now uses mock API instead of real API

---

## How to Test

### Option 1: Use the API Tester (Recommended)

Open in browser: **`http://yoursite.com/api_tester.html`**

Features:

- ✅ Check single domain availability
- ✅ Check multiple domains at once
- ✅ Register domains (mock)
- ✅ Renew domains (mock)
- ✅ Get domain info
- ✅ Get pricing for all TLDs
- ✅ Send custom requests

### Option 2: Direct API Calls

```javascript
// Check single domain
fetch("api/mock_domains.php", {
  method: "POST",
  body: new URLSearchParams({
    action: "check",
    domain: "test.com",
  }),
})
  .then((r) => r.json())
  .then((data) => console.log(data));
```

### Option 3: Form Submissions

```html
<form action="api/mock_domains.php" method="POST">
  <input name="action" value="check" />
  <input name="domain" placeholder="test.com" />
  <button type="submit">Check</button>
</form>
```

---

## Supported Actions

### 1. Check Single Domain

```
POST api/mock_domains.php
action=check&domain=example.com

Response:
{
  "success": true,
  "message": "Domain check successful",
  "data": {
    "domain": "example.com",
    "available": false,
    "premium": false,
    "price": 12.99,
    "tld": "com"
  }
}
```

### 2. Check Multiple Domains

```
POST api/mock_domains.php
action=check_multiple&domains=["test.com", "example.com"]

Response:
{
  "success": true,
  "message": "Batch check successful",
  "data": {
    "results": [
      {
        "domain": "test.com",
        "available": true,
        "premium": false,
        "price": 12.99,
        "tld": "com"
      },
      // ... more results
    ]
  }
}
```

### 3. Register Domain

```
POST api/mock_domains.php
action=register&domain=test.com&period=1

Response:
{
  "success": true,
  "message": "Domain registered successfully",
  "data": {
    "domain": "test.com",
    "registration_id": "REG20260210123456",
    "registered_date": "2026-02-10",
    "expiry_date": "2027-02-10",
    "period": 1,
    "total_cost": 12.99,
    "status": "active"
  }
}
```

### 4. Renew Domain

```
POST api/mock_domains.php
action=renew&domain=test.com&period=1

Response:
{
  "success": true,
  "message": "Domain renewed successfully",
  "data": {
    "domain": "test.com",
    "renewal_id": "REN20260210123456",
    "new_expiry_date": "2027-02-10",
    "period": 1,
    "total_cost": 12.99
  }
}
```

### 5. Get Domain Info

```
POST api/mock_domains.php
action=info&domain=test.com

Response:
{
  "success": true,
  "message": "Domain information retrieved",
  "data": {
    "domain": "test.com",
    "status": "active",
    "registered_date": "2024-02-10",
    "expiry_date": "2027-02-10",
    "registrar": "MockNIC",
    "nameservers": ["ns1.example.com", "ns2.example.com"]
  }
}
```

### 6. Get Pricing

```
POST api/mock_domains.php
action=pricing

Response:
{
  "success": true,
  "message": "Pricing retrieved",
  "data": {
    "tlds": {
      "com": 12.99,
      "net": 13.99,
      "org": 14.99,
      "io": 35.99,
      "tech": 45.99
    }
  }
}
```

---

## Test Domains

Available domains (will show as available):

- ✅ test.com
- ✅ myproject.com
- ✅ startupname.io
- ✅ innovation.tech
- ✅ blog.net

Taken domains (will show as unavailable):

- ❌ google.com
- ❌ facebook.com
- ❌ example.com

Any other domain will randomly show as available/unavailable.

---

## When to Switch to Real API

Once you have completed:

1. ✅ Frontend design & layout
2. ✅ Database schema & connections
3. ✅ User authentication
4. ✅ Shopping cart functionality
5. ✅ Order management
6. ✅ Admin dashboard

### Then Switch:

In `config/onlinenic.php`, change:

```php
// From:
define('DEMO_MODE', true);
define('ONLINENIC_ENV', 'demo');

// To:
define('DEMO_MODE', false);
define('ONLINENIC_ENV', 'live');
```

And update your API endpoints from `api/mock_domains.php` back to `api/domain_search.php` which uses the real OnlineNIC API.

---

## Benefits of This Approach

✅ **No external dependencies** - Works offline  
✅ **Reliable & consistent** - Same results every time  
✅ **Fast development** - No API throttling  
✅ **Easy testing** - Predictable mock data  
✅ **Easy migration** - Just change config when ready  
✅ **Better debugging** - See exactly what data is returned

---

## Next Steps

1. Open `api_tester.html` in your browser and test all actions
2. Build out your frontend features
3. Connect database for user/domain storage
4. Implement shopping cart and checkout
5. Switch to real OnlineNIC API when ready

Good luck! 🚀
