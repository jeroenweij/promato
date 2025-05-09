<?php
session_start();

require 'includes/header.php';
require 'includes/db.php';

// Directory where exports are saved
$exportDir = __DIR__ . '/exports';

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $deleteFile = basename($_POST['delete']);
    $fullPath = $exportDir . '/' . $deleteFile;

    if (is_file($fullPath)) {
        unlink($fullPath);
    } else {
        echo "<script>alert('File not found.');</script>";
    }
}

// Get list of .xls files in the export directory
$files = glob($exportDir . '/*.xls');

// Check if any rows are marked for export
$stmt = $pdo->query("SELECT COUNT(*) FROM Activities WHERE Export = 1");
$exportCount = (int) $stmt->fetchColumn();
?>

<section>
    <div class="container">
        <h2>Excel Export Manager</h2>

        <!-- Generate New File Button -->
        <form method="post" action="export_generate.php" style="margin-bottom: 20px;">
            <button type="submit" class="btn btn-primary" <?= $exportCount === 0 ? 'disabled' : '' ?>>
                Generate New File
            </button>
            <?php if ($exportCount === 0): ?>
                <p class="text-muted">No rows marked for export.</p>
            <?php endif; ?>
        </form>

        <!-- Files List -->
        <?php if (empty($files)): ?>
            <p>No export files found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>File</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $filePath): 
                        $filename = basename($filePath);
                        $created = date("Y-m-d H:i:s", filemtime($filePath));
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($filename) ?></td>
                            <td><?= $created ?></td>
                            <td>
                                <a href="<?= 'exports/' . urlencode($filename) ?>" class="btn btn-sm btn-success" download>Download</a>
                                
                                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this file?')" style="display:inline;">
                                    <input type="hidden" name="delete" value="<?= htmlspecialchars($filename) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
