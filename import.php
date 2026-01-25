<?php
/**
 * Import Backup - Restore data from backup.php exports
 *
 * Truncates all data tables and imports from uploaded SQL file.
 */

require 'includes/header.php';

// Extra auth check - must be level 7
if ($userAuthLevel < 7) {
    echo '<section class="white"><div class="container"><div class="alert alert-danger">Access denied. God level access required.</div></div></section>';
    require 'includes/footer.php';
    exit;
}

$messages = [];
$errors = [];
$importSuccess = false;

// Tables in the order they appear in backups (and their truncate order - reverse due to FK constraints)
$backupTables = ['Teams', 'Personel', 'Wbso', 'WbsoBudget', 'Projects', 'Activities', 'Hours', 'TeamHours', 'Availability', 'Budgets'];
$truncateOrder = array_reverse($backupTables);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['backup_file'])) {
    $file = $_FILES['backup_file'];

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload error: " . $file['error'];
    } elseif ($file['size'] === 0) {
        $errors[] = "Uploaded file is empty";
    } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB limit
        $errors[] = "File too large (max 50MB)";
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'sql') {
            $errors[] = "Invalid file type. Only .sql files are allowed";
        }
    }

    if (empty($errors)) {
        $sqlContent = file_get_contents($file['tmp_name']);

        if (empty(trim($sqlContent))) {
            $errors[] = "SQL file is empty";
        } else {
            try {
                // Disable foreign key checks
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                $messages[] = "Foreign key checks disabled";

                // Truncate tables in reverse order
                foreach ($truncateOrder as $table) {
                    $quotedTable = "`" . str_replace("`", "``", $table) . "`";
                    $pdo->exec("TRUNCATE TABLE $quotedTable");
                    $messages[] = "Truncated table: $table";
                }

                // Split SQL into individual statements
                // Handle multi-line INSERT statements properly
                $statements = [];
                $currentStatement = '';

                foreach (explode("\n", $sqlContent) as $line) {
                    $trimmedLine = trim($line);

                    // Skip empty lines and comments
                    if (empty($trimmedLine) || strpos($trimmedLine, '--') === 0) {
                        continue;
                    }

                    $currentStatement .= $line . "\n";

                    // Check if statement ends with semicolon
                    if (substr($trimmedLine, -1) === ';') {
                        $statements[] = trim($currentStatement);
                        $currentStatement = '';
                    }
                }

                // Execute each statement
                $insertCount = 0;
                $rowsAffected = 0;

                foreach ($statements as $statement) {
                    if (!empty(trim($statement))) {
                        $affected = $pdo->exec($statement);
                        if ($affected !== false) {
                            $insertCount++;
                            $rowsAffected += $affected;
                        }
                    }
                }

                $messages[] = "Executed $insertCount INSERT statements ($rowsAffected rows affected)";

                // Re-enable foreign key checks
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                $messages[] = "Foreign key checks re-enabled";

                $importSuccess = true;
                $messages[] = "Import completed successfully";

            } catch (PDOException $e) {
                // Try to re-enable FK checks even on error
                try {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                } catch (Exception $e2) {
                    // Ignore
                }
                $errors[] = "Database error: " . $e->getMessage();
            } catch (Exception $e) {
                try {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                } catch (Exception $e2) {
                    // Ignore
                }
                $errors[] = "Error: " . $e->getMessage();
            }
        }
    }
}

// Get table row counts for display
$tableCounts = [];
foreach ($backupTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $tableCounts[$table] = $stmt->fetchColumn();
    } catch (Exception $e) {
        $tableCounts[$table] = 'Error';
    }
}
?>

<section class="white">
    <div class="container">
        <h2>Import Backup</h2>

        <div class="alert alert-danger mb-4">
            <strong>Warning:</strong> This will <strong>DELETE ALL DATA</strong> in the following tables and replace it with data from the backup file:
            <br><code><?= implode(', ', $backupTables) ?></code>
            <br><br>
            This action cannot be undone. Make sure you have a current backup before proceeding.
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>Import Failed:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($importSuccess): ?>
            <div class="alert alert-success">
                <strong>Import Successful!</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($messages as $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Current Table Status -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Current Table Status</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Table</th>
                            <th class="text-right">Row Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableCounts as $table => $count): ?>
                        <tr>
                            <td><?= htmlspecialchars($table) ?></td>
                            <td class="text-right"><?= is_numeric($count) ? number_format($count) : $count ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Import Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Upload Backup File</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="backup_file">Select .sql backup file</label>
                        <input type="file" class="form-control-file" id="backup_file" name="backup_file" accept=".sql" required>
                        <small class="form-text text-muted">Maximum file size: 50MB. Only .sql files created by backup.php are supported.</small>
                    </div>

                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="confirm" required>
                        <label class="form-check-label" for="confirm">
                            I understand this will delete all existing data and replace it with the backup
                        </label>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i data-lucide="upload" class="mr-2"></i>
                        Import Backup
                    </button>
                    <a href="backup.php" class="btn btn-secondary ml-2">
                        <i data-lucide="download" class="mr-2"></i>
                        Create Backup First
                    </a>
                </form>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
