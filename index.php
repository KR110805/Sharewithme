<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <!-- SEO -->
  <title>ShareWithMe — Share Resources Easily</title>
  <meta name="description" content="Organize notes, assignments, PYQs and study materials in one place. Share with anyone — no login required." />

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />

  <!-- Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>

  <!-- ===== NAVBAR ===== -->
  <nav id="navbar" class="navbar">
    <div class="nav-container">
      <a href="#" class="nav-brand" id="nav-brand">
        <span class="brand-icon">📎</span>
        ShareWithMe
      </a>

      <ul class="nav-links" id="nav-links">
        <li><a href="#hero" class="nav-link active" data-section="hero">Home</a></li>
        <li><a href="#resources" class="nav-link" data-section="resources">Resources</a></li>
        <li><a href="#" class="nav-link nav-upload-trigger" id="nav-upload-trigger">Upload</a></li>
        <li><a href="admin.php" class="nav-link nav-admin-trigger" id="nav-admin-trigger" title="Admin Portal">🔒 <span id="admin-badge-text">Admin</span></a></li>
      </ul>

      <button class="nav-hamburger" id="nav-hamburger" aria-label="Toggle navigation">
        <span></span>
        <span></span>
        <span></span>
      </button>
    </div>
  </nav>

  <!-- ===== HERO SECTION ===== -->
  <section id="hero" class="hero">
    <div class="hero-content">
      <h1 class="hero-title">Share resources easily</h1>
      <p class="hero-subtitle">Organize notes, assignments, PYQs and study materials in one place.</p>
      <button type="button" class="btn btn-primary hero-btn" id="hero-upload-btn">
        <span class="btn-icon">⬆</span>
        Upload Resource
      </button>
    </div>
    <div class="hero-shapes">
      <div class="shape shape-1"></div>
      <div class="shape shape-2"></div>
      <div class="shape shape-3"></div>
    </div>
  </section>

  <!-- ===== RESOURCES SECTION ===== -->
  <section id="resources" class="section resources-section">
    <div class="container">

      <!-- Search Bar -->
      <div class="search-bar" id="search-bar">
        <span class="search-icon">🔍</span>
        <input
          type="text"
          id="search-input"
          class="search-input"
          placeholder="Search folders and resources…"
        />
      </div>

      <!-- Breadcrumbs -->
      <div class="breadcrumbs-bar" id="breadcrumbs-bar">
        <nav class="breadcrumbs" id="breadcrumbs"></nav>
        <button type="button" class="btn-icon-action" id="new-folder-btn" title="New Folder">
          <span>＋</span> New Folder
        </button>
      </div>

      <!-- Folder Details Header -->
      <div class="folder-details-card hidden" id="folder-details-card">
        <div class="folder-header-row">
          <div class="folder-title-section">
            <h1 class="folder-title" id="current-folder-title">Folder Name</h1>
            <div class="folder-meta-row" id="current-folder-meta">
              <span class="meta-item" id="meta-owner">👤 Owner: Anonymous</span>
              <span class="meta-separator">•</span>
              <span class="meta-item" id="meta-contributors">👥 Contributors: 0</span>
              <span class="meta-separator">•</span>
              <span class="meta-item" id="meta-files">📄 Files: 0</span>
              <span class="meta-separator">•</span>
              <span class="meta-item" id="meta-created">📅 Created: N/A</span>
            </div>
          </div>
          <div class="admin-bar hidden" id="current-folder-admin-bar">
            <button type="button" class="btn btn-secondary btn-sm" id="admin-pin-btn" title="Pin Folder">📌 Pin</button>
            <button type="button" class="btn btn-secondary btn-sm" id="admin-edit-btn" title="Edit Folder">✏️ Edit</button>
            <button type="button" class="btn btn-danger btn-sm" id="admin-delete-btn" title="Delete Folder">🗑️ Delete</button>
          </div>
        </div>
        <p class="folder-description" id="current-folder-desc">Folder Description</p>
        <div class="folder-notes-section" id="current-folder-notes-section">
          <div class="folder-notes-label">📌 Folder Notes</div>
          <p class="folder-notes" id="current-folder-notes">Folder notes go here...</p>
        </div>
      </div>

      <!-- Folders Header -->
      <div class="section-header hidden" id="folders-header">
        <h2 class="section-label">FOLDERS</h2>
        <p class="section-subtitle">Browse organized study material</p>
      </div>

      <!-- Folders Grid -->
      <div class="folders-grid" id="folders-grid"></div>

      <!-- Resources Header -->
      <div class="section-header hidden" id="resources-header">
        <h2 class="section-label">RESOURCES</h2>
        <p class="section-subtitle">Recently shared resources</p>
      </div>

      <!-- Resources Grid -->
      <div class="resource-grid" id="resource-grid"></div>

      <!-- Empty State -->
      <div class="empty-state hidden" id="empty-state">
        <span class="empty-icon">📂</span>
        <h3>This folder is empty</h3>
        <p>Upload a resource or create a folder to get started.</p>
      </div>

     <!-- ===== UPLOAD MODAL (Tabbed) ===== -->
  <div id="upload-modal" class="modal hidden">
    <div class="modal-overlay" id="upload-modal-overlay"></div>
    <div class="modal-content modal-upload">
      <button class="modal-close" id="upload-modal-close" aria-label="Close">&times;</button>
      
      <!-- Source Toggle Tabs -->
      <div class="modal-tabs" id="modal-tabs">
        <button type="button" class="modal-tab active" data-source="file" id="tab-file-btn">Upload Files</button>
        <button type="button" class="modal-tab" data-source="link" id="tab-link-btn">Add Link</button>
      </div>

      <!-- File Upload Form (Default) -->
      <div class="modal-body" id="upload-form-file-container">
        <form id="upload-form-file" novalidate>
          <div class="form-group">
            <label for="file-uploaded-by" class="form-label">Your Name</label>
            <input type="text" id="file-uploaded-by" class="form-input" placeholder="e.g. Kshitij" />
          </div>
          <div class="form-group">
            <label class="form-label">Choose Folder</label>
            <select id="upload-folder-file" class="form-input form-select">
              <option value="">Home</option>
            </select>
          </div>
          <div class="form-group">
            <div class="file-drop-zone" id="file-drop-zone">
              <span class="file-drop-icon">📁</span>
              <p class="file-drop-text">Drag & drop here, or <span class="file-browse">browse</span></p>
              <p class="file-drop-hint">PDF, ZIP, DOCX, PPTX · Max 100 files · Max 500 MB total</p>
              <input type="file" id="resource-file" name="files[]" class="file-input-hidden" accept=".pdf,.zip,.docx,.pptx" multiple />
            </div>
            <p class="file-name-display hidden" id="file-name-display"></p>
            <!-- File Preview Container -->
            <div class="file-preview-container hidden" id="file-preview-container">
              <ul class="file-preview-list" id="file-preview-list"></ul>
            </div>
            <!-- Upload Progress Container -->
            <div class="upload-progress-container hidden" id="upload-progress-container">
              <div class="upload-progress-track">
                <div class="upload-progress-fill" id="upload-progress-fill"></div>
              </div>
              <span class="upload-progress-text" id="upload-progress-text">0%</span>
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-full" id="upload-file-btn">Upload Files</button>
        </form>
      </div>

      <!-- Link Upload Form -->
      <div class="modal-body hidden" id="upload-form-link-container">
        <form id="upload-form-link" novalidate>
          <div class="form-group">
            <label for="link-uploaded-by" class="form-label">Your Name</label>
            <input type="text" id="link-uploaded-by" class="form-input" placeholder="e.g. Kshitij" />
          </div>
          <div class="form-group">
            <label for="resource-title" class="form-label">Title</label>
            <input type="text" id="resource-title" class="form-input" placeholder="e.g. Reference Website" required />
          </div>
          <div class="form-group">
            <label for="resource-description" class="form-label">Description</label>
            <textarea id="resource-description" class="form-input form-textarea" placeholder="A short description…" rows="2"></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Choose Folder</label>
            <select id="upload-folder-link" class="form-input form-select">
              <option value="">Home</option>
            </select>
          </div>
          <div class="form-group">
            <label for="resource-link" class="form-label">Paste Link</label>
            <input type="url" id="resource-link" class="form-input" placeholder="https://example.com/resource" required />
          </div>
          <button type="submit" class="btn btn-primary btn-full" id="upload-link-btn">Save Link</button>
        </form>
      </div>

    </div>
  </div>

  <!-- ===== FOLDER CREATION MODAL ===== -->
  <div id="folder-modal" class="modal hidden">
    <div class="modal-overlay" id="folder-modal-overlay"></div>
    <div class="modal-content modal-upload">
      <button class="modal-close" id="folder-modal-close" aria-label="Close">&times;</button>
      <h3 class="modal-title">Create Folder</h3>
      <form id="folder-form" novalidate>
        <div class="form-group">
          <label for="folder-created-by" class="form-label">Your Name</label>
          <input type="text" id="folder-created-by" name="createdBy" class="form-input" placeholder="e.g. Kshitij" />
        </div>
        <div class="form-group">
          <label for="folder-name" class="form-label">Folder Name</label>
          <input type="text" id="folder-name" name="name" class="form-input" placeholder="e.g. Web Development" required />
        </div>
        <div class="form-group">
          <label for="folder-description" class="form-label">Folder Description</label>
          <textarea id="folder-description" name="description" class="form-input form-textarea" placeholder="e.g. Collaborative repository for Second Year End Semester 2026." rows="2"></textarea>
        </div>
        <div class="form-group">
          <label for="folder-notes" class="form-label">Folder Notes</label>
          <textarea id="folder-notes" name="notes" class="form-input form-textarea" placeholder="e.g. Upload notes, PYQs, assignments..." rows="3"></textarea>
        </div>
        <!-- parentId is set automatically from currentFolderId -->
        <input type="hidden" id="folder-parent" name="parentId" value="" />
        <button type="submit" class="btn btn-primary btn-full" id="folder-submit-btn">Create Folder</button>
      </form>
    </div>
  </div>

  <!-- ===== REPORT MODAL ===== -->
  <div id="report-modal" class="modal hidden">
    <div class="modal-overlay" id="report-modal-overlay"></div>
    <div class="modal-content modal-upload">
      <button class="modal-close" id="report-modal-close" aria-label="Close">&times;</button>
      <h3 class="modal-title">Report Content</h3>
      <form id="report-form" novalidate>
        <input type="hidden" id="report-target-type" value="" />
        <input type="hidden" id="report-target-id" value="" />
        <div class="form-group">
          <label class="form-label">Reason for reporting</label>
          <div class="radio-group">
            <label class="radio-label">
              <input type="radio" name="report-reason" value="Spam" checked /> Spam / Advertising
            </label>
            <label class="radio-label">
              <input type="radio" name="report-reason" value="Duplicate" /> Duplicate Content
            </label>
            <label class="radio-label">
              <input type="radio" name="report-reason" value="Incorrect" /> Incorrect / Broken
            </label>
            <label class="radio-label">
              <input type="radio" name="report-reason" value="Irrelevant" /> Irrelevant
            </label>
          </div>
        </div>
        <div class="form-group">
          <label for="report-details" class="form-label">Additional Details (Optional)</label>
          <textarea id="report-details" class="form-input form-textarea" placeholder="Describe the issue..." rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-danger btn-full" id="report-submit-btn">Submit Report</button>
      </form>
    </div>
  </div>

  <!-- ===== ADMIN LOGIN MODAL ===== -->
  <div id="admin-login-modal" class="modal hidden">
    <div class="modal-overlay" id="admin-login-modal-overlay"></div>
    <div class="modal-content modal-upload">
      <button class="modal-close" id="admin-login-modal-close" aria-label="Close">&times;</button>
      <h3 class="modal-title">Admin Login</h3>
      <p class="modal-subtitle">Enter passphrase to unlock administrative actions.</p>
      <form id="admin-login-form" novalidate>
        <div class="form-group">
          <label for="admin-passphrase" class="form-label">Passphrase</label>
          <input type="password" id="admin-passphrase" class="form-input" placeholder="••••••••••••" required />
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="admin-login-submit-btn">Unlock Admin Mode</button>
      </form>
    </div>
  </div>

  <!-- ===== ADMIN PANEL MODAL ===== -->
  <div id="admin-panel-modal" class="modal hidden">
    <div class="modal-overlay" id="admin-panel-modal-overlay"></div>
    <div class="modal-content modal-large">
      <button class="modal-close" id="admin-panel-modal-close" aria-label="Close">&times;</button>
      <h3 class="modal-title">Admin Dashboard</h3>
      <div class="admin-dashboard-tabs">
        <button type="button" class="admin-tab active" id="admin-tab-reports">Pending Reports (<span id="reports-count">0</span>)</button>
        <button type="button" class="btn btn-secondary btn-sm" id="admin-logout-btn">Lock/Logout</button>
      </div>
      <div class="admin-panel-body">
        <div class="table-responsive">
          <table class="admin-reports-table">
            <thead>
              <tr>
                <th>Target</th>
                <th>Reason</th>
                <th>Details</th>
                <th>Reported At</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="admin-reports-tbody">
              <tr>
                <td colspan="5" class="table-empty">No pending reports.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== DUPLICATE WARNING MODAL ===== -->
  <div id="duplicate-modal" class="modal hidden">
    <div class="modal-overlay" id="duplicate-modal-overlay"></div>
    <div class="modal-content modal-upload">
      <button class="modal-close" id="duplicate-modal-close" aria-label="Close">&times;</button>
      <h3 class="modal-title">Folder Already Exists</h3>
      <div class="duplicate-warning-box">
        <span class="warning-icon">⚠️</span>
        <p>A folder named <strong id="duplicate-folder-name">Folder Name</strong> already exists in this directory.</p>
      </div>
      <div class="duplicate-actions">
        <button type="button" class="btn btn-secondary btn-full" id="duplicate-open-btn">Open Existing Folder</button>
        <button type="button" class="btn btn-danger btn-full" id="duplicate-force-btn">Create Anyway</button>
      </div>
    </div>
  </div>

  <!-- ===== ADMIN EDIT FOLDER MODAL ===== -->
  <div id="admin-edit-folder-modal" class="modal hidden">
    <div class="modal-overlay" id="admin-edit-folder-modal-overlay"></div>
    <div class="modal-content modal-upload">
      <button class="modal-close" id="admin-edit-folder-modal-close" aria-label="Close">&times;</button>
      <h3 class="modal-title">Edit Folder Details</h3>
      <form id="admin-edit-folder-form" novalidate>
        <input type="hidden" id="edit-folder-id" value="" />
        <div class="form-group">
          <label for="edit-folder-name" class="form-label">Folder Name</label>
          <input type="text" id="edit-folder-name" class="form-input" required />
        </div>
        <div class="form-group">
          <label for="edit-folder-description" class="form-label">Folder Description</label>
          <textarea id="edit-folder-description" class="form-input form-textarea" rows="2"></textarea>
        </div>
        <div class="form-group">
          <label for="edit-folder-notes" class="form-label">Folder Notes</label>
          <textarea id="edit-folder-notes" class="form-input form-textarea" rows="3"></textarea>
        </div>
        <button type="submit" class="btn btn-primary btn-full" id="edit-folder-submit-btn">Save Changes</button>
      </form>
    </div>
  </div>

  <!-- ===== TOAST ===== -->
  <div class="toast-container" id="toast-container"></div>

  <script src="assets/js/script.js"></script>
</body>
</html>
