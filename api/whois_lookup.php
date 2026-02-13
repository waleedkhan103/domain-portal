<?php
/**
 * Public WHOIS Lookup API
 * Returns mock WHOIS data for development. In production, use real WHOIS server.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$domain = strtolower(trim($_GET['domain'] ?? $_POST['domain'] ?? ''));

if (empty($domain)) {
  echo json_encode(['success' => false, 'message' => 'Domain is required', 'data' => null]);
  exit;
}

// Basic domain validation
if (!preg_match('/^[a-z0-9][a-z0-9\-]*(\.[a-z0-9][a-z0-9\-]*)+$/i', $domain)) {
  echo json_encode(['success' => false, 'message' => 'Invalid domain format', 'data' => null]);
  exit;
}

// Mock WHOIS response - in production use fsockopen to query whois servers
$mockWhois = "Domain Name: " . strtoupper($domain) . "
Registry Domain ID: MOCK" . strtoupper(str_replace('.', '', $domain)) . "
Registrar WHOIS Server: whois.example.com
Registrar URL: https://example.com
Updated Date: " . date('Y-m-d') . "T00:00:00Z
Creation Date: " . date('Y-m-d', strtotime('-1 year')) . "T00:00:00Z
Registry Expiry Date: " . date('Y-m-d', strtotime('+1 year')) . "T00:00:00Z
Registrar: DomainPortal (Mock)
Registrar IANA ID: 9999
Domain Status: clientTransferProhibited
Domain Status: clientUpdateProhibited
Name Server: ns1.example.com
Name Server: ns2.example.com
DNSSEC: unsigned
";

echo json_encode([
  'success' => true,
  'message' => 'WHOIS data retrieved',
  'data' => [
    'domain' => $domain,
    'whois_text' => $mockWhois,
    'registrar' => 'DomainPortal (Mock)',
    'created' => date('Y-m-d', strtotime('-1 year')),
    'expires' => date('Y-m-d', strtotime('+1 year')),
    'nameservers' => ['ns1.example.com', 'ns2.example.com']
  ]
]);
