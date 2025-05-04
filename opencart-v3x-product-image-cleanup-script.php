<?php
require_once('config.php'); // Adjust path if not in OpenCart root

// Database connection
$db = new mysqli(DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
if ($db->connect_error) die("Connection failed: " . $db->connect_error);

// Get tables with prefix
$productTable = DB_PREFIX . 'product';
$productImageTable = DB_PREFIX . 'product_image';

// Fetch used images
$usedImages = [];
$result = $db->query("SELECT image FROM $productTable WHERE image != '' UNION SELECT image FROM $productImageTable WHERE image != ''");
while ($row = $result->fetch_assoc()) {
    $usedImages[] = $row['image'];
}
$usedImages = array_unique($usedImages);

// Scan image directory
$dir = rtrim(DIR_IMAGE, '/') . '/';
$orphans = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['jpg','jpeg','png','gif'])) {
        $path = ltrim(str_replace($dir, '', $file->getRealPath()), '/');
        if (!in_array($path, $usedImages)) {
            $orphans[] = $file->getRealPath();
        }
    }
}

// Handle actions
$deleted = [];
$errors = [];
$action = $_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['action'] ?? 'dry_run') : '';

if ($action === 'delete') {
    foreach ($orphans as $file) {
        if (@unlink($file)) {
            $deleted[] = $file;
        } else {
            $errors[] = "Failed: $file";
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
    </style>
</head>
<body>
    <h1>Orphan Product Image Cleanup</h1>

    <div class="warning">
        <strong>⚠️ Warning:</strong> This script permanently deletes images not found in:<br>
        - <?= DB_PREFIX ?>product.image<br>
        - <?= DB_PREFIX ?>product_image.image<br>
        Always backup your system before proceeding!
    </div>

    <?php if ($action === 'delete'): ?>
        <h2>Results</h2>
        <p>Deleted <?= count($deleted) ?> files</p>
        <?php if ($errors): ?>
            <h3>Errors:</h3>
            <pre><?= htmlspecialchars(implode("\n", $errors)) ?></pre>
        <?php endif; ?>
        <a href="?"><button>↩ Back</button></a>
        
    <?php elseif ($action === 'dry_run'): ?>
        <h2>Dry Run Results</h2>
        <p>Found <?= count($orphans) ?> orphan files</p>

        <?php if ($orphans): ?>
            <pre><?= htmlspecialchars(implode("\n", $orphans)) ?></pre>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="delete" onclick="return confirm('PERMANENTLY DELETE ALL LISTED FILES?')">
                    🗑️ Delete All Orphans
                </button>
            </form>
        <?php endif; ?>
        <a href="?"><button>↩ Back</button></a>

    <?php else: ?>
        <form method="POST">
            <input type="hidden" name="action" value="dry_run">
            <button type="submit">🔍 Start Dry Run</button>
        </form>
    <?php endif; ?>
</body>
</html>
