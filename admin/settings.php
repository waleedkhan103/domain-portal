<?php
$pageTitle = 'Admin - Settings';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/header.php';

global $conn;
if (!$conn) {
    echo '<div class="alert alert-warning">Database unavailable. Please check configuration.</div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div class="container-fluid py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 mb-0">Settings</h1>
        <button id="save-settings-btn" class="btn btn-primary">
            <span class="spinner-border spinner-border-sm d-none" id="save-spinner" role="status"
                aria-hidden="true"></span>
            Save Changes
        </button>
    </div>

    <div class="alert alert-info d-none" id="settings-alert" role="alert"></div>

    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-4" id="settings-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general"
                type="button" role="tab" aria-controls="general" aria-selected="true">General</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button"
                role="tab" aria-controls="email" aria-selected="false">Email / SMTP</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="registrar-tab" data-bs-toggle="tab" data-bs-target="#registrar" type="button"
                role="tab" aria-controls="registrar" aria-selected="false">Registrar API</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment" type="button"
                role="tab" aria-controls="payment" aria-selected="false">Payment Gateways</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="domain-tab" data-bs-toggle="tab" data-bs-target="#domain" type="button"
                role="tab" aria-controls="domain" aria-selected="false">Domain Settings</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications"
                type="button" role="tab" aria-controls="notifications" aria-selected="false">Notifications</button>
        </li>
    </ul>

    <!-- Tab panes -->
    <div class="tab-content" id="settings-tab-content">

        <!-- General Tab -->
        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">General Settings</h5>
                    <form id="general-settings-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" name="site_name" data-setting="site_name">
                                <div class="form-text text-muted">Displayed in header and emails</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Site Email</label>
                                <input type="email" class="form-control" name="site_email" data-setting="site_email">
                                <div class="form-text text-muted">Main contact email address</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Site URL</label>
                                <input type="url" class="form-control" name="site_url" data-setting="site_url">
                                <div class="form-text text-muted">Base URL (e.g., https://example.com)</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Timezone</label>
                                <select class="form-select" name="timezone" data-setting="timezone">
                                    <option value="UTC">UTC</option>
                                    <option value="America/New_York">Eastern Time</option>
                                    <option value="America/Chicago">Central Time</option>
                                    <option value="America/Denver">Mountain Time</option>
                                    <option value="America/Los_Angeles">Pacific Time</option>
                                    <option value="Europe/London">London</option>
                                    <option value="Europe/Paris">Paris</option>
                                    <option value="Asia/Dubai">Dubai</option>
                                    <option value="Asia/Kolkata">India</option>
                                    <option value="Asia/Singapore">Singapore</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Date Format</label>
                                <select class="form-select" name="date_format" data-setting="date_format">
                                    <option value="Y-m-d">YYYY-MM-DD</option>
                                    <option value="m/d/Y">MM/DD/YYYY</option>
                                    <option value="d/m/Y">DD/MM/YYYY</option>
                                    <option value="d-m-Y">DD-MM-YYYY</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Records Per Page</label>
                                <select class="form-select" name="records_per_page" data-setting="records_per_page">
                                    <option value="10">10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Email / SMTP Tab -->
        <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Email & SMTP Configuration</h5>
                    <form id="email-settings-form">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="smtp_enabled"
                                data-setting="smtp_enabled" value="1">
                            <label class="form-check-label">Enable SMTP (use external mail server)</label>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" class="form-control" name="smtp_host" data-setting="smtp_host"
                                    placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">SMTP Port</label>
                                <input type="number" class="form-control" name="smtp_port" data-setting="smtp_port"
                                    placeholder="587">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Encryption</label>
                                <select class="form-select" name="smtp_encryption" data-setting="smtp_encryption">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">None</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mt-2">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Username</label>
                                <input type="text" class="form-control" name="smtp_username"
                                    data-setting="smtp_username">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Password</label>
                                <input type="password" class="form-control" name="smtp_password"
                                    data-setting="smtp_password">
                                <div class="form-text text-muted">Leave blank to keep current password</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6>Email Headers</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">From Email</label>
                                <input type="email" class="form-control" name="from_email" data-setting="from_email"
                                    placeholder="noreply@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">From Name</label>
                                <input type="text" class="form-control" name="from_name" data-setting="from_name"
                                    placeholder="Domain Portal">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Registrar API Tab -->
        <div class="tab-pane fade" id="registrar" role="tabpanel" aria-labelledby="registrar-tab">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Domain Registrar API</h5>
                    <form id="registrar-settings-form">
                        <div class="mb-3">
                            <label class="form-label">Registrar Provider</label>
                            <select class="form-select" name="registrar_provider" data-setting="registrar_provider">
                                <option value="none">None (Manual)</option>
                                <option value="namecheap">Namecheap</option>
                                <option value="godaddy">GoDaddy</option>
                                <option value="enom">Enom</option>
                                <option value="resellerclub">ResellerClub</option>
                            </select>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">API Key</label>
                                <input type="text" class="form-control" name="registrar_api_key"
                                    data-setting="registrar_api_key">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">API Secret</label>
                                <input type="password" class="form-control" name="registrar_api_secret"
                                    data-setting="registrar_api_secret">
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" name="registrar_username"
                                    data-setting="registrar_username">
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="registrar_test_mode"
                                        data-setting="registrar_test_mode" value="1">
                                    <label class="form-check-label">Test / Sandbox Mode</label>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <strong>Note:</strong> Configure your registrar API credentials to enable automatic domain
                            registration, renewal, and DNS management features.
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Payment Gateways Tab -->
        <div class="tab-pane fade" id="payment" role="tabpanel" aria-labelledby="payment-tab">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">General Payment Settings</h5>
                    <form id="payment-general-form">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Currency</label>
                                <select class="form-select" name="payment_currency" data-setting="payment_currency">
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                    <option value="AED">AED - UAE Dirham</option>
                                    <option value="INR">INR - Indian Rupee</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tax Rate (%)</label>
                                <input type="number" class="form-control" name="payment_tax_rate"
                                    data-setting="payment_tax_rate" min="0" max="100" step="0.01">
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Stripe</h5>
                    <form id="stripe-settings-form">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="stripe_enabled"
                                data-setting="stripe_enabled" value="1">
                            <label class="form-check-label">Enable Stripe Payments</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Publishable Key</label>
                                <input type="text" class="form-control" name="stripe_public_key"
                                    data-setting="stripe_public_key" placeholder="pk_live_... or pk_test_...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Secret Key</label>
                                <input type="password" class="form-control" name="stripe_secret_key"
                                    data-setting="stripe_secret_key" placeholder="sk_live_... or sk_test_...">
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <label class="form-label">Webhook Secret</label>
                                <input type="password" class="form-control" name="stripe_webhook_secret"
                                    data-setting="stripe_webhook_secret" placeholder="whsec_...">
                                <div class="form-text">From Stripe Dashboard &rarr; Webhooks. Endpoint URL: <code>/api/stripe_webhook.php</code></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">PayPal</h5>
                    <form id="paypal-settings-form">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="paypal_enabled"
                                data-setting="paypal_enabled" value="1">
                            <label class="form-check-label">Enable PayPal Payments</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Client ID</label>
                                <input type="text" class="form-control" name="paypal_client_id"
                                    data-setting="paypal_client_id">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Secret</label>
                                <input type="password" class="form-control" name="paypal_secret"
                                    data-setting="paypal_secret">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Domain Settings Tab -->
        <div class="tab-pane fade" id="domain" role="tabpanel" aria-labelledby="domain-tab">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Domain Defaults & Management</h5>
                    <form id="domain-settings-form">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="domain_auto_renew_default"
                                data-setting="domain_auto_renew_default" value="1">
                            <label class="form-check-label">Auto-Renew by Default</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="domain_privacy_default"
                                data-setting="domain_privacy_default" value="1">
                            <label class="form-check-label">WHOIS Privacy Protection by Default</label>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Expiry Notice Days</label>
                                <input type="text" class="form-control" name="domain_expiry_notice_days"
                                    data-setting="domain_expiry_notice_days" placeholder="30,14,7,1">
                                <div class="form-text text-muted">Comma-separated days before expiry to send reminders
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Grace Period (Days)</label>
                                <input type="number" class="form-control" name="domain_grace_period_days"
                                    data-setting="domain_grace_period_days" min="0">
                                <div class="form-text text-muted">Days after expiry before domain is suspended</div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notifications Tab -->
        <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Admin Notifications</h5>
                    <form id="notifications-settings-form">
                        <div class="mb-3">
                            <label class="form-label">Admin Notification Email</label>
                            <input type="email" class="form-control" name="admin_notification_email"
                                data-setting="admin_notification_email">
                            <div class="form-text text-muted">Email address for admin alerts (leave blank to use site
                                email)</div>
                        </div>

                        <hr class="my-4">

                        <h6>Enable Notifications For:</h6>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="notify_new_order"
                                data-setting="notify_new_order" value="1">
                            <label class="form-check-label">New Orders</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="notify_domain_expiry"
                                data-setting="notify_domain_expiry" value="1">
                            <label class="form-check-label">Domain Expiry Warnings</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="notify_payment_failed"
                                data-setting="notify_payment_failed" value="1">
                            <label class="form-check-label">Failed Payments</label>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div><!-- /.tab-content -->

</div><!-- /.container-fluid -->

<input type="hidden" id="csrf-token" value="<?php echo generateCSRFToken(); ?>">

<script src="<?php echo BASE_PATH; ?>/admin/assets/js/settings.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>