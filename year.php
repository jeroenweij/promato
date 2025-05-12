<?php
session_start();

// Define available years with an associative array for easy maintenance
$availableYears = [
    2024 => '2024',
    2025 => '2025', 
    2026 => '2026'
];

// Check if a new year is being selected
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selectedYear'])) {
    // Validate the selected year
    $postedYear = (int)$_POST['selectedYear'];
    
    // Ensure the posted year is in the list of available years
    if (array_key_exists($postedYear, $availableYears)) {
        $_SESSION['selectedYear'] = $postedYear;
        $successMessage = "Year {$postedYear} has been selected.";
    } else {
        $errorMessage = "Invalid year selection.";
    }
}

require 'includes/header.php';
?>

<section>
    <div class="container">
        <h2>Year Selection</h2>
        
        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        
        <?php if (isset($successMessage)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="yearSelect">Select Year:</label>
                <select name="selectedYear" id="yearSelect" class="form-control">
                    <?php foreach ($availableYears as $year => $label): ?>
                        <option value="<?= $year ?>" <?= $year === $selectedYear ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Change Year</button>
        </form>
        
        <p>Current Selected Year: <?= $selectedYear ?: 'Not Set' ?></p>
    </div>
</section>

<?php require 'includes/footer.php'; ?>