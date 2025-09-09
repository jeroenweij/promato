<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

require 'includes/auth.php';
require 'PhpSpreadsheet/autoload.php';
require 'includes/db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

function formatDate($value) {
    // Match a MySQL-style DATE (YYYY-MM-DD)
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        $date = DateTime::createFromFormat('Y-m-d', $value);
        return $date ? $date->format('d-m-Y') : $value;
    }
    return $value;
}

try {
    $stmt = $pdo->query("SELECT 
        p.Name AS PN, 
        p.Id AS PID, 
        (SELECT MIN(StartDate) FROM Activities WHERE Project = p.Id) AS PSD,
        (SELECT MAX(EndDate) FROM Activities WHERE Project = p.Id) AS PED,
        a.Name AS AN, 
        a.Active AS AA, 
        p.Status AS PS, 
        a.Key AS AK, 
        a.StartDate AS ASD, 
        a.EndDate AS AED,
        w.Name AS WL
    FROM Activities a
    LEFT JOIN Projects p ON p.Id = a.Project
    LEFT JOIN Personel u ON p.Manager = u.Id
    LEFT JOIN Wbso w ON w.Id = a.Wbso
    WHERE Export = 1;"); 
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        die("No data found.");
    }

    // Create a new Spreadsheet
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Add headers
    $headers = array('project_naam','project_begindatum ','project_einddatum','project_code','project_notitie','project_status','project_extrastatus','project_classificatie','project_trefwoorden','klant_code','afdeling_naam','hoofdproject_code','project_grootboek',
        'project_kostenplaats','project_kostendrager','activiteit_naam','activiteit_code','activiteit_begindatum','activiteit_einddatum','activiteit_status','activiteit_facturabel','exclude_approval','activiteit_omschrijving','activiteit_trefwoorden',
        'activiteit_grootboek','activiteit_kostenplaats','activiteit_kostendrager','soortactiviteit','projectsoortactiviteit','projectbudgettype','financialBudget','aanneemsom','projectbudget','activiteitbudget','budgetactief','notificatiepercentage','toonnotificatie','currency','projectmanagers','projectrolename','linkprojectemployee');
    $columnIndex = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($columnIndex . '1', $header);
        $columnIndex++;
    }

    // Add rows
    $rowNumber = 2;
    foreach ($rows as $row) {
        $sheet->setCellValue('A' . $rowNumber, $row["PN"]);
        $sheet->setCellValue('B' . $rowNumber, formatDate($row["PSD"]));
        $sheet->setCellValue('C' . $rowNumber, formatDate($row["PED"]));
        $sheet->setCellValue('D' . $rowNumber, $row["PID"]);
        $sheet->setCellValue('F' . $rowNumber, $row["PS"] == 3 ? 'active' : 'closed');
        $sheet->setCellValue('G' . $rowNumber, $row["WL"] ?? '');
        $sheet->setCellValue('H' . $rowNumber, empty($row["WL"]) ? '' : 'WBSO');
        $sheet->setCellValue('P' . $rowNumber, $row["AN"]);
        $sheet->setCellValue('Q' . $rowNumber, str_pad($row['AK'], 3, '0', STR_PAD_LEFT));
        $sheet->setCellValue('R' . $rowNumber, formatDate($row["ASD"]));
        $sheet->setCellValue('S' . $rowNumber, formatDate($row["AED"]));
        $sheet->setCellValue('T' . $rowNumber, $row["AA"] ? 'active' : 'closed');
        $sheet->setCellValue('AB' . $rowNumber, 'Time');
        $sheet->setCellValue('AC' . $rowNumber, 'Time');
        $sheet->setCellValue('AD' . $rowNumber, 'activity');
        $sheet->setCellValue('AI' . $rowNumber, '1');
        $sheet->setCellValue('AJ' . $rowNumber, '100');
        $sheet->setCellValue('AK' . $rowNumber, '0');
        $sheet->setCellValue('AL' . $rowNumber, 'eur');
        $rowNumber++;
    }
  
    $filename = __DIR__  . '/exports/export_' . date('Ymd_His') . '.xls';
    $writer = new Xls($spreadsheet);
    $writer->save($filename);

    $pdo->exec("UPDATE Activities SET Export = 0");
    header("Location: export.php");
    if (ob_get_length() === 0) {
        header("Location: export.php");
        exit;
    }

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
<html><head>
    <script>
        // JavaScript redirect as fallback if PHP header redirect fails
        window.location.href = "export.php";
    </script>
</head></html>
<?php
ob_end_flush();