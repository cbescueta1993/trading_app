<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$filter_month = date('m');
$filter_year = date('Y');
if (isset($_GET['month']) && isset($_GET['year'])) {
    $filter_month = $_GET['month'];
    $filter_year = $_GET['year'];
}

$sql = "SELECT * FROM trade_journal WHERE google_id = ? AND MONTH(created_at) = ? AND YEAR(created_at) = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $_SESSION['user_id'], $filter_month, $filter_year);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="favicon.ico"></link>
</head>
<body class="bg-light">
    <nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dashboard</a>
        </div>
    </nav>

    <div class="container mt-5">
        <h3 class="text-center">Trade History</h3>
        <form method="GET" class="row g-3 mb-3">
            <div class="col-md-5">
                <label for="month" class="form-label">Month</label>
                <select class="form-select" name="month" id="month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m == $filter_month ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label for="year" class="form-label">Year</label>
                <select class="form-select" name="year" id="year">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">Filter</button>
            </div>
        </form>

        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Side</th>
                    <th>Quantity</th>
                    <th>Entry Price</th>
                    <th>Leverage</th>
                    <th>Margin</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['symbol']) ?></td>
                        <td><?= htmlspecialchars($row['side']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['entry_price']) ?></td>
                        <td><?= htmlspecialchars($row['leverage']) ?></td>
                        <td><?= htmlspecialchars($row['margin']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars($row['created_at']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
