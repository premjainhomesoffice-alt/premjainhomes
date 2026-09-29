<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Receiver Email Address
    $to = "premjainhomesoffice@gmail.com";

    // Form inputs sanitize karein
    $first_name = filter_var(trim($_POST["first_name"]), FILTER_SANITIZE_STRING);
    $last_name  = filter_var(trim($_POST["last_name"]), FILTER_SANITIZE_STRING);
    $email      = filter_var(trim($_POST["email"]), FILTER_SANITIZE_EMAIL);
    $phone      = filter_var(trim($_POST["phone"]), FILTER_SANITIZE_STRING);
    $comments   = filter_var(trim($_POST["comments"]), FILTER_SANITIZE_STRING);

    // Validation Check
    if (empty($first_name) || empty($last_name) || empty($phone) || empty($comments) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Please complete all fields correctly.";
        exit;
    }

    // Email Subject & Body Layout
    $subject = "New Contact Form Inquiry from: " . $first_name . " " . $last_name;
    
    $email_content  = "You have received a new inquiry from your website contact form:\n\n";
    $email_content .= "First Name: $first_name\n";
    $email_content .= "Last Name: $last_name\n";
    $email_content .= "Email: $email\n";
    $email_content .= "Phone: $phone\n\n";
    $email_content .= "Message:\n$comments\n";

    // Headers
    $headers  = "From: " . $first_name . " <" . $email . ">\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    // Mail send processing
    if (mail($to, $subject, $email_content, $headers)) {
        http_response_code(200);
        echo "Thank you! Your message has been sent successfully.";
    } else {
        http_response_code(500);
        echo "Oops! Something went wrong, and we couldn't send your message.";
    }

} else {
    http_response_code(403);
    echo "There was a problem with your submission, please try again.";
}
?>