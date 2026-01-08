<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTables = array('Teams', 'Personel', 'Wbso', 'WbsoBudget', 'Projects', 'Activities', 'Hours', 'TeamHours', 'Availability', 'Budgets');
    $sqlData = "";

    foreach ($selectedTables as $table) {
        $quotedTable = "`" . str_replace("`", "``", $table) . "`";

        $stmt = $pdo->query("SELECT * FROM $quotedTable");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $rowCount = count($rows);

        if ($rowCount === 0) continue;

        $columns = array_map(fn($col) => "`" . str_replace("`", "``", $col) . "`", array_keys($rows[0]));
        $colList = implode(", ", $columns);

        for ($i = 0; $i < $rowCount; $i += 500) {
            $chunk = array_slice($rows, $i, 500);
            $valueLines = [];

            foreach ($chunk as $row) {
                $values = array_map(function ($val) use ($pdo) {
                    return isset($val) ? $pdo->quote($val) : 'NULL';
                }, array_values($row));
                $valueLines[] = "(" . implode(", ", $values) . ")";
            }

            $sqlData .= "INSERT INTO $quotedTable ($colList) VALUES\n" . implode(",\n", $valueLines) . ";\n\n";
        }
    }

    // Output as downloadable SQL file
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="backup_' . date('Y-m-d_H-i-s') . '.sql"');
    echo $sqlData;
    exit;
}
?>

<?php
require 'includes/header.php';
?>
<section id="pricing"><div class="container">
    <h2>Create backup</h2>
    <form method="post">
        <button type="submit">Generate Backup</button>
    </form>
    </div>
</section>
<?php require 'includes/footer.php'; ?>

