<?php
date_default_timezone_set('Asia/Manila'); // Set your time zone

$hostname = '{imap.yourdomain.com:993/imap/ssl}INBOX';
$username = 'your_email@yourdomain.com';
$password = 'your_password';

// Connect to mailbox
$inbox = imap_open($hostname, $username, $password) or die('Cannot connect: ' . imap_last_error());

// Search for unseen emails from noreply@tradingview.com
$search = 'UNSEEN FROM "noreply@tradingview.com"';
$emails = imap_search($inbox, $search);

if ($emails) {
    echo "New email(s) from noreply@tradingview.com found at " . date('Y-m-d H:i:s') . "\n";

    rsort($emails); // newest first
    foreach ($emails as $email_number) {
        $overview = imap_fetch_overview($inbox, $email_number, 0);
        $body = imap_fetchbody($inbox, $email_number, 1);

        echo "Subject: " . $overview[0]->subject . "\n";
        echo "Date: " . $overview[0]->date . "\n";
        echo "Body (first 200 chars):\n" . substr($body, 0, 200) . "\n\n";
    }
} else {
    echo "No new emails from noreply@tradingview.com at " . date('Y-m-d H:i:s') . "\n";
}

// Close connection
imap_close($inbox);
?>
