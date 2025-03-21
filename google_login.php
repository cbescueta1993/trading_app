<?php
require 'config.php';
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

$login_url = $client->createAuthUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login with Google</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container text-center mt-5">
        <h3>Login to Trading App</h3>
        <img src="logo.jpg" alt="Trading App Logo" class="mb-3" width="150">
        <br>
        <a href="<?= $login_url ?>" class="btn btn-danger btn-lg mt-3">
            <i class="fab fa-google"></i> Sign in with Google
        </a>
    </div>
</body>
</html>
