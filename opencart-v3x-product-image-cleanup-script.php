<?php
require_once('config.php');

// Database connection
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($db->connect_error) die("Connection failed: " . $db->connect_error);

// Initialize variables
$productTable = DB_PREFIX . 'product';
$productImageTable = DB_PREFIX . 'product_image';
$deleted = [];
$errors = [];
$orphans = [];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$baseDir = rtrim(DIR_IMAGE, '/') . '/';
$subdirectories = [];
$selectedDir = '';

// Get all subdirectories in image directory
function getSubdirectories($path) {
    $dirs = [];
    try {
        $items = new DirectoryIterator($path);
        foreach ($items as $item) {
            if ($item->isDir() && !$item->isDot()) {
                $dirs[] = $item->getFilename();
            }
        }
    } catch (Exception $e) {
        return [];
    }
    sort($dirs);
    return $dirs;
}
$subdirectories = getSubdirectories(DIR_IMAGE);

// Process directory input
if (!empty($_POST['directory'])) {
    $selectedDir = rtrim($_POST['directory'], '/');
    $fullPath = realpath(DIR_IMAGE . '/' . $selectedDir);
    
    // Validate directory is within image directory
    if ($fullPath && 
        is_dir($fullPath) && 
        strpos($fullPath, realpath(DIR_IMAGE)) === 0
    ) {
        $baseDir = $fullPath . '/';
    } else {
        $errors[] = "Invalid directory: " . htmlspecialchars($selectedDir);
        $baseDir = DIR_IMAGE;
    }
}

// Fetch used images from database
$usedImages = [];
if (empty($errors)) {
    $result = $db->query("SELECT image FROM $productTable WHERE image != '' UNION SELECT image FROM $productImageTable WHERE image != ''");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usedImages[] = $row['image'];
        }
        $usedImages = array_unique($usedImages);
    } else {
        $errors[] = "Database error: " . $db->error;
    }
}

// Scan for orphan files
if (empty($errors)) {
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg','jpeg','png','gif'])) {
                // Get relative path from main image directory
                $fullPath = $file->getRealPath();
                $relativePath = ltrim(str_replace(realpath(DIR_IMAGE), '', $fullPath), '/');
                
                if (!in_array($relativePath, $usedImages)) {
                    $orphans[] = $fullPath;
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "Directory scan failed: " . $e->getMessage();
    }
}

// Handle deletion
if ($action === 'delete' && empty($errors)) {
    foreach ($orphans as $file) {
        if (@unlink($file)) {
            $deleted[] = $file;
        } else {
            $errors[] = "Failed to delete: $file";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Orphan Product Image Cleaner for Opencart v3.x</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem }
        .warning { background: #fff3cd; padding: 1rem; border-left: 4px solid #ffeeba; margin: 2rem 0 }
        button { background: #4CAF50; color: white; padding: 0.5rem 1rem; border: none; cursor: pointer }
        button.delete { background: #f44336 }
        pre { background: #f8f9fa; padding: 1rem; overflow-x: auto }
        .dir-select { margin: 1rem 0 }
        .dir-list { margin: 1rem 0; padding: 0.5rem; border: 1px solid #ddd }
    </style>
</head>
<body>
    <h1>Orphan Product Image Cleanup</h1>
    
    <div class="warning">
        <strong>⚠️ Warning:</strong> This script permanently deletes images not found in:<br>
        - <?php echo DB_PREFIX; ?>product.image<br>
        - <?php echo DB_PREFIX; ?>product_image.image<br>
        Always backup your system before proceeding!
    </div>

    <?php if (!empty($errors)): ?>
    <div style="color: red; margin: 1rem 0;">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="dir-select">
            <h3>Select Directory (relative to <?php echo htmlspecialchars(DIR_IMAGE); ?>)</h3>
            <input type="text" name="directory" value="<?php echo htmlspecialchars($selectedDir); ?>" 
                   placeholder="Enter subdirectory path">
            
            <div class="dir-list">
                <strong>Available directories:</strong><br>
                <?php foreach ($subdirectories as $dir): ?>
                    <button type="button" onclick="document.querySelector('[name=directory]').value = '<?php echo htmlspecialchars($dir); ?>'">
                        <?php echo htmlspecialchars($dir); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($action === 'delete'): ?>
            <h2>Results</h2>
            <p>Deleted <?php echo count($deleted); ?> files</p>
            <?php if (!empty($errors)): ?>
                <h3>Errors:</h3>
                <pre><?php echo htmlspecialchars(implode("\n", $errors)); ?></pre>
            <?php endif; ?>
            <a href="?"><button>↩ Back</button></a>
        
        <?php elseif ($action === 'dry_run'): ?>
            <h2>Dry Run Results</h2>
            <p>Found <?php echo count($orphans); ?> orphan files in <?php echo htmlspecialchars($baseDir); ?></p>
            
            <?php if (!empty($orphans)): ?>
                <pre><?php echo htmlspecialchars(implode("\n", $orphans)); ?></pre>
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="delete" onclick="return confirm('PERMANENTLY DELETE ALL LISTED FILES?')">
                    🗑️ Delete All Orphans
                </button>
            <?php endif; ?>
            <a href="?"><button>↩ Back</button></a>
            
        <?php else: ?>
            <input type="hidden" name="action" value="dry_run">
            <button type="submit">🔍 Start Dry Run</button>
        <?php endif; ?>
    </form>
</body>
</html>
