<?php
/**
 * KlinFlow Contact Form Handler (Hostinger + PHPMailer SMTP)
 * Path: apps/Public/PHP Email/contact.php
 * Expects JSON: { fullName, email, phone, module, subject, message, meta? }
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

/** Adjust this path if your vendor/ lives elsewhere */
$vendorPath = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($vendorPath)) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Mailer not installed (vendor/autoload.php missing)']);
  exit;
}
require $vendorPath;

/* ---- Read and validate JSON ---- */
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
  exit;
}

$fullName = trim($data['fullName'] ?? '');
$email    = trim($data['email'] ?? '');
$phone    = trim($data['phone'] ?? '');
$module   = trim($data['module'] ?? '');
$subject  = trim($data['subject'] ?? '');
$message  = trim($data['message'] ?? '');
$meta     = $data['meta'] ?? [];

if (!$fullName || !$email || !$subject || !$message) {
  http_response_code(422);
  echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
  exit;
}

/* header injection / malformed email */
if (preg_match('/[\r\n]/', $email) || strpos($email, ',') !== false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid email']);
  exit;
}

/* ---- SMTP settings (Hostinger) ----
 * Create an email account in hPanel (e.g., no-reply@yourdomain.com)
 * Then use those credentials here.
 */
$SMTP_HOST   = 'smtp.hostinger.com';
$SMTP_PORT   = 587;           // or 465 (SMTPS)
$SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS; // use ENCRYPTION_SMTPS for 465
$SMTP_USER   = 'no-reply@yourdomain.com';      // TODO: change
$SMTP_PASS   = 'YOUR_SMTP_PASSWORD';           // TODO: change
$FROM_EMAIL  = $SMTP_USER;                     // must be the same domain mailbox
$FROM_NAME   = 'KlinFlow Support';

$ADMIN_TO    = 'askklinflow@gmail.com';
$SITE_URL    = 'https://www.klinflow.com';     // adjust if needed

/* ---- Build message bodies ---- */
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$tz = $meta['tz'] ?? '';
$url = $meta['href'] ?? '';

$adminSubject = "KlinFlow Contact: {$subject}";
$adminBody = "New contact form message from KlinFlow site:\n\n"
  . "Name: {$fullName}\n"
  . "Email: {$email}\n"
  . ($phone   ? "Phone: {$phone}\n" : '')
  . ($module  ? "Module Interest: {$module}\n" : '')
  . "Subject: {$subject}\n\n"
  . "Message:\n{$message}\n\n"
  . "-----\nMeta Info:\n"
  . "IP: {$ip}\n"
  . ($tz  ? "Timezone: {$tz}\n" : '')
  . ($url ? "Page: {$url}\n" : '');

$userSubject = "We’ve received your message — KlinFlow Team";
$userBody = "Hi {$fullName},\n\n"
  . "Thanks for reaching out to KlinFlow!\n\n"
  . "We’ve received your message titled \"{$subject}\" and our support team will review it shortly.\n"
  . "You’ll hear back from us within one business day (Bangladesh time).\n\n"
  . "Modules you can explore:\n"
  . "- Retail POS — billing, inventory, and VAT ready\n"
  . "- HotelFlow — front-desk, housekeeping, and POS\n"
  . "- Bhata — brickfield management\n"
  . "- School — academic and billing suite\n"
  . "- MedFlow — clinic and pharmacy\n"
  . "- DMS — document and approval management\n\n"
  . "Best regards,\n"
  . "KlinFlow Support Team\n"
  . "📧 askklinflow@gmail.com\n"
  . "🌐 {$SITE_URL}\n\n"
  . "— This is an automated acknowledgment. Please don’t reply to this email.";

/* ---- Helper: new configured PHPMailer ---- */
function newMailer($SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USER, $SMTP_PASS, $FROM_EMAIL, $FROM_NAME) {
  $m = new PHPMailer(true);
  $m->isSMTP();
  $m->Host       = $SMTP_HOST;
  $m->SMTPAuth   = true;
  $m->Username   = $SMTP_USER;
  $m->Password   = $SMTP_PASS;
  $m->Port       = $SMTP_PORT;
  $m->SMTPSecure = $SMTP_SECURE;
  $m->CharSet    = 'UTF-8';
  $m->isHTML(false); // plain text
  $m->setFrom($FROM_EMAIL, $FROM_NAME);
  // Optional: $m->SMTPDebug = 2;
  return $m;
}

try {
  /* ---- Send admin notification ---- */
  $adminMailer = newMailer($SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USER, $SMTP_PASS, $FROM_EMAIL, $FROM_NAME);
  $adminMailer->addAddress($ADMIN_TO, 'KlinFlow Admin');
  $adminMailer->addReplyTo($email, $fullName); // replies go to requester
  $adminMailer->Subject = $adminSubject;
  $adminMailer->Body    = $adminBody;
  $adminMailer->send();

  /* ---- Send confirmation to requester ---- */
  $userMailer = newMailer($SMTP_HOST, $SMTP_PORT, $SMTP_SECURE, $SMTP_USER, $SMTP_PASS, $FROM_EMAIL, $FROM_NAME);
  $userMailer->addAddress($email, $fullName);
  $userMailer->addReplyTo('askklinflow@gmail.com', 'KlinFlow Support');
  $userMailer->Subject = $userSubject;
  $userMailer->Body    = $userBody;
  $userSent = $userMailer->send();

  echo json_encode(['ok' => true, 'userConfirmation' => $userSent]);
} catch (Exception $e) {
  // Don’t leak SMTP details to the client
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Could not send email at the moment.']);
}