<?php
// Admin configuration
// If you prefer DB-based admin users, create an `admins` table or add `is_admin` flag on `users`.
define('ADMIN_USERNAME', 'admin');
// Default password is 'admin' — change immediately in production or set up DB admins.
define('ADMIN_PASSWORD', 'admin');

// Toggle strict DB-only admin auth: true = only DB admins allowed
define('ADMIN_REQUIRE_DB', false);
