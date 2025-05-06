<?php
require_once('config.php');

// Database connection
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

// Initialize variables
$productTable = DB_PREFIX . 'product';
$productImageTable = DB_PREFIX . 'product_image';
$deleted = [];
$errors = [];
$orphans = [];
$action = isset($_POST['action']) ? $_POST['action'] : '';
$baseDir = rtrim(DIR_IMAGE, '/') . '/';
$submittedDir = isset($_POST['directory']) ? $_POST['directory'] : '';

// Process directory input
if (!empty($submittedDir)) {
    $submittedDir = rtrim($submittedDir, '/') . '/';
    $resolvedPath = realpath($submittedDir);
    
    if ($resolvedPath && is_dir($resolvedPath) && is_readable($resolvedPath)) {
        $baseDir = $resolvedPath . '/';
    } else {
        $errors[] = "Invalid directory: " . htmlspecialchars($submittedDir);
    }
}

// Fetch used images
if (empty($errors)) {
    $usedImages = [];
    $result = $db->query("SELECT image FROM $productTable WHERE image != '' UNION SELECT image FROM $productImageTable WHERE image != ''");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $usedImages[] = $row['image'];
        }
        $usedImages = array_unique($usedImages);
    } else {
        $errors[] = "Database query failed: " . $db->error;
    }
}

// Scan directory for orphan files
if (empty($errors)) {
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg','jpeg','png','gif'])) {
                $path = ltrim(str_replace($baseDir, '', $file->getRealPath()), '/');
                if (!in_array($path, $usedImages)) {
                    $orphans[] = $file->getRealPath();
                }
            }
        }
    } catch (Exception $e) {
        $errors[] = "Directory scan failed: " . $e->getMessage();
    }
}

// Handle file deletion
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
        .directory-input { margin: 1rem 0 }
        .directory-input input { width: 300px; padding: 0.3rem }
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
    <div class="error" style="color: red; margin: 1rem 0;">
        <?php foreach ($errors as $error): ?>
            <p><?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="directory-input">
            <label>Scan Directory: 
                <input type="text" name="directory" value="<?php echo htmlspecialchars($baseDir); ?>"
                    placeholder="Enter image directory path">
            </label>
            <small>(Default: <?php echo htmlspecialchars(DIR_IMAGE); ?>)</small>
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
            <p>Found <?php echo count($orphans); ?> orphan files</p>
            
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
