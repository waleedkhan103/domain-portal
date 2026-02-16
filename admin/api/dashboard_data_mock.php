<?php
/**
 * Mock Dashboard Data API
 * Used when database is unavailable for testing/development
 */

// Only set headers if not already sent
if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

// Compute dates first
$dates = [
  date('Y-m-d H:i:s', strtotime('-2 days')),
  date('Y-m-d H:i:s', strtotime('-5 days')),
  date('Y-m-d H:i:s', strtotime('-8 days')),
  date('Y-m-d H:i:s', strtotime('-1 day')),
  date('Y-m-d H:i:s', strtotime('-3 days')),
  date('Y-m-d H:i:s', strtotime('-10 days')),
  date('Y-m-d H:i:s', strtotime('-6 days')),
  date('Y-m-d H:i:s', strtotime('-4 days')),
  date('Y-m-d H:i:s', strtotime('-7 days')),
  date('Y-m-d H:i:s', strtotime('-9 days'))
];

$orderDates = [
  date('Y-m-d H:i:s', strtotime('-2 hours')),
  date('Y-m-d H:i:s', strtotime('-4 hours')),
  date('Y-m-d H:i:s', strtotime('-6 hours')),
  date('Y-m-d H:i:s', strtotime('-8 hours')),
  date('Y-m-d H:i:s', strtotime('-1 day')),
  date('Y-m-d H:i:s', strtotime('-1 day -2 hours'))
];

$mockData = [
  'success' => true,
  'data' => [
    'total_domains' => 1247,
    'active_domains' => 1156,
    'expiring_30' => 34,
    'revenue_month' => 15234.50,
    'revenue_last_month' => 12890.75,
    'pending_orders' => 7,
    'recent_registrations' => 18,
    'failed_payments' => 3,
    'domains_by_status' => [
      'active' => 1156,
      'pending' => 54,
      'expired' => 23,
      'suspended' => 14
    ],
    'recent_domains' => [
      ['id' => 1, 'domain_name' => 'example-tech.com', 'user_id' => 5, 'status' => 'active', 'registered_at' => $dates[0], 'email' => 'user5@example.com'],
      ['id' => 2, 'domain_name' => 'startup-hub.io', 'user_id' => 8, 'status' => 'active', 'registered_at' => $dates[1], 'email' => 'user8@example.com'],
      ['id' => 3, 'domain_name' => 'business-solutions.net', 'user_id' => 12, 'status' => 'active', 'registered_at' => $dates[2], 'email' => 'user12@example.com'],
      ['id' => 4, 'domain_name' => 'creative-agency.co', 'user_id' => 3, 'status' => 'pending', 'registered_at' => $dates[3], 'email' => 'user3@example.com'],
      ['id' => 5, 'domain_name' => 'online-shop.com', 'user_id' => 15, 'status' => 'active', 'registered_at' => $dates[4], 'email' => 'user15@example.com'],
      ['id' => 6, 'domain_name' => 'development-team.org', 'user_id' => 22, 'status' => 'active', 'registered_at' => $dates[5], 'email' => 'user22@example.com'],
      ['id' => 7, 'domain_name' => 'marketing-pro.xyz', 'user_id' => 11, 'status' => 'active', 'registered_at' => $dates[6], 'email' => 'user11@example.com'],
      ['id' => 8, 'domain_name' => 'cloud-services.app', 'user_id' => 7, 'status' => 'active', 'registered_at' => $dates[7], 'email' => 'user7@example.com'],
      ['id' => 9, 'domain_name' => 'data-analytics.io', 'user_id' => 19, 'status' => 'active', 'registered_at' => $dates[8], 'email' => 'user19@example.com'],
      ['id' => 10, 'domain_name' => 'mobile-app-dev.net', 'user_id' => 25, 'status' => 'active', 'registered_at' => $dates[9], 'email' => 'user25@example.com']
    ],
    'recent_orders' => [
      ['id' => 501, 'order_number' => 'ORD-20260216-001', 'user_id' => 5, 'total' => 89.99, 'status' => 'completed', 'created_at' => $orderDates[0], 'email' => 'user5@example.com'],
      ['id' => 502, 'order_number' => 'ORD-20260216-002', 'user_id' => 8, 'total' => 149.50, 'status' => 'pending', 'created_at' => $orderDates[1], 'email' => 'user8@example.com'],
      ['id' => 503, 'order_number' => 'ORD-20260216-003', 'user_id' => 3, 'total' => 299.97, 'status' => 'completed', 'created_at' => $orderDates[2], 'email' => 'user3@example.com'],
      ['id' => 504, 'order_number' => 'ORD-20260216-004', 'user_id' => 12, 'total' => 59.99, 'status' => 'completed', 'created_at' => $orderDates[3], 'email' => 'user12@example.com'],
      ['id' => 505, 'order_number' => 'ORD-20260215-001', 'user_id' => 7, 'total' => 179.98, 'status' => 'failed', 'created_at' => $orderDates[4], 'email' => 'user7@example.com'],
      ['id' => 506, 'order_number' => 'ORD-20260215-002', 'user_id' => 15, 'total' => 119.99, 'status' => 'completed', 'created_at' => $orderDates[5], 'email' => 'user15@example.com']
    ],
    'alerts' => [
      'expiring_7' => 5,
      'expiring_14' => 12,
      'pending_orders' => 7
    ]
  ]
];

echo json_encode($mockData);
