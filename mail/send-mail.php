<?php
// Prem Jain Homes contact form handler.
// Upload this file in the same folder as contact.html.

declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: contact.html');
    exit;
}

// Simple honeypot spam protection.
if (!empty($_POST['website'] ?? '')) {
    header('Location: contact.html?status=success#contact-form1');
    exit;
}

function clean_text(string $value): string
{
    $value = trim($value);
    $value = str_replace(["\r", "\0"], '', $value);
    return $value;
}

$firstName = clean_text((string)($_POST['first_name'] ?? ''));
$lastName  = clean_text((string)($_POST['last_name'] ?? ''));
$email     = trim((string)($_POST['email'] ?? ''));
$phone     = clean_text((string)($_POST['phone'] ?? ''));
$message   = trim((string)($_POST['message'] ?? ''));

// Server-side validation.
if (
    $firstName === '' ||
    $lastName === '' ||
    $message === '' ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    strlen($firstName) > 80 ||
    strlen($lastName) > 80 ||
    strlen($email) > 150 ||
    strlen($phone) > 40 ||
    strlen($message) > 5000
) {
    header('Location: contact.html?status=error#contact-form1');
    exit;
}

// Prevent email-header injection through the visitor email field.
if (preg_match('/[\r\n]/', $email)) {
    header('Location: contact.html?status=error#contact-form1');
    exit;
}

$to = 'premjainhomesoffice@gmail.com';
$subject = 'New Website Inquiry - Prem Jain Homes';

$safeFirstName = htmlspecialchars($firstName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeLastName  = htmlspecialchars($lastName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeEmail     = htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safePhone     = htmlspecialchars($phone !== '' ? $phone : 'Not provided', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$safeMessage   = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

$body = <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Website Inquiry</title>
</head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#222;">
    <h2>New Contact Form Submission</h2>
    <p><strong>Name:</strong> {$safeFirstName} {$safeLastName}</p>
    <p><strong>Email:</strong> {$safeEmail}</p>
    <p><strong>Phone:</strong> {$safePhone}</p>
    <p><strong>Message:</strong><br>{$safeMessage}</p>
</body>
</html>
HTML;

// Use your website domain as the sender. The visitor's address stays in Reply-To,
// which avoids spoofing Gmail as the sending domain and lets you reply directly.
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/html; charset=UTF-8',
    'From: Prem Jain Homes Website <website@premjainhomes.com>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . phpversion(),
];

$sent = mail($to, $subject, $body, implode("\r\n", $headers));

if ($sent) {
    header('Location: contact.html?status=success#contact-form1');
    exit;
}

header('Location: contact.html?status=error#contact-form1');
exit;
