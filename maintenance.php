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
    <div class="text-center">
    <h1 class="mb-3">🛠️ Under Maintenance</h1>
    <p class="mb-4">We're performing some updates. Please check back later.</p>
    <button class="btn btn-danger" onclick="showConfirmModal()">Clear Logs</button>
    <div id="responseMessage" class="mt-3 text-success fw-bold"></div>
  </div>

  <!-- Confirmation Modal -->
  <div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-warning">
          <h5 class="modal-title" id="confirmModalLabel">Confirm Action - Clear Logs</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="mb-3">Are you sure you want to clear all logs in <strong>alertlog_okx.txt</strong>?</p>
          
          <div class="mb-3">
            <h6>Current Log Contents:</h6>
            <div id="logLoadingSpinner" class="text-center">
              <div class="spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
              <span class="ms-2">Loading log content...</span>
            </div>
            <div id="logContent" class="border rounded p-3 bg-light" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.9em; white-space: pre-wrap; display: none;"></div>
            <div id="logError" class="alert alert-danger" style="display: none;"></div>
          </div>
          
          <div class="alert alert-warning">
            <strong>⚠️ Warning:</strong> This action cannot be undone. All log entries will be permanently deleted.
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger" onclick="confirmClear()">Yes, Clear Logs</button>
        </div>
      </div>
    </div>
</div>

<script>
    function showConfirmModal() {
      // Show the modal first
      const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
      modal.show();
      
      // Reset content display
      document.getElementById('logLoadingSpinner').style.display = 'block';
      document.getElementById('logContent').style.display = 'none';
      document.getElementById('logError').style.display = 'none';
      
      // Fetch and display log content
      fetch('get_log_content.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Failed to fetch log content');
          }
          return response.text();
        })
        .then(data => {
          document.getElementById('logLoadingSpinner').style.display = 'none';
          const logContentDiv = document.getElementById('logContent');
          
          if (data.trim() === '') {
            logContentDiv.innerHTML = '<em class="text-muted">Log file is empty</em>';
          } else {
            logContentDiv.textContent = data;
          }
          
          logContentDiv.style.display = 'block';
        })
        .catch(error => {
          document.getElementById('logLoadingSpinner').style.display = 'none';
          document.getElementById('logError').style.display = 'block';
          document.getElementById('logError').textContent = 'Error loading log content: ' + error.message;
          console.error('Error:', error);
        });
    }

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