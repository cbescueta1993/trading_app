<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

class CryptoTrade {
    public $conn;

    public function __construct($paramconn) {
        $this->conn = $paramconn;
    }

    public function getAllCryptos() {
        return $this->conn->query("SELECT * FROM cryptos");
    }

    public function getCrypto($id) {
        return $this->conn->query("SELECT * FROM cryptos WHERE id=$id")->fetch_assoc();
    }

    public function addCrypto($name, $symbol, $price) {
        $this->conn->query("INSERT INTO cryptos (name, symbol, price) VALUES ('$name', '$symbol', '$price')");
    }

    public function updateCrypto($id, $name, $symbol, $price) {
        $this->conn->query("UPDATE cryptos SET name='$name', symbol='$symbol', price='$price' WHERE id=$id");
    }

    public function deleteCrypto($id) {
        $this->conn->query("DELETE FROM cryptos WHERE id=$id");
    }
}

$cryptoTrade = new CryptoTrade($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $cryptoTrade->addCrypto($_POST['name'], $_POST['symbol'], $_POST['price']);
    } elseif (isset($_POST['update'])) {
        $cryptoTrade->updateCrypto($_POST['id'], $_POST['name'], $_POST['symbol'], $_POST['price']);
    } elseif (isset($_POST['delete'])) {
        $cryptoTrade->deleteCrypto($_POST['id']);
    }
    header('Location: cryptocoins.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ASSETS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body  class="bg-light">
<nav class="navbar navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">Dashboard</a>
        </div>
    </nav>
    <h2 class="text-center">Crypto Trading</h2>
    <button class="btn btn-success mb-2" data-bs-toggle="modal" data-bs-target="#cryptoModal" onclick="openModal()">Add New Crypto</button>
    
    <table class="table table-bordered">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Symbol</th>
            <th>Price</th>
            <th>Actions</th>
        </tr>
        <?php
        $result = $cryptoTrade->getAllCryptos();
        while ($row = $result->fetch_assoc()) {
            echo "<tr>
                    <td>{$row['id']}</td>
                    <td>{$row['name']}</td>
                    <td>{$row['symbol']}</td>
                    <td>\${$row['price']}</td>
                    <td> 
                        <button class='btn btn-warning' data-bs-toggle='modal' data-bs-target='#cryptoModal' onclick='openModal({$row['id']}, \"{$row['name']}\", \"{$row['symbol']}\", \"{$row['price']}\")'>Edit</button>
                        <button class='btn btn-danger' data-bs-toggle='modal' data-bs-target='#deleteModal' onclick='setDeleteId({$row['id']})'>Delete</button>
                        <a href='https://orchid-boar-297382.hostingersite.com/tradingapp/createorder_okx.php?input={$row['name']};BUY;102871033794724054940;' target='_blank' class='btn btn-primary'>Buy</a>
                        <a href='https://orchid-boar-297382.hostingersite.com/tradingapp/createorder_okx.php?input={$row['name']};SELL;102871033794724054940;' target='_blank' class='btn btn-secondary'>Sell</a>
                    </td>
                  </tr>";
        }
        ?>
    </table>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="cryptoModal" tabindex="-1" aria-labelledby="cryptoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cryptoModalLabel">Add/Edit Crypto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" id="crypto_id" name="id">
                        <input type="text" id="crypto_name" name="name" class="form-control mb-2" placeholder="Crypto Name" required>
                        <input type="text" id="crypto_symbol" name="symbol" class="form-control mb-2" placeholder="Symbol" required>
                        <input type="number" step="0.01" id="crypto_price" name="price" class="form-control mb-2" placeholder="Price" required>
                        <button type="submit" id="cryptoSubmit" name="add" class="btn btn-success">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this crypto?
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <input type="hidden" id="delete_id" name="id">
                        <button type="submit" name="delete" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openModal(id = '', name = '', symbol = '', price = '') {
            document.getElementById('crypto_id').value = id;
            document.getElementById('crypto_name').value = name;
            document.getElementById('crypto_symbol').value = symbol;
            document.getElementById('crypto_price').value = price;
            document.getElementById('cryptoSubmit').name = id ? 'update' : 'add';
            document.getElementById('cryptoSubmit').textContent = id ? 'Update' : 'Save';
        }
        
        function setDeleteId(id) {
            document.getElementById('delete_id').value = id;
        }
    </script>
</body>
</html>
