<?php
/**
 * config/stripe.php
 * Loads Stripe API keys from the admin settings table and provides
 * a curl-based helper to call the Stripe REST API (no SDK required).
 */

// Load keys from settings table (graceful fallback to empty strings)
$_stripePublic = '';
$_stripeSecret = '';

if (isset($conn) && $conn) {
    $chk = mysqli_query($conn, "SHOW TABLES LIKE 'settings'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $keyRes = mysqli_query($conn, "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('stripe_public_key','stripe_secret_key','stripe_enabled')");
        if ($keyRes) {
            while ($row = mysqli_fetch_assoc($keyRes)) {
                if ($row['setting_key'] === 'stripe_public_key') $_stripePublic = trim($row['setting_value'] ?? '');
                if ($row['setting_key'] === 'stripe_secret_key') $_stripeSecret = trim($row['setting_value'] ?? '');
            }
        }
    }
}

if (!defined('STRIPE_PUBLIC_KEY')) define('STRIPE_PUBLIC_KEY', $_stripePublic);
if (!defined('STRIPE_SECRET_KEY')) define('STRIPE_SECRET_KEY', $_stripeSecret);
if (!defined('STRIPE_ENABLED'))    define('STRIPE_ENABLED', !empty($_stripePublic) && !empty($_stripeSecret));

unset($_stripePublic, $_stripeSecret);

/**
 * Make a request to the Stripe API using curl.
 *
 * @param string $method   HTTP method: 'GET' or 'POST'
 * @param string $endpoint Stripe endpoint, e.g. 'payment_intents'
 * @param array  $data     POST body fields (for GET, appended as query string)
 * @return array Decoded JSON response from Stripe
 */
function stripeRequest(string $method, string $endpoint, array $data = []): array
{
    $secretKey = STRIPE_SECRET_KEY;
    if (empty($secretKey)) {
        return ['error' => ['message' => 'Stripe secret key not configured']];
    }

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');

    if ($method === 'GET' && !empty($data)) {
        $url .= '?' . http_build_query($data);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secretKey . ':',
        CURLOPT_HTTPHEADER     => ['Stripe-Version: 2023-10-16'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    curl_close($ch);

    if ($errno || $response === false) {
        return ['error' => ['message' => 'curl error ' . $errno]];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : ['error' => ['message' => 'Invalid Stripe response']];
}
