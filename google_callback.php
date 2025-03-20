<?php
require 'config.php';
require 'vendor/autoload.php';

$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email");
$client->addScope("profile");

if (!isset($_GET['code'])) {
    header("Location: index.php");
    exit();
}

$token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
$client->setAccessToken($token);

$oauth2 = new Google_Service_Oauth2($client);
$userInfo = $oauth2->userinfo->get();

$google_id = $userInfo->id;
$name = $userInfo->name;
$email = $userInfo->email;

$query = $conn->prepare("SELECT id FROM users WHERE google_id = ?");
$query->bind_param("s", $google_id);
$query->execute();
$result = $query->get_result();

if ($result->num_rows == 0) {
    $stmt = $conn->prepare("INSERT INTO users (google_id, name, email) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $google_id, $name, $email);
    $stmt->execute();
}

$_SESSION['user_id'] = $google_id;
$_SESSION['name'] = $name;
$_SESSION['email'] = $email;

header("Location: dashboard.php");
exit();
?>
