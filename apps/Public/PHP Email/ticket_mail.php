<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendTicketEmails(array $t)
{
    // Locate Composer autoload
    $vendorPath = __DIR__ . '/../../../vendor/autoload.php';
    if (!file_exists($vendorPath)) throw new \Exception('Mailer not installed');
    require_once $vendorPath;

    // SMTP (Hostinger)
    $SMTP_HOST   = 'smtp.hostinger.com';
    $SMTP_PORT   = 587;
    $SMTP_SECURE = PHPMailer::ENCRYPTION_STARTTLS;
    $SMTP_USER   = 'no-reply@yourdomain.com';   // TODO
    $SMTP_PASS   = 'YOUR_SMTP_PASSWORD';        // TODO
    $FROM_EMAIL  = $SMTP_USER;
    $FROM_NAME   = 'KlinFlow Support';
    $ADMIN_TO    = 'askklinflow@gmail.com';
    $SITE_URL    = 'https://www.klinflow.com';

    $adminSubject = "New Ticket {$t['id']} — {$t['subject']}";
    $adminBody = "A new support ticket has been opened.\n\n"
      . "Ticket ID: {$t['id']}\n"
      . "Name: {$t['name']}\n"
      . "Email: {$t['email']}\n"
      . ($t['phone'] ? "Phone: {$t['phone']}\n" : '')
      . ($t['module']? "Module: {$t['module']}\n" : '')
      . "Subject: {$t['subject']}\n"
      . "Opened: {$t['created_at']}\n\n"
      . "Message:\n{$t['body']}\n";

    $userSubject = "Your Ticket {$t['id']} is Open — KlinFlow";
    $userBody = "Hi {$t['name']},\n\n"
      . "Thanks for opening a support ticket with KlinFlow.\n"
      . "We’ve registered your request and will get back within one business day (Bangladesh time).\n\n"
      . "Ticket details:\n"
      . "- Ticket ID: {$t['id']}\n"
      . "- Subject: {$t['subject']}\n"
      . ($t['module']? "- Module: {$t['module']}\n" : '')
      . "- Opened: {$t['created_at']}\n\n"
      . "You can reply to this email with any additional details or screenshots.\n\n"
      . "Best regards,\nKlinFlow Support Team\n📧 askklinflow@gmail.com\n🌐 {$SITE_URL}\n"
      . "— Automated acknowledgment. Please keep the Ticket ID in your subject for faster help.";

    $newMailer = function() use ($SMTP_HOST,$SMTP_PORT,$SMTP_SECURE,$SMTP_USER,$SMTP_PASS,$FROM_EMAIL,$FROM_NAME){
        $m = new PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $SMTP_HOST;
        $m->SMTPAuth   = true;
        $m->Username   = $SMTP_USER;
        $m->Password   = $SMTP_PASS;
        $m->Port       = $SMTP_PORT;
        $m->SMTPSecure = $SMTP_SECURE;
        $m->CharSet    = 'UTF-8';
        $m->isHTML(false);
        $m->setFrom($FROM_EMAIL, $FROM_NAME);
        return $m;
    };

    // Admin notification
    $admin = $newMailer();
    $admin->addAddress($ADMIN_TO, 'KlinFlow Admin');
    $admin->addReplyTo($t['email'], $t['name']);
    $admin->Subject = $adminSubject;
    $admin->Body    = $adminBody;
    $admin->send();

    // User confirmation
    $user = $newMailer();
    $user->addAddress($t['email'], $t['name']);
    $user->addReplyTo('askklinflow@gmail.com', 'KlinFlow Support');
    $user->Subject = $userSubject;
    $user->Body    = $userBody;
    $user->send();
}