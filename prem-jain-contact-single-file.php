<?php
/* =========================================================
   PREM JAIN HOMES - SINGLE FILE CONTACT FORM
   Save this file as: contact.php
   ========================================================= */

// -------------------------
// Gmail SMTP configuration
// -------------------------
const CONTACT_TO_EMAIL = 'premjainhomesoffice@gmail.com';
const GMAIL_SMTP_USER   = 'premjainhomesoffice@gmail.com';

// IMPORTANT:
// Paste the 16-character Google App Password below (remove spaces).
// Do NOT use your normal Gmail password.
const GMAIL_APP_PASSWORD = 'PASTE_16_CHARACTER_APP_PASSWORD_HERE';

$form_status = '';
$form_message = '';

function clean_header_value($value) {
    return trim(str_replace(["\r", "\n"], '', (string)$value));
}

function smtp_read_response($socket) {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        // SMTP multiline response ends when the 4th char is a space.
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_expect($socket, $expectedCodes) {
    $response = smtp_read_response($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, (array)$expectedCodes, true)) {
        throw new Exception('SMTP error: ' . trim($response));
    }
    return $response;
}

function smtp_command($socket, $command, $expectedCodes) {
    fwrite($socket, $command . "\r\n");
    return smtp_expect($socket, $expectedCodes);
}

function send_with_gmail_smtp($firstName, $lastName, $visitorEmail, $phone, $visitorMessage) {
    $smtpUser = GMAIL_SMTP_USER;
    $smtpPass = str_replace(' ', '', GMAIL_APP_PASSWORD);
    $to       = CONTACT_TO_EMAIL;

    if ($smtpPass === '' || $smtpPass === 'PASTE_16_CHARACTER_APP_PASSWORD_HERE') {
        throw new Exception('Gmail App Password is not configured.');
    }

    $errno = 0;
    $errstr = '';
    $useStartTls = false;

    // First try Gmail SMTP over implicit SSL (port 465).
    $socket = @stream_socket_client(
        'ssl://smtp.gmail.com:465',
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    // If port 465 is blocked by the host, fall back to port 587 + STARTTLS.
    if (!$socket) {
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'tcp://smtp.gmail.com:587',
            $errno,
            $errstr,
            20,
            STREAM_CLIENT_CONNECT
        );
        $useStartTls = true;
    }

    if (!$socket) {
        throw new Exception('Could not connect to Gmail SMTP: ' . $errstr);
    }

    stream_set_timeout($socket, 20);

    try {
        smtp_expect($socket, [220]);
        smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'premjainhomes.com'), [250]);

        if ($useStartTls) {
            smtp_command($socket, 'STARTTLS', [220]);
            $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            if ($cryptoOk !== true) {
                throw new Exception('Could not enable TLS encryption for Gmail SMTP.');
            }
            smtp_command($socket, 'EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'premjainhomes.com'), [250]);
        }

        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($smtpUser), [334]);
        smtp_command($socket, base64_encode($smtpPass), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $smtpUser . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);

        $safeFirst = clean_header_value($firstName);
        $safeLast  = clean_header_value($lastName);
        $safeEmail = clean_header_value($visitorEmail);
        $safePhone = trim($phone);
        $fullName  = trim($safeFirst . ' ' . $safeLast);

        $subject = 'New Website Inquiry - ' . ($fullName !== '' ? $fullName : 'Prem Jain Homes');

        $body  = "New inquiry received from Prem Jain Homes website.\r\n\r\n";
        $body .= "First Name: " . $safeFirst . "\r\n";
        $body .= "Last Name: " . $safeLast . "\r\n";
        $body .= "Email: " . $safeEmail . "\r\n";
        $body .= "Phone: " . ($safePhone !== '' ? $safePhone : 'Not provided') . "\r\n\r\n";
        $body .= "Message:\r\n" . trim($visitorMessage) . "\r\n\r\n";
        $body .= "Submitted: " . date('Y-m-d H:i:s') . "\r\n";
        $body .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "\r\n";

        $headers = [
            'From: Prem Jain Homes Website <' . $smtpUser . '>',
            'Reply-To: ' . $fullName . ' <' . $safeEmail . '>',
            'To: Prem Jain Homes <' . $to . '>',
            'Subject: ' . $subject,
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'premjainhomes.com') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit'
        ];

        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        // SMTP dot-stuffing.
        $data = preg_replace('/(?m)^\./', '..', $data);
        fwrite($socket, $data . "\r\n.\r\n");
        smtp_expect($socket, [250]);
        smtp_command($socket, 'QUIT', [221]);

        fclose($socket);
        return true;
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        throw $e;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_form'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $message   = trim($_POST['message'] ?? '');
    $website   = trim($_POST['website'] ?? ''); // Honeypot

    if ($website !== '') {
        // Silently treat bot submissions as successful.
        header('Location: contact.php?sent=1#contact-form1');
        exit;
    }

    if ($firstName === '' || $lastName === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $form_status = 'error';
        $form_message = 'Please complete all required fields and enter a valid email address.';
    } elseif (strlen($firstName) > 80 || strlen($lastName) > 80 || strlen($email) > 150 || strlen($phone) > 40 || strlen($message) > 5000) {
        $form_status = 'error';
        $form_message = 'One or more fields are too long. Please shorten your entry and try again.';
    } else {
        try {
            send_with_gmail_smtp($firstName, $lastName, $email, $phone, $message);
            header('Location: contact.php?sent=1#contact-form1');
            exit;
        } catch (Throwable $e) {
            // Keep detailed server error out of the public page.
            error_log('Prem Jain Homes contact form error: ' . $e->getMessage());
            $form_status = 'error';
            $form_message = 'Your message could not be sent right now. Please try again or contact us directly at premjainhomesoffice@gmail.com.';
        }
    }
}

if (isset($_GET['sent']) && $_GET['sent'] === '1') {
    $form_status = 'success';
    $form_message = 'Thank you! Your message has been submitted successfully. We have received your inquiry and will get back to you soon.';
}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="zxx">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Prem Jain Homes - Contact Us</title>
    <!-- Favicon -->
    <link rel="shortcut icon" type="image/x-icon" href="images/favicon.png">
    <!-- Bootstrap core CSS -->
    <link href="css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <!--Custom CSS-->
    <link href="css/style.css" rel="stylesheet" type="text/css">
    <!--Plugin CSS-->
    <link href="css/plugin.css" rel="stylesheet" type="text/css">
    <!--Flaticons CSS-->
    <link href="fonts/flaticon.css" rel="stylesheet" type="text/css">
    <!--Font Awesome-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.11.2/css/all.min.css">

    <link rel="stylesheet" href="fonts/line-icons.css" type="text/css">
    <style>
        .form-submit-note {
            padding: 18px 20px;
            border-radius: 4px;
            border: 1px solid transparent;
        }
        .form-submit-note i {
            font-size: 30px;
            margin-bottom: 8px;
        }
        .form-submit-success {
            background: #eef9f1;
            border-color: #b9dfc2;
            color: #245c32;
        }
        .form-submit-error {
            background: #fff1f1;
            border-color: #efc2c2;
            color: #8a2525;
        }
    </style>
</head>
<body>

     <!-- header starts -->
    <header class="main_header_area">
        <!-- Navigation Bar -->
        <div class="header_menu" id="header_menu">
            <nav class="navbar navbar-default">
                <div class="container">
                    <div class="navbar-flex d-flex align-items-center justify-content-between w-100 pb-3 pt-3">
                        <!-- Brand and toggle get grouped for better mobile display -->
                        <div class="navbar-header">
                            <a class="navbar-brand" href="index.html">
                                <img src="images/Prem jain-logos-navbar.webp" alt="image"
                                    style="width: 240px; height: auto;">
                            </a>
                        </div>
                        <!-- Collect the nav links, forms, and other content for toggling -->
                        <div class="navbar-collapse1 d-flex align-items-center" id="bs-example-navbar-collapse-1">
                            <ul class="nav navbar-nav" id="responsive-menu">
                                <li class="dropdown submenu">
                                    <a href="index.html" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                        aria-haspopup="true" aria-expanded="false">Home</a>
                                </li>
                                <li class="submenu dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                        aria-haspopup="true" aria-expanded="false">Properties</a>
                                    <ul class="dropdown-menu">
                                        <li><a href="listing-fullwidth.html">Active Listings</a></li>
                                    </ul>
                                </li>

                                <li class="submenu dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                        aria-haspopup="true" aria-expanded="false">Buying</a>
                                    <ul class="dropdown-menu">
                                        <li><a href="buyers-guide.html">Buyer's Guide</a></li>
                                    </ul>
                                </li>
                                <li><a href="about.html">About Us</a></li>

                                <li class="submenu dropdown">
                                    <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                        aria-haspopup="true" aria-expanded="false">Blog <i class="icon-arrow-down"
                                            aria-hidden="true"></i></a>
                                    <ul class="dropdown-menu">
                                        <li class="submenu dropdown">
                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                                aria-haspopup="true" aria-expanded="false">Blog Grid<i
                                                    class="fa fa-angle-right" aria-hidden="true"></i></a>
                                            <ul class="dropdown-menu">
                                                <li><a href="post-grid-1.html">Blog Grid 1</a></li>
                                                <li><a href="post-grid-2.html">Blog Grid 2</a></li>
                                                <li><a href="post-grid-3.html">Blog Grid 3</a></li>
                                            </ul>
                                        </li>
                                        <li class="submenu dropdown">
                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                                aria-haspopup="true" aria-expanded="false">Blog List<i
                                                    class="fa fa-angle-right" aria-hidden="true"></i></a>
                                            <ul class="dropdown-menu">
                                                <li><a href="post-list-1.html">Blog List 1</a></li>
                                                <li><a href="post-list-2.html">Blog List 2</a></li>
                                                <li><a href="post-list-3.html">Blog List 3</a></li>
                                            </ul>
                                        </li>
                                        <li class="submenu dropdown">
                                            <a href="#" class="dropdown-toggle" data-toggle="dropdown" role="button"
                                                aria-haspopup="true" aria-expanded="false">Blog Single<i
                                                    class="fa fa-angle-right" aria-hidden="true"></i></a>
                                            <ul class="dropdown-menu">
                                                <li><a href="detail-1.html">Blog Single 1</a></li>
                                                <li><a href="detail-2.html">Blog Single 2</a></li>
                                                <li><a href="detail-3.html">Blog Single 3</a></li>
                                            </ul>
                                        </li>
                                    </ul>
                                </li>
                                <li class="active"><a href="contact.php">Contact Us</a></li>
                                <li class="search-main"><a href="#search1" class="mt_search"><i
                                            class="fa fa-search"></i></a></li>
                            </ul>
                        </div><!-- /.navbar-collapse -->
                        <div class="register-login d-flex align-items-center">
                            <div class="header_sidemenu me-3">
                                <div class="mhead">
                                    <span class="menu-ham">
                                        <a href="#" class="cart-icon d-flex align-items-center ms-1"><i
                                                class="fa fa-th-large fs-5 black bg-grey p-2"></i></a>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div id="slicknav-mobile"></div>
                    </div>
                </div><!-- /.container-fluid -->
            </nav>
        </div>
        <!-- Navigation Bar Ends -->
    </header>
    <!-- header ends -->

    <!-- contact starts -->
    <section class="contact-main pt-0 pb-10 bg-grey">
        <div class="map">
            <div style="width: 100%">
                <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d23042.994576479643!2d-79.4747116!3d43.7858452!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x882b2f005edee2e1%3A0xc1d3213638b38b4c!2sKFC!5e0!3m2!1sen!2s!4v1777616800771!5m2!1sen!2s" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
        </div>
        <div class="container">
            <div class="contact-info-main">
                <div class="row">
                    <div class="col-lg-10 col-offset-lg-1 mx-auto">
                        <div class="contact-info bg-white pt-10 pb-10 px-5">
                            <div class="contact-info-title text-center mb-4 px-5">
                                <h3 class="mb-1">INFORMATION ABOUT US</h3>
                                <p class="mb-0">Your time is our time. We guarantee that we won't waste it.</p>
                            </div>
                            <div class="contact-info-content row mb-1">
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="info-item bg-lgrey px-4 py-5 border-all text-center">
                                        <div class="info-icon mb-2">
                                            <i class="fa fa-map-marker"></i>
                                        </div>
                                        <div class="info-content">
                                            <p class="m-0">2654 Boardfish Road</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-6 mb-4">
                                    <div class="info-item bg-lgrey px-4 py-5 border-all text-center">
                                        <div class="info-icon mb-2">
                                            <i class="fa fa-phone"></i>
                                        </div>
                                        <div class="info-content">
                                            <p class="m-0">(647) 781-9338</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-lg-4 col-md-12 mb-4">
                                    <div class="info-item bg-lgrey px-4 py-5 border-all text-center">
                                        <div class="info-icon mb-2">
                                            <i class="fa fa-envelope"></i>
                                        </div>
                                        <div class="info-content ps-4">
                                            <p class="m-0">premjainhomesoffice@gmail.com</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="contact-form1" class="contact-form px-5">
                                <div class="contact-info-title text-center mb-4 px-5">
                                    <h3 class="mb-1">Keep in Touch</h3>
                                    <p class="mb-0">Reach out to us for any inquiries, property viewings, or assistance.</p>
                                </div>

                                <?php if ($form_status === 'success'): ?>
                                    <div class="form-submit-note form-submit-success text-center mb-4" role="status">
                                        <i class="fa fa-check-circle" aria-hidden="true"></i>
                                        <h4 class="mb-1">Message Sent Successfully</h4>
                                        <p class="mb-0"><?php echo htmlspecialchars($form_message, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                <?php elseif ($form_status === 'error'): ?>
                                    <div class="form-submit-note form-submit-error text-center mb-4" role="alert">
                                        <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                                        <h4 class="mb-1">Message Not Sent</h4>
                                        <p class="mb-0"><?php echo htmlspecialchars($form_message, ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                <?php endif; ?>

                                <!-- CONTACT FORM: handled by this same PHP file -->
                                <form action="contact.php#contact-form1" method="POST" id="contactForm">
                                    <input type="hidden" name="contact_form" value="1">

                                    <div class="form-group mb-2">
                                        <input type="text" name="first_name" class="form-control" placeholder="First Name" maxlength="80" autocomplete="given-name" value="<?php echo $form_status === 'error' ? htmlspecialchars($_POST['first_name'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                                    </div>
                                    <div class="form-group mb-2">
                                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" maxlength="80" autocomplete="family-name" value="<?php echo $form_status === 'error' ? htmlspecialchars($_POST['last_name'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                                    </div>
                                    <div class="form-group mb-2">
                                        <input type="email" name="email" class="form-control" placeholder="Email" maxlength="150" autocomplete="email" value="<?php echo $form_status === 'error' ? htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                                    </div>
                                    <div class="form-group mb-2">
                                        <input type="tel" name="phone" class="form-control" placeholder="Phone" maxlength="40" autocomplete="tel" value="<?php echo $form_status === 'error' ? htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?>">
                                    </div>
                                    <div class="textarea mb-2">
                                        <textarea name="message" placeholder="Enter a message" maxlength="5000" required><?php echo $form_status === 'error' ? htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                                    </div>

                                    <!-- Spam protection: real visitors leave this field empty -->
                                    <div style="position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden;" aria-hidden="true">
                                        <label for="website">Website</label>
                                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                                    </div>

                                    <div class="comment-btn text-center">
                                        <button type="submit" class="nir-btn" id="contactSubmitBtn">Send Message</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- contact Ends -->

   <section class="newsletter p-0 position-relative">
        <div class="newsletter-main p-5 pb-3" style="background-color: #dbba77;">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-8 mb-2">
                        <div class="newsletter-content">
                            <h2 class="mb-0 text-dark text-lg-start text-center">
                                Stay Updated with the Best Property Deals.
                            </h2>
                            <p class="mb-0 white">Receive new listings, investment opportunities, and market insights
                                directly in your inbox.</p>
                        </div>
                    </div>
                    <div class="col-lg-4 mb-2">
                        <div class="newsletter-form w-100">
                            <form action="#" method="get" accept-charset="utf-8" class="border-0">
                                <input type="text" placeholder="Email Address">
                                <button class="nir-btn-black">Subscribe</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- footer starts -->
    <footer class="pt-10 footermain">
        <div class="footer-upper pb-4">
            <div class="container">
                <div class="row">
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                        <div class="footer-about">
                            <img src="images/Prem jain-logos-navbar.webp" alt="">
                            <p class="mt-3 mb-3 white">
                                Your trusted real estate partner, helping you find, buy,
                                and invest in the perfect property with confidence.
                            </p>
                            <ul>
                                <li class="white"><i class="fas fa-phone-alt themeicon3"></i> (647) 781-9338</li>
                                <li class="white"><i class="fas fa-envelope themeicon3"></i>
                                    premjainhomesoffice@gmail.com</li>
                                <li class="white">
                                    <i class="fas fa-globe themeicon3"></i> www.premjainhomes.com
                                </li>
                            </ul>

                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                        <div class="footer-links">
                            <h3 class="white">Quick link</h3>
                            <ul>
                                <li><a href="about.html">About Us</a></li>
                                <li><a href="listing-fullwidth.html">Active Listings</a></li>
                                <li><a href="buyers-guide.html">Buyer's Guide</a></li>
                                <li><a href="contact.php">Contact Us</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 col-sm-12 mb-4">
                        <div class="footer-links">
                            <h3 class="white">Popular Posts</h3>
                            <div class="trend-main">
                                <div class="trend-item d-flex align-items-center mb-2">
                                    <div class="trend-image w-25 me-4">
                                        <img src="images/trending/trending4.jpg" alt="image">
                                    </div>
                                    <div class="trend-content-main w-75">
                                        <div class="trend-content">
                                            <h5 class="mb-1"><a href="detail-1.html">3 Easy Ways To Make Your iPhone
                                                    Faster</a></h5>
                                            <div class="entry-meta">
                                                <div class="entry-metalist d-flex align-items-center">
                                                    <small><a href="post-grid-1.html" class="white"><i
                                                                class="fa fa-calendar"></i> 22 Mar 2021</a></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="trend-item d-flex align-items-center mb-2">
                                    <div class="trend-image w-25 me-4">
                                        <img src="images/trending/trending5.jpg" alt="image">
                                    </div>
                                    <div class="trend-content-main w-75">
                                        <div class="trend-content">
                                            <h5 class="mb-1"><a href="detail-1.html">Facts About Business That Will Help
                                                    You Success</a></h5>
                                            <div class="entry-meta">
                                                <div class="entry-metalist d-flex align-items-center">
                                                    <small><a href="post-grid-1.html" class="white"><i
                                                                class="fa fa-calendar"></i> 22 Mar 2021</a></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="trend-item d-flex align-items-center">
                                    <div class="trend-image w-25 me-4">
                                        <img src="images/trending/trending6.jpg" alt="image">
                                    </div>
                                    <div class="trend-content-main w-75">
                                        <div class="trend-content">
                                            <h5 class="mb-1"><a href="detail-1.html">Your Light Is About To Stop Being
                                                    Relevant</a></h5>
                                            <div class="entry-meta">
                                                <div class="entry-metalist d-flex align-items-center">
                                                    <small><a href="post-grid-1.html" class="white"><i
                                                                class="fa fa-calendar"></i> 22 Mar 2021</a></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-copyright pt-2 pb-2">
            <div class="container">
                <div class="copyright-inner d-md-flex align-items-center justify-content-between">
                    <div class="copyright-text">
                        <p class="m-0 white">2026 Prem Jain Homes. All rights reserved.</p>
                    </div>
                    <div class="social-icons">
                        <a href="https://www.instagram.com/premjainhomes/" target="_blank"><i
                                class="fab fa-instagram"></i></a>
                        <a href="https://x.com/premjainhomes/" target="_blank"><i class="fab fa-twitter"></i></a>
                        <a href="https://www.facebook.com/prem.jain.555493" target="_blank"><i
                                class="fab fa-facebook-f"></i></a>
                        <a href="https://www.youtube.com/channel/UCQTUnbo5fzROyD5jlf4-PwA" target="_blank"><i
                                class="fab fa-youtube"></i></a>
                        <a href="https://www.tiktok.com/@premjainhomes" target="_blank"><i
                                class="fab fa-tiktok"></i></a>
                        <a href="https://www.linkedin.com/in/prem-jain-472206265/?_l=en_US" target="_blank"><i
                                class="fab fa-linkedin-in"></i></a>
                    </div>

                </div>
            </div>
        </div>
    </footer>
    <!-- footer ends -->

    
    <!-- Back to top start -->
    <div id="back-to-top">
        <a href="#"></a>
    </div>
    <!-- Back to top ends -->

    <!-- search popup -->
    <div id="search1">
        <button type="button" class="close">×</button>
        <form>
            <input type="search" value="" placeholder="type keyword(s) here" />
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>

    <!-- header side menu --> 
    <div class="header_sidemenu">
        <div class="header_sidemenu_in">
            <div class="menu py-5 px-4">
                <div class="close-menu">
                    <i class="fa fa-times"></i>
                </div>
                 <div class="m-contentmain">
                    <div class="m-contentmain">
                        <div class="m-logo mb-5">
                            <img src="images/Prem jain-logos-navbar.webp" alt="m-logo">
                        </div>

                        <div class="content-box mb-5">
                            <h3 class="">Get In Touch</h3>
                            <p class="mb-2">Your trusted real estate partner, helping you find, buy, and invest in the perfect property with confidence.</p>
                            <a href="contact.php" class="nir-btn">Consultation</a>
                        </div>

                        <div class="contact-info1">
                            <h3 class="">Contact Info</h3>
                            <ul>
                                <li class="d-block mb-1"><i class="fa fa-map-marker-alt me-2"></i> 2654 Boardfish Road</li>
                                <li class="d-block mb-1"><i class="fa fa-phone-alt me-2"></i>(647) 781-9338</li>
                                <li class="d-block mb-1"><i class="fa fa-envelope-open me-2"></i>premjainhomesoffice@gmail.com</li>
                            </ul>
                        </div>
                    </div>
                </div>    
            </div>
            <div class="overlay hide"></div>
        </div>
    </div>

    <!-- *Scripts* -->
    <script src="js/jquery-3.5.1.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/plugin.js"></script>
    <script src="js/main.js"></script>
    <script src="js/custom-nav.js"></script>
    <script>
        (function () {
            var form = document.getElementById('contactForm');
            var btn = document.getElementById('contactSubmitBtn');
            if (form && btn) {
                form.addEventListener('submit', function () {
                    btn.disabled = true;
                    btn.innerHTML = 'Sending...';
                });
            }
        })();
    </script>
</body>
</html>
