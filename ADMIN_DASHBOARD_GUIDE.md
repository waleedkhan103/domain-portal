**Admin Dashboard - Getting Started Guide**

## Default Admin Credentials

- **Username**: `admin`
- **Password**: `admin`

## Steps to Access Dashboard

### 1. Navigate to Admin Login Page

```
http://localhost/admin/login.php
```

### 2. Log In

- Enter username: `admin`
- Enter password: `admin`
- Click "Login"

### 3. Access Dashboard

After successful login, you'll be redirected to the dashboard.

## What You'll See (Mock Data - Database Offline)

The dashboard displays sample data while your database is being set up:

- **Metrics**: 1,247 total domains, 1,156 active, 34 expiring soon
- **Revenue**: $15,234.50 this month (up 18% from last month)
- **Recent Domains**: Sample registered domains with status
- **Recent Orders**: Sample orders showing completed, pending, and failed statuses
- **Alerts**: 5 domains expiring within 7 days, 12 within 14 days, 7 pending orders
- **Status Chart**: Pie chart showing domain distribution by status
- **Quick Search**: Search for domains or orders by ID/name

## Starting Your Database

When you're ready to use live data instead of mock data:

### Windows - Start MySQL Service

```bash
net start MySQL80
```

Or manually in Services (services.msc):

1. Press `Win + R`, type `services.msc`
2. Find "MySQL80" in the list
3. Right-click → Start

### Create Database Tables

```bash
mysql -u allrounder -p'7ujm&5tgb%' < database/schema_additions.sql
```

### Verify Connection

```bash
php -r "
\$conn = mysqli_connect('localhost', 'allrounder', '7ujm&5tgb%', 'domain_portal');
echo \$conn ? 'Database Connected!' : 'Error: ' . mysqli_connect_error();
"
```

## Features Available

✅ **Dashboard Overview** - Key metrics and quick stats
✅ **Domain Management** (stubbed for UI completion)
✅ **User Management** (stubbed for UI completion)  
✅ **Order Management** (stubbed for UI completion)
✅ **Search** - Quick search across domains and orders
✅ **Alerts** - Warnings for expiring domains and pending orders
✅ **Responsive Design** - Works on desktop and tablet

## Troubleshooting

### Still seeing "Error: Unable to load dashboard data"?

1. **Make sure you're logged in**: You should NOT see the login form when accessing `/admin/dashboard.php`. If you do, you need to log in first.

2. **Check browser console**: Press `F12` → Console tab for detailed error messages

3. **Verify session**: Make sure cookies/sessions are enabled in your browser

4. **Clear cache**: Try a hard refresh (`Ctrl + Shift + R`)

### To debug the API:

```bash
# Simulate a logged-in request to the API
curl -s "http://localhost/admin/api/dashboard_data.php" | head -100
```

## Next Steps

1. ✅ Complete the Admin Dashboard (current)
2. ⏳ Add Users Management UI
3. ⏳ Add Domains Management UI
4. ⏳ Add Orders Management UI
5. ⏳ Add Settings page
6. ⏳ Implement CSRF protection across all forms
7. ⏳ Add audit logging for admin actions
