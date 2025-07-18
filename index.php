<?php
echo file_get_contents("https://api.ipify.org");
require 'config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body class="bg-light">
    <div class="container text-center mt-5">
        <h2 class="mb-4">Welcome to Trading App</h2>
        <!-- Logo added above the button -->
        <img src="logo.png" alt="Trading App Logo" class="mb-3" width="150">
        <br>
        <a href="google_login.php" class="btn btn-success btn-lg">
            <i class="fab fa-google"></i> Proceed
        </a>
    </div>
</body>
</html>
