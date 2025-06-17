<!DOCTYPE html>
<html lang="en">
<head>
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
    <div class="text-center">
    <h1 class="mb-3">🛠️ Under Maintenance</h1>
    <p class="mb-4">We're performing some updates. Please check back later.</p>
    <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#confirmModal">Clear Logs</button>
    <div id="responseMessage" class="mt-3 text-success fw-bold"></div>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="confirmModalLabel">Confirm Action</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          Are you sure you want to clear all logs in <strong>alertlog_okx.txt</strong>?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" onclick="confirmClear()">Yes, Clear Logs</button>
        </div>
      </div>
    </div>
</div>

<script>
    function confirmClear() {
      fetch('clear_logs.php', { method: 'POST' })
        .then(response => response.text())
        .then(data => {
          document.getElementById('responseMessage').innerText = data;
          const modal = bootstrap.Modal.getInstance(document.getElementById('confirmModal'));
          modal.hide();
        })
        .catch(error => {
          document.getElementById('responseMessage').innerText = '❌ Error clearing logs.';
          console.error('Error:', error);
        });
    }
  </script>

</body>
</html>
