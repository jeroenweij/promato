<?php
$pageSpecificCSS = ['page-access-admin.css'];
require 'includes/header.php';
require_once 'includes/db.php';

// Check if user has admin access
if (($_SESSION['auth_level'] ?? 0) < 4) {
    die("Access denied. Admin privileges required.");
}

// Fetch all active personnel, ordered by Type and Shortname
$personnelStmt = $pdo->query("
    SELECT Id, Shortname, Type, Team
    FROM Personel
    WHERE plan = 1 AND (EndDate IS NULL OR EndDate >= CURDATE())
    ORDER BY Type DESC, Team, Ord, Shortname
");
$personnel = $personnelStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all pages, ordered by Auth level and Name
$pagesStmt = $pdo->query("
    SELECT Id, Name, Auth, Menu
    FROM Pages
    ORDER BY Auth ASC, Menu, Id
");
$pages = $pagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all existing PageAccess records
$accessStmt = $pdo->query("
    SELECT UserId, PageId
    FROM PageAccess
");
$accessRecords = $accessStmt->fetchAll(PDO::FETCH_ASSOC);

// Build a lookup array for quick access checks
$hasAccess = [];
foreach ($accessRecords as $record) {
    $hasAccess[$record['UserId'] . '-' . $record['PageId']] = true;
}

// Get personnel types for grouping
$typesStmt = $pdo->query("
    SELECT Id, Name
    FROM Types
    ORDER BY Id DESC
");
$types = $typesStmt->fetchAll(PDO::FETCH_ASSOC);
$typeById = [];
foreach ($types as $type) {
    $typeById[$type['Id']] = $type['Name'];
}

// Define color scheme for different auth levels
$authColors = [
    1 => '#f0f0f0', // Guest
    2 => '#e3f2fd', // Basic
    3 => '#fff9c4', // User
    4 => '#ffe0b2', // Manager
    5 => '#ffccbc', // Admin
    6 => '#f8bbd0'  // Super Admin
];
?>

<section id="page-access-admin">
    <div class="container-fluid">
        <h1>Page Access Management</h1>
        
        <div class="legend">
            <strong>Auth Levels:</strong>
            <?php foreach ($authColors as $level => $color): ?>
                <div class="legend-item">
                    <div class="legend-color" style="background-color: <?= $color ?>;"></div>
                    <span><?= $typeById[$level] ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="save-indicator" id="saveIndicator"></div>

        <div class="table-container">
            <table class="page-access-table">
                <thead>
                    <tr>
                        <th class="person-header">Person</th>
                        <?php foreach ($pages as $page): ?>
                            <th class="page-header" style="background-color: <?= $authColors[$page['Auth']] ?? '#fff' ?>;">
                                <div title="<?= htmlspecialchars($page['Name']) ?> (Auth: <?= $page['Auth'] ?>)">
                                    <?= htmlspecialchars($page['Name']) ?>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentType = null;
                    foreach ($personnel as $person): 
                        
                        if ($currentType !== $person['Type']):
                            $currentType = $person['Type'];
                            $typeName = $typeById[$currentType] ?? 'Unknown';
                        endif;
                        
                        $personAuth = $person['Type'] ?? 0;
                    ?>
                        <tr>
                            <td class="person-cell">
                                <?= htmlspecialchars($person['Shortname']) ?>
                            </td>
                            <?php foreach ($pages as $page): 
                                $key = $person['Id'] . '-' . $page['Id'];
                                $hasExplicitAccess = isset($hasAccess[$key]);
                                $hasAuthAccess = $personAuth >= $page['Auth'];
                                $isChecked = $hasExplicitAccess || $hasAuthAccess;
                                $isDisabled = $hasAuthAccess;
                            ?>
                                <td class="access-cell" 
                                    style="background-color: <?= $authColors[$page['Auth']] ?? '#fff' ?>;"
                                    data-user-id="<?= $person['Id'] ?>"
                                    data-page-id="<?= $page['Id'] ?>"
                                    data-disabled="<?= $isDisabled ? 'true' : 'false' ?>">
                                    <input 
                                        type="checkbox" 
                                        <?= $isChecked ? 'checked' : '' ?>
                                        <?= $isDisabled ? 'disabled' : '' ?>
                                        onchange="toggleAccess(this, <?= $person['Id'] ?>, <?= $page['Id'] ?>)"
                                        title="<?= $isDisabled ? 'Already has access via auth level' : 'Grant explicit access' ?>"
                                    >
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
function showSaveIndicator(message, isError = false) {
    const indicator = document.getElementById('saveIndicator');
    indicator.textContent = message;
    indicator.className = 'save-indicator' + (isError ? ' error' : '');
    indicator.style.display = 'block';
    
    setTimeout(() => {
        indicator.style.display = 'none';
    }, 2000);
}

function toggleAccess(checkbox, userId, pageId) {
    const isChecked = checkbox.checked;
    const action = isChecked ? 'grant' : 'revoke';
    
    fetch('update_page_access.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            userId: userId,
            pageId: pageId,
            action: action
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSaveIndicator(isChecked ? 'Access granted' : 'Access revoked');
        } else {
            showSaveIndicator('Error: ' + (data.message || 'Unknown error'), true);
            checkbox.checked = !isChecked; // Revert checkbox
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showSaveIndicator('Error saving changes', true);
        checkbox.checked = !isChecked; // Revert checkbox
    });
}

// Optional: Click on cell to toggle checkbox
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.access-cell').forEach(cell => {
        cell.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT' && this.dataset.disabled !== 'true') {
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (checkbox && !checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    checkbox.onchange();
                }
            }
        });
    });
});
</script>

<?php require 'includes/footer.php'; ?>