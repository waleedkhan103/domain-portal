<?php
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  jsonResponse(false, 'Invalid request method');
}

if (!isLoggedIn())
  jsonResponse(false, 'Not authenticated');

$user = getCurrentUser();

$first_name = sanitizeInput($_POST['first_name'] ?? '');
$last_name = sanitizeInput($_POST['last_name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$company = sanitizeInput($_POST['company'] ?? '');
$address = sanitizeInput($_POST['address'] ?? '');
$city = sanitizeInput($_POST['city'] ?? '');
$state = sanitizeInput($_POST['state'] ?? '');
$country = sanitizeInput($_POST['country'] ?? '');
$postal_code = sanitizeInput($_POST['postal_code'] ?? '');
$password = $_POST['password'] ?? '';

$updates = [];
if ($first_name !== '')
  $updates[] = "first_name = '" . mysqli_real_escape_string($conn, $first_name) . "'";
if ($last_name !== '')
  $updates[] = "last_name = '" . mysqli_real_escape_string($conn, $last_name) . "'";
if ($phone !== '')
  $updates[] = "phone = '" . mysqli_real_escape_string($conn, $phone) . "'";
if ($company !== '')
  $updates[] = "company = '" . mysqli_real_escape_string($conn, $company) . "'";
if ($address !== '')
  $updates[] = "address = '" . mysqli_real_escape_string($conn, $address) . "'";
if ($city !== '')
  $updates[] = "city = '" . mysqli_real_escape_string($conn, $city) . "'";
if ($state !== '')
  $updates[] = "state = '" . mysqli_real_escape_string($conn, $state) . "'";
if ($country !== '')
  $updates[] = "country = '" . mysqli_real_escape_string($conn, $country) . "'";
if ($postal_code !== '')
  $updates[] = "postal_code = '" . mysqli_real_escape_string($conn, $postal_code) . "'";

if (!empty($updates)) {
  $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = " . (int) $user['id'];
  if (!mysqli_query($conn, $sql)) {
    jsonResponse(false, 'Failed to update profile');
  }
}

// Change password if provided
if ($password !== '') {
  $hash = password_hash($password, PASSWORD_DEFAULT);
  $sql = "UPDATE users SET password = '" . mysqli_real_escape_string($conn, $hash) . "' WHERE id = " . (int) $user['id'];
  if (!mysqli_query($conn, $sql)) {
    jsonResponse(false, 'Failed to update password');
  }
}

jsonResponse(true, 'Profile updated');

?>