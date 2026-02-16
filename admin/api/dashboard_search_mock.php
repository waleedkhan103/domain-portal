<?php
/**
 * Mock Dashboard Search API
 * Used when database is unavailable for testing/development
 */

// Only set headers if not already sent
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

$q = trim($_GET['q'] ?? '');
if ($q === '') {
  echo json_encode(['success' => true, 'data' => []]);
  exit;
}

$results = [];

// Mock domains
$mockDomains = [
  'example-tech.com',
  'startup-hub.io',
  'business-solutions.net',
  'creative-agency.co',
  'online-shop.com',
  'development-team.org',
  'marketing-pro.xyz',
  'cloud-services.app',
  'data-analytics.io',
  'mobile-app-dev.net'
];

// Mock orders
$mockOrders = ['ORD-20260216-001', 'ORD-20260216-002', 'ORD-20260216-003', 'ORD-20260216-004'];

// Search domains
foreach ($mockDomains as $idx => $domain) {
  if (stripos($domain, $q) !== false) {
    $results[] = [
      'type' => 'domain',
      'id' => $idx + 1,
      'label' => $domain
    ];
    if (count($results) >= 8)
      break;
  }
}

// Search orders (numeric or by order number)
if (is_numeric($q)) {
  $oid = (int) $q;
  $results[] = [
    'type' => 'order',
    'id' => 500 + $oid,
    'label' => 'ORD-' . str_pad($oid, 8, '0', STR_PAD_LEFT)
  ];
} else {
  foreach ($mockOrders as $idx => $order) {
    if (stripos($order, $q) !== false) {
      $results[] = [
        'type' => 'order',
        'id' => 501 + $idx,
        'label' => $order
      ];
    }
  }
}

echo json_encode(['success' => true, 'data' => $results]);
exit;
