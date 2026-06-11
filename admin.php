<?php
/* ============================================================
   ShareWithMe — admin.php
   
   Lightweight Administrative Moderation Panel.
   Protects with session authentication against data/admin.json.
   ============================================================ */

session_start();

define('DATA_DIR',   __DIR__ . '/data/');
define('DATA_FILE',  DATA_DIR . 'data.json');
define('ADMIN_FILE', DATA_DIR . 'admin.json');

// --- Helper Functions ---

function readData() {
    $defaultData = ['folders' => [], 'resources' => [], 'reports' => []];
    if (!file_exists(DATA_FILE)) {
        return $defaultData;
    }
    $data = json_decode(file_get_contents(DATA_FILE), true);
    if (!is_array($data)) {
        return $defaultData;
    }
    if (!isset($data['folders']))   $data['folders'] = [];
    if (!isset($data['resources'])) $data['resources'] = [];
    if (!isset($data['reports']))   $data['reports'] = [];
    return $data;
}

function saveData($data) {
    return file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

function getFolderAndChildrenIds($folderId, $folders) {
    $ids = [$folderId];
    foreach ($folders as $f) {
        if ($f['parentId'] === $folderId) {
            $ids = array_merge($ids, getFolderAndChildrenIds($f['id'], $folders));
        }
    }
    return array_unique($ids);
}

function getFolderPath($folderId, $folders) {
    if (!$folderId) return 'Home';
    $parts = [];
    $cur = null;
    foreach ($folders as $f) {
        if ($f['id'] === $folderId) {
            $cur = $f;
            break;
        }
    }
    while ($cur) {
        array_unshift($parts, $cur['name']);
        $parentId = $cur['parentId'];
        $cur = null;
        if ($parentId) {
            foreach ($folders as $f) {
                if ($f['id'] === $parentId) {
                    $cur = $f;
                    break;
                }
            }
        }
    }
    return implode(' › ', $parts);
}

// Ensure admin config exists
if (!file_exists(ADMIN_FILE)) {
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    $defaultHash = hash('sha256', 'sharewithme-admin');
    file_put_contents(ADMIN_FILE, json_encode(['passphraseHash' => $defaultHash], JSON_PRETTY_PRINT));
}

$adminConfig = json_decode(file_get_contents(ADMIN_FILE), true);
$expectedHash = isset($adminConfig['passphraseHash']) ? $adminConfig['passphraseHash'] : '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    session_destroy();
    header("Location: admin.php");
    exit;
}

// Handle Login Form Submission
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_passphrase'])) {
    $passphrase = trim($_POST['login_passphrase']);
    if (hash('sha256', $passphrase) === $expectedHash) {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $loginError = 'Invalid admin passphrase.';
    }
}

// Check authorization
$isAuthenticated = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle Actions (Only for Authorized Admins)
$actionFeedback = ['type' => '', 'message' => ''];
if ($isAuthenticated && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim($_POST['action']);
    $data = readData();
    $modified = false;

    if ($action === 'delete_folder') {
        $folderId = trim($_POST['folderId']);
        if (!empty($folderId)) {
            $foldersToDelete = getFolderAndChildrenIds($folderId, $data['folders']);
            $deletedResourceIds = [];
            
            // Delete resource files on disk
            $remainingResources = [];
            foreach ($data['resources'] as $res) {
                if (in_array($res['folderId'], $foldersToDelete)) {
                    $deletedResourceIds[] = $res['id'];
                    if ($res['type'] === 'file' && !empty($res['file'])) {
                        $filePath = __DIR__ . '/' . $res['file'];
                        if (file_exists($filePath) && is_file($filePath)) {
                            unlink($filePath);
                        }
                    }
                } else {
                    $remainingResources[] = $res;
                }
            }
            $data['resources'] = $remainingResources;

            // Delete folder entries
            $remainingFolders = [];
            foreach ($data['folders'] as $fold) {
                if (!in_array($fold['id'], $foldersToDelete)) {
                    $remainingFolders[] = $fold;
                }
            }
            $data['folders'] = $remainingFolders;

            // Clean reports
            $remainingReports = [];
            foreach ($data['reports'] as $rpt) {
                $isTargetDeleted = false;
                if ($rpt['targetType'] === 'folder' && in_array($rpt['targetId'], $foldersToDelete)) {
                    $isTargetDeleted = true;
                } else if ($rpt['targetType'] === 'resource' && in_array($rpt['targetId'], $deletedResourceIds)) {
                    $isTargetDeleted = true;
                }
                if (!$isTargetDeleted) {
                    $remainingReports[] = $rpt;
                }
            }
            $data['reports'] = $remainingReports;
            
            $modified = true;
            $actionFeedback = ['type' => 'success', 'message' => 'Folder and its contents recursively deleted!'];
        }
    } 
    
    elseif ($action === 'delete_resource') {
        $resourceId = trim($_POST['resourceId']);
        if (!empty($resourceId)) {
            $remainingResources = [];
            foreach ($data['resources'] as $res) {
                if ($res['id'] === $resourceId) {
                    if ($res['type'] === 'file' && !empty($res['file'])) {
                        $filePath = __DIR__ . '/' . $res['file'];
                        if (file_exists($filePath) && is_file($filePath)) {
                            unlink($filePath);
                        }
                    }
                } else {
                    $remainingResources[] = $res;
                }
            }
            $data['resources'] = $remainingResources;

            // Clean reports
            $remainingReports = [];
            foreach ($data['reports'] as $rpt) {
                if (!($rpt['targetType'] === 'resource' && $rpt['targetId'] === $resourceId)) {
                    $remainingReports[] = $rpt;
                }
            }
            $data['reports'] = $remainingReports;

            $modified = true;
            $actionFeedback = ['type' => 'success', 'message' => 'Resource deleted successfully!'];
        }
    } 
    
    elseif ($action === 'pin_folder') {
        $folderId = trim($_POST['folderId']);
        foreach ($data['folders'] as &$folder) {
            if ($folder['id'] === $folderId) {
                $folder['pinned'] = isset($folder['pinned']) ? !$folder['pinned'] : true;
                $modified = true;
                $state = $folder['pinned'] ? 'pinned' : 'unpinned';
                $actionFeedback = ['type' => 'success', 'message' => "Folder successfully {$state}!"];
                break;
            }
        }
    } 
    
    elseif ($action === 'edit_folder') {
        $folderId = trim($_POST['folderId']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $notes = trim($_POST['notes']);

        if (!empty($folderId) && !empty($name)) {
            foreach ($data['folders'] as &$folder) {
                if ($folder['id'] === $folderId) {
                    $folder['name'] = $name;
                    $folder['description'] = $description;
                    $folder['notes'] = $notes;
                    $modified = true;
                    $actionFeedback = ['type' => 'success', 'message' => 'Folder details updated!'];
                    break;
                }
            }
        }
    } 
    
    elseif ($action === 'dismiss_report') {
        $reportId = trim($_POST['reportId']);
        $remainingReports = [];
        foreach ($data['reports'] as $rpt) {
            if ($rpt['id'] !== $reportId) {
                $remainingReports[] = $rpt;
            } else {
                $modified = true;
            }
        }
        $data['reports'] = $remainingReports;
        if ($modified) {
            $actionFeedback = ['type' => 'success', 'message' => 'Report dismissed.'];
        }
    }

    if ($modified) {
        saveData($data);
    }
}

// Load current view data
$data = readData();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShareWithMe Admin — Lightweight Moderation</title>
  
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  
  <link rel="stylesheet" href="assets/css/style.css">
  
  <style>
    /* Admin specific sleek overrides */
    .admin-container {
      max-width: var(--max-width);
      margin: 80px auto var(--space-48);
      padding: 0 var(--space-24);
    }
    
    /* Login Page layout */
    .login-wrapper {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-24);
      background: linear-gradient(180deg, var(--white) 0%, var(--bg) 100%);
    }
    .login-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      padding: var(--space-32);
      width: 100%;
      max-width: 400px;
      text-align: center;
    }
    .login-icon {
      font-size: 2.5rem;
      margin-bottom: var(--space-16);
      display: inline-block;
    }
    .login-title {
      font-size: 1.5rem;
      font-weight: 700;
      letter-spacing: -0.025em;
      margin-bottom: var(--space-8);
      color: var(--text);
    }
    .login-subtitle {
      font-size: 0.9rem;
      color: var(--text-secondary);
      margin-bottom: var(--space-24);
    }
    
    /* Alert styles */
    .admin-alert {
      padding: var(--space-12) var(--space-16);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-24);
      font-size: 0.9rem;
      font-weight: 500;
    }
    .admin-alert-success {
      background: rgba(52, 199, 89, 0.1);
      border: 1px solid rgba(52, 199, 89, 0.3);
      color: #1e7e34;
    }
    .admin-alert-danger {
      background: rgba(255, 59, 48, 0.1);
      border: 1px solid rgba(255, 59, 48, 0.3);
      color: #b3241c;
    }
    
    /* Dashboard statistics cards */
    .metrics-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: var(--space-16);
      margin-bottom: var(--space-32);
    }
    .metric-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: var(--space-24);
      box-shadow: var(--shadow-sm);
      display: flex;
      flex-direction: column;
    }
    .metric-label {
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-secondary);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: var(--space-8);
    }
    .metric-val {
      font-size: 2.2rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1;
    }
    
    /* Split dashboard columns */
    .dashboard-layout {
      display: grid;
      grid-template-columns: 2fr 1fr;
      gap: var(--space-32);
    }
    
    @media(max-width: 992px) {
      .dashboard-layout {
        grid-template-columns: 1fr;
      }
    }
    
    /* Tables list */
    .card-panel {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: var(--space-24);
      box-shadow: var(--shadow-sm);
      margin-bottom: var(--space-32);
    }
    .panel-title {
      font-size: 1.1rem;
      font-weight: 700;
      margin-bottom: var(--space-16);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .admin-table-wrapper {
      width: 100%;
      overflow-x: auto;
    }
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
    }
    .admin-table th,
    .admin-table td {
      padding: var(--space-12) var(--space-16);
      border-bottom: 1px solid var(--border);
      text-align: left;
    }
    .admin-table th {
      background: var(--bg);
      font-weight: 600;
      color: var(--text-secondary);
    }
    .admin-table tr:hover td {
      background: rgba(0, 0, 0, 0.01);
    }
    
    /* Badge tags */
    .tag-badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: var(--radius-sm);
      font-size: 0.75rem;
      font-weight: 600;
    }
    .tag-reported {
      background: rgba(255, 59, 48, 0.1);
      color: var(--danger);
    }
    .tag-file {
      background: rgba(0, 113, 227, 0.1);
      color: var(--accent);
    }
    
    .btn-action-outline {
      background: none;
      border: 1px solid var(--border);
      padding: 4px 8px;
      font-size: 0.78rem;
      font-weight: 500;
      border-radius: var(--radius-sm);
      color: var(--text);
      transition: all var(--transition);
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .btn-action-outline:hover {
      background: var(--bg);
      border-color: var(--text-secondary);
    }
    .btn-action-danger {
      color: var(--danger);
    }
    .btn-action-danger:hover {
      background: rgba(255, 59, 48, 0.08);
      border-color: var(--danger);
    }
    
    /* Edit Folder modal styling (simple inline layout if visible) */
    .edit-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 1000;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: var(--space-24);
    }
    .edit-modal-content {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      padding: var(--space-32);
      width: 100%;
      max-width: 500px;
      position: relative;
    }
  </style>
</head>
<body>

  <?php if (!$isAuthenticated): ?>
    
    <!-- ===== LOGIN VIEW ===== -->
    <div class="login-wrapper">
      <div class="login-card">
        <span class="login-icon">🔒</span>
        <h1 class="login-title">ShareWithMe Admin</h1>
        <p class="login-subtitle">Unlock administrative moderation tools</p>
        
        <?php if (!empty($loginError)): ?>
          <div class="admin-alert admin-alert-danger"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="admin.php">
          <div class="form-group" style="text-align: left; margin-bottom: var(--space-24);">
            <label for="login-passphrase" class="form-label">Admin Passphrase</label>
            <input type="password" name="login_passphrase" id="login-passphrase" class="form-input" placeholder="••••••••••••" required autofocus />
          </div>
          <button type="submit" class="btn btn-primary btn-full">Unlock Dashboard</button>
        </form>
      </div>
    </div>
    
  <?php else: ?>
    
    <!-- ===== NAVBAR ===== -->
    <nav class="navbar scrolled">
      <div class="nav-container">
        <a href="index.php" class="nav-brand">
          <span class="brand-icon">📎</span>
          ShareWithMe Admin Panel
        </a>
        <ul class="nav-links">
          <li><a href="index.php" class="nav-link">Main Site</a></li>
          <li><a href="admin.php?action=logout" class="nav-link btn-action-danger" style="font-weight:600;">Lock Panel 🔒</a></li>
        </ul>
      </div>
    </nav>
    
    <main class="admin-container">
      
      <!-- Feedback alerts -->
      <?php if (!empty($actionFeedback['message'])): ?>
        <div class="admin-alert admin-alert-<?= htmlspecialchars($actionFeedback['type']) ?>">
          <?= htmlspecialchars($actionFeedback['message']) ?>
        </div>
      <?php endif; ?>
      
      <!-- Metrics summary row -->
      <div class="metrics-grid">
        <div class="metric-card">
          <span class="metric-label">Total Folders</span>
          <span class="metric-val"><?= count($data['folders']) ?></span>
        </div>
        <div class="metric-card">
          <span class="metric-label">Total Resources</span>
          <span class="metric-val"><?= count($data['resources']) ?></span>
        </div>
        <div class="metric-card">
          <span class="metric-label">Files Shared</span>
          <span class="metric-val">
            <?php
              $files = 0;
              foreach ($data['resources'] as $res) {
                  if ($res['type'] === 'file') $files++;
              }
              echo $files;
            ?>
          </span>
        </div>
        <div class="metric-card">
          <span class="metric-label">Active Reports</span>
          <span class="metric-val" style="color: <?= count($data['reports']) > 0 ? 'var(--danger)' : 'var(--text)' ?>;">
            <?= count($data['reports']) ?>
          </span>
        </div>
      </div>
      
      <!-- Split Dashboard Columns -->
      <div class="dashboard-layout">
        
        <!-- MAIN PANEL: RECENT FILES AND FOLDERS -->
        <div class="layout-main">
          
          <!-- Recent Uploads Table -->
          <div class="card-panel">
            <h2 class="panel-title">Recent Uploads & Shared Resources</h2>
            <div class="admin-table-wrapper">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Type</th>
                    <th>Folder Location</th>
                    <th>Shared By</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($data['resources'])): ?>
                    <tr>
                      <td colspan="5" style="text-align:center; color: var(--text-secondary); padding: 30px 0;">No shared resources yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php
                      // Display last 10 resources
                      $recentResources = array_slice($data['resources'], 0, 10);
                      foreach ($recentResources as $res):
                        $isLink = $res['type'] === 'link';
                    ?>
                      <tr>
                        <td>
                          <div style="font-weight: 600; color: var(--text);"><?= htmlspecialchars($res['title']) ?></div>
                          <div style="font-size:0.75rem; color:var(--text-secondary);"><?= htmlspecialchars($res['created_at']) ?></div>
                        </td>
                        <td>
                          <span class="tag-badge <?= $isLink ? 'tag-reported' : 'tag-file' ?>" style="background: <?= $isLink ? '#ff95001a' : 'rgba(0,113,227,0.1)' ?>; color: <?= $isLink ? '#ff9500' : 'var(--accent)' ?>;">
                            <?= htmlspecialchars($isLink ? 'Link' : $res['resourceType']) ?>
                          </span>
                        </td>
                        <td>
                          <small style="color:var(--text-secondary);"><?= htmlspecialchars(getFolderPath($res['folderId'], $data['folders'])) ?></small>
                        </td>
                        <td>
                          <small><?= htmlspecialchars(isset($res['uploadedBy']) ? $res['uploadedBy'] : 'Anonymous') ?></small>
                        </td>
                        <td>
                          <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete this file permanently?');">
                            <input type="hidden" name="action" value="delete_resource">
                            <input type="hidden" name="resourceId" value="<?= htmlspecialchars($res['id']) ?>">
                            <button type="submit" class="btn-action-outline btn-action-danger">🗑️ Delete</button>
                          </form>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- All Folders Moderation Table -->
          <div class="card-panel">
            <h2 class="panel-title">Folders Management</h2>
            <div class="admin-table-wrapper">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Folder Name</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Location Path</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($data['folders'])): ?>
                    <tr>
                      <td colspan="5" style="text-align:center; color: var(--text-secondary); padding: 30px 0;">No folders created yet.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($data['folders'] as $fold): 
                      $isPinned = isset($fold['pinned']) && $fold['pinned'] === true;
                    ?>
                      <tr>
                        <td>
                          <div style="font-weight: 600;"><?= htmlspecialchars($fold['name']) ?></div>
                          <?php if(!empty($fold['description'])): ?>
                            <div style="font-size: 0.78rem; color: var(--text-secondary);"><?= htmlspecialchars($fold['description']) ?></div>
                          <?php endif; ?>
                        </td>
                        <td>
                          <?php if ($isPinned): ?>
                            <span class="tag-badge" style="background: rgba(0, 102, 204, 0.1); color: var(--accent);">📌 Pinned</span>
                          <?php else: ?>
                            <span class="tag-badge" style="background: var(--bg); color: var(--text-secondary);">Standard</span>
                          <?php endif; ?>
                        </td>
                        <td>
                          <small><?= htmlspecialchars(isset($fold['createdBy']) ? $fold['createdBy'] : 'Anonymous') ?></small>
                        </td>
                        <td>
                          <small style="color: var(--text-secondary);"><?= htmlspecialchars(getFolderPath($fold['parentId'], $data['folders'])) ?></small>
                        </td>
                        <td>
                          <div style="display:flex; gap:6px; flex-wrap:wrap;">
                            <!-- Pin toggle -->
                            <form method="POST" action="admin.php" style="display:inline;">
                              <input type="hidden" name="action" value="pin_folder">
                              <input type="hidden" name="folderId" value="<?= htmlspecialchars($fold['id']) ?>">
                              <button type="submit" class="btn-action-outline"><?= $isPinned ? '📍 Unpin' : '📌 Pin' ?></button>
                            </form>
                            <!-- Edit trigger link -->
                            <a href="admin.php?edit=<?= urlencode($fold['id']) ?>" class="btn-action-outline">✏️ Edit</a>
                            <!-- Delete form -->
                            <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('DANGER: Delete folder, subfolders and resources recursively?');">
                              <input type="hidden" name="action" value="delete_folder">
                              <input type="hidden" name="folderId" value="<?= htmlspecialchars($fold['id']) ?>">
                              <button type="submit" class="btn-action-outline btn-action-danger">🗑️ Delete</button>
                            </form>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
          
        </div>
        
        <!-- SIDEBAR PANEL: REPORTED CONTENT FLAGS -->
        <div class="layout-sidebar">
          
          <div class="card-panel" style="border-color: <?= count($data['reports']) > 0 ? 'var(--danger)' : 'var(--border)' ?>;">
            <h2 class="panel-title" style="color: <?= count($data['reports']) > 0 ? 'var(--danger)' : 'var(--text)' ?>;">
              🚨 Reported Content (<?= count($data['reports']) ?>)
            </h2>
            
            <?php if (empty($data['reports'])): ?>
              <div style="text-align:center; color: var(--text-secondary); padding: 40px var(--space-8);">
                <div style="font-size: 1.8rem; margin-bottom: 8px;">✅</div>
                <h4>Zero reported content flags!</h4>
                <p style="font-size:0.8rem; margin-top:4px;">No community reports pending review.</p>
              </div>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap: var(--space-16);">
                <?php foreach ($data['reports'] as $rpt): 
                  // Find details of target
                  $targetName = 'Deleted Target';
                  $parentPath = '';
                  if ($rpt['targetType'] === 'folder') {
                      foreach ($data['folders'] as $f) {
                          if ($f['id'] === $rpt['targetId']) {
                              $targetName = '📁 ' . $f['name'];
                              $parentPath = getFolderPath($f['parentId'], $data['folders']);
                              break;
                          }
                      }
                  } else {
                      foreach ($data['resources'] as $r) {
                          if ($r['id'] === $rpt['targetId']) {
                              $targetName = '📄 ' . $r['title'];
                              $parentPath = getFolderPath($r['folderId'], $data['folders']);
                              break;
                          }
                      }
                  }
                ?>
                  <div style="border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-16); background: var(--bg-secondary);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: var(--space-8);">
                      <span class="tag-badge tag-reported"><?= htmlspecialchars($rpt['reason']) ?></span>
                      <small style="color:var(--text-secondary);"><?= htmlspecialchars(date('M d, H:i', $rpt['createdAt'])) ?></small>
                    </div>
                    <div style="font-weight: 600; font-size: 0.88rem; margin-bottom: 4px;"><?= htmlspecialchars($targetName) ?></div>
                    <div style="font-size: 0.76rem; color: var(--text-secondary); margin-bottom: var(--space-8);">Location: <?= htmlspecialchars($parentPath) ?></div>
                    <?php if (!empty($rpt['details'])): ?>
                      <p style="font-size:0.82rem; background: var(--white); border: 1px solid var(--border); padding: 6px 10px; border-radius: var(--radius-sm); margin-bottom: var(--space-12); color: var(--text);">
                        <?= htmlspecialchars($rpt['details']) ?>
                      </p>
                    <?php endif; ?>
                    
                    <div style="display:flex; gap:6px;">
                      <!-- Dismiss report -->
                      <form method="POST" action="admin.php" style="flex:1;">
                        <input type="hidden" name="action" value="dismiss_report">
                        <input type="hidden" name="reportId" value="<?= htmlspecialchars($rpt['id']) ?>">
                        <button type="submit" class="btn-action-outline" style="width:100%; justify-content:center;">Dismiss</button>
                      </form>
                      <!-- Delete Target -->
                      <form method="POST" action="admin.php" style="flex:1;" onsubmit="return confirm('Delete this reported content target?');">
                        <input type="hidden" name="action" value="<?= $rpt['targetType'] === 'folder' ? 'delete_folder' : 'delete_resource' ?>">
                        <input type="hidden" name="<?= $rpt['targetType'] === 'folder' ? 'folderId' : 'resourceId' ?>" value="<?= htmlspecialchars($rpt['targetId']) ?>">
                        <button type="submit" class="btn-action-outline btn-action-danger" style="width:100%; justify-content:center;">🗑️ Delete</button>
                      </form>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          
        </div>
        
      </div>
      
    </main>

    <!-- ===== EDIT FOLDER MODAL OVERLAY (PHP QUERY STATE TRIGGERED) ===== -->
    <?php 
      if (isset($_GET['edit'])):
        $editId = trim($_GET['edit']);
        $editFolder = null;
        foreach ($data['folders'] as $f) {
            if ($f['id'] === $editId) {
                $editFolder = $f;
                break;
            }
        }
        if ($editFolder):
    ?>
      <div class="edit-modal-backdrop">
        <div class="edit-modal-content">
          <a href="admin.php" class="modal-close" style="position:absolute; top: 16px; right: 20px; font-size:1.5rem;">&times;</a>
          <h3 class="modal-title" style="margin-bottom: var(--space-16);">Edit Folder: <?= htmlspecialchars($editFolder['name']) ?></h3>
          
          <form method="POST" action="admin.php">
            <input type="hidden" name="action" value="edit_folder">
            <input type="hidden" name="folderId" value="<?= htmlspecialchars($editFolder['id']) ?>">
            
            <div class="form-group">
              <label for="edit-name" class="form-label">Folder Name</label>
              <input type="text" name="name" id="edit-name" class="form-input" value="<?= htmlspecialchars($editFolder['name']) ?>" required>
            </div>
            
            <div class="form-group">
              <label for="edit-desc" class="form-label">Folder Description</label>
              <textarea name="description" id="edit-desc" class="form-input form-textarea" rows="2"><?= htmlspecialchars(isset($editFolder['description']) ? $editFolder['description'] : '') ?></textarea>
            </div>
            
            <div class="form-group">
              <label for="edit-notes" class="form-label">Pinned Notes</label>
              <textarea name="notes" id="edit-notes" class="form-input form-textarea" rows="3"><?= htmlspecialchars(isset($editFolder['notes']) ? $editFolder['notes'] : '') ?></textarea>
            </div>
            
            <div style="display:flex; gap: 12px; margin-top: var(--space-24);">
              <a href="admin.php" class="btn btn-secondary" style="flex:1; text-align:center; padding: 12px;">Cancel</a>
              <button type="submit" class="btn btn-primary" style="flex:2;">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    <?php 
        endif;
      endif; 
    ?>

  <?php endif; ?>

</body>
</html>
