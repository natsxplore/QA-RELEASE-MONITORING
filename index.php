<?php
require_once __DIR__ . '/includes/database.php';
$dbStatus = getDatabaseStatus();
$dbConnected = $dbStatus['connected'];
$dbMessage = $dbStatus['message'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QA Release Monitoring</title>
  <style>
<?php
$cssPath = __DIR__ . '/assets/css/style.css';
if (is_readable($cssPath)) {
    readfile($cssPath);
} else {
    echo '/* QA Release Monitoring: assets/css/style.css not found on server — upload the assets/ folder */';
}
?>
  </style>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
</head>
<body class="<?php echo $dbConnected ? '' : 'db-offline'; ?>">

  <div id="dbAlert" class="db-alert" <?php echo $dbConnected ? 'hidden' : ''; ?>>
    <strong>Database not connected.</strong>
    <span id="dbAlertMessage"><?php echo htmlspecialchars($dbMessage, ENT_QUOTES, 'UTF-8'); ?></span>
  </div>

  <div class="header">
    <div class="header-top">
      <h2>QA Release Monitoring</h2>
      <?php if ($dbConnected) : ?>
      <div class="action-btn-group" id="actionBtnGroup">
        <button type="button" class="btn btn-primary" onclick="openCreateModal()">＋ Add Ticket</button>
        <button type="button" class="btn btn-secondary" onclick="openModal('sprintModal')">＋ New Sprint</button>
        <button type="button" class="btn btn-neutral" onclick="openModal('memberModal')">＋ New Dev / QA</button>
        <button type="button" class="btn btn-import" onclick="openModal('importModal')">📥 Import Data</button>
        <button type="button" class="btn btn-reset" onclick="resetFilters()">Reset Filters</button>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($dbConnected) : ?>
    <div class="filters" id="filtersBar">
      <div class="filter-group">
        <label for="titleFilter">Search Title:</label>
        <input type="text" id="titleFilter" placeholder="Filter by Title..." oninput="onFilterChange()">
      </div>
      <div class="filter-group">
        <label for="dateFrom">Date From:</label>
        <input type="date" id="dateFrom" onchange="onFilterChange()">
      </div>
      <div class="filter-group">
        <label for="dateTo">Date To:</label>
        <input type="date" id="dateTo" onchange="onFilterChange()">
      </div>
      <div class="filter-group">
        <label for="sprintFilter">Sprint:</label>
        <select id="sprintFilter" onchange="onFilterChange()">
          <option value="all">All Sprints</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="typeFilter">Change Type:</label>
        <select id="typeFilter" onchange="onFilterChange()">
          <option value="all">All Types</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="stageFilter">Change Stage:</label>
        <select id="stageFilter" onchange="onFilterChange()">
          <option value="all">All Stages</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="changeStatusFilter">Change Status:</label>
        <select id="changeStatusFilter" onchange="onFilterChange()">
          <option value="all">All Statuses</option>
        </select>
      </div>
      <div class="filter-group">
        <label for="statusFilter">Release Status:</label>
        <select id="statusFilter" onchange="onFilterChange()">
          <option value="all">All Statuses</option>
          <option value="Released">Released</option>
          <option value="For release">For release</option>
          <option value="Not in release">Not in release</option>
        </select>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($dbConnected) : ?>
  <div id="appDataPanel">
    <div class="dashboard-top">
      <div class="kpi-grid">
        <div class="kpi-card">
          <h4>Total Tickets</h4>
          <div class="val" id="kpiTotal">—</div>
          <div class="sub">100% of Filtered</div>
        </div>
        <div class="kpi-card">
          <h4>Released</h4>
          <div class="val" style="color: #16a34a;" id="kpiReleased">—</div>
          <div class="sub" id="subReleased">—</div>
        </div>
        <div class="kpi-card">
          <h4>For Release</h4>
          <div class="val" style="color: #ca8a04;" id="kpiForRelease">—</div>
          <div class="sub" id="subForRelease">—</div>
        </div>
        <div class="kpi-card">
          <h4>Not In Release</h4>
          <div class="val" style="color: #dc2626;" id="kpiNotInRelease">—</div>
          <div class="sub" id="subNotInRelease">—</div>
        </div>
      </div>
      <div class="chart-card">
        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.85rem; color: #64748b;">Count & % of Release Status</h4>
        <div style="width: 250px; height: 250px;">
          <canvas id="statusChart"></canvas>
        </div>
      </div>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th onclick="sortTable('Sprint')">Sprint <span class="th-sort-icon" id="sort-Sprint">↕</span></th>
            <th onclick="sortTable('Change ID')">Change ID <span class="th-sort-icon" id="sort-Change ID">↕</span></th>
            <th onclick="sortTable('Title')">Title <span class="th-sort-icon" id="sort-Title">↕</span></th>
            <th onclick="sortTable('Change Owner')">Developer (Owner) <span class="th-sort-icon" id="sort-Change Owner">↕</span></th>
            <th onclick="sortTable('Assigned QA')">Assigned QA <span class="th-sort-icon" id="sort-Assigned QA">↕</span></th>
            <th onclick="sortTable('Change Stage')">Change Stage <span class="th-sort-icon" id="sort-Change Stage">↕</span></th>
            <th onclick="sortTable('Change Status')">Change Status <span class="th-sort-icon" id="sort-Change Status">↕</span></th>
            <th onclick="sortTable('Change Type')">Change Type <span class="th-sort-icon" id="sort-Change Type">↕</span></th>
            <th onclick="sortTable('Created Time')">Created Time <span class="th-sort-icon" id="sort-Created Time">↕</span></th>
            <th onclick="sortTable('Release Status')">Release Status <span class="th-sort-icon" id="sort-Release Status">↕</span></th>
          </tr>
        </thead>
        <tbody id="tableBody">
          <tr><td colspan="10">Loading data...</td></tr>
        </tbody>
      </table>
    </div>

    <div class="pagination-footer">
      <div class="filter-group">
        <label for="rowsPerPageSelect">Rows per page:</label>
        <select id="rowsPerPageSelect" onchange="changeRowsPerPage()">
          <option value="10" selected>10</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </select>
      </div>
      <span id="paginationInfo">Showing 0-0 of 0 items</span>
      <div class="pagination-controls">
        <button type="button" class="btn btn-neutral" id="prevBtn" onclick="prevPage()">Previous</button>
        <span id="pageNumberDisplay" style="font-weight: 600;">Page 1</span>
        <button type="button" class="btn btn-neutral" id="nextBtn" onclick="nextPage()">Next</button>
      </div>
    </div>
  </div>
  <?php else : ?>
  <div class="db-offline-panel">
    <p>No ticket data is shown until the application can connect to MySQL.</p>
    <ol>
      <li>Edit <code>api/config.php</code> with your database credentials.</li>
      <li>In phpMyAdmin, import <code>database/schema.sql</code>, then <code>database/migrate.sql</code> (optional demo: <code>database/seed.sql</code>).</li>
      <li>See <code>README.md</code> for safe updates without deleting existing data.</li>
      <li>Reload this page.</li>
    </ol>
  </div>
  <?php endif; ?>

  <div class="dropdown-menu" id="actionMenu">
    <button type="button" onclick="viewCurrentTicket()">👁 View</button>
    <button type="button" onclick="editCurrentTicket()">✏️ Edit</button>
    <button type="button" onclick="viewTicketHistory()">📜 History</button>
    <button type="button" class="danger" onclick="deleteCurrentTicket()">🗑 Delete</button>
  </div>

  <div class="modal-overlay" id="ticketModal">
    <div class="modal modal-ticket">
      <h3 id="modalTitle">Add New Ticket Data</h3>
      <div class="modal-body">
      <input type="hidden" id="editingTicketId" value="">
      <div class="form-group">
        <label>Sprint Category</label>
        <select id="newTicketSprint"></select>
      </div>
      <div class="transfer-box" id="transferSprintSection" hidden>
        <div class="transfer-box-title">Transfer Ticket to Other Sprint</div>
        <div class="form-group" style="margin-bottom: 0.5rem;">
          <label for="targetSprintSelect">Target Sprint:</label>
          <select id="targetSprintSelect"></select>
        </div>
        <button type="button" class="btn btn-transfer" id="transferSprintBtn" onclick="transferTicketSprint()">Transfer Sprint</button>
      </div>
      <div class="form-group">
        <label>Change ID (e.g., CH-1520)</label>
        <input type="text" id="newTicketId" placeholder="CH-XXXX">
      </div>
      <div class="form-group">
        <label>Title / Description</label>
        <input type="text" id="newTicketTitle" placeholder="Describe the change...">
      </div>
      <div class="form-group">
        <label>Developer (Change Owner)</label>
        <select id="newTicketDev"></select>
      </div>
      <div class="form-group">
        <label>Assigned QA</label>
        <select id="newTicketQA"></select>
      </div>
      <div class="form-group">
        <label>Change Stage</label>
        <select id="newTicketStage">
          <option value="Planning">Planning</option>
          <option value="Development">Development</option>
          <option value="Leader Code Review">Leader Code Review</option>
          <option value="UAT">UAT</option>
          <option value="Implementation">Implementation</option>
          <option value="Release">Release</option>
          <option value="Close">Close</option>
        </select>
      </div>
      <div class="form-group">
        <label>Change Status</label>
        <select id="newTicketChangeStatus">
          <option value="Active">Active</option>
          <option value="Pending - For RCA">Pending - For RCA</option>
          <option value="Development In Progress">Development In Progress</option>
          <option value="Leader Code Review">Leader Code Review</option>
          <option value="Dev Rework">Dev Rework</option>
          <option value="For UAT">For UAT</option>
          <option value="Release Associated">Release Associated</option>
          <option value="Open">Open</option>
          <option value="Completed">Completed</option>
        </select>
      </div>
      <div class="form-group">
        <label>Change Type</label>
        <select id="newTicketType">
          <option value="Emergency">Emergency</option>
          <option value="Normal">Normal</option>
          <option value="Standard">Standard</option>
        </select>
      </div>
      <div class="form-group">
        <label>Release Status</label>
        <select id="newTicketStatus">
          <option value="For release">For release</option>
          <option value="Released">Released</option>
          <option value="Not in release">Not in release</option>
        </select>
      </div>
      <div class="form-group">
        <label for="ticketRemarks">Remarks / Notes:</label>
        <textarea id="ticketRemarks" rows="3" placeholder="Add remarks or reason for sprint transfer..."></textarea>
      </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-reset" onclick="closeModal('ticketModal')">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveTicketBtn" onclick="submitTicket()">Save Ticket</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="sprintModal">
    <div class="modal">
      <h3>Create New Sprint Category</h3>
      <div class="form-group">
        <label>Sprint Name (e.g., Sprint 11, Sprint 12)</label>
        <input type="text" id="newSprintInput" placeholder="Sprint Name">
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-reset" onclick="closeModal('sprintModal')">Cancel</button>
        <button type="button" class="btn btn-secondary" onclick="submitSprint()">Add Sprint</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="memberModal">
    <div class="modal">
      <h3>Add New Team Member</h3>
      <div class="form-group">
        <label>Full Name</label>
        <input type="text" id="newMemberName" placeholder="John Doe">
      </div>
      <div class="form-group">
        <label>Role</label>
        <select id="newMemberRole">
          <option value="Developer">Developer</option>
          <option value="QA">QA</option>
        </select>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-reset" onclick="closeModal('memberModal')">Cancel</button>
        <button type="button" class="btn btn-neutral" onclick="submitMember()">Add Member</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="historyModal">
    <div class="modal modal-wide">
      <h3 id="historyModalTitle">Ticket History</h3>
      <p id="historyModalSubtitle" class="history-subtitle"></p>
      <div id="historyLoading" class="history-loading" hidden>Loading history…</div>
      <div id="historyEmpty" class="history-empty" hidden>No actions recorded yet for this ticket.</div>
      <div class="history-table-wrap" id="historyTableWrap" hidden>
        <table class="history-table">
          <thead>
            <tr>
              <th>When</th>
              <th>Action</th>
              <th>Details</th>
            </tr>
          </thead>
          <tbody id="historyTableBody"></tbody>
        </table>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-reset" onclick="closeModal('historyModal')">Close</button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="importModal">
    <div class="modal">
      <h3>Import / Export Ticket Data</h3>
      <div class="import-box">
        <p style="font-size:0.85rem; color:#475569; margin:0;">Download template or export existing system data:</p>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
          <button type="button" class="btn btn-secondary" onclick="downloadXlsxTemplate()">📥 Download XLSX Template</button>
          <button type="button" class="btn btn-neutral" onclick="exportAllExistingFiles()">📤 Export All Existing Data</button>
        </div>
      </div>
      <div class="form-group">
        <label>Select Spreadsheet File (.xlsx, .xls, or .csv)</label>
        <input type="file" id="xlsxFileInput" accept=".xlsx, .xls, .csv">
        <small style="color:#64748b; font-size: 0.75rem; margin-top: 0.25rem;">Note: If imported tickets contain an existing <b>Change ID</b>, their records will be overwritten.</small>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-reset" onclick="closeModal('importModal')">Cancel</button>
        <button type="button" class="btn btn-primary" onclick="uploadXlsxData()">Upload Data</button>
      </div>
    </div>
  </div>

  <script>
    window.APP_DB = {
      connected: <?php echo $dbConnected ? 'true' : 'false'; ?>,
      message: <?php echo json_encode($dbMessage, JSON_UNESCAPED_UNICODE); ?>
    };
  </script>
  <script>
<?php
$jsPath = __DIR__ . '/assets/js/app.js';
if (is_readable($jsPath)) {
    readfile($jsPath);
} else {
    echo 'console.error("QA Release Monitoring: assets/js/app.js not found on server — upload the assets/ folder.");';
}
?>
  </script>
</body>
</html>
