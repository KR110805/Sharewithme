/* ============================================================
   ShareWithMe — Frontend Logic
   
   Clean, minimal interaction layer.
   Backend contract preserved: same POST fields to upload.php.
   ============================================================ */

const UPLOAD_URL = 'upload.php';
const DATA_URL   = 'data/data.json';

// New Modals and controls
const reportModal = document.getElementById('report-modal');
const reportOverlay = document.getElementById('report-modal-overlay');
const reportClose = document.getElementById('report-modal-close');
const reportForm = document.getElementById('report-form');
const reportTargetType = document.getElementById('report-target-type');
const reportTargetId = document.getElementById('report-target-id');
const reportDetails = document.getElementById('report-details');

const adminLoginModal = document.getElementById('admin-login-modal');
const adminLoginOverlay = document.getElementById('admin-login-modal-overlay');
const adminLoginClose = document.getElementById('admin-login-modal-close');
const adminLoginForm = document.getElementById('admin-login-form');
const adminPassphraseInput = document.getElementById('admin-passphrase');

const adminPanelModal = document.getElementById('admin-panel-modal');
const adminPanelOverlay = document.getElementById('admin-panel-modal-overlay');
const adminPanelClose = document.getElementById('admin-panel-modal-close');
const adminReportsTbody = document.getElementById('admin-reports-tbody');
const reportsCountSpan = document.getElementById('reports-count');
const adminLogoutBtn = document.getElementById('admin-logout-btn');
const navAdminTrigger = document.getElementById('nav-admin-trigger');

const duplicateModal = document.getElementById('duplicate-modal');
const duplicateOverlay = document.getElementById('duplicate-modal-overlay');
const duplicateClose = document.getElementById('duplicate-modal-close');
const duplicateFolderName = document.getElementById('duplicate-folder-name');
const duplicateOpenBtn = document.getElementById('duplicate-open-btn');
const duplicateForceBtn = document.getElementById('duplicate-force-btn');

const adminEditFolderModal = document.getElementById('admin-edit-folder-modal');
const adminEditFolderOverlay = document.getElementById('admin-edit-folder-modal-overlay');
const adminEditFolderClose = document.getElementById('admin-edit-folder-modal-close');
const adminEditFolderForm = document.getElementById('admin-edit-folder-form');
const editFolderIdInput = document.getElementById('edit-folder-id');
const editFolderNameInput = document.getElementById('edit-folder-name');
const editFolderDescInput = document.getElementById('edit-folder-description');
const editFolderNotesInput = document.getElementById('edit-folder-notes');

// Folder Admin buttons inside Folder Details Card
const currentFolderAdminBar = document.getElementById('current-folder-admin-bar');
const adminPinBtn = document.getElementById('admin-pin-btn');
const adminEditBtn = document.getElementById('admin-edit-btn');
const adminDeleteBtn = document.getElementById('admin-delete-btn');

// Contributor Name inputs
const fileUploadedBy = document.getElementById('file-uploaded-by');
const linkUploadedBy = document.getElementById('link-uploaded-by');
const folderCreatedBy = document.getElementById('folder-created-by');

// Base UI Elements
const searchInput = document.getElementById('search-input');
const foldersHeader = document.getElementById('folders-header');
const foldersGrid = document.getElementById('folders-grid');
const resourcesHeader = document.getElementById('resources-header');
const resourceGrid = document.getElementById('resource-grid');
const emptyState = document.getElementById('empty-state');
const breadcrumbsNav = document.getElementById('breadcrumbs');

// Folder details
const folderDetailsCard = document.getElementById('folder-details-card');
const currentFolderTitle = document.getElementById('current-folder-title');
const currentFolderDesc = document.getElementById('current-folder-desc');
const currentFolderNotes = document.getElementById('current-folder-notes');
const currentFolderNotesSection = document.getElementById('current-folder-notes-section');

// Navigation & Navbar
const navbar = document.getElementById('navbar');
const navLinkItems = document.querySelectorAll('.nav-link');
const navHamburger = document.getElementById('nav-hamburger');
const navLinks = document.getElementById('nav-links');

// Upload controls & components
const heroUploadBtn = document.getElementById('hero-upload-btn');
const navUploadTrigger = document.getElementById('nav-upload-trigger');
const uploadModal = document.getElementById('upload-modal');
const uploadOverlay = document.getElementById('upload-modal-overlay');
const uploadClose = document.getElementById('upload-modal-close');

const tabFileBtn = document.getElementById('tab-file-btn');
const tabLinkBtn = document.getElementById('tab-link-btn');
const fileFormContainer = document.getElementById('upload-form-file-container');
const linkFormContainer = document.getElementById('upload-form-link-container');
const fileDropZone = document.getElementById('file-drop-zone');
const fileInput = document.getElementById('resource-file');
const filePreviewContainer = document.getElementById('file-preview-container');
const filePreviewList = document.getElementById('file-preview-list');
const fileNameDisp = document.getElementById('file-name-display');

const progressContainer = document.getElementById('upload-progress-container');
const progressFill = document.getElementById('upload-progress-fill');
const progressText = document.getElementById('upload-progress-text');

const folderSelectFile = document.getElementById('upload-folder-file');
const folderSelectLink = document.getElementById('upload-folder-link');
const titleInput = document.getElementById('resource-title');
const descInput = document.getElementById('resource-description');
const linkInput = document.getElementById('resource-link');

const uploadFileBtn = document.getElementById('upload-file-btn');
const uploadLinkBtn = document.getElementById('upload-link-btn');
const fileForm = document.getElementById('upload-form-file');
const linkForm = document.getElementById('upload-form-link');

const newFolderBtn = document.getElementById('new-folder-btn');
const folderParent = document.getElementById('folder-parent');
const folderNameInput = document.getElementById('folder-name');
const folderDescInput = document.getElementById('folder-description');
const folderNotesInput = document.getElementById('folder-notes');
const folderModal = document.getElementById('folder-modal');
const folderOverlay = document.getElementById('folder-modal-overlay');
const folderClose = document.getElementById('folder-modal-close');
const folderForm = document.getElementById('folder-form');
const folderSubmitBtn = document.getElementById('folder-submit-btn');

const toastContainer = document.getElementById('toast-container');

// ── STATE ────────────────────────────────────────────────────
let allFolders      = [];
let allResources    = [];
let currentFolderId = null;
let uploadSource    = 'file';  // 'file' or 'link'
let selectedFiles   = [];
let isAdmin         = false;
let adminKey        = '';
let contributorName = localStorage.getItem('contributorName') || '';
let allReports      = [];
let pendingFolderData = null;


// ══════════════════════════════════════════════════════════════
// DATA LOADING
// ══════════════════════════════════════════════════════════════

// JS recursion helpers for stats
function getFolderAndChildrenIdsJs(folderId) {
  if (!folderId) return [];
  let ids = [folderId];
  let queue = [folderId];
  while (queue.length > 0) {
    const currentId = queue.shift();
    const children = (allFolders || []).filter(f => f && f.parentId === currentId);
    children.forEach(c => {
      if (c && c.id && !ids.includes(c.id)) {
        ids.push(c.id);
        queue.push(c.id);
      }
    });
  }
  return ids;
}

function getContributorsCount(folderId) {
  if (!folderId) return 0;
  const childFolderIds = getFolderAndChildrenIdsJs(folderId);
  const folderResources = (allResources || []).filter(r => r && r.folderId && childFolderIds.includes(r.folderId));
  const contributors = new Set();
  folderResources.forEach(r => {
    if (r && r.uploadedBy) {
      contributors.add(r.uploadedBy);
    }
  });
  return contributors.size;
}

function getFilesCount(folderId) {
  if (!folderId) return 0;
  const childFolderIds = getFolderAndChildrenIdsJs(folderId);
  return (allResources || []).filter(r => r && r.folderId && childFolderIds.includes(r.folderId) && r.type === 'file').length;
}

async function loadData() {
  try {
    const res = await fetch(DATA_URL + '?t=' + Date.now());
    if (!res.ok) { allFolders = []; allResources = []; allReports = []; render(); return; }
    const data = await res.json();
    console.log('Fetched data:', data);

    if (Array.isArray(data)) {
      // Legacy flat array — migrate client-side
      allFolders = [];
      allResources = data.map(r => ({
        ...r,
        folderId: r.folderId || null,
        resourceType: r.resourceType || (r.type === 'file' ? 'PDF' : 'Other')
      }));
      allReports = [];
    } else {
      allFolders   = data.folders   || [];
      allResources = data.resources || [];
      allReports   = data.reports   || [];
    }

    populateFolderSelects();
    updateNavbarAdminUI();
    render();
  } catch (e) {
    console.error('Load failed:', e);
    allFolders = []; allResources = []; allReports = [];
    render();
  }
}

function updateNavbarAdminUI() {
  if (!navAdminTrigger) return;
  if (isAdmin) {
    navAdminTrigger.classList.add('admin-active');
    const badgeText = document.getElementById('admin-badge-text');
    if (badgeText) badgeText.textContent = 'Admin Mode';
  } else {
    navAdminTrigger.classList.remove('admin-active');
    const badgeText = document.getElementById('admin-badge-text');
    if (badgeText) badgeText.textContent = 'Admin';
  }
}


// ══════════════════════════════════════════════════════════════
// FOLDER PATH HELPERS
// ══════════════════════════════════════════════════════════════

function getFolderPath(id) {
  if (!id) return '';
  const parts = [];
  let cur = (allFolders || []).find(f => f && f.id === id);
  while (cur) {
    parts.unshift(cur.name || 'Untitled');
    cur = cur.parentId ? (allFolders || []).find(f => f && f.id === cur.parentId) : null;
  }
  return parts.join(' › ');
}

function populateFolderSelects() {
  const opts = (allFolders || [])
    .filter(f => f && f.id)
    .map(f => ({ id: f.id, path: getFolderPath(f.id) }))
    .sort((a, b) => a.path.localeCompare(b.path));

  const html = '<option value="">Home</option>' +
    opts.map(o => `<option value="${esc(o.id)}">${esc(o.path)}</option>`).join('');

  [folderSelectFile, folderSelectLink].forEach(sel => {
    if (sel) { sel.innerHTML = html; sel.value = currentFolderId || ''; }
  });
}


// ══════════════════════════════════════════════════════════════
// RENDERING
// ══════════════════════════════════════════════════════════════

function render() {
  const query = searchInput.value.trim().toLowerCase();
  renderBreadcrumbs(query);

  // Render folder details header banner
  if (currentFolderId && !query) {
    const curFolder = allFolders.find(f => f.id === currentFolderId);
    if (curFolder) {
      currentFolderTitle.textContent = curFolder.name;
      
      // Calculate Stats
      const owner = curFolder.createdBy || 'Anonymous Contributor';
      const contributors = getContributorsCount(curFolder.id);
      const filesCount = getFilesCount(curFolder.id);
      const createdDate = curFolder.createdAt ? relativeTime(curFolder.createdAt) : 'N/A';

      document.getElementById('meta-owner').textContent = `👤 Owner: ${owner}`;
      document.getElementById('meta-contributors').textContent = `👥 Contributors: ${contributors}`;
      document.getElementById('meta-files').textContent = `📄 Files: ${filesCount}`;
      document.getElementById('meta-created').textContent = `📅 Created: ${createdDate}`;

      // Admin actions inside folder card
      if (isAdmin) {
        currentFolderAdminBar.classList.remove('hidden');
        adminPinBtn.textContent = curFolder.pinned ? '📌 Unpin' : '📌 Pin';
      } else {
        currentFolderAdminBar.classList.add('hidden');
      }

      if (curFolder.description) {
        currentFolderDesc.textContent = curFolder.description;
        currentFolderDesc.classList.remove('hidden');
      } else {
        currentFolderDesc.classList.add('hidden');
      }

      if (curFolder.notes) {
        currentFolderNotes.textContent = curFolder.notes;
        currentFolderNotesSection.classList.remove('hidden');
      } else {
        currentFolderNotesSection.classList.add('hidden');
      }
      
      folderDetailsCard.classList.remove('hidden');
    } else {
      folderDetailsCard.classList.add('hidden');
    }
  } else {
    folderDetailsCard.classList.add('hidden');
  }

  let folders = [];
  let resources = [];

  if (query) {
    // Global search
    folders   = allFolders.filter(f => f.name.toLowerCase().includes(query));
    resources = allResources.filter(r =>
      r.title.toLowerCase().includes(query) ||
      (r.description || '').toLowerCase().includes(query)
    );
  } else {
    // Browse current folder
    folders   = allFolders.filter(f => f.parentId === currentFolderId);
    resources = allResources.filter(r => r.folderId === currentFolderId);
  }

  // Sort folders: Pinned first, then alphabetically
  folders.sort((a, b) => {
    const pinA = a.pinned ? 1 : 0;
    const pinB = b.pinned ? 1 : 0;
    if (pinA !== pinB) return pinB - pinA;
    return a.name.localeCompare(b.name);
  });

  // Render folders
  if (folders.length > 0) {
    foldersHeader.classList.remove('hidden');
    foldersGrid.innerHTML = folders.map((f, i) => {
      const count = allFolders.filter(sf => sf.parentId === f.id).length +
                    allResources.filter(r => r.folderId === f.id).length;
      
      const pinBadgeHtml = f.pinned ? `<span class="pinned-badge">📌 Pinned</span>` : '';
      const creatorName = f.createdBy ? `<span style="font-size:0.75rem; color:var(--text-secondary);">by ${esc(f.createdBy)}</span>` : '';
      
      // Admin options inside card
      let adminControlsHtml = '';
      if (isAdmin) {
        adminControlsHtml = `
          <div class="admin-card-controls">
            <button class="btn-card-action" title="Pin/Unpin" onclick="event.stopPropagation(); adminTogglePin('${esc(f.id)}')">📌</button>
            <button class="btn-card-action" title="Edit" onclick="event.stopPropagation(); adminEditFolder('${esc(f.id)}')">✏️</button>
            <button class="btn-card-action action-delete" title="Delete" onclick="event.stopPropagation(); adminDeleteFolder('${esc(f.id)}')">🗑️</button>
          </div>
        `;
      }

      return `
        <div class="folder-card" data-id="${esc(f.id)}" style="animation-delay:${i * 0.04}s">
          <div style="display:flex; justify-content:space-between; align-items:center;">
            <div class="folder-card-icon">📁</div>
            ${pinBadgeHtml}
          </div>
          <div class="folder-card-name">${esc(f.name)}</div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top: auto;">
            <div class="folder-card-count">${count} item${count !== 1 ? 's' : ''}</div>
            ${creatorName}
          </div>
          <button type="button" class="report-btn" title="Report Folder" onclick="event.stopPropagation(); openReportModal('folder', '${esc(f.id)}')">⚠️</button>
          ${adminControlsHtml}
        </div>`;
    }).join('');
    foldersGrid.classList.remove('hidden');
  } else {
    foldersHeader.classList.add('hidden');
    foldersGrid.innerHTML = '';
    foldersGrid.classList.add('hidden');
  }

  // Render resources
  if (resources.length > 0) {
    resourcesHeader.classList.remove('hidden');
    resourceGrid.innerHTML = resources.map((r, i) => {
      const icon = getIcon(r);
      const isFile = r.type === 'file';
      const href = isFile ? r.file : r.link;
      const label = isFile ? 'Download' : 'View';
      const time = relativeTime(r.created_at || r.timestamp);
      const showPath = query && r.folderId;
      const pathHtml = showPath
        ? `<span class="resource-card-path">📁 ${esc(getFolderPath(r.folderId))}</span>`
        : '';
      const uploaderName = r.uploadedBy ? `<span style="font-size:0.75rem; color:var(--text-secondary);">Contributor: ${esc(r.uploadedBy)}</span>` : '';

      // Admin options inside card
      let adminControlsHtml = '';
      if (isAdmin) {
        adminControlsHtml = `
          <div class="admin-card-controls">
            <button class="btn-card-action action-delete" title="Delete" onclick="event.stopPropagation(); adminDeleteResource('${esc(r.id)}')">🗑️</button>
          </div>
        `;
      }

      return `
        <article class="resource-card" style="animation-delay:${(folders.length + i) * 0.04}s">
          <div class="resource-card-header">
            <span class="resource-card-icon">${icon}</span>
            <span class="resource-card-title">${esc(r.title)}</span>
          </div>
          ${r.description ? `<p class="resource-card-desc">${esc(r.description)}</p>` : ''}
          ${pathHtml}
          <div style="margin-top: auto; padding-top: var(--space-8);">
            ${uploaderName}
          </div>
          <div class="resource-card-footer" style="margin-top:var(--space-8);">
            <span class="resource-card-time">${time}</span>
            <a class="resource-card-action" href="${esc(href)}" target="_blank" rel="noopener noreferrer" ${isFile ? 'download' : ''}>
              ${label} <span>→</span>
            </a>
          </div>
          <button type="button" class="report-btn" title="Report Resource" onclick="event.stopPropagation(); openReportModal('resource', '${esc(r.id)}')">⚠️</button>
          ${adminControlsHtml}
        </article>`;
    }).join('');
    resourceGrid.classList.remove('hidden');
  } else {
    resourcesHeader.classList.add('hidden');
    resourceGrid.innerHTML = '';
    resourceGrid.classList.add('hidden');
  }

  // Empty state
  if (folders.length === 0 && resources.length === 0) {
    emptyState.classList.remove('hidden');
    if (query) {
      emptyState.querySelector('h3').textContent = 'No results found';
      emptyState.querySelector('p').textContent = 'Try a different search term.';
      emptyState.querySelector('.empty-icon').textContent = '🔍';
    } else {
      emptyState.querySelector('.empty-icon').textContent = '📂';
      emptyState.querySelector('h3').textContent = 'This folder is empty';
      emptyState.querySelector('p').textContent = 'Upload a resource or create a folder to get started.';
    }
  } else {
    emptyState.classList.add('hidden');
  }

  // Bind folder card clicks
  foldersGrid.querySelectorAll('.folder-card').forEach(card => {
    card.addEventListener('click', () => {
      currentFolderId = card.dataset.id;
      searchInput.value = '';
      render();
      populateFolderSelects();
    });
  });
}


// ── Breadcrumbs ──────────────────────────────────────────────

function renderBreadcrumbs(query) {
  if (!breadcrumbsNav) return;
  if (query) {
    breadcrumbsNav.innerHTML =
      `<span class="breadcrumb-item" data-id="root">Home</span>` +
      `<span class="breadcrumb-sep">›</span>` +
      `<span class="breadcrumb-item active">Search results</span>`;
    return;
  }

  const trail = [{ id: null, name: 'Home' }];
  if (currentFolderId) {
    const path = [];
    let cur = (allFolders || []).find(f => f && f.id === currentFolderId);
    while (cur) {
      path.unshift(cur);
      cur = cur.parentId ? (allFolders || []).find(f => f && f.id === cur.parentId) : null;
    }
    trail.push(...path);
  }

  breadcrumbsNav.innerHTML = trail.map((c, i) => {
    const isLast = i === trail.length - 1;
    const sep = i > 0 ? '<span class="breadcrumb-sep">›</span>' : '';
    if (isLast) return sep + `<span class="breadcrumb-item active">${esc(c.name)}</span>`;
    return sep + `<span class="breadcrumb-item" data-id="${c.id || 'root'}">${esc(c.name)}</span>`;
  }).join('');
}

breadcrumbsNav.addEventListener('click', e => {
  const item = e.target.closest('.breadcrumb-item');
  if (!item || item.classList.contains('active')) return;
  currentFolderId = item.dataset.id === 'root' ? null : item.dataset.id;
  searchInput.value = '';
  render();
  populateFolderSelects();
});


// ── Helpers ──────────────────────────────────────────────────

function getIcon(r) {
  if (r.type === 'file') {
    const ext = (r.file || '').split('.').pop().toLowerCase();
    if (ext === 'zip') return '📦';
    if (ext === 'docx') return '📝';
    if (ext === 'pptx') return '📊';
    return '📄';
  }
  return '🔗';
}

function relativeTime(input) {
  if (!input) return '';
  let ts;
  if (typeof input === 'number') {
    ts = input * 1000;
  } else {
    ts = new Date(input + (input.includes('T') ? '' : ' UTC')).getTime();
    if (isNaN(ts)) ts = new Date(input).getTime();
  }
  if (isNaN(ts)) return '';

  const now  = Date.now();
  const diff = Math.floor((now - ts) / 1000);

  if (diff < 60)    return 'Just now';
  if (diff < 3600)  return `${Math.floor(diff/60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff/3600)}h ago`;

  const days = Math.floor(diff / 86400);
  if (days === 1) return 'Yesterday';
  if (days < 7)   return `${days} days ago`;

  const d = new Date(ts);
  const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  return `${months[d.getMonth()]} ${d.getFullYear()}`;
}

function esc(str) {
  if (!str) return '';
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}


// ══════════════════════════════════════════════════════════════
// SEARCH
// ══════════════════════════════════════════════════════════════

let searchTimer;
searchInput.addEventListener('input', () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(render, 200);
});


// ══════════════════════════════════════════════════════════════
// UPLOAD MODAL — Multi-step
// ══════════════════════════════════════════════════════════════

function openUploadModal() {
  uploadModal.classList.remove('hidden');
  titleInput.value = '';
  descInput.value = '';
  linkInput.value = '';
  fileInput.value = '';
  selectedFiles = [];
  if (filePreviewContainer) filePreviewContainer.classList.add('hidden');
  if (fileNameDisp) fileNameDisp.classList.add('hidden');
  if (progressContainer) progressContainer.classList.add('hidden');
  if (progressFill) progressFill.style.width = '0%';
  if (progressText) progressText.textContent = '0%';
  
  // Set tab to file by default
  uploadSource = 'file';
  tabFileBtn.classList.add('active');
  tabLinkBtn.classList.remove('active');
  fileFormContainer.classList.remove('hidden');
  linkFormContainer.classList.add('hidden');

  // Pre-fill contributor name
  syncContributorName(contributorName);

  // Pre-select current folder
  populateFolderSelects();
}

function closeUploadModal() {
  uploadModal.classList.add('hidden');
}

if (heroUploadBtn) heroUploadBtn.addEventListener('click', openUploadModal);
if (navUploadTrigger) navUploadTrigger.addEventListener('click', e => { e.preventDefault(); openUploadModal(); });
if (uploadOverlay) uploadOverlay.addEventListener('click', closeUploadModal);
if (uploadClose) uploadClose.addEventListener('click', closeUploadModal);

// Tab switching
if (tabFileBtn) {
  tabFileBtn.addEventListener('click', () => {
    uploadSource = 'file';
    tabFileBtn.classList.add('active');
    tabLinkBtn.classList.remove('active');
    fileFormContainer.classList.remove('hidden');
    linkFormContainer.classList.add('hidden');
  });
}
if (tabLinkBtn) {
  tabLinkBtn.addEventListener('click', () => {
    uploadSource = 'link';
    tabLinkBtn.classList.add('active');
    tabFileBtn.classList.remove('active');
    linkFormContainer.classList.remove('hidden');
    fileFormContainer.classList.add('hidden');
    setTimeout(() => titleInput.focus(), 100);
  });
}

// File drop zone
if (fileDropZone) {
  fileDropZone.addEventListener('dragover', e => { e.preventDefault(); fileDropZone.classList.add('drag-over'); });
  fileDropZone.addEventListener('dragleave', () => fileDropZone.classList.remove('drag-over'));
  fileDropZone.addEventListener('drop', e => {
    e.preventDefault();
    fileDropZone.classList.remove('drag-over');
    if (e.dataTransfer.files.length) {
      updateFilesSelection(e.dataTransfer.files);
    }
  });
}
if (fileInput) {
  fileInput.addEventListener('change', () => {
    if (fileInput.files.length) {
      updateFilesSelection(fileInput.files);
    }
  });
}

function updateFilesSelection(filesList) {
  const newFiles = Array.from(filesList);
  const totalCount = selectedFiles.length + newFiles.length;

  if (totalCount > 100) {
    showToast('Maximum of 100 files allowed at once.', 'error');
    return;
  }

  let totalSize = selectedFiles.reduce((sum, f) => sum + f.size, 0);
  for (const f of newFiles) {
    totalSize += f.size;
  }
  if (totalSize > 500 * 1024 * 1024) {
    showToast('Total upload size exceeds 500 MB limit.', 'error');
    return;
  }

  const allowedExtensions = ['pdf', 'zip', 'docx', 'pptx'];
  for (const f of newFiles) {
    const ext = f.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(ext)) {
      showToast(`Invalid file type: ${f.name}. Only PDF, ZIP, DOCX, and PPTX allowed.`, 'error');
      return;
    }
  }

  selectedFiles.push(...newFiles);
  renderFilesPreview();
}

function renderFilesPreview() {
  if (selectedFiles.length === 0) {
    filePreviewContainer.classList.add('hidden');
    fileNameDisp.classList.add('hidden');
    return;
  }

  filePreviewContainer.classList.remove('hidden');

  if (selectedFiles.length === 1) {
    const file = selectedFiles[0];
    const ext = file.name.split('.').pop().toLowerCase();
    let emoji = '📄';
    if (ext === 'zip') emoji = '📦';
    if (ext === 'docx') emoji = '📝';
    if (ext === 'pptx') emoji = '📊';
    fileNameDisp.textContent = `${emoji} ${file.name} (${formatBytes(file.size)})`;
    fileNameDisp.classList.remove('hidden');
  } else {
    fileNameDisp.classList.add('hidden');
  }

  filePreviewList.innerHTML = selectedFiles.map((file, index) => {
    const ext = file.name.split('.').pop().toLowerCase();
    let emoji = '📄';
    if (ext === 'zip') emoji = '📦';
    if (ext === 'docx') emoji = '📝';
    if (ext === 'pptx') emoji = '📊';
    return `
      <li class="file-preview-item">
        <div style="display:flex; align-items:center; gap:8px; min-width:0;">
          <span style="font-size:1.1rem; flex-shrink:0;">${emoji}</span>
          <span class="file-preview-name" title="${esc(file.name)}">${esc(file.name)}</span>
          <span class="file-preview-size">(${formatBytes(file.size)})</span>
        </div>
        <span class="file-preview-remove" data-index="${index}">&times;</span>
      </li>
    `;
  }).join('');

  filePreviewList.querySelectorAll('.file-preview-remove').forEach(btn => {
    btn.addEventListener('click', () => {
      const idx = parseInt(btn.dataset.index, 10);
      selectedFiles.splice(idx, 1);
      renderFilesPreview();
    });
  });
}

function formatBytes(bytes) {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

// Contributor name syncing
function syncContributorName(name) {
  contributorName = name;
  localStorage.setItem('contributorName', name);
  if (fileUploadedBy) fileUploadedBy.value = name;
  if (linkUploadedBy) linkUploadedBy.value = name;
  if (folderCreatedBy) folderCreatedBy.value = name;
}

[fileUploadedBy, linkUploadedBy, folderCreatedBy].forEach(input => {
  if (input) {
    input.addEventListener('input', e => {
      syncContributorName(e.target.value.trim());
    });
  }
});

// Submit FILE upload
if (fileForm) {
  fileForm.addEventListener('submit', async e => {
    e.preventDefault();
    if (!selectedFiles.length) { showToast('Please select a file.', 'error'); return; }

    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('category', 'Other');
    formData.append('folderId', folderSelectFile.value);
    formData.append('uploadedBy', contributorName);

    selectedFiles.forEach(file => {
      formData.append('files[]', file);
    });

    console.log("Selected Files:", selectedFiles);
    console.log("FormData Entries:");
    for (const pair of formData.entries()) {
        console.log(pair[0], pair[1]);
    }

    await submitUpload(formData, uploadFileBtn);
  });
}

// Submit LINK upload
if (linkForm) {
  linkForm.addEventListener('submit', async e => {
    e.preventDefault();
    clearErrors();
    const link = linkInput.value.trim();
    if (!link) { linkInput.classList.add('error'); showToast('Please paste a link.', 'error'); return; }

    const title = titleInput.value.trim();
    if (!title) { titleInput.classList.add('error'); showToast('Please enter a title.', 'error'); return; }

    const description = descInput.value.trim();

    const formData = new FormData();
    formData.append('action', 'upload');
    formData.append('title', title);
    formData.append('description', description);
    formData.append('category', 'Other');
    formData.append('folderId', folderSelectLink.value);
    formData.append('link', link);
    formData.append('resourceType', 'Other');
    formData.append('uploadedBy', contributorName);

    await submitUpload(formData, uploadLinkBtn);
  });
}

async function submitUpload(formData, btn) {
  btn.disabled = true;
  const origText = btn.textContent;
  btn.textContent = 'Uploading…';

  progressContainer.classList.remove('hidden');
  progressFill.style.width = '0%';
  progressText.textContent = '0%';

  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', UPLOAD_URL, true);

    xhr.upload.addEventListener('progress', e => {
      if (e.lengthComputable) {
        const percent = Math.round((e.loaded / e.total) * 100);
        progressFill.style.width = percent + '%';
        progressText.textContent = percent + '%';
      }
    });

    xhr.onload = async () => {
      try {
        const response = JSON.parse(xhr.responseText);
        if (xhr.status === 200 || xhr.status === 201) {
          if (response.success) {
            showToast('Resources shared! 🎉', 'success');
            closeUploadModal();
            await loadData();
            resolve();
          } else {
            showToast(response.message || 'Something went wrong.', 'error');
            reject(new Error(response.message));
          }
        } else {
          showToast(response.message || 'Server error occurred.', 'error');
          reject(new Error(response.message));
        }
      } catch (err) {
        showToast('Upload succeeded, but response format was invalid.', 'error');
        reject(err);
      } finally {
        btn.disabled = false;
        btn.textContent = origText;
        progressContainer.classList.add('hidden');
      }
    };

    xhr.onerror = () => {
      showToast('Upload failed. Check your connection.', 'error');
      btn.disabled = false;
      btn.textContent = origText;
      progressContainer.classList.add('hidden');
      reject(new Error('Network error'));
    };

    xhr.send(formData);
  });
}

function clearErrors() {
  document.querySelectorAll('.form-input.error').forEach(el => el.classList.remove('error'));
}


// ══════════════════════════════════════════════════════════════
// FOLDER MODAL & DUPLICATE WARNINGS
// ══════════════════════════════════════════════════════════════

if (newFolderBtn) {
  newFolderBtn.addEventListener('click', () => {
    folderParent.value = currentFolderId || '';
    folderNameInput.value = '';
    folderDescInput.value = '';
    folderNotesInput.value = '';
    syncContributorName(contributorName);
    folderModal.classList.remove('hidden');
    setTimeout(() => folderNameInput.focus(), 100);
  });
}

if (folderOverlay) folderOverlay.addEventListener('click', () => folderModal.classList.add('hidden'));
if (folderClose) folderClose.addEventListener('click', () => folderModal.classList.add('hidden'));

if (folderForm) {
  folderForm.addEventListener('submit', async e => {
    e.preventDefault();
    const name = folderNameInput.value.trim();
    const description = folderDescInput.value.trim();
    const notes = folderNotesInput.value.trim();

    if (!name) { folderNameInput.classList.add('error'); showToast('Enter a folder name.', 'error'); return; }

    pendingFolderData = {
      name,
      description,
      notes,
      parentId: folderParent.value,
      createdBy: contributorName
    };

    // First check for duplicates
    const dupCheckForm = new FormData();
    dupCheckForm.append('action', 'check_duplicate_folder');
    dupCheckForm.append('name', name);
    dupCheckForm.append('parentId', folderParent.value);

    try {
      const checkRes = await fetch(UPLOAD_URL, { method: 'POST', body: dupCheckForm });
      const checkResult = await checkRes.json();
      if (checkResult.success && checkResult.exists) {
        // Open duplicate modal
        duplicateFolderName.textContent = name;
        duplicateModal.classList.remove('hidden');
        
        // Bind duplicate actions
        duplicateOpenBtn.onclick = () => {
          currentFolderId = checkResult.folders[0].id;
          render();
          populateFolderSelects();
          duplicateModal.classList.add('hidden');
          folderModal.classList.add('hidden');
        };
        
        duplicateForceBtn.onclick = async () => {
          duplicateModal.classList.add('hidden');
          await createFolderDirectly();
        };
      } else {
        await createFolderDirectly();
      }
    } catch (err) {
      console.error(err);
      showToast('Error verifying folder name.', 'error');
    }
  });
}

async function createFolderDirectly() {
  if (!pendingFolderData) return;

  folderSubmitBtn.disabled = true;
  folderSubmitBtn.textContent = 'Creating…';

  const fd = new FormData();
  fd.append('action', 'create_folder');
  fd.append('name', pendingFolderData.name);
  fd.append('description', pendingFolderData.description);
  fd.append('notes', pendingFolderData.notes);
  fd.append('parentId', pendingFolderData.parentId);
  fd.append('createdBy', pendingFolderData.createdBy);

  try {
    const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
      showToast('Folder created! 📁', 'success');
      folderModal.classList.add('hidden');
      await loadData();
    } else {
      showToast(result.message || 'Failed.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Failed to create folder.', 'error');
  } finally {
    folderSubmitBtn.disabled = false;
    folderSubmitBtn.textContent = 'Create Folder';
    pendingFolderData = null;
  }
}


// ══════════════════════════════════════════════════════════════
// NAV & SCROLL
// ══════════════════════════════════════════════════════════════

const sections = document.querySelectorAll('section[id]');
function onScroll() {
  if (navbar) navbar.classList.toggle('scrolled', window.scrollY > 10);
  let current = '';
  sections.forEach(s => { if (window.scrollY >= s.offsetTop - 100) current = s.id; });
  if (navLinkItems) {
    navLinkItems.forEach(l => l.classList.toggle('active', l.dataset.section === current));
  }
}
window.addEventListener('scroll', onScroll, { passive: true });

if (navHamburger) {
  navHamburger.addEventListener('click', () => {
    navHamburger.classList.toggle('open');
    if (navLinks) navLinks.classList.toggle('open');
  });
}
if (navLinkItems) {
  navLinkItems.forEach(l => l.addEventListener('click', () => {
    if (navHamburger) navHamburger.classList.remove('open');
    if (navLinks) navLinks.classList.remove('open');
  }));
}


// ══════════════════════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════════════════════

function showToast(msg, type = 'info') {
  if (!toastContainer) return;
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.textContent = msg;
  toastContainer.appendChild(t);
  setTimeout(() => t.remove(), 3000);
}


// ══════════════════════════════════════════════════════════════
// REPORT CONTENT ACTIONS
// ══════════════════════════════════════════════════════════════

function openReportModal(targetType, targetId) {
  reportTargetType.value = targetType;
  reportTargetId.value = targetId;
  reportDetails.value = '';
  reportModal.classList.remove('hidden');
}

if (reportClose) reportClose.addEventListener('click', () => reportModal.classList.add('hidden'));
if (reportOverlay) reportOverlay.addEventListener('click', () => reportModal.classList.add('hidden'));

if (reportForm) {
  reportForm.addEventListener('submit', async e => {
    e.preventDefault();
    const reasonEl = document.querySelector('input[name="report-reason"]:checked');
    const reason = reasonEl ? reasonEl.value : 'Spam';
    const details = reportDetails.value.trim();

    const fd = new FormData();
    fd.append('action', 'report');
    fd.append('targetType', reportTargetType.value);
    fd.append('targetId', reportTargetId.value);
    fd.append('reason', reason);
    fd.append('details', details);

    try {
      const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
      const result = await res.json();
      if (result.success) {
        showToast('Report submitted. Thank you! 🙏', 'success');
        reportModal.classList.add('hidden');
        await loadData();
      } else {
        showToast(result.message || 'Report submission failed.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Error submitting report.', 'error');
    }
  });
}


// ══════════════════════════════════════════════════════════════
// ADMIN LOGIN & LOGOUT
// ══════════════════════════════════════════════════════════════

// Admin navbar trigger is standard link to admin.php and is not intercepted by JavaScript

if (adminLoginClose) adminLoginClose.addEventListener('click', () => adminLoginModal.classList.add('hidden'));
if (adminLoginOverlay) adminLoginOverlay.addEventListener('click', () => adminLoginModal.classList.add('hidden'));

if (adminLoginForm) {
  adminLoginForm.addEventListener('submit', async e => {
    e.preventDefault();
    const passphrase = adminPassphraseInput.value.trim();
    if (!passphrase) return;

    const fd = new FormData();
    fd.append('action', 'admin_login');
    fd.append('passphrase', passphrase);

    try {
      const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
      const result = await res.json();
      if (result.success) {
        isAdmin = true;
        adminKey = result.adminKey;
        localStorage.setItem('isAdmin', 'true');
        localStorage.setItem('adminKey', adminKey);
        
        showToast('Admin mode unlocked! 🔓', 'success');
        adminLoginModal.classList.add('hidden');
        updateNavbarAdminUI();
        render();
        openAdminPanel();
      } else {
        showToast(result.message || 'Incorrect passphrase.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Login error occurred.', 'error');
    }
  });
}

if (adminLogoutBtn) {
  adminLogoutBtn.addEventListener('click', () => {
    isAdmin = false;
    adminKey = '';
    localStorage.removeItem('isAdmin');
    localStorage.removeItem('adminKey');
    showToast('Admin mode locked. 🔒', 'info');
    adminPanelModal.classList.add('hidden');
    updateNavbarAdminUI();
    render();
  });
}


// ══════════════════════════════════════════════════════════════
// ADMIN DASHBOARD
// ══════════════════════════════════════════════════════════════

function openAdminPanel() {
  adminPanelModal.classList.remove('hidden');
  renderReportsTable();
}

if (adminPanelClose) adminPanelClose.addEventListener('click', () => adminPanelModal.classList.add('hidden'));
if (adminPanelOverlay) adminPanelOverlay.addEventListener('click', () => adminPanelModal.classList.add('hidden'));

function renderReportsTable() {
  reportsCountSpan.textContent = allReports.length;
  if (allReports.length === 0) {
    adminReportsTbody.innerHTML = `
      <tr>
        <td colspan="5" class="table-empty">No pending reports.</td>
      </tr>
    `;
    return;
  }

  adminReportsTbody.innerHTML = allReports.map(rpt => {
    let targetName = 'Unknown Target';
    if (rpt.targetType === 'folder') {
      const folder = allFolders.find(f => f.id === rpt.targetId);
      targetName = folder ? `📁 Folder: ${folder.name}` : `📁 Deleted Folder (${rpt.targetId})`;
    } else if (rpt.targetType === 'resource') {
      const res = allResources.find(r => r.id === rpt.targetId);
      targetName = res ? `📄 File: ${res.title}` : `📄 Deleted File (${rpt.targetId})`;
    }

    const dateStr = rpt.createdAt ? new Date(rpt.createdAt * 1000).toLocaleString() : 'N/A';

    return `
      <tr>
        <td><strong>${esc(targetName)}</strong></td>
        <td><span class="pinned-badge" style="background:#ff3b301a; color:#ff3b30;">${esc(rpt.reason)}</span></td>
        <td>${esc(rpt.details || '—')}</td>
        <td><small>${esc(dateStr)}</small></td>
        <td>
          <div style="display:flex; gap:6px;">
            <button class="btn btn-secondary btn-sm" onclick="adminDismissReport('${esc(rpt.id)}')">Dismiss</button>
            <button class="btn btn-danger btn-sm" onclick="adminDeleteReportedTarget('${esc(rpt.targetType)}', '${esc(rpt.targetId)}', '${esc(rpt.id)}')">Delete Target</button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

async function adminDismissReport(reportId) {
  const fd = new FormData();
  fd.append('action', 'admin_dismiss_report');
  fd.append('reportId', reportId);
  fd.append('adminKey', adminKey);

  try {
    const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
      showToast('Report dismissed.', 'success');
      await loadData();
      renderReportsTable();
    } else {
      showToast(result.message || 'Action failed.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error.', 'error');
  }
}

async function adminDeleteReportedTarget(targetType, targetId, reportId) {
  if (targetType === 'folder') {
    await adminDeleteFolder(targetId);
  } else {
    await adminDeleteResource(targetId);
  }
  // Dismiss report after deletion
  await adminDismissReport(reportId);
}


// ══════════════════════════════════════════════════════════════
// ADMIN MANAGEMENT OPERATIONS (PIN/EDIT/DELETE)
// ══════════════════════════════════════════════════════════════

async function adminTogglePin(folderId) {
  const fd = new FormData();
  fd.append('action', 'admin_pin_folder');
  fd.append('folderId', folderId);
  fd.append('adminKey', adminKey);

  try {
    const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
      showToast(result.pinned ? 'Folder pinned! 📌' : 'Folder unpinned.', 'success');
      await loadData();
    } else {
      showToast(result.message || 'Action failed.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error.', 'error');
  }
}

function adminEditFolder(folderId) {
  const folder = allFolders.find(f => f.id === folderId);
  if (!folder) return;

  editFolderIdInput.value = folder.id;
  editFolderNameInput.value = folder.name;
  editFolderDescInput.value = folder.description || '';
  editFolderNotesInput.value = folder.notes || '';
  
  adminEditFolderModal.classList.remove('hidden');
}

if (adminEditFolderClose) adminEditFolderClose.addEventListener('click', () => adminEditFolderModal.classList.add('hidden'));
if (adminEditFolderOverlay) adminEditFolderOverlay.addEventListener('click', () => adminEditFolderModal.classList.add('hidden'));

if (adminEditFolderForm) {
  adminEditFolderForm.addEventListener('submit', async e => {
    e.preventDefault();
    const folderId = editFolderIdInput.value;
    const name = editFolderNameInput.value.trim();
    const description = editFolderDescInput.value.trim();
    const notes = editFolderNotesInput.value.trim();

    if (!name) return;

    const fd = new FormData();
    fd.append('action', 'admin_edit_folder');
    fd.append('folderId', folderId);
    fd.append('name', name);
    fd.append('description', description);
    fd.append('notes', notes);
    fd.append('adminKey', adminKey);

    try {
      const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
      const result = await res.json();
      if (result.success) {
        showToast('Folder updated successfully!', 'success');
        adminEditFolderModal.classList.add('hidden');
        await loadData();
      } else {
        showToast(result.message || 'Action failed.', 'error');
      }
    } catch (err) {
      console.error(err);
      showToast('Network error.', 'error');
    }
  });
}

async function adminDeleteFolder(folderId) {
  const folder = allFolders.find(f => f.id === folderId);
  if (!folder) return;

  const confirmMsg = `⚠️ DANGER!\nAre you sure you want to delete folder "${folder.name}"?\n\nThis will permanently delete all subfolders, files, and links recursively. This action cannot be undone!`;
  if (!confirm(confirmMsg)) return;

  const fd = new FormData();
  fd.append('action', 'admin_delete_folder');
  fd.append('folderId', folderId);
  fd.append('adminKey', adminKey);

  try {
    const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
      showToast('Folder deleted recursively.', 'success');
      // If we are currently inside the deleted folder (or its children), go Home
      if (currentFolderId === folderId || getFolderAndChildrenIdsJs(folderId).includes(currentFolderId)) {
        currentFolderId = null;
      }
      await loadData();
    } else {
      showToast(result.message || 'Action failed.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error.', 'error');
  }
}

async function adminDeleteResource(resourceId) {
  const resObj = allResources.find(r => r.id === resourceId);
  if (!resObj) return;

  if (!confirm(`Are you sure you want to delete "${resObj.title}"?`)) return;

  const fd = new FormData();
  fd.append('action', 'admin_delete_resource');
  fd.append('resourceId', resourceId);
  fd.append('adminKey', adminKey);

  try {
    const res = await fetch(UPLOAD_URL, { method: 'POST', body: fd });
    const result = await res.json();
    if (result.success) {
      showToast('Resource deleted.', 'success');
      await loadData();
    } else {
      showToast(result.message || 'Action failed.', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast('Network error.', 'error');
  }
}

// Bind duplicate modal close
if (duplicateClose) duplicateClose.addEventListener('click', () => duplicateModal.classList.add('hidden'));
if (duplicateOverlay) duplicateOverlay.addEventListener('click', () => duplicateModal.classList.add('hidden'));

// Bind header admin buttons
if (adminPinBtn) {
  adminPinBtn.addEventListener('click', () => {
    if (currentFolderId) adminTogglePin(currentFolderId);
  });
}

if (adminEditBtn) {
  adminEditBtn.addEventListener('click', () => {
    if (currentFolderId) adminEditFolder(currentFolderId);
  });
}

if (adminDeleteBtn) {
  adminDeleteBtn.addEventListener('click', () => {
    if (currentFolderId) adminDeleteFolder(currentFolderId);
  });
}


// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', () => {
  loadData();
  onScroll();
});
