<?php
require 'includes/header.php';
require 'includes/db.php';

$userId = $_SESSION['user_id'] ?? null;

// Redirect if not logged in
if (!$userId) {
    header('Location: login.php');
    exit();
}

// Clean up old entries (remove entries from previous week)
$cleanupStmt = $pdo->prepare("DELETE FROM snack_orders WHERE order_date < DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) + 7 DAY)");
$cleanupStmt->execute();

// Handle form submission
$message = '';
$messageType = '';

// Handle cancel order
if ($_POST && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
    
    try {
        $pdo->beginTransaction();
        
        // Get the current order to restore stock
        $orderStmt = $pdo->prepare("SELECT snack_id FROM snack_orders WHERE user_id = ? AND order_date >= ?");
        $orderStmt->execute([$userId, $currentWeekStart]);
        $currentOrder = $orderStmt->fetch();
        
        if ($currentOrder) {
            // Restore stock count
            $restoreStmt = $pdo->prepare("UPDATE snack_options SET available_count = available_count + 1 WHERE id = ?");
            $restoreStmt->execute([$currentOrder['snack_id']]);
            
            // Delete the order
            $deleteStmt = $pdo->prepare("DELETE FROM snack_orders WHERE user_id = ? AND order_date >= ?");
            $deleteStmt->execute([$userId, $currentWeekStart]);
            
            $message = "Your order has been cancelled successfully!";
            $messageType = "success";
        } else {
            $message = "No active order found to cancel.";
            $messageType = "info";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error cancelling order: " . $e->getMessage();
        $messageType = "error";
    }
}

if ($_POST && isset($_POST['snack_id'])) {
    $snackId = (int)$_POST['snack_id'];
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
    
    try {
        $pdo->beginTransaction();
        
        // Check if user already has an order this week
        $checkStmt = $pdo->prepare("SELECT snack_id FROM snack_orders WHERE user_id = ? AND order_date >= ?");
        $checkStmt->execute([$userId, $currentWeekStart]);
        $existingOrder = $checkStmt->fetch();
        
        // Check if selected snack is available
        $availabilityStmt = $pdo->prepare("SELECT name, available_count FROM snack_options WHERE id = ? AND available_count > 0");
        $availabilityStmt->execute([$snackId]);
        $selectedSnack = $availabilityStmt->fetch();
        
        if (!$selectedSnack) {
            throw new Exception("Selected snack is not available!");
        }
        
        if ($existingOrder) {
            // Update existing order
            $oldSnackId = $existingOrder['snack_id'];
            
            if ($oldSnackId != $snackId) {
                // Restore count for old snack
                $restoreStmt = $pdo->prepare("UPDATE snack_options SET available_count = available_count + 1 WHERE id = ?");
                $restoreStmt->execute([$oldSnackId]);
                
                // Decrease count for new snack
                $decreaseStmt = $pdo->prepare("UPDATE snack_options SET available_count = available_count - 1 WHERE id = ?");
                $decreaseStmt->execute([$snackId]);
                
                // Update the order
                $updateStmt = $pdo->prepare("UPDATE snack_orders SET snack_id = ?, order_date = CURDATE() WHERE user_id = ? AND order_date >= ?");
                $updateStmt->execute([$snackId, $userId, $currentWeekStart]);
                
                $message = "Your snack choice has been updated to " . htmlspecialchars($selectedSnack['name']) . "!";
                $messageType = "success";
            } else {
                $message = "You already selected " . htmlspecialchars($selectedSnack['name']) . " for this week!";
                $messageType = "info";
            }
        } else {
            // Create new order
            $decreaseStmt = $pdo->prepare("UPDATE snack_options SET available_count = available_count - 1 WHERE id = ?");
            $decreaseStmt->execute([$snackId]);
            
            $insertStmt = $pdo->prepare("INSERT INTO snack_orders (user_id, snack_id, order_date) VALUES (?, ?, CURDATE())");
            $insertStmt->execute([$userId, $snackId]);
            
            $message = "Your snack choice " . htmlspecialchars($selectedSnack['name']) . " has been registered!";
            $messageType = "success";
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get available snacks
$snacksStmt = $pdo->prepare("SELECT id, name, description, available_count FROM snack_options ORDER BY name");
$snacksStmt->execute();
$snacks = $snacksStmt->fetchAll();

// Get user's current order for this week
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));
$currentOrderStmt = $pdo->prepare("
    SELECT so.name 
    FROM snack_orders sorders 
    JOIN snack_options so ON sorders.snack_id = so.id 
    WHERE sorders.user_id = ? AND sorders.order_date >= ?
");
$currentOrderStmt->execute([$userId, $currentWeekStart]);
$currentOrder = $currentOrderStmt->fetch();

// Get overview of all orders for this week
$overviewStmt = $pdo->prepare("
    SELECT so.name, COUNT(*) as order_count
    FROM snack_orders sorders 
    JOIN snack_options so ON sorders.snack_id = so.id 
    WHERE sorders.order_date >= ?
    GROUP BY so.id, so.name 
    ORDER BY so.name
");
$overviewStmt->execute([$currentWeekStart]);
$overview = $overviewStmt->fetchAll();

// Get next Friday's date
$nextFriday = date('Y-m-d', strtotime('friday this week'));
if (date('w') == 5 && date('H') >= 12) { // If it's Friday after noon, show next Friday
    $nextFriday = date('Y-m-d', strtotime('next friday'));
}
?>

<section>
    <div class="container">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h2 class="text-center mb-4">🥖 Kroketto - Friday Lunch Orders</h2>
                <p class="text-center text-muted mb-4">Order your snack for Friday lunch (<?= date('d-m-Y', strtotime($nextFriday)) ?>)</p>
                
                <?php if ($currentOrder): ?>
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Your Current Order</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1"><?= htmlspecialchars($currentOrder['name']) ?></h4>
                                <p class="text-muted mb-0">
                                    <small>For Friday, <?= date('d-m-Y', strtotime($nextFriday)) ?></small><br>
                                    <small>You can change your selection or cancel until Friday morning.</small>
                                </p>
                            </div>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="action" value="cancel_order">
                                <button type="submit" class="btn btn-outline-danger" 
                                        onclick="return confirm('Are you sure you want to cancel your order for <?= htmlspecialchars($currentOrder['name'], ENT_QUOTES) ?>?')">
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
                        <h5 class="mb-0">Select Your Snack</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="row g-3">
                                <?php foreach ($snacks as $snack): ?>
                                <div class="col-sm-6">
                                    <div class="card h-100 <?= $snack['available_count'] == 0 ? 'text-muted' : '' ?>">
                                        <div class="card-body d-flex flex-column">
                                            <div class="form-check flex-grow-1">
                                                <input class="form-check-input" type="radio" name="snack_id" 
                                                       id="snack_<?= $snack['id'] ?>" value="<?= $snack['id'] ?>"
                                                       <?= $snack['available_count'] == 0 ? 'disabled' : '' ?>>
                                                <label class="form-check-label w-100" for="snack_<?= $snack['id'] ?>">
                                                    <strong><?= htmlspecialchars($snack['name']) ?></strong>
                                                </label>
                                                <p class="mt-1 mb-2 text-muted small">
                                                    <?= htmlspecialchars($snack['description']) ?>
                                                </p>
                                            </div>
                                            <small class="<?= $snack['available_count'] == 0 ? 'text-danger' : 'text-success' ?>">
                                                <?= $snack['available_count'] == 0 ? 'Out of stock' : $snack['available_count'] . ' available' ?>
                                            </small>
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
                            <div class="col-sm-6 col-md-3">
                                <div class="text-center p-3 bg-light rounded">
                                    <div class="h4 text-primary mb-1"><?= $item['order_count'] ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars($item['name']) ?></div>
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
                        You can change your order until Friday morning.
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
