<?php
require 'includes/header.php';
require 'includes/db.php';

$userId = $_SESSION['user_id'] ?? null;

// Redirect if not logged in
if (!$userId) {
    header('Location: login.php');
    exit();
}

// Check if user is admin/chef (Type > 1 from your example)
$adminStmt = $pdo->prepare("SELECT Type FROM Personel WHERE Id = ? AND Type > 1");
$adminStmt->execute([$userId]);
$isAdmin = $adminStmt->fetch();

if (!$isAdmin) {
    header('Location: kroketto.php');
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

// Add new snack
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'add_snack') {
    $name = trim($_POST['name']);
    $count = (int)$_POST['available_count'];
    
    if (empty($name) || $count < 0) {
        $message = "Please provide a valid snack name and count!";
        $messageType = "error";
    } else {
        try {
            $addStmt = $pdo->prepare("INSERT INTO snack_options (name, available_count) VALUES (?, ?)");
            $addStmt->execute([$name, $count]);
            $message = "Snack '" . htmlspecialchars($name) . "' added successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error adding snack: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Update snack count
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'update_count') {
    $snackId = (int)$_POST['snack_id'];
    $newCount = (int)$_POST['new_count'];
    
    if ($newCount < 0) {
        $message = "Count cannot be negative!";
        $messageType = "error";
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE snack_options SET available_count = ? WHERE id = ?");
            $updateStmt->execute([$newCount, $snackId]);
            $message = "Snack count updated successfully!";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Error updating count: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Delete snack
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_snack') {
    $snackId = (int)$_POST['snack_id'];
    
    try {
        // Check if snack has orders
        $orderCheckStmt = $pdo->prepare("SELECT COUNT(*) as order_count FROM snack_orders WHERE snack_id = ?");
        $orderCheckStmt->execute([$snackId]);
        $orderCount = $orderCheckStmt->fetch()['order_count'];
        
        if ($orderCount > 0) {
            $message = "Cannot delete snack that has existing orders! Cancel orders first.";
            $messageType = "error";
        } else {
            $deleteStmt = $pdo->prepare("DELETE FROM snack_options WHERE id = ?");
            $deleteStmt->execute([$snackId]);
            $message = "Snack deleted successfully!";
            $messageType = "success";
        }
    } catch (PDOException $e) {
        $message = "Error deleting snack: " . $e->getMessage();
        $messageType = "error";
    }
}

// Reset weekly orders
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reset_orders') {
    try {
        $pdo->beginTransaction();
              
        // Delete orders
        $deleteOrdersStmt = $pdo->prepare("DELETE FROM snack_orders");
        $deleteOrdersStmt->execute([]);
        
        $pdo->commit();
        $message = "All orders for this week have been removed!";
        $messageType = "success";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error resetting orders: " . $e->getMessage();
        $messageType = "error";
    }
}

// Bulk update counts
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['counts'] as $snackId => $count) {
            $count = (int)$count;
            if ($count >= 0) {
                $updateStmt = $pdo->prepare("UPDATE snack_options SET available_count = ? WHERE id = ?");
                $updateStmt->execute([$count, $snackId]);
            }
        }
        
        $pdo->commit();
        $message = "All counts updated successfully!";
        $messageType = "success";
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error updating counts: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all snacks
$snacksStmt = $pdo->prepare("SELECT * FROM snack_options ORDER BY name");
$snacksStmt->execute();
$snacks = $snacksStmt->fetchAll();

// Get current week statistics
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));
$statsStmt = $pdo->prepare("
    SELECT 
        so.id,
        so.name, 
        so.available_count,
        COALESCE(orders.order_count, 0) as ordered_count,
        (so.available_count + COALESCE(orders.order_count, 0)) as original_count
    FROM snack_options so
    LEFT JOIN (
        SELECT snack_id, COUNT(*) as order_count 
        FROM snack_orders 
        WHERE order_date >= ? 
        GROUP BY snack_id
    ) orders ON so.id = orders.snack_id
    ORDER BY so.name
");
$statsStmt->execute([$currentWeekStart]);
$stats = $statsStmt->fetchAll();

// Get detailed orders for current week
$detailsStmt = $pdo->prepare("
    SELECT 
        sorders.id,
        so.name as snack_name,
        sorders.order_date,
        sorders.created_at,
        p.Shortname as user_name
    FROM snack_orders sorders
    JOIN snack_options so ON sorders.snack_id = so.id
    LEFT JOIN Personel p ON sorders.user_id = p.Id
    WHERE sorders.order_date >= ?
    ORDER BY so.name, sorders.created_at
");
$detailsStmt->execute([$currentWeekStart]);
$orderDetails = $detailsStmt->fetchAll();

$nextFriday = date('Y-m-d', strtotime('friday this week'));
if (date('w') == 5 && date('H') >= 12) {
    $nextFriday = date('Y-m-d', strtotime('next friday'));
}
?>

<section>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>🔧 Kroketto Administration</h2>
                    <a href="kroketto.php" class="btn btn-outline-primary">← Back to Orders</a>
                </div>
                
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType == 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center bg-primary text-white">
                            <div class="card-body">
                                <h4><?= count($snacks) ?></h4>
                                <small>Total Snack Types</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <h4><?= array_sum(array_column($stats, 'available_count')) ?></h4>
                                <small>Available Stock</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-warning text-white">
                            <div class="card-body">
                                <h4><?= array_sum(array_column($stats, 'ordered_count')) ?></h4>
                                <small>This Week's Orders</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-info text-white">
                            <div class="card-body">
                                <h4><?= date('d-m-Y', strtotime($nextFriday)) ?></h4>
                                <small>Next Friday</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs mb-4" id="adminTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link active" id="inventory-tab" data-bs-toggle="tab" href="#inventory" role="tab" aria-controls="inventory" aria-selected="true">
                            📦 Inventory Management
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="orders-tab" data-bs-toggle="tab" href="#orders" role="tab" aria-controls="orders" aria-selected="false">
                            📋 Current Orders
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link" id="add-snack-tab" data-bs-toggle="tab" href="#add-snack" role="tab" aria-controls="add-snack" aria-selected="false">
                            ➕ Add New Snack
                        </a>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="adminTabsContent">
                    
                    <!-- Inventory Management Tab -->
                    <div class="tab-pane fade show active" id="inventory" role="tabpanel" aria-labelledby="inventory-tab">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Stock Overview</h5>
                                <form method="POST" action="" class="d-inline">
                                    <input type="hidden" name="action" value="reset_orders">
                                    <button type="submit" class="btn btn-warning" onclick="return confirm('Are you sure you want to delete all orders?')">Delete all orders</button>
                                </form>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="bulk_update">
                                    <div class="table-responsive">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Snack Name</th>
                                                    <th>Available Now</th>
                                                    <th>Ordered This Week</th>
                                                    <th>Original Stock</th>
                                                    <th>Update Count</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($stats as $stat): ?>
                                                <tr class="<?= $stat['available_count'] == 0 ? 'table-warning' : '' ?>">
                                                    <td>
                                                        <strong><?= htmlspecialchars($stat['name']) ?></strong>
                                                        <?php if ($stat['available_count'] == 0): ?>
                                                        <span class="badge bg-danger ms-2">Out of Stock</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $stat['available_count'] > 5 ? 'success' : ($stat['available_count'] > 0 ? 'warning' : 'danger') ?>">
                                                            <?= $stat['available_count'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $stat['ordered_count'] ?></td>
                                                    <td><?= $stat['original_count'] ?></td>
                                                    <td>
                                                        <input type="number" class="form-control form-control-sm" 
                                                               name="counts[<?= $stat['id'] ?>]" 
                                                               value="<?= $stat['available_count'] ?>" 
                                                               min="0" style="width: 80px;">
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                onclick="deleteSnack(<?= $stat['id'] ?>, '<?= htmlspecialchars($stat['name'], ENT_QUOTES) ?>')">
                                                            🗑️
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button type="submit" class="btn btn-primary">💾 Update All Counts</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Current Orders Tab -->
                    <div class="tab-pane fade" id="orders" role="tabpanel" aria-labelledby="orders-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Orders for Week Starting <?= date('d-m-Y', strtotime($currentWeekStart)) ?></h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($orderDetails)): ?>
                                <p class="text-muted text-center py-4">No orders for this week yet.</p>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Snack</th>
                                                <th>Order Date</th>
                                                <th>Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orderDetails as $order): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($order['user_name'] ?? 'Unknown') ?></td>
                                                <td><?= htmlspecialchars($order['snack_name']) ?></td>
                                                <td><?= date('d-m-Y', strtotime($order['order_date'])) ?></td>
                                                <td><?= date('H:i', strtotime($order['created_at'])) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Summary by snack type -->
                                <div class="row mt-4">
                                    <div class="col-12">
                                        <h6>Preparation List:</h6>
                                        <div class="row g-3">
                                            <?php 
                                            $snackCounts = [];
                                            foreach ($orderDetails as $order) {
                                                $snackCounts[$order['snack_name']] = ($snackCounts[$order['snack_name']] ?? 0) + 1;
                                            }
                                            foreach ($snackCounts as $snackName => $count): 
                                            ?>
                                            <div class="col-sm-6 col-md-3">
                                                <div class="card text-center">
                                                    <div class="card-body py-2">
                                                        <div class="h5 text-primary mb-1"><?= $count ?></div>
                                                        <div class="small"><?= htmlspecialchars($snackName) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Add New Snack Tab -->
                    <div class="tab-pane fade" id="add-snack" role="tabpanel" aria-labelledby="add-snack-tab">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Add New Snack Type</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="add_snack">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label">Snack Name</label>
                                            <input type="text" class="form-control" id="name" name="name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="available_count" class="form-label">Available Count</label>
                                            <input type="number" class="form-control" id="available_count" name="available_count" 
                                                   min="0" value="0" required>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-success">➕ Add Snack</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Delete Snack Form (hidden) -->
<form id="deleteSnackForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete_snack">
    <input type="hidden" name="snack_id" id="deleteSnackId">
</form>

<script>
// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tabs
    var triggerTabList = [].slice.call(document.querySelectorAll('#adminTabs a'));
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl);
        
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    
    // Highlight low stock items
    const rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        const availableCell = row.cells[1];
        if (availableCell) {
            const count = parseInt(availableCell.textContent.trim());
            if (count <= 2 && count > 0) {
                row.classList.add('table-warning');
            }
        }
    });
});

// Delete snack confirmation
function deleteSnack(snackId, snackName) {
    if (confirm('Are you sure you want to delete "' + snackName + '"?\n\nThis action cannot be undone and will fail if there are existing orders for this snack.')) {
        document.getElementById('deleteSnackId').value = snackId;
        document.getElementById('deleteSnackForm').submit();
    }
}
</script>

<?php require 'includes/footer.php'; ?>
