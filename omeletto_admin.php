<?php
require 'includes/header.php';
require_once 'includes/db.php';

// Redirect if not logged in
if (!$userId) {
    header('Location: login.php');
    exit();
}

// Check if user is admin (Type > 1)
$adminStmt = $pdo->prepare("SELECT Type FROM Personel WHERE Id = ? AND Type > 1");
$adminStmt->execute([$userId]);
$isAdmin = $adminStmt->fetch();

if (!$isAdmin) {
    header('Location: omeletto.php');
    exit();
}

// Handle form submissions
$message = '';
$messageType = '';

// Reset weekly orders
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'reset_orders') {
    try {
        $deleteOrdersStmt = $pdo->prepare("DELETE FROM egg_orders");
        $deleteOrdersStmt->execute();

        $message = "All egg orders have been deleted!";
        $messageType = "success";

    } catch (PDOException $e) {
        $message = "Error resetting orders: " . $e->getMessage();
        $messageType = "error";
    }
}

// Delete specific order
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'delete_order') {
    $orderId = (int)$_POST['order_id'];

    try {
        $deleteStmt = $pdo->prepare("DELETE FROM egg_orders WHERE id = ?");
        $deleteStmt->execute([$orderId]);
        $message = "Order deleted successfully!";
        $messageType = "success";

    } catch (PDOException $e) {
        $message = "Error deleting order: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get current week statistics
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));

// Get overview by egg type
$statsStmt = $pdo->prepare("
    SELECT
        egg_type,
        COUNT(*) as order_count
    FROM egg_orders
    WHERE order_date >= ?
    GROUP BY egg_type
    ORDER BY egg_type
");
$statsStmt->execute([$currentWeekStart]);
$stats = $statsStmt->fetchAll();

// Calculate totals
$totalOrders = 0;
$eggCounts = ['boiled' => 0, 'fried' => 0];
foreach ($stats as $stat) {
    $totalOrders += $stat['order_count'];
    $eggCounts[$stat['egg_type']] = $stat['order_count'];
}

// Get detailed orders for current week
$detailsStmt = $pdo->prepare("
    SELECT
        eo.id,
        eo.egg_type,
        eo.order_date,
        eo.created_at,
        p.Shortname as user_name,
        p.Name as full_name
    FROM egg_orders eo
    LEFT JOIN Personel p ON eo.user_id = p.Id
    WHERE eo.order_date >= ?
    ORDER BY eo.egg_type, eo.created_at
");
$detailsStmt->execute([$currentWeekStart]);
$orderDetails = $detailsStmt->fetchAll();

// Get next Wednesday's date
$nextWednesday = date('Y-m-d', strtotime('wednesday this week'));
if (date('w') == 3 && date('H') >= 12) {
    $nextWednesday = date('Y-m-d', strtotime('next wednesday'));
} elseif (date('w') > 3) {
    $nextWednesday = date('Y-m-d', strtotime('next wednesday'));
}

// Egg options
$eggOptions = [
    'boiled' => [
        'name' => 'Boiled Egg',
        'icon' => '🥚'
    ],
    'fried' => [
        'name' => 'Fried Egg',
        'icon' => '🍳'
    ]
];
?>

<section>
    <div class="container">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>🔧 Omeletto Administration</h2>
                    <a href="omeletto.php" class="btn btn-outline-primary">← Back to Orders</a>
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
                                <div style="font-size: 2rem;">🥚</div>
                                <h4><?= $eggCounts['boiled'] ?></h4>
                                <small>Boiled Eggs</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-success text-white">
                            <div class="card-body">
                                <div style="font-size: 2rem;">🍳</div>
                                <h4><?= $eggCounts['fried'] ?></h4>
                                <small>Fried Eggs</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-warning text-white">
                            <div class="card-body">
                                <div style="font-size: 2rem;">📊</div>
                                <h4><?= $totalOrders ?></h4>
                                <small>Total Orders</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center bg-info text-white">
                            <div class="card-body">
                                <div style="font-size: 2rem;">📅</div>
                                <h4><?= date('d-m', strtotime($nextWednesday)) ?></h4>
                                <small>Next Wednesday</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preparation Summary -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🍳 Preparation List for Wednesday <?= date('d-m-Y', strtotime($nextWednesday)) ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if ($totalOrders == 0): ?>
                        <p class="text-muted text-center py-4">No orders for this week yet.</p>
                        <?php else: ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($eggOptions as $type => $option): ?>
                            <?php if ($eggCounts[$type] > 0): ?>
                            <div class="col-sm-6">
                                <div class="card text-center h-100">
                                    <div class="card-body">
                                        <div style="font-size: 3rem;" class="mb-2"><?= $option['icon'] ?></div>
                                        <h3 class="text-primary mb-1"><?= $eggCounts[$type] ?></h3>
                                        <div class="text-muted"><?= htmlspecialchars($option['name']) ?><?= $eggCounts[$type] > 1 ? 's' : '' ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>

                        <div class="alert alert-info">
                            <strong>Quick Summary:</strong> Prepare <?= $eggCounts['boiled'] ?> boiled and <?= $eggCounts['fried'] ?> fried eggs for Wednesday.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Detailed Orders Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">📋 Detailed Orders - Week of <?= date('d-m-Y', strtotime($currentWeekStart)) ?></h5>
                        <?php if ($totalOrders > 0): ?>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="action" value="reset_orders">
                            <button type="submit" class="btn btn-warning btn-sm"
                                    onclick="return confirm('Are you sure you want to delete all egg orders? This cannot be undone!')">
                                🗑️ Delete All Orders
                            </button>
                        </form>
                        <?php endif; ?>
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
                                        <th>Egg Type</th>
                                        <th>Order Date</th>
                                        <th>Time</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderDetails as $order): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($order['user_name'] ?? 'Unknown') ?>
                                            <?php if ($order['full_name']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($order['full_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 1.5rem;"><?= $eggOptions[$order['egg_type']]['icon'] ?></span>
                                            <?= htmlspecialchars($eggOptions[$order['egg_type']]['name']) ?>
                                        </td>
                                        <td><?= date('d-m-Y', strtotime($order['order_date'])) ?></td>
                                        <td><?= date('H:i', strtotime($order['created_at'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-danger btn-sm"
                                                    onclick="deleteOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['user_name'], ENT_QUOTES) ?>')">
                                                🗑️
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Orders grouped by egg type (for easy printing) -->
                        <div class="mt-4">
                            <h6>Orders by Type (for preparation):</h6>
                            <?php
                            $ordersByType = [];
                            foreach ($orderDetails as $order) {
                                $ordersByType[$order['egg_type']][] = $order;
                            }
                            ?>
                            <div class="row g-3">
                                <?php foreach ($ordersByType as $type => $orders): ?>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <strong><?= $eggOptions[$type]['icon'] ?> <?= htmlspecialchars($eggOptions[$type]['name']) ?> (<?= count($orders) ?>)</strong>
                                        </div>
                                        <div class="card-body">
                                            <ul class="mb-0">
                                                <?php foreach ($orders as $order): ?>
                                                <li><?= htmlspecialchars($order['user_name'] ?? 'Unknown') ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        Orders automatically clean up after the week is over.<br>
                        Users can order until Wednesday morning.
                    </small>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Delete Order Form (hidden) -->
<form id="deleteOrderForm" method="POST" action="" style="display: none;">
    <input type="hidden" name="action" value="delete_order">
    <input type="hidden" name="order_id" id="deleteOrderId">
</form>

<script>
// Auto-dismiss alerts
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});

// Delete order confirmation
function deleteOrder(orderId, userName) {
    if (confirm('Are you sure you want to delete the order for "' + userName + '"?\n\nThis action cannot be undone.')) {
        document.getElementById('deleteOrderId').value = orderId;
        document.getElementById('deleteOrderForm').submit();
    }
}

// Print preparation list
function printPreparationList() {
    window.print();
}
</script>

<style>
@media print {
    .btn, .card-header form, nav, footer {
        display: none !important;
    }
}
</style>

<?php require 'includes/footer.php'; ?>
