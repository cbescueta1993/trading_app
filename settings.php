<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Fetch user data using MySQLi
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE google_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$success = isset($_GET['success']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $margin = $_POST['margin'];
    $leverage = $_POST['leverage'];
    $api_key = $_POST['api_key'];
    $api_secret = $_POST['api_secret'];
    $apiKeyOkx = $_POST['apiKeyOkx'];
    $secretKeyOkx = $_POST['secretKeyOkx'];
    $passPhraseOkx = $_POST['passPhraseOkx'];

    // Update user settings using MySQLi
    $query = "UPDATE users SET margin = ?, leverage = ?, api_key = ?, api_secret = ?, apiKeyOkx = ?, secretKeyOkx = ?, passPhraseOkx = ? WHERE google_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("dissssss", $margin, $leverage, $api_key, $api_secret, $apiKeyOkx, $secretKeyOkx, $passPhraseOkx, $user_id);
    $stmt->execute();

    header("Location: settings.php?success=1");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dashboard</a>
        </div>
    </nav>

<div class="container mt-5">
    <h3>Settings</h3>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="successModalLabel">Success</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Settings updated successfully!
                </div>
            </div>
        </div>
    </div>

    <form method="POST">
        <label>Margin</label>
        <input type="number" step="0.1" name="margin" class="form-control" value="<?= htmlspecialchars($user['margin']) ?>" required>

        <label>Leverage</label>
        <input type="number" name="leverage" class="form-control" value="<?= htmlspecialchars($user['leverage']) ?>" required>

        <label>Binance API Key</label>
        <input type="text" name="api_key" class="form-control" value="<?= htmlspecialchars($user['api_key']) ?>">

        <label>Binance API Secret</label>
        <input type="text" name="api_secret" class="form-control" value="<?= htmlspecialchars($user['api_secret']) ?>">

        <label>OKX API Key</label>
        <input type="text" name="apiKeyOkx" class="form-control" value="<?= htmlspecialchars($user['apiKeyOkx']) ?>">

        <label>OKX Secret Key</label>
        <input type="text" name="secretKeyOkx" class="form-control" value="<?= htmlspecialchars($user['secretKeyOkx']) ?>">

        <label>OKX Passphrase</label>
        <input type="text" name="passPhraseOkx" class="form-control" value="<?= htmlspecialchars($user['passPhraseOkx']) ?>">

        <button type="submit" class="btn btn-success mt-3">Save Changes</button>
    </form>
    <a href="index.php" class="btn btn-secondary mt-3">Back</a>
</div>

<!-- JavaScript to Show Modal on Success and Auto-Close -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        <?php if ($success): ?>
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
            setTimeout(function () {
                successModal.hide();
            }, 2000); // Auto-close in 2 seconds
        <?php endif; ?>
    });
</script>

</body>
</html>
