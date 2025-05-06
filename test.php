<?php
require 'includes/header.php';
require 'includes/db.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['person_id'])) {
    // Fetch the selected user
    $stmt = $pdo->prepare("SELECT * FROM Personel WHERE Id = ?");
    $stmt->execute([$_POST['person_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['user_id'] = $user['Id'];
        $_SESSION['user_email'] = $user['Email'];
        $_SESSION['user_name'] = $user['Shortname'];
        $_SESSION['auth_level'] = $user['Type'];
        echo "<div class='alert alert-success'>Logged in as " . htmlspecialchars($user['Name']) . ".</div>";
    } else {
        echo "<div class='alert alert-danger'>Invalid user selected.</div>";
    }
}

// Fetch all personnel
$stmt = $pdo->query("SELECT Personel.*, Types.Name AS TypeName FROM Personel LEFT JOIN Types ON Personel.Type = Types.Id ORDER BY Ord, Name");
$personnel = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<section>
    <div class="container">
        <h2>Test Login as User</h2>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="alert alert-info">
                Currently logged in as: <?= htmlspecialchars($_SESSION['user_name']) ?> (<?= htmlspecialchars($_SESSION['user_email']) ?>)
                <br>
                User ID: <?= htmlspecialchars($_SESSION['user_id']) ?> | Auth Level: <?= htmlspecialchars($_SESSION['auth_level']) ?>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                No user is currently logged in.
            </div>
        <?php endif; ?>

        <form method="POST" class="form-inline">
            <div class="form-group mb-2">
                <label for="person_id" class="mr-2">Select user:</label>
                <select name="person_id" id="person_id" class="form-control">
                    <?php foreach ($personnel as $p): ?>
                        <option value="<?= $p['Id'] ?>"><?= htmlspecialchars($p['Name']) ?> (<?= htmlspecialchars($p['Email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary mb-2 ml-2">Login as User</button>
        </form>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
