<?php
require 'includes/header.php';

// Clean up old entries (remove entries from previous week's Wednesday)
$cleanupStmt = $pdo->prepare("DELETE FROM egg_orders WHERE order_date < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 5 DAY)");
$cleanupStmt->execute();

// Handle form submission
$message = '';
$messageType = '';

// Handle cancel order
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));

    try {
        $deleteStmt = $pdo->prepare("DELETE FROM egg_orders WHERE user_id = ? AND order_date >= ?");
        $result = $deleteStmt->execute([$userId, $currentWeekStart]);

        if ($result && $deleteStmt->rowCount() > 0) {
            $message = "Your egg order has been cancelled successfully!";
            $messageType = "success";
        } else {
            $message = "No active order found to cancel.";
            $messageType = "info";
        }

    } catch (Exception $e) {
        $message = "Error cancelling order: " . $e->getMessage();
        $messageType = "error";
    }
}

// Handle egg selection
if ($_POST && isset($_POST['egg_type'])) {
    $eggType = $_POST['egg_type'];
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));

    // Validate egg type
    if (!in_array($eggType, ['boiled', 'fried'])) {
        $message = "Invalid egg type selected!";
        $messageType = "error";
    } else {
        try {
            // Check if user already has an order for this week
            $checkStmt = $pdo->prepare("SELECT egg_type FROM egg_orders WHERE user_id = ? AND order_date >= ?");
            $checkStmt->execute([$userId, $currentWeekStart]);
            $existingOrder = $checkStmt->fetch();

            if ($existingOrder) {
                // Update existing order
                $oldEggType = $existingOrder['egg_type'];

                if ($oldEggType != $eggType) {
                    $updateStmt = $pdo->prepare("UPDATE egg_orders SET egg_type = ?, order_date = CURDATE() WHERE user_id = ? AND order_date >= ?");
                    $updateStmt->execute([$eggType, $userId, $currentWeekStart]);

                    $eggName = ucfirst($eggType) . ' Egg';
                    $message = "Your egg choice has been updated to " . $eggName . "!";
                    $messageType = "success";
                } else {
                    $eggName = ucfirst($eggType) . ' Egg';
                    $message = "You already selected " . $eggName . " for this week!";
                    $messageType = "info";
                }
            } else {
                // Create new order
                $insertStmt = $pdo->prepare("INSERT INTO egg_orders (user_id, egg_type, order_date) VALUES (?, ?, CURDATE())");
                $insertStmt->execute([$userId, $eggType]);

                $eggName = ucfirst($eggType) . ' Egg';
                $message = "Your egg choice " . $eggName . " has been registered!";
                $messageType = "success";
            }

        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get user's current order for this week
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));
$currentOrderStmt = $pdo->prepare("
    SELECT egg_type
    FROM egg_orders
    WHERE user_id = ? AND order_date >= ?
");
$currentOrderStmt->execute([$userId, $currentWeekStart]);
$currentOrder = $currentOrderStmt->fetch();

// Get overview of all orders for this week
$overviewStmt = $pdo->prepare("
    SELECT egg_type, COUNT(*) as order_count
    FROM egg_orders
    WHERE order_date >= ?
    GROUP BY egg_type
    ORDER BY egg_type
");
$overviewStmt->execute([$currentWeekStart]);
$overview = $overviewStmt->fetchAll();

// Get next Wednesday's date
$nextWednesday = date('Y-m-d', strtotime('wednesday this week'));
if (date('w') == 3 && date('H') >= 12) { // If it's Wednesday after noon, show next Wednesday
    $nextWednesday = date('Y-m-d', strtotime('next wednesday'));
} elseif (date('w') > 3) { // If it's after Wednesday, show next week's Wednesday
    $nextWednesday = date('Y-m-d', strtotime('next wednesday'));
}

// Egg options
$eggOptions = [
    'boiled' => [
        'name' => 'Boiled Egg',
        'description' => 'Soft or hard boiled, perfectly cooked',
        'icon' => '🥚'
    ],
    'fried' => [
        'name' => 'Fried Egg',
        'description' => 'Sunny side up or over easy',
        'icon' => '🍳'
    ]
];
?>

<section>
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h2 class="text-center mb-4">🍳 Omeletto - Wednesday Egg Orders</h2>
                <p class="text-center text-muted mb-4">Order your egg for Wednesday (<?= date('d-m-Y', strtotime($nextWednesday)) ?>)</p>

                <?php if ($currentOrder): ?>
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Your Current Order</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">
                                    <?= $eggOptions[$currentOrder['egg_type']]['icon'] ?>
                                    <?= htmlspecialchars($eggOptions[$currentOrder['egg_type']]['name']) ?>
                                </h4>
                                <p class="text-muted mb-0">
                                    <small>For Wednesday, <?= date('d-m-Y', strtotime($nextWednesday)) ?></small><br>
                                    <small>You can change your selection or cancel until Wednesday morning.</small>
                                </p>
                            </div>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="cancel_order">
                                <button type="submit" class="btn btn-outline-danger"
                                        onclick="return confirm('Are you sure you want to cancel your order for <?= htmlspecialchars($eggOptions[$currentOrder['egg_type']]['name'], ENT_QUOTES) ?>?')">
                                    ❌ Cancel Order
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType == 'success' ? 'success' : ($messageType == 'error' ? 'danger' : 'info') ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Select Your Egg</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <?php foreach ($eggOptions as $type => $option): ?>
                                <div class="col-sm-6">
                                    <div class="card h-100 <?= $currentOrder && $currentOrder['egg_type'] == $type ? 'border-primary' : '' ?>">
                                        <div class="card-body d-flex flex-column">
                                            <div class="form-check flex-grow-1">
                                                <input class="form-check-input" type="radio" name="egg_type"
                                                       id="egg_<?= $type ?>" value="<?= $type ?>"
                                                       <?= $currentOrder && $currentOrder['egg_type'] == $type ? 'checked' : '' ?>>
                                                <label class="form-check-label w-100" for="egg_<?= $type ?>">
                                                    <div class="text-center mb-2" style="font-size: 3rem;">
                                                        <?= $option['icon'] ?>
                                                    </div>
                                                    <strong class="d-block text-center"><?= htmlspecialchars($option['name']) ?></strong>
                                                </label>
                                                <p class="mt-2 mb-0 text-muted small text-center">
                                                    <?= htmlspecialchars($option['description']) ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <?= $currentOrder ? 'Update Order' : 'Place Order' ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Overview Section -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">📊 This Week's Overview</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($overview)): ?>
                        <p class="text-muted text-center">No orders yet for this week.</p>
                        <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($overview as $item): ?>
                            <div class="col-sm-6">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="mb-2" style="font-size: 2rem;">
                                        <?= $eggOptions[$item['egg_type']]['icon'] ?>
                                    </div>
                                    <div class="h4 text-primary mb-1"><?= $item['order_count'] ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($eggOptions[$item['egg_type']]['name']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-3 text-center">
                            <strong>Total orders: <?= array_sum(array_column($overview, 'order_count')) ?></strong>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        Orders automatically reset each week on Monday.<br>
                        You can change your order until Wednesday morning.
                    </small>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<?php require 'includes/footer.php'; ?>
