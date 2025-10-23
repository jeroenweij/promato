<?php
$pageSpecificCSS = ['db-admin.css'];
require 'includes/header.php';
require_once 'includes/db.php';

// Initialize variables
$query = $_POST['query'] ?? '';
$result = null;
$error = null;
$rowCount = 0;
$executed = false;

// Get all tables in the database
$tablesStmt = $pdo->query("SHOW TABLES");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

// Store the table name from SELECT queries for edit functionality
$tableName = null;

// Execute query if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($query)) {
    $executed = true;

    try {
        // Check for destructive operations
        $isDestructive = preg_match('/^\s*(DELETE|DROP|TRUNCATE)\s/i', $query);

        // Extract table name from SELECT query for edit functionality
        if (preg_match('/^\s*SELECT\s+.*?\s+FROM\s+`?(\w+)`?/i', $query, $matches)) {
            $tableName = $matches[1];
        }

        // Add LIMIT to SELECT queries if not present (safety feature)
        if (preg_match('/^\s*SELECT\s/i', $query) && !preg_match('/\bLIMIT\s+\d+/i', $query)) {
            $query .= ' LIMIT 100';
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute();

        // Get affected rows for UPDATE/DELETE/INSERT
        $rowCount = $stmt->rowCount();

        // Fetch results for SELECT queries
        if (preg_match('/^\s*SELECT\s/i', $query)) {
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}

// Function to get query templates
function getQueryTemplate($table, $type) {
    switch ($type) {
        case 'select':
            return "SELECT * FROM `$table` LIMIT 100";
        case 'select_where':
            return "SELECT * FROM `$table` WHERE [condition] LIMIT 100";
        case 'insert':
            return "INSERT INTO `$table` ([columns]) VALUES ([values])";
        case 'update':
            return "UPDATE `$table` SET [column]=[value] WHERE [condition]";
        default:
            return '';
    }
}
?>

<section class="db-admin-section">
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar - Tables List -->
            <div class="col-md-2 sidebar">
                <h4>Database Tables</h4>
                <div class="tables-list">
                    <?php foreach ($tables as $table): ?>
                        <div class="table-item">
                            <div class="table-name" onclick="toggleTable('<?= htmlspecialchars($table) ?>')">
                                <i class="icon ion-md-arrow-dropdown"></i>
                                <?= htmlspecialchars($table) ?>
                            </div>
                            <div class="table-queries" id="table-<?= htmlspecialchars($table) ?>" style="display: none;">
                                <div class="query-template" onclick="loadTemplate('<?= htmlspecialchars(getQueryTemplate($table, 'select')) ?>')">
                                    SELECT all
                                </div>
                                <div class="query-template" onclick="loadTemplate('<?= htmlspecialchars(getQueryTemplate($table, 'select_where')) ?>')">
                                    SELECT with WHERE
                                </div>
                                <div class="query-template" onclick="loadTemplate('<?= htmlspecialchars(getQueryTemplate($table, 'insert')) ?>')">
                                    INSERT
                                </div>
                                <div class="query-template" onclick="loadTemplate('<?= htmlspecialchars(getQueryTemplate($table, 'update')) ?>')">
                                    UPDATE
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Main Content - Query Interface -->
            <div class="col-md-10 main-content">
                <h2>Database Admin - Raw Query Interface</h2>
                <p class="text-muted">Execute SQL queries directly on the database. USE WITH CAUTION.</p>

                <form method="POST" id="queryForm" onsubmit="return confirmDestructive()">
                    <div class="form-group">
                        <label for="query"><strong>SQL Query:</strong></label>
                        <textarea
                            class="form-control query-textarea"
                            id="query"
                            name="query"
                            rows="8"
                            placeholder="Enter your SQL query here..."
                            required><?= htmlspecialchars($query) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="icon ion-md-play"></i> Execute Query
                    </button>

                    <button type="button" class="btn btn-secondary" onclick="clearQuery()">
                        <i class="icon ion-md-trash"></i> Clear
                    </button>
                </form>

                <?php if ($executed): ?>
                    <!-- Status Block -->
                    <div class="status-block mt-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <h5><i class="icon ion-md-close-circle"></i> Query Failed</h5>
                                <p><strong>Error:</strong> <?= htmlspecialchars($error) ?></p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success">
                                <h5><i class="icon ion-md-checkmark-circle"></i> Query Executed Successfully</h5>
                                <?php if ($rowCount > 0): ?>
                                    <p><strong>Rows affected:</strong> <?= $rowCount ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Results Block -->
                    <?php if (!$error && $result !== null): ?>
                        <div class="results-block mt-4">
                            <h4>Query Results (<?= count($result) ?> rows)</h4>

                            <?php if (empty($result)): ?>
                                <div class="alert alert-info">No results returned.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-bordered table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <?php if ($tableName): ?>
                                                    <th>Actions</th>
                                                <?php endif; ?>
                                                <?php foreach (array_keys($result[0]) as $column): ?>
                                                    <th><?= htmlspecialchars($column) ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result as $row): ?>
                                                <tr>
                                                    <?php if ($tableName): ?>
                                                        <td class="actions-cell">
                                                            <button
                                                                class="btn btn-sm btn-edit"
                                                                onclick='loadUpdateQuery(<?= json_encode($tableName) ?>, <?= json_encode($row) ?>)'
                                                                title="Edit this row">
                                                                <i class="icon ion-md-create"></i>
                                                            </button>
                                                        </td>
                                                    <?php endif; ?>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<script>
// Toggle table dropdown
function toggleTable(tableName) {
    const element = document.getElementById('table-' + tableName);
    const isVisible = element.style.display !== 'none';

    // Close all other tables
    document.querySelectorAll('.table-queries').forEach(el => {
        el.style.display = 'none';
    });

    // Toggle this table
    element.style.display = isVisible ? 'none' : 'block';
}

// Load query template into textarea
function loadTemplate(template) {
    document.getElementById('query').value = template;
    document.getElementById('query').focus();
}

// Clear query textarea
function clearQuery() {
    document.getElementById('query').value = '';
    document.getElementById('query').focus();
}

// Load UPDATE query from a row
function loadUpdateQuery(tableName, rowData) {
    // Build SET clause with all columns
    const setClauses = [];
    const whereClauses = [];

    // Common ID column names to use for WHERE clause
    const idColumns = ['Id', 'id', 'ID'];
    let primaryKey = null;

    for (const [column, value] of Object.entries(rowData)) {
        // Escape single quotes in values
        const escapedValue = value === null ? 'NULL' : "'" + String(value).replace(/'/g, "\\'") + "'";

        // Add to SET clause
        setClauses.push(`\`${column}\` = ${escapedValue}`);

        // Check if this is a primary key column
        if (idColumns.includes(column)) {
            primaryKey = column;
            whereClauses.push(`\`${column}\` = ${escapedValue}`);
        }
    }

    // If no primary key found, use all columns in WHERE (less ideal but works)
    if (whereClauses.length === 0) {
        for (const [column, value] of Object.entries(rowData)) {
            const escapedValue = value === null ? 'NULL' : "'" + String(value).replace(/'/g, "\\'") + "'";
            whereClauses.push(`\`${column}\` = ${escapedValue}`);
        }
    }

    // Build the UPDATE query
    const query = `UPDATE \`${tableName}\`\nSET ${setClauses.join(',\n    ')}\nWHERE ${whereClauses.join(' AND ')}`;

    // Load into textarea and scroll to top
    document.getElementById('query').value = query;
    document.getElementById('query').focus();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Confirm destructive queries
function confirmDestructive() {
    const query = document.getElementById('query').value.trim();
    const isDestructive = /^\s*(DELETE|DROP|TRUNCATE)\s/i.test(query);

    if (isDestructive) {
        return confirm('WARNING: You are about to execute a destructive query that will permanently modify or delete data.\n\nQuery: ' + query + '\n\nAre you sure you want to proceed?');
    }

    return true;
}

// Highlight SQL syntax (basic)
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('query');

    // Add line numbers effect (optional enhancement)
    textarea.addEventListener('scroll', function() {
        // Could add line numbers here if desired
    });
});
</script>

<?php require 'includes/footer.php'; ?>
