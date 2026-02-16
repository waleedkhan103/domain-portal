<?php
session_start();
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_id'] = 1;
$_SERVER['REQUEST_URI'] = '/admin/api/dashboard_data.php';
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';

// Include dashboard data API
require_once 'admin/api/dashboard_data.php';
