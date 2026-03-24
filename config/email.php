<?php
/**
 * Email Configuration
 *
 * SETUP INSTRUCTIONS:
 * 1. Install PHPMailer: composer require phpmailer/phpmailer
 * 2. Configure SMTP settings below
 * 3. Test with: php test_email.php
 */

// SMTP Configuration
define('SMTP_HOST', 'smtp.gmail.com');           // Gmail, SendGrid, AWS SES, etc.
define('SMTP_PORT', 587);                        // 587 for TLS, 465 for SSL
define('SMTP_USERNAME', 'your-email@gmail.com'); // SMTP username
define('SMTP_PASSWORD', 'your-app-password');    // App password (not Gmail password)
define('SMTP_ENCRYPTION', 'tls');                // 'tls' or 'ssl'

// Sender Info
define('MAIL_FROM_EMAIL', 'noreply@domainportal.com');
define('MAIL_FROM_NAME', 'Domain Portal');

// Email Settings
define('MAIL_ENABLED', false); // Set to true when SMTP is configured

/**
 * Send Email using PHPMailer
 *
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $body HTML body
 * @param string $altBody Plain text alternative
 * @return bool Success status
 */
function sendEmail($to, $subject, $body, $altBody = '')
{
  if (!MAIL_ENABLED) {
    error_log("[Email] Skipped (MAIL_ENABLED=false): To=$to, Subject=$subject");
    return true; // Return true to not block operations
  }

  // Check if PHPMailer is available
  if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    error_log("[Email] PHPMailer not installed. Run: composer require phpmailer/phpmailer");
    return false;
  }

  try {
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    // SMTP Configuration
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USERNAME;
    $mail->Password   = SMTP_PASSWORD;
    $mail->SMTPSecure = SMTP_ENCRYPTION;
    $mail->Port       = SMTP_PORT;

    // Sender & Recipient
    $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
    $mail->addAddress($to);

    // Content
    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    if ($altBody) {
      $mail->AltBody = $altBody;
    }

    $mail->send();
    error_log("[Email] Sent successfully: To=$to, Subject=$subject");
    return true;
  } catch (Exception $e) {
    error_log("[Email] Failed: {$mail->ErrorInfo}");
    return false;
  }
}

/**
 * Send templated email for common scenarios
 */
function sendTemplateEmail($type, $to, $data = [])
{
  $templates = [
    'order_confirmation' => [
      'subject' => 'Order Confirmation - Order #{order_number}',
      'body' => '
        <h2>Thank You for Your Order!</h2>
        <p>Hi {first_name},</p>
        <p>Your order <strong>#{order_number}</strong> has been placed successfully.</p>
        <p><strong>Total:</strong> ${total}</p>
        <p><strong>Domains:</strong></p>
        <ul>{domains}</ul>
        <p>You can view your invoice here: <a href="{invoice_url}">{invoice_url}</a></p>
        <p>Thank you for choosing Domain Portal!</p>
      '
    ],
    'renewal_success' => [
      'subject' => 'Domain Renewed - {domain_name}',
      'body' => '
        <h2>Domain Renewed Successfully</h2>
        <p>Hi {first_name},</p>
        <p>Your domain <strong>{domain_name}</strong> has been renewed for {period} year(s).</p>
        <p><strong>New Expiry Date:</strong> {expiry_date}</p>
        <p>Thank you!</p>
      '
    ],
    'auto_renewal_success' => [
      'subject' => 'Auto-Renewed - {domain_name}',
      'body' => '
        <h2>Domain Auto-Renewed</h2>
        <p>Hi {first_name},</p>
        <p>Your domain <strong>{domain_name}</strong> was automatically renewed.</p>
        <p><strong>New Expiry Date:</strong> {expiry_date}</p>
        <p><strong>Amount Charged:</strong> ${amount}</p>
      '
    ],
    'expiry_reminder' => [
      'subject' => 'Domain Expiring Soon - {domain_name}',
      'body' => '
        <h2>⚠️ Your Domain is Expiring Soon</h2>
        <p>Hi {first_name},</p>
        <p>Your domain <strong>{domain_name}</strong> will expire in <strong>{days_left} days</strong> on {expiry_date}.</p>
        <p>Renew now to avoid losing your domain!</p>
        <p><a href="{renew_url}" style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Renew Now</a></p>
      '
    ],
    'password_reset' => [
      'subject' => 'Password Reset Request',
      'body' => '
        <h2>Password Reset</h2>
        <p>Hi {first_name},</p>
        <p>Click the link below to reset your password:</p>
        <p><a href="{reset_url}">{reset_url}</a></p>
        <p>This link expires in 1 hour.</p>
        <p>If you didn\'t request this, ignore this email.</p>
      '
    ]
  ];

  if (!isset($templates[$type])) {
    error_log("[Email] Unknown template type: $type");
    return false;
  }

  $template = $templates[$type];
  $subject = $template['subject'];
  $body = $template['body'];

  // Replace placeholders
  foreach ($data as $key => $value) {
    $subject = str_replace('{' . $key . '}', $value, $subject);
    $body = str_replace('{' . $key . '}', $value, $body);
  }

  return sendEmail($to, $subject, $body);
}
