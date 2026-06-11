<?php
/* ============================================================
   ShareWithMe — upload.php
   
   Handles resource uploads (PDF files or links) and stores
   metadata in data/data.json.

   Endpoints:
     POST /upload.php  →  Upload a new resource

   Helper functions:
     readData()   — Safely read data/data.json (creates if missing)
     saveData()   — Write array back to data/data.json
     respond()    — Send a JSON response and exit
   ============================================================ */


// ── CONFIGURATION ────────────────────────────────────────────
define('DATA_DIR',   __DIR__ . '/data/');          // Folder for data.json
define('DATA_FILE',  DATA_DIR . 'data.json');      // Path to JSON storage
define('UPLOAD_DIR', __DIR__ . '/uploads/');        // Folder for uploaded PDFs
define('MAX_FILE_SIZE', 500 * 1024 * 1024);        // 500 MB max file size


// ── HELPER FUNCTIONS ─────────────────────────────────────────

/**
 * Send a JSON response to the browser and stop execution.
 *
 * @param bool   $success   Whether the operation succeeded
 * @param string $message   Human-readable message
 * @param int    $httpCode  HTTP status code (200, 201, 400, 500, etc.)
 * @param array  $extra     Optional extra data to include in response
 */
function respond($success, $message, $httpCode = 200, $extra = []) {
    http_response_code($httpCode);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/**
 * Read all data from data/data.json.
 * Creates the file with default structure if it doesn't exist.
 *
 * @return array  Structured data with 'folders' and 'resources' keys
 */
function readData() {
    // Create the data/ directory if it doesn't exist
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }

    $defaultData = [
        'folders' => [],
        'resources' => []
    ];

    // Create data.json with default structure if it doesn't exist
    if (!file_exists(DATA_FILE)) {
        file_put_contents(DATA_FILE, json_encode($defaultData, JSON_PRETTY_PRINT));
        return $defaultData;
    }

    // Read and parse the JSON file
    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);

    // If file was corrupted, return default structure
    if (!is_array($data)) {
        return $defaultData;
    }

    // --- MIGRATION LOGIC ---
    // If it's a flat list array (old schema), migrate it to structured schema
    $isFlatList = array_keys($data) === range(0, count($data) - 1);
    if ($isFlatList || (!isset($data['folders']) && !isset($data['resources']))) {
        $migratedResources = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $item['folderId'] = isset($item['folderId']) ? $item['folderId'] : null;
                $item['resourceType'] = isset($item['resourceType']) ? $item['resourceType'] : (isset($item['type']) && $item['type'] === 'file' ? 'PDF' : 'Other');
                $migratedResources[] = $item;
            }
        }
        $migratedData = [
            'folders' => [],
            'resources' => $migratedResources
        ];
        saveData($migratedData);
        return $migratedData;
    }

    // Ensure keys exist
    if (!isset($data['folders'])) {
        $data['folders'] = [];
    }
    if (!isset($data['resources'])) {
        $data['resources'] = [];
    }
    if (!isset($data['reports'])) {
        $data['reports'] = [];
    }

    return $data;
}

/**
 * Save the structured data back to data/data.json.
 *
 * @param array $data  The full data structure to save
 * @return bool  True on success, false on failure
 */
function saveData($data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents(DATA_FILE, $json) !== false;
}

/**
 * Get dynamic resource type label based on file extension.
 *
 * @param string $fileName
 * @return string
 */
function getResourceTypeFromExtension($fileName) {
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    switch ($extension) {
        case 'pdf':  return 'PDF';
        case 'zip':  return 'ZIP Archive';
        case 'docx': return 'Word Document';
        case 'pptx': return 'Presentation';
        default:     return 'Other';
    }
}

/**
 * Normalizes $_FILES array to always return a flat array of file uploads.
 * Each item in the returned array has keys: name, type, tmp_name, error, size.
 */
function getNormalizedFiles() {
    $normalized = [];
    $keysToCheck = ['files', 'files[]', 'file', 'file[]'];

    foreach ($keysToCheck as $key) {
        if (!isset($_FILES[$key])) {
            continue;
        }

        $fileData = $_FILES[$key];

        if (is_array($fileData['name'])) {
            // It's an array of files (e.g. key[] structure)
            $count = count($fileData['name']);
            for ($i = 0; $i < $count; $i++) {
                if (isset($fileData['error'][$i]) && $fileData['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $normalized[] = [
                        'name'     => $fileData['name'][$i],
                        'type'     => $fileData['type'][$i],
                        'tmp_name' => $fileData['tmp_name'][$i],
                        'error'    => $fileData['error'][$i],
                        'size'     => $fileData['size'][$i],
                    ];
                }
            }
        } else {
            // It's a single file
            if (isset($fileData['error']) && $fileData['error'] !== UPLOAD_ERR_NO_FILE) {
                $normalized[] = [
                    'name'     => $fileData['name'],
                    'type'     => $fileData['type'],
                    'tmp_name' => $fileData['tmp_name'],
                    'error'    => $fileData['error'],
                    'size'     => $fileData['size'],
                ];
            }
        }
    }

    return $normalized;
}

/**
 * Recursively find all subfolder IDs inside a parent folder.
 *
 * @param string $folderId
 * @param array  $folders
 * @return array
 */
function getFolderAndChildrenIds($folderId, $folders) {
    $ids = [$folderId];
    foreach ($folders as $f) {
        if ($f['parentId'] === $folderId) {
            $ids = array_merge($ids, getFolderAndChildrenIds($f['id'], $folders));
        }
    }
    return array_unique($ids);
}

/**
 * Verify admin credentials by hashing the POSTed adminKey.
 */
function verifyAdmin() {
    $adminKey = isset($_POST['adminKey']) ? trim($_POST['adminKey']) : '';
    if (empty($adminKey)) {
        respond(false, 'Admin authentication required.', 401);
    }
    $adminConfigPath = DATA_DIR . 'admin.json';
    if (!file_exists($adminConfigPath)) {
        // Create admin.json if missing
        $defaultHash = hash('sha256', 'sharewithme-admin');
        file_put_contents($adminConfigPath, json_encode(['passphraseHash' => $defaultHash], JSON_PRETTY_PRINT));
    }
    $config = json_decode(file_get_contents($adminConfigPath), true);
    $expectedHash = isset($config['passphraseHash']) ? $config['passphraseHash'] : '';
    if (hash('sha256', $adminKey) !== $expectedHash) {
        respond(false, 'Invalid admin passphrase.', 403);
    }
}


// ── HEADERS ──────────────────────────────────────────────────
// Tell the browser we return JSON
header('Content-Type: application/json');

// CORS headers for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    respond(true, 'OK');
}


// ── REQUEST VALIDATION ───────────────────────────────────────

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Only POST requests are allowed.', 405);
}

// ── ROUTE ACTIONS ────────────────────────────────────────────
$action = isset($_POST['action']) ? trim($_POST['action']) : 'upload';

// Actions that do NOT require file/resource uploads can be handled first

if ($action === 'admin_login') {
    $passphrase = isset($_POST['passphrase']) ? trim($_POST['passphrase']) : '';
    $adminConfigPath = DATA_DIR . 'admin.json';
    if (!file_exists($adminConfigPath)) {
        $defaultHash = hash('sha256', 'sharewithme-admin');
        file_put_contents($adminConfigPath, json_encode(['passphraseHash' => $defaultHash], JSON_PRETTY_PRINT));
    }
    $config = json_decode(file_get_contents($adminConfigPath), true);
    $expectedHash = isset($config['passphraseHash']) ? $config['passphraseHash'] : '';
    if (hash('sha256', $passphrase) === $expectedHash) {
        respond(true, 'Login successful!', 200, ['adminKey' => $passphrase]);
    } else {
        respond(false, 'Invalid passphrase.', 401);
    }
}

if ($action === 'check_duplicate_folder') {
    $parentId = isset($_POST['parentId']) && trim($_POST['parentId']) !== '' ? trim($_POST['parentId']) : null;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    if (empty($name)) {
        respond(false, 'Folder name is required.', 400);
    }
    $data = readData();
    $duplicates = [];
    foreach ($data['folders'] as $folder) {
        if ($folder['parentId'] === $parentId && strcasecmp(trim($folder['name']), $name) === 0) {
            $duplicates[] = $folder;
        }
    }
    if (count($duplicates) > 0) {
        respond(true, 'Similar folder name exists.', 200, ['exists' => true, 'folders' => $duplicates]);
    } else {
        respond(true, 'No duplicates found.', 200, ['exists' => false, 'folders' => []]);
    }
}

if ($action === 'report') {
    $targetType = isset($_POST['targetType']) ? trim($_POST['targetType']) : '';
    $targetId = isset($_POST['targetId']) ? trim($_POST['targetId']) : '';
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $details = isset($_POST['details']) ? trim($_POST['details']) : '';

    if (empty($targetType) || empty($targetId) || empty($reason)) {
        respond(false, 'Target type, target ID and reason are required.', 400);
    }
    if (!in_array($targetType, ['folder', 'resource'])) {
        respond(false, 'Invalid target type.', 400);
    }

    $data = readData();
    $newReport = [
        'id' => uniqid('rpt_'),
        'targetType' => $targetType,
        'targetId' => $targetId,
        'reason' => $reason,
        'details' => $details,
        'createdAt' => time()
    ];
    $data['reports'][] = $newReport;

    if (!saveData($data)) {
        respond(false, 'Failed to save report.', 500);
    }
    respond(true, 'Report submitted successfully!', 201, ['report' => $newReport]);
}

if ($action === 'admin_delete_folder') {
    verifyAdmin();
    $folderId = isset($_POST['folderId']) ? trim($_POST['folderId']) : '';
    if (empty($folderId)) {
        respond(false, 'Folder ID is required.', 400);
    }

    $data = readData();

    // Verify folder exists
    $folderExists = false;
    foreach ($data['folders'] as $fold) {
        if ($fold['id'] === $folderId) {
            $folderExists = true;
            break;
        }
    }
    if (!$folderExists) {
        respond(false, 'Folder not found.', 404);
    }

    $foldersToDelete = getFolderAndChildrenIds($folderId, $data['folders']);
    $deletedResourceIds = [];
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
    
    $remainingFolders = [];
    foreach ($data['folders'] as $fold) {
        if (!in_array($fold['id'], $foldersToDelete)) {
            $remainingFolders[] = $fold;
        }
    }
    $data['folders'] = $remainingFolders;

    // Clean up reports targeting deleted folders/resources
    $remainingReports = [];
    if (isset($data['reports'])) {
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
    }
    $data['reports'] = $remainingReports;

    if (!saveData($data)) {
        respond(false, 'Failed to update data store.', 500);
    }
    respond(true, 'Folder and all contents deleted successfully.');
}

if ($action === 'admin_delete_resource') {
    verifyAdmin();
    $resourceId = isset($_POST['resourceId']) ? trim($_POST['resourceId']) : '';
    if (empty($resourceId)) {
        respond(false, 'Resource ID is required.', 400);
    }

    $data = readData();

    $deleted = false;
    $remainingResources = [];
    foreach ($data['resources'] as $res) {
        if ($res['id'] === $resourceId) {
            if ($res['type'] === 'file' && !empty($res['file'])) {
                $filePath = __DIR__ . '/' . $res['file'];
                if (file_exists($filePath) && is_file($filePath)) {
                    unlink($filePath);
                }
            }
            $deleted = true;
        } else {
            $remainingResources[] = $res;
        }
    }
    if (!$deleted) {
        respond(false, 'Resource not found.', 404);
    }
    $data['resources'] = $remainingResources;

    // Remove associated reports
    $remainingReports = [];
    if (isset($data['reports'])) {
        foreach ($data['reports'] as $rpt) {
            if (!($rpt['targetType'] === 'resource' && $rpt['targetId'] === $resourceId)) {
                $remainingReports[] = $rpt;
            }
        }
    }
    $data['reports'] = $remainingReports;

    if (!saveData($data)) {
        respond(false, 'Failed to update data store.', 500);
    }
    respond(true, 'Resource deleted successfully.');
}

if ($action === 'admin_edit_folder') {
    verifyAdmin();
    $folderId = isset($_POST['folderId']) ? trim($_POST['folderId']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    if (empty($folderId)) {
        respond(false, 'Folder ID is required.', 400);
    }
    if (empty($name)) {
        respond(false, 'Folder name is required.', 400);
    }

    $data = readData();
    $updated = false;
    foreach ($data['folders'] as &$folder) {
        if ($folder['id'] === $folderId) {
            $folder['name'] = $name;
            $folder['description'] = $description;
            $folder['notes'] = $notes;
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        respond(false, 'Folder not found.', 404);
    }

    if (!saveData($data)) {
        respond(false, 'Failed to update folder.', 500);
    }
    respond(true, 'Folder updated successfully.');
}

if ($action === 'admin_pin_folder') {
    verifyAdmin();
    $folderId = isset($_POST['folderId']) ? trim($_POST['folderId']) : '';
    if (empty($folderId)) {
        respond(false, 'Folder ID is required.', 400);
    }

    $data = readData();
    $updated = false;
    $pinnedState = false;
    foreach ($data['folders'] as &$folder) {
        if ($folder['id'] === $folderId) {
            $folder['pinned'] = isset($folder['pinned']) ? !$folder['pinned'] : true;
            $pinnedState = $folder['pinned'];
            $updated = true;
            break;
        }
    }
    if (!$updated) {
        respond(false, 'Folder not found.', 404);
    }

    if (!saveData($data)) {
        respond(false, 'Failed to update folder pin status.', 500);
    }
    respond(true, 'Folder pin toggled successfully.', 200, ['pinned' => $pinnedState]);
}

if ($action === 'admin_dismiss_report') {
    verifyAdmin();
    $reportId = isset($_POST['reportId']) ? trim($_POST['reportId']) : '';
    if (empty($reportId)) {
        respond(false, 'Report ID is required.', 400);
    }

    $data = readData();
    $remainingReports = [];
    $dismissed = false;
    if (isset($data['reports'])) {
        foreach ($data['reports'] as $rpt) {
            if ($rpt['id'] === $reportId) {
                $dismissed = true;
            } else {
                $remainingReports[] = $rpt;
            }
        }
    }
    if (!$dismissed) {
        respond(false, 'Report not found.', 404);
    }
    $data['reports'] = $remainingReports;

    if (!saveData($data)) {
        respond(false, 'Failed to update reports list.', 500);
    }
    respond(true, 'Report dismissed successfully.');
}

if ($action === 'create_folder' || $action === 'createFolder') {
    // --- CREATE FOLDER ACTION ---
    $folderName = '';
    if (isset($_POST['folderName'])) {
        $folderName = trim($_POST['folderName']);
    } elseif (isset($_POST['name'])) {
        $folderName = trim($_POST['name']);
    }

    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $notes       = isset($_POST['notes'])       ? trim($_POST['notes'])       : '';
    $parentId    = isset($_POST['parentId']) && trim($_POST['parentId']) !== '' ? trim($_POST['parentId']) : null;
    $createdBy   = isset($_POST['createdBy']) && trim($_POST['createdBy']) !== '' ? trim($_POST['createdBy']) : 'Anonymous Contributor';

    if (empty($folderName)) {
        respond(false, 'Folder name is required.', 400);
    }

    $data = readData();

    // Verify parent folder exists if a parentId is specified
    if ($parentId !== null) {
        $parentExists = false;
        foreach ($data['folders'] as $folder) {
            if ($folder['id'] === $parentId) {
                $parentExists = true;
                break;
            }
        }
        if (!$parentExists) {
            respond(false, 'Specified parent folder does not exist.', 400);
        }
    }

    $newFolder = [
        'id'          => uniqid('fold_'),
        'name'        => $folderName,
        'description' => $description,
        'notes'       => $notes,
        'parentId'    => $parentId,
        'createdBy'   => $createdBy,
        'createdAt'   => time(),
        'pinned'      => false
    ];

    // Append folder
    $data['folders'][] = $newFolder;

    if (!saveData($data)) {
        respond(false, 'Failed to save folder data.', 500);
    }

    respond(true, 'Folder created successfully!', 201, ['folder' => $newFolder]);
}

// Default action: Handle Resource Upload Action
// ── READ FORM DATA ───────────────────────────────────────────
$title        = isset($_POST['title'])        ? trim($_POST['title'])        : '';
$description  = isset($_POST['description'])  ? trim($_POST['description'])  : '';
$category     = isset($_POST['category'])     ? trim($_POST['category'])     : 'Other';
$link         = isset($_POST['link'])         ? trim($_POST['link'])         : '';
$folderId     = isset($_POST['folderId']) && trim($_POST['folderId']) !== '' ? trim($_POST['folderId']) : null;
$resourceType = isset($_POST['resourceType']) ? trim($_POST['resourceType']) : 'Other';
$uploadedBy   = isset($_POST['uploadedBy']) && trim($_POST['uploadedBy']) !== '' ? trim($_POST['uploadedBy']) : 'Anonymous Contributor';

// Check what was provided
error_log("FILES received by upload.php: " . print_r($_FILES, true));
file_put_contents(__DIR__ . '/upload_debug.log', date('[Y-m-d H:i:s] ') . "FILES: " . print_r($_FILES, true) . " POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
$uploadedFiles = getNormalizedFiles();
$hasFile     = !empty($uploadedFiles);
$hasLink     = !empty($link);

// Either a file or a link must be provided
if (!$hasFile && !$hasLink) {
    respond(false, 'Please provide either a file or a link.', 400);
}

// ── VALIDATE INPUT ───────────────────────────────────────────

// Title is required only for link uploads
if ($hasLink && empty($title)) {
    respond(false, 'Title is required.', 400);
}

// Check folder destination if specified
$data = readData();
if ($folderId !== null) {
    $folderExists = false;
    foreach ($data['folders'] as $folder) {
        if ($folder['id'] === $folderId) {
            $folderExists = true;
            break;
        }
    }
    if (!$folderExists) {
        respond(false, 'Destination folder does not exist.', 400);
    }
}

$allowedExtensions = ['pdf', 'zip', 'docx', 'pptx'];
$allowedMimeTypes = [
    'application/pdf',
    'application/zip',
    'application/x-zip-compressed',
    'application/x-zip',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/octet-stream'
];

$newResources = [];

if ($hasFile) {
    $fileCount = count($uploadedFiles);
    
    // Ensure within limits
    if ($fileCount > 100) {
        respond(false, 'Maximum of 100 files allowed at once.', 400);
    }
    
    $totalSize = 0;
    foreach ($uploadedFiles as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            respond(false, 'Error during file upload code: ' . $file['error'], 400);
        }
        $totalSize += $file['size'];
    }
    
    if ($totalSize > MAX_FILE_SIZE) {
        respond(false, 'Total upload size exceeds 500 MB limit.', 400);
    }

    // Validate formats for all files first
    foreach ($uploadedFiles as $file) {
        $fileName = $file['name'];
        $fileTmp  = $file['tmp_name'];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        if (empty($fileTmp) || !file_exists($fileTmp)) {
            respond(false, "Failed to locate temporary upload file for '$fileName'.", 400);
        }

        $mimeType  = mime_content_type($fileTmp);

        if (!in_array($extension, $allowedExtensions) || !in_array($mimeType, $allowedMimeTypes)) {
            respond(false, "File '$fileName' has an invalid format. Only PDF, ZIP, DOCX, and PPTX are allowed.", 400);
        }
    }

    // Create the uploads/ folder if it doesn't exist
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    // Process files
    foreach ($uploadedFiles as $file) {
        $fileName = $file['name'];
        $fileTmp  = $file['tmp_name'];
        
        $safeName   = preg_replace('/[^a-zA-Z0-9._-]/', '', $fileName);
        $uniqueName = time() . '_' . uniqid() . '_' . $safeName;
        $destination = UPLOAD_DIR . $uniqueName;

        if (!move_uploaded_file($fileTmp, $destination)) {
            respond(false, "Failed to save the file '$fileName'.", 500);
        }

        $filePath = 'uploads/' . $uniqueName;
        $resTitle = pathinfo($fileName, PATHINFO_FILENAME);
        $resType  = getResourceTypeFromExtension($fileName);

        $newResources[] = [
            'id'           => uniqid('res_'),
            'folderId'     => $folderId,
            'title'        => $resTitle,
            'description'  => '',
            'category'     => $category,
            'type'         => 'file',
            'resourceType' => $resType,
            'file'         => $filePath,
            'link'         => '',
            'uploadedBy'   => $uploadedBy,
            'created_at'   => date('Y-m-d H:i:s'),
            'timestamp'    => time()
        ];
    }
} else {
    // Link upload
    $newResources[] = [
        'id'           => uniqid('res_'),
        'folderId'     => $folderId,
        'title'        => $title,
        'description'  => $description,
        'category'     => $category,
        'type'         => 'link',
        'resourceType' => $resourceType,
        'file'         => '',
        'link'         => $link,
        'uploadedBy'   => $uploadedBy,
        'created_at'   => date('Y-m-d H:i:s'),
        'timestamp'    => time()
    ];
}

// ── SAVE TO DATA.JSON ────────────────────────────────────────
// Prepend new resources (newest first)
foreach (array_reverse($newResources) as $res) {
    array_unshift($data['resources'], $res);
}

if (!saveData($data)) {
    respond(false, 'Failed to save resource data.', 500);
}

// ── SUCCESS RESPONSE ─────────────────────────────────────────
if (count($newResources) > 1) {
    respond(true, 'Resources shared successfully!', 201, ['resources' => $newResources]);
} else {
    respond(true, 'Resource shared successfully!', 201, ['resource' => $newResources[0]]);
}
