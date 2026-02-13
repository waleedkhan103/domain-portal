# New Features Added - Domain Portal

This document lists all new features added to make the portal more competitive with GoDaddy, Domains.com, and similar registrars.

## 1. Password Reset
- **Forgot Password** page (`pages/forgot_password.php`) - Request reset link via email
- **Reset Password** page (`pages/reset_password.php`) - Set new password with token
- API: `api/password_reset.php` (actions: request, reset)
- Link added to Login page
- Database: `password_reset_tokens` table (auto-created)

## 2. Domain Suggestions When Taken
- When a searched domain is unavailable, alternative suggestions are shown
- Suggestions include: same name with different TLDs (.net, .org, .io, .co)
- Prefix variations: get*, my*, the*, try* + domain
- Each suggestion shows availability and price with "Add to Cart" button

## 3. Promo/Coupon Codes
- Apply promo codes at checkout for discounts
- API: `api/promo_validate.php` - Validates and returns discount amount
- API: `api/promo_codes` table with sample codes: **SAVE10** (10% off, min $20), **FIRST5** ($5 off, min $10)
- Checkout page includes promo input and Apply button

## 4. First-Year vs Renewal Pricing
- Domain search results show both registration and renewal prices
- Mock API returns `renewal_price` (typically 10% higher)

## 5. Billing History
- New page: `pages/billing_history.php`
- Lists all transactions (orders, renewals)
- Linked from user dropdown menu
- Database: `transactions` table (auto-created)

## 6. Contact Management (Registrant, Admin, Tech, Billing)
- New page: `pages/contacts.php`
- Displays four contact types: Registrant, Admin, Technical, Billing
- Links to Profile for editing
- Database: `contacts.contact_type` column (run ALTER if needed)

## 7. WHOIS Lookup (Public)
- New page: `pages/whois.php` - Public, no login required
- API: `api/whois_lookup.php` - Returns mock WHOIS data
- Added to main navigation
- In production, replace with real WHOIS server queries

## 8. Domain Watchlist & Expiry Alerts
- New page: `pages/watchlist.php` - Add domains to watch for expiry
- API: `api/watchlist.php` (actions: add, remove, list)
- Cron script: `cron/expiry_alerts.php` - Send email reminders
- Run daily: `php cron/expiry_alerts.php` or add to crontab
- Linked from nav (Dashboard > Watchlist)

## 9. Full DNS Record Management
- New tab in Domain Details: **DNS Records**
- Add/delete A, AAAA, CNAME, MX, TXT records
- API: `api/dns_records.php` (actions: list, add, delete)
- Database: `dns_records` table (auto-created)
- Modal form for adding records with Type, Host, Value, TTL, Priority (for MX)

## 10. Two-Factor Authentication (2FA)
- Placeholder added in Profile > Security section
- Full TOTP implementation requires additional library (e.g. RobThree/PhpTotp)

---

## Database Setup

Run the SQL in `database/schema_additions.sql` to create new tables. Many features auto-create tables on first use.

For contacts with types, run:
```sql
ALTER TABLE contacts ADD COLUMN contact_type VARCHAR(20) DEFAULT 'registrant';
```

## Bootstrap Styling

All new and updated pages use Bootstrap 5 classes:
- Cards, forms, buttons, badges, nav-tabs
- Responsive grid (row, col-*)
- Bootstrap Icons (bi-*)
