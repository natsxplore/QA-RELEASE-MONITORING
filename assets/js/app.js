Chart.register(ChartDataLabels);

const API = {
  data: 'api/data.php',
  ticketCreate: 'api/tickets/create.php',
  ticketUpdate: 'api/tickets/update.php',
  ticketDelete: 'api/tickets/delete.php',
  ticketImport: 'api/tickets/import.php',
  ticketHistory: 'api/tickets/history.php',
  ticketTransferSprint: 'api/tickets/transfer_sprint.php',
  sprintCreate: 'api/sprints/create.php',
  memberCreate: 'api/members/create.php',
};

let rawData = [];
let customSprints = [];
let developers = [];
let qaMembers = [];
let statusChartInstance = null;

let dbConnected = window.APP_DB && window.APP_DB.connected === true;
let dbConnectionMessage = (window.APP_DB && window.APP_DB.message) || '';

let currentSortColumn = null;
let currentSortAsc = true;
let activeMenuTicketId = null;
let currentPage = 1;
let rowsPerPage = 10;

function requireDatabaseConnection(actionLabel) {
  if (dbConnected) return true;
  alert(dbConnectionMessage || 'Database is not connected.');
  return false;
}

function setDatabaseDisconnected(message) {
  dbConnected = false;
  dbConnectionMessage = message || 'Database is not connected.';
  rawData = [];
  customSprints = [];
  developers = [];
  qaMembers = [];

  document.body.classList.add('db-offline');
  const alertEl = document.getElementById('dbAlert');
  const msgEl = document.getElementById('dbAlertMessage');
  if (alertEl) alertEl.hidden = false;
  if (msgEl) msgEl.textContent = dbConnectionMessage;

  const panel = document.getElementById('appDataPanel');
  if (panel) panel.hidden = true;
  const filters = document.getElementById('filtersBar');
  if (filters) filters.hidden = true;
  const actions = document.getElementById('actionBtnGroup');
  if (actions) actions.hidden = true;

  if (statusChartInstance) {
    statusChartInstance.destroy();
    statusChartInstance = null;
  }
}

async function apiPost(url, body) {
  let res;
  try {
    res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
  } catch (err) {
    console.error(err);
    return {
      success: false,
      connected: false,
      message: 'Unable to reach the server API.',
    };
  }

  const rawText = await res.text();
  let data;
  try {
    data = rawText ? JSON.parse(rawText) : null;
  } catch (err) {
    console.error(err, rawText);
    const hint =
      res.status >= 500
        ? ' Server error — check that database/migrate.sql was imported (release_status rows).'
        : '';
    return {
      success: false,
      connected: res.status === 503 ? false : true,
      message: `Invalid server response.${hint}`,
    };
  }

  if (typeof data !== 'object' || data === null) {
    return {
      success: false,
      connected: false,
      message: 'Invalid server response.',
    };
  }

  if (data.connected === false) {
    setDatabaseDisconnected(data.message || 'Database is not connected.');
  }

  if (data.success === false && !data.message) {
    data.message = 'Request failed.';
  }

  return data;
}

function parseDate(dateStr) {
  if (!dateStr) return null;
  const parts = String(dateStr).split(' ')[0].split('-');
  if (parts.length === 3) {
    if (parts[0].length === 4) return new Date(parts[0], parts[1] - 1, parts[2]);
    return new Date(parts[2], parts[0] - 1, parts[1]);
  }
  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? null : d;
}

function fillFilterSelect(elementId, values, allLabel) {
  const select = document.getElementById(elementId);
  const previous = select.value;
  select.innerHTML = `<option value="all">${allLabel}</option>`;
  [...values].sort().forEach((value) => {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = value;
    select.appendChild(opt);
  });
  if ([...select.options].some((o) => o.value === previous)) {
    select.value = previous;
  }
}

function findTicketById(id) {
  return rawData.find((t) => t.id === id);
}

async function fetchDBData() {
  if (!window.APP_DB || window.APP_DB.connected !== true) {
    setDatabaseDisconnected(window.APP_DB && window.APP_DB.message);
    return;
  }

  try {
    const res = await fetch(API.data);
    const json = await res.json();
    dbConnected = json.connected === true && json.success === true;

    if (!dbConnected) {
      setDatabaseDisconnected(json.message || 'Unable to connect to the database.');
      return;
    }

    rawData = json.data.tickets || [];
    customSprints = json.data.sprints || [];
    developers = json.data.developers || [];
    qaMembers = json.data.qaMembers || [];

    document.body.classList.remove('db-offline');
    const alertEl = document.getElementById('dbAlert');
    if (alertEl) alertEl.hidden = true;
    const panel = document.getElementById('appDataPanel');
    if (panel) panel.hidden = false;
    const filters = document.getElementById('filtersBar');
    if (filters) filters.hidden = false;
    const actions = document.getElementById('actionBtnGroup');
    if (actions) actions.hidden = false;
  } catch (err) {
    console.error(err);
    setDatabaseDisconnected('Unable to reach the server API.');
    return;
  }

  populateDropdowns();
  renderApp();
}

function populateDropdowns() {
  const stages = [...new Set(rawData.map((d) => d['Change Stage']).filter(Boolean))];
  const changeStatuses = [...new Set(rawData.map((d) => d['Change Status']).filter(Boolean))];
  const types = [...new Set(rawData.map((d) => d['Change Type']).filter(Boolean))];

  fillFilterSelect('stageFilter', stages, 'All Stages');
  fillFilterSelect('changeStatusFilter', changeStatuses, 'All Statuses');
  fillFilterSelect('typeFilter', types, 'All Types');

  const sprintSelect = document.getElementById('sprintFilter');
  const modalSprintSelect = document.getElementById('newTicketSprint');
  const allSprints = [...new Set([...customSprints, ...rawData.map((d) => d.Sprint || 'Sprint 10')])];

  sprintSelect.innerHTML = '<option value="all">All Sprints</option>';
  modalSprintSelect.innerHTML = '';
  allSprints.forEach((s) => {
    const opt1 = document.createElement('option');
    opt1.value = s;
    opt1.textContent = s;
    sprintSelect.appendChild(opt1);

    const opt2 = document.createElement('option');
    opt2.value = s;
    opt2.textContent = s;
    modalSprintSelect.appendChild(opt2);
  });

  const modalDev = document.getElementById('newTicketDev');
  if (developers.length === 0) {
    modalDev.innerHTML = '<option value="">— Add a Developer first —</option>';
  } else {
    modalDev.innerHTML = developers
      .map((d) => `<option value="${escapeHtml(d)}">${escapeHtml(d)}</option>`)
      .join('');
  }

  const modalQA = document.getElementById('newTicketQA');
  if (qaMembers.length === 0) {
    modalQA.innerHTML = '<option value="Not Assigned">Not Assigned</option>';
  } else {
    modalQA.innerHTML = qaMembers
      .map((q) => `<option value="${escapeHtml(q)}">${escapeHtml(q)}</option>`)
      .join('');
  }
}

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function onFilterChange() {
  currentPage = 1;
  renderApp();
}

function changeRowsPerPage() {
  const select = document.getElementById('rowsPerPageSelect');
  if (!select) return;
  rowsPerPage = parseInt(select.value, 10) || 10;
  currentPage = 1;
  renderApp();
}

function prevPage() {
  if (currentPage > 1) {
    currentPage -= 1;
    renderApp();
  }
}

function nextPage() {
  currentPage += 1;
  renderApp();
}

function ensureSelectOption(selectId, value) {
  if (!value) return;
  const select = document.getElementById(selectId);
  if (!select) return;
  if (![...select.options].some((o) => o.value === value)) {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = value;
    select.appendChild(opt);
  }
}

function sortTable(columnKey) {
  if (!dbConnected) return;
  if (currentSortColumn === columnKey) {
    currentSortAsc = !currentSortAsc;
  } else {
    currentSortColumn = columnKey;
    currentSortAsc = true;
  }

  document.querySelectorAll('.th-sort-icon').forEach((icon) => {
    icon.textContent = '↕';
  });
  const activeIcon = document.getElementById(`sort-${columnKey}`);
  if (activeIcon) activeIcon.textContent = currentSortAsc ? '↑' : '↓';

  renderApp();
}

function renderApp() {
  if (!dbConnected) {
    return;
  }

  const titleVal = document.getElementById('titleFilter').value.toLowerCase().trim();
  const stageVal = document.getElementById('stageFilter').value;
  const changeStatusVal = document.getElementById('changeStatusFilter').value;
  const typeVal = document.getElementById('typeFilter').value;
  const sprintVal = document.getElementById('sprintFilter').value;
  const statusVal = document.getElementById('statusFilter').value;
  const dateFromVal = document.getElementById('dateFrom').value
    ? new Date(document.getElementById('dateFrom').value)
    : null;
  const dateToVal = document.getElementById('dateTo').value
    ? new Date(document.getElementById('dateTo').value)
    : null;

  if (dateToVal) dateToVal.setHours(23, 59, 59, 999);

  let filtered = rawData.filter((d) => {
    const titleMatch = !titleVal || (d.Title && d.Title.toLowerCase().includes(titleVal));
    const matchStage = stageVal === 'all' || d['Change Stage'] === stageVal;
    const matchChangeStatus = changeStatusVal === 'all' || d['Change Status'] === changeStatusVal;
    const matchType = typeVal === 'all' || d['Change Type'] === typeVal;
    const matchSprint = sprintVal === 'all' || (d.Sprint || 'Sprint 10') === sprintVal;
    const matchStatus = statusVal === 'all' || d['Release Status'] === statusVal;

    const createdDate = parseDate(d['Created Time']);
    let matchDate = true;
    if (createdDate) {
      if (dateFromVal && createdDate < dateFromVal) matchDate = false;
      if (dateToVal && createdDate > dateToVal) matchDate = false;
    }

    return titleMatch && matchStage && matchChangeStatus && matchType && matchSprint && matchStatus && matchDate;
  });

  if (currentSortColumn) {
    filtered.sort((a, b) => {
      let valA = a[currentSortColumn] || '';
      let valB = b[currentSortColumn] || '';

      if (currentSortColumn === 'Created Time') {
        valA = parseDate(valA) || 0;
        valB = parseDate(valB) || 0;
      } else {
        valA = valA.toString().toLowerCase();
        valB = valB.toString().toLowerCase();
      }

      if (valA < valB) return currentSortAsc ? -1 : 1;
      if (valA > valB) return currentSortAsc ? 1 : -1;
      return 0;
    });
  }

  const totalCount = filtered.length;
  const releasedCount = filtered.filter((d) => d['Release Status'] === 'Released').length;
  const forReleaseCount = filtered.filter((d) => d['Release Status'] === 'For release').length;
  const notInReleaseCount = filtered.filter((d) => d['Release Status'] === 'Not in release').length;

  const releasedPct = totalCount ? ((releasedCount / totalCount) * 100).toFixed(1) : '0.0';
  const forReleasePct = totalCount ? ((forReleaseCount / totalCount) * 100).toFixed(1) : '0.0';
  const notInReleasePct = totalCount ? ((notInReleaseCount / totalCount) * 100).toFixed(1) : '0.0';

  document.getElementById('kpiTotal').textContent = totalCount;
  document.getElementById('kpiReleased').textContent = releasedCount;
  document.getElementById('subReleased').textContent = `${releasedPct}%`;

  document.getElementById('kpiForRelease').textContent = forReleaseCount;
  document.getElementById('subForRelease').textContent = `${forReleasePct}%`;

  document.getElementById('kpiNotInRelease').textContent = notInReleaseCount;
  document.getElementById('subNotInRelease').textContent = `${notInReleasePct}%`;

  const totalPages = Math.ceil(totalCount / rowsPerPage) || 1;
  if (currentPage > totalPages) currentPage = totalPages;
  if (currentPage < 1) currentPage = 1;

  const startIdx = (currentPage - 1) * rowsPerPage;
  const endIdx = Math.min(startIdx + rowsPerPage, totalCount);
  const paginatedItems = filtered.slice(startIdx, endIdx);

  const paginationInfo = document.getElementById('paginationInfo');
  const pageNumberDisplay = document.getElementById('pageNumberDisplay');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  if (paginationInfo) {
    paginationInfo.textContent =
      totalCount === 0
        ? 'Showing 0-0 of 0 items'
        : `Showing ${startIdx + 1}-${endIdx} of ${totalCount} items`;
  }
  if (pageNumberDisplay) {
    pageNumberDisplay.textContent = `Page ${currentPage} of ${totalPages}`;
  }
  if (prevBtn) prevBtn.disabled = currentPage <= 1;
  if (nextBtn) nextBtn.disabled = currentPage >= totalPages;

  const tbody = document.getElementById('tableBody');
  tbody.innerHTML = '';

  if (paginatedItems.length === 0) {
    tbody.innerHTML =
      '<tr><td colspan="10" style="text-align:center; color:#64748b;">No matching tickets found.</td></tr>';
  } else {
    paginatedItems.forEach((row) => {
      const tr = document.createElement('tr');
      const releaseStatus = row['Release Status'] || '';
      let tagClass = '';
      if (releaseStatus === 'Released') tagClass = 'tag-released';
      else if (releaseStatus === 'For release') tagClass = 'tag-for-release';
      else if (releaseStatus === 'Not in release') tagClass = 'tag-not-in-release';

      const devName = row['Change Owner'] || '-';
      const sprintName = row.Sprint || 'Sprint 10';
      const ticketId = row.id;

      tr.innerHTML = `
          <td><strong>${escapeHtml(sprintName)}</strong></td>
          <td><strong>${escapeHtml(row['Change ID'] || '')}</strong></td>
          <td class="title-col" title="${escapeHtml(row.Title || '')}">${escapeHtml(row.Title || '')}</td>
          <td><strong>${escapeHtml(devName)}</strong></td>
          <td>${escapeHtml(row['Assigned QA'] || '-')}</td>
          <td>${escapeHtml(row['Change Stage'] || 'Development')}</td>
          <td>${escapeHtml(row['Change Status'] || 'In Progress')}</td>
          <td>${escapeHtml(row['Change Type'] || '-')}</td>
          <td>${escapeHtml(row['Created Time'] || '-')}</td>
          <td>
            <div class="action-cell">
              <span class="status-tag ${tagClass}">${escapeHtml(releaseStatus)}</span>
              <button class="menu-btn" onclick="toggleActionMenu(event, ${ticketId})">⋮</button>
            </div>
          </td>
        `;
      tbody.appendChild(tr);
    });
  }

  updateChart(releasedCount, forReleaseCount, notInReleaseCount, totalCount);
}

function toggleActionMenu(event, ticketId) {
  event.stopPropagation();
  activeMenuTicketId = ticketId;
  const menu = document.getElementById('actionMenu');

  const rect = event.target.getBoundingClientRect();
  menu.style.top = `${rect.bottom + window.scrollY}px`;
  menu.style.left = `${rect.left - 80 + window.scrollX}px`;
  menu.style.display = 'flex';
}

document.addEventListener('click', () => {
  document.getElementById('actionMenu').style.display = 'none';
});

function openCreateModal() {
  if (!requireDatabaseConnection('add a ticket')) return;
  document.getElementById('editingTicketId').value = '';
  document.getElementById('modalTitle').textContent = 'Add New Ticket Data';
  document.getElementById('saveTicketBtn').style.display = 'inline-block';
  document.getElementById('transferSprintSection').hidden = true;
  setFormDisabled(false);

  document.getElementById('newTicketId').value = '';
  document.getElementById('newTicketId').disabled = false;
  document.getElementById('newTicketTitle').value = '';
  document.getElementById('newTicketStage').value = 'Development';
  document.getElementById('newTicketChangeStatus').value = 'Active';
  document.getElementById('ticketRemarks').value = '';
  openModal('ticketModal');
}

function viewCurrentTicket() {
  if (activeMenuTicketId == null) return;
  const ticket = findTicketById(activeMenuTicketId);
  if (!ticket) return;

  document.getElementById('editingTicketId').value = ticket.id;
  document.getElementById('modalTitle').textContent = 'View Ticket Details';
  document.getElementById('saveTicketBtn').style.display = 'none';
  document.getElementById('transferSprintSection').hidden = true;

  fillModalFields(ticket);
  setFormDisabled(true);
  openModal('ticketModal');
}

function formatHistoryDetails(entry) {
  const changes = entry.changes || [];
  if (changes.length > 0) {
    return changes
      .map((c) => {
        const from = c.from === null || c.from === undefined || c.from === '' ? '—' : c.from;
        const to = c.to === null || c.to === undefined || c.to === '' ? '—' : c.to;
        return `<div class="history-change-line"><strong>${escapeHtml(c.field)}:</strong> ${escapeHtml(String(from))} → ${escapeHtml(String(to))}</div>`;
      })
      .join('');
  }
  if (entry.snapshot && entry.action === 'deleted') {
    return `<div class="history-change-line">Removed ticket <strong>${escapeHtml(entry.snapshot['Change ID'] || entry.change_id)}</strong> — ${escapeHtml(entry.snapshot.Title || '')}</div>`;
  }
  if (entry.snapshot) {
    const snap = entry.snapshot;
    return `<div class="history-change-line">${escapeHtml(snap.Title || '')} (${escapeHtml(snap['Release Status'] || '')})</div>`;
  }
  return '—';
}

async function viewTicketHistory() {
  if (!requireDatabaseConnection('view ticket history')) return;
  if (activeMenuTicketId == null) return;
  const ticket = findTicketById(activeMenuTicketId);
  if (!ticket) return;

  document.getElementById('historyModalTitle').textContent = 'Ticket History';
  document.getElementById('historyModalSubtitle').textContent = `${ticket['Change ID']} — ${ticket.Title || ''}`;
  document.getElementById('historyLoading').hidden = false;
  document.getElementById('historyEmpty').hidden = true;
  document.getElementById('historyTableWrap').hidden = true;
  document.getElementById('historyTableBody').innerHTML = '';
  openModal('historyModal');

  let result;
  try {
    const res = await fetch(`${API.ticketHistory}?id=${encodeURIComponent(ticket.id)}`);
    result = await res.json();
  } catch (err) {
    console.error(err);
    document.getElementById('historyLoading').hidden = true;
    alert('Unable to load ticket history.');
    return;
  }

  document.getElementById('historyLoading').hidden = true;

  if (!result.success) {
    alert(result.message || 'Unable to load ticket history.');
    closeModal('historyModal');
    if (result.connected === false) {
      setDatabaseDisconnected(result.message);
    }
    return;
  }

  const history = (result.data && result.data.history) || [];
  if (history.length === 0) {
    document.getElementById('historyEmpty').hidden = false;
    return;
  }

  const tbody = document.getElementById('historyTableBody');
  history.forEach((entry) => {
    const tr = document.createElement('tr');
    const actionClass = `action-${(entry.action || 'updated').replace(/_/g, '-')}`;
    tr.innerHTML = `
      <td>${escapeHtml(entry.created_at || '')}</td>
      <td><span class="history-action ${actionClass}">${escapeHtml(entry.action_label || entry.action || '')}</span></td>
      <td>${formatHistoryDetails(entry)}</td>
    `;
    tbody.appendChild(tr);
  });
  document.getElementById('historyTableWrap').hidden = false;
}

function editCurrentTicket() {
  if (activeMenuTicketId == null) return;
  const ticket = findTicketById(activeMenuTicketId);
  if (!ticket) return;

  document.getElementById('editingTicketId').value = ticket.id;
  document.getElementById('modalTitle').textContent = 'Edit Ticket Details';
  document.getElementById('saveTicketBtn').style.display = 'inline-block';
  document.getElementById('transferSprintSection').hidden = false;

  fillModalFields(ticket);
  setFormDisabled(false);
  document.getElementById('newTicketId').disabled = true;
  openModal('ticketModal');
}

function fillModalFields(ticket) {
  document.getElementById('newTicketSprint').value = ticket.Sprint || customSprints[0] || 'Sprint 10';
  document.getElementById('newTicketId').value = ticket['Change ID'] || '';
  document.getElementById('newTicketTitle').value = ticket.Title || '';

  const devSelect = document.getElementById('newTicketDev');
  if (ticket['Change Owner'] && ![...devSelect.options].some((o) => o.value === ticket['Change Owner'])) {
    const opt = document.createElement('option');
    opt.value = ticket['Change Owner'];
    opt.textContent = ticket['Change Owner'];
    devSelect.appendChild(opt);
  }
  devSelect.value = ticket['Change Owner'] || developers[0] || '';

  const qaSelect = document.getElementById('newTicketQA');
  const qaVal = ticket['Assigned QA'];
  if (qaVal && qaVal !== 'Not Assigned' && ![...qaSelect.options].some((o) => o.value === qaVal)) {
    const opt = document.createElement('option');
    opt.value = qaVal;
    opt.textContent = qaVal;
    qaSelect.appendChild(opt);
  }
  qaSelect.value = qaVal && qaVal !== 'Not Assigned' ? qaVal : qaMembers[0] || '';

  const stageVal = ticket['Change Stage'] || 'Development';
  const statusVal = ticket['Change Status'] || 'Active';
  ensureSelectOption('newTicketStage', stageVal);
  ensureSelectOption('newTicketChangeStatus', statusVal);
  document.getElementById('newTicketStage').value = stageVal;
  document.getElementById('newTicketChangeStatus').value = statusVal;

  document.getElementById('newTicketType').value = ticket['Change Type'] || 'Normal';
  document.getElementById('newTicketStatus').value = ticket['Release Status'] || 'For release';
  document.getElementById('ticketRemarks').value = ticket.Remarks || '';

  const currentSprint = ticket.Sprint || customSprints[0] || 'Sprint 10';
  const allSprints = [...new Set([...customSprints, ...rawData.map((d) => d.Sprint || 'Sprint 10')])];
  const otherSprints = allSprints.filter((s) => s !== currentSprint);
  const targetSelect = document.getElementById('targetSprintSelect');
  if (otherSprints.length === 0) {
    targetSelect.innerHTML = '<option value="">No other sprint available</option>';
  } else {
    targetSelect.innerHTML = otherSprints
      .map((s) => `<option value="${escapeHtml(s)}">${escapeHtml(s)}</option>`)
      .join('');
  }
}

async function transferTicketSprint() {
  if (!requireDatabaseConnection('transfer a ticket')) return;
  const idVal = document.getElementById('editingTicketId').value;
  if (!idVal) return;

  const ticket = findTicketById(parseInt(idVal, 10));
  if (!ticket) return;

  const targetSprint = document.getElementById('targetSprintSelect').value;
  const currentSprint = ticket.Sprint || 'Sprint 10';
  const transferReason = document.getElementById('ticketRemarks').value.trim();

  if (!targetSprint) {
    alert('Please select a target Sprint to transfer.');
    return;
  }
  if (targetSprint === currentSprint) {
    alert('Target sprint must be different from current sprint.');
    return;
  }

  const result = await apiPost(API.ticketTransferSprint, {
    id: parseInt(idVal, 10),
    targetSprint,
    transferReason,
  });

  if (!result.success) {
    alert(result.message || 'Unable to transfer ticket.');
    return;
  }

  const updated = result.data && result.data.ticket;
  if (updated) {
    document.getElementById('newTicketSprint').value = updated.Sprint || targetSprint;
    document.getElementById('ticketRemarks').value = updated.Remarks || '';
    fillModalFields(updated);
  }

  await fetchDBData();
  alert(`Ticket successfully transferred to ${targetSprint}!`);
}

function setFormDisabled(disabled) {
  document.getElementById('newTicketSprint').disabled = disabled;
  document.getElementById('newTicketId').disabled = disabled;
  document.getElementById('newTicketTitle').disabled = disabled;
  document.getElementById('newTicketDev').disabled = disabled;
  document.getElementById('newTicketQA').disabled = disabled;
  document.getElementById('newTicketStage').disabled = disabled;
  document.getElementById('newTicketChangeStatus').disabled = disabled;
  document.getElementById('newTicketType').disabled = disabled;
  document.getElementById('newTicketStatus').disabled = disabled;
  document.getElementById('ticketRemarks').disabled = disabled;
  document.getElementById('targetSprintSelect').disabled = disabled;
  document.getElementById('transferSprintBtn').disabled = disabled;
}

async function deleteCurrentTicket() {
  if (!requireDatabaseConnection('delete a ticket')) return;
  if (activeMenuTicketId == null) return;
  const ticket = findTicketById(activeMenuTicketId);
  if (!ticket) return;

  if (!confirm('Are you sure you want to delete this ticket?')) return;

  const result = await apiPost(API.ticketDelete, { id: ticket.id });
  if (!result.success) {
    alert(result.message || 'Unable to delete ticket.');
    return;
  }
  await fetchDBData();
}

function updateChart(released, forRelease, notInRelease, total) {
  const ctx = document.getElementById('statusChart').getContext('2d');
  if (statusChartInstance) statusChartInstance.destroy();

  statusChartInstance = new Chart(ctx, {
    type: 'pie',
    data: {
      labels: ['Released', 'For release', 'Not in release'],
      datasets: [
        {
          data: [released, forRelease, notInRelease],
          backgroundColor: ['#86efac', '#fef08a', '#fca5a5'],
          borderColor: ['#16a34a', '#ca8a04', '#dc2626'],
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
        tooltip: {
          callbacks: {
            label(context) {
              const value = context.raw || 0;
              const percentage = total ? ((value / total) * 100).toFixed(1) : 0;
              return ` ${context.label}: ${value} (${percentage}%)`;
            },
          },
        },
        datalabels: {
          color: '#1e293b',
          font: { weight: 'bold', size: 11 },
          formatter(value) {
            if (value === 0 || !total) return '';
            const percentage = ((value / total) * 100).toFixed(1);
            return `${value}\n(${percentage}%)`;
          },
          textAlign: 'center',
        },
      },
    },
  });
}

function openModal(id) {
  if (!requireDatabaseConnection('use this feature')) return;
  document.getElementById(id).style.display = 'flex';
}
function closeModal(id) {
  document.getElementById(id).style.display = 'none';
}

function downloadXlsxTemplate() {
  const templateData = [
    {
      Sprint: 'Sprint 11',
      'Change ID': 'CH-9001',
      Title: 'Sample Title - Add new login feature',
      'Change Owner': 'Earl Rhayan D. Padua',
      'Assigned QA': 'Mark Anthony A. Udarbe',
      'Change Stage': 'UAT',
      'Change Status': 'For UAT',
      'Change Type': 'Normal',
      'Created Time': '09-18-2026 10:00',
      'Release Status': 'For release',
      Remarks: '',
    },
  ];

  const worksheet = XLSX.utils.json_to_sheet(templateData);
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Tickets');
  XLSX.writeFile(workbook, 'QA_Tickets_Import_Template.xlsx');
}

function exportAllExistingFiles() {
  if (!requireDatabaseConnection('export data')) return;
  if (!rawData || rawData.length === 0) {
    alert('No data available to export.');
    return;
  }

  const exportArray = rawData.map((item) => ({
    Sprint: item.Sprint || 'Sprint 10',
    'Change ID': item['Change ID'] || '',
    Title: item.Title || '',
    'Change Owner': item['Change Owner'] || '',
    'Assigned QA': item['Assigned QA'] || '',
    'Change Stage': item['Change Stage'] || '',
    'Change Status': item['Change Status'] || '',
    'Change Type': item['Change Type'] || '',
    'Created Time': item['Created Time'] || '',
    'Release Status': item['Release Status'] || '',
    Remarks: item.Remarks || '',
  }));

  const worksheet = XLSX.utils.json_to_sheet(exportArray);
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'All_Tickets');
  const dateStr = new Date().toISOString().slice(0, 10);
  XLSX.writeFile(workbook, `QA_Tickets_Export_${dateStr}.xlsx`);
}

async function uploadXlsxData() {
  if (!requireDatabaseConnection('import data')) return;
  const fileInput = document.getElementById('xlsxFileInput');
  if (!fileInput.files || fileInput.files.length === 0) {
    alert('Please select an Excel file to upload.');
    return;
  }

  const file = fileInput.files[0];
  const reader = new FileReader();

  reader.onload = async function onLoad(e) {
    const data = new Uint8Array(e.target.result);
    const workbook = XLSX.read(data, { type: 'array' });
    const firstSheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[firstSheetName];
    const importedData = XLSX.utils.sheet_to_json(worksheet);

    if (importedData.length === 0) {
      alert('No valid data found in the imported file.');
      return;
    }

    const parsedRows = importedData.map((row) => ({
      Sprint: row.Sprint || 'Sprint 10',
      'Change ID': row['Change ID'] || `CH-${Math.floor(1000 + Math.random() * 9000)}`,
      Title: row.Title || 'Imported Change Request',
      'Change Owner': row['Change Owner'] || row.Developer || 'Unassigned',
      'Assigned QA': row['Assigned QA'] || 'Not Assigned',
      'Change Stage': row['Change Stage'] || 'Development',
      'Change Status': row['Change Status'] || 'Active',
      'Change Type': row['Change Type'] || 'Normal',
      'Created Time': row['Created Time'] || new Date().toLocaleString(),
      'Release Status': row['Release Status'] || 'For release',
      Remarks: row.Remarks || '',
    }));

    const result = await apiPost(API.ticketImport, { tickets: parsedRows });
    if (!result.success) {
      alert(result.message || 'Import failed.');
      return;
    }

    closeModal('importModal');
    fileInput.value = '';
    const { imported = 0, updated = 0, skipped = 0 } = result.data || {};
    currentPage = 1;
    alert(
      `Import completed!\n- ${updated} ticket(s) updated/overwritten\n- ${imported} new ticket(s) added` +
        (skipped > 0 ? `\n- ${skipped} row(s) skipped` : '') +
        '.'
    );
    await fetchDBData();
  };

  reader.readAsArrayBuffer(file);
}

async function submitTicket() {
  if (!requireDatabaseConnection('save a ticket')) return;
  const idVal = document.getElementById('editingTicketId').value;
  const isEdit = idVal !== '';
  const existing = isEdit ? findTicketById(parseInt(idVal, 10)) : null;

  const owner = document.getElementById('newTicketDev').value.trim();
  if (!owner) {
    alert('Please select or add a Developer (Change Owner). Use + New Dev / QA if the list is empty.');
    return;
  }

  const ticket = {
    Sprint: document.getElementById('newTicketSprint').value,
    'Change ID':
      document.getElementById('newTicketId').value ||
      `CH-${Math.floor(1000 + Math.random() * 9000)}`,
    Title: document.getElementById('newTicketTitle').value || 'New Change Request',
    'Change Owner': owner,
    'Assigned QA': document.getElementById('newTicketQA').value,
    'Change Type': document.getElementById('newTicketType').value,
    'Release Status': document.getElementById('newTicketStatus').value,
    'Change Stage': document.getElementById('newTicketStage').value,
    'Change Status': document.getElementById('newTicketChangeStatus').value,
    'Created Time': isEdit && existing ? existing['Created Time'] : undefined,
    Remarks: document.getElementById('ticketRemarks').value,
  };

  if (!ticket['Change ID'].trim()) {
    alert('Change ID is required.');
    return;
  }

  const url = isEdit ? API.ticketUpdate : API.ticketCreate;
  const body = isEdit ? { id: parseInt(idVal, 10), ticket } : { ticket };
  const result = await apiPost(url, body);

  if (!result.success) {
    alert(result.message || 'Unable to save ticket.');
    return;
  }

  closeModal('ticketModal');
  await fetchDBData();
}

async function submitSprint() {
  if (!requireDatabaseConnection('add a sprint')) return;
  const val = document.getElementById('newSprintInput').value.trim();
  if (!val) return;

  const result = await apiPost(API.sprintCreate, { sprintName: val });
  if (!result.success) {
    alert(result.message || 'Unable to add sprint.');
    return;
  }

  closeModal('sprintModal');
  document.getElementById('newSprintInput').value = '';
  await fetchDBData();
}

async function submitMember() {
  if (!requireDatabaseConnection('add a member')) return;
  const name = document.getElementById('newMemberName').value.trim();
  const role = document.getElementById('newMemberRole').value;
  if (!name) return;

  const result = await apiPost(API.memberCreate, { name, role });
  if (!result.success) {
    alert(result.message || 'Unable to add member.');
    return;
  }

  closeModal('memberModal');
  document.getElementById('newMemberName').value = '';
  await fetchDBData();
}

function resetFilters() {
  if (!dbConnected) return;
  document.getElementById('titleFilter').value = '';
  document.getElementById('dateFrom').value = '';
  document.getElementById('dateTo').value = '';
  document.getElementById('sprintFilter').value = 'all';
  document.getElementById('stageFilter').value = 'all';
  document.getElementById('changeStatusFilter').value = 'all';
  document.getElementById('typeFilter').value = 'all';
  document.getElementById('statusFilter').value = 'all';
  currentSortColumn = null;
  currentSortAsc = true;
  document.querySelectorAll('.th-sort-icon').forEach((icon) => {
    icon.textContent = '↕';
  });
  currentPage = 1;
  renderApp();
}

if (window.APP_DB && window.APP_DB.connected === true) {
  fetchDBData();
} else {
  setDatabaseDisconnected(window.APP_DB && window.APP_DB.message);
}
