<?php
require 'includes/header.php';
require 'includes/db.php';

$editing = isset($_GET['id']);
$person = [
    'Email' => '',
    'Name' => '',
    'Startdate' => date('Y-m-d'),
    'Enddate' => '',
    'WBSO' => 0,
    'Fultime' => 100,
    'Type' => 1,
    'Ord' => 250,
    'plan' => 1,
    'Shortname' => ''
];

if ($editing) {
    $stmt = $pdo->prepare("SELECT * FROM Personel WHERE Id = ?");
    $stmt->execute([$_GET['id']]);
    $person = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$person) {
        echo "<p>Person not found.</p>";
        require 'includes/footer.php';
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['Email'],
        $_POST['Name'],
        $_POST['Startdate'],
        $_POST['Enddate'] ?: null,
        isset($_POST['WBSO']) ? 1 : 0,
        $_POST['Fultime'],
        $_POST['Type'],
        $_POST['Ord'],
        isset($_POST['plan']) ? 1 : 0,
        $_POST['Shortname']
    ];

    if ($editing) {
        $data[] = $_GET['id'];
        $sql = "UPDATE Personel SET Email=?, Name=?, Startdate=?, Enddate=?, WBSO=?, Fultime=?, Type=?, Ord=?, plan=?, Shortname=? WHERE Id=?";
    } else {
        $sql = "INSERT INTO Personel (Email, Name, Startdate, Enddate, WBSO, Fultime, Type, Ord, plan, Shortname) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
    header("Location: personel.php");
    exit;
}

// Get types
$types = $pdo->query("SELECT Id, Name FROM Types ORDER BY Id")->fetchAll(PDO::FETCH_ASSOC);
?>

<section>
    <div class="container">
        <h2><?= $editing ? 'Edit' : 'Add' ?> Person</h2>
        <form method="post">
            <label>Email:<br><input type="email" name="Email" required value="<?= htmlspecialchars($person['Email']) ?>"></label><br><br>
            <label>Name:<br><input type="text" name="Name" value="<?= htmlspecialchars($person['Name']) ?>"></label><br><br>
            <label>Shortname:<br><input type="text" name="Shortname" required value="<?= htmlspecialchars($person['Shortname']) ?>"></label><br><br>
            <label>Start Date:<br><input type="date" name="Startdate" value="<?= htmlspecialchars($person['Startdate']) ?>"></label><br><br>
            <label>End Date:<br><input type="date" name="Enddate" value="<?= htmlspecialchars($person['Enddate']) ?>"></label><br><br>
            <label>Fulltime %:<br><input type="number" name="Fultime" value="<?= htmlspecialchars($person['Fultime']) ?>" min="0" max="100"></label><br><br>
            <label>Order:<br><input type="number" name="Ord" value="<?= htmlspecialchars($person['Ord']) ?>"></label><br><br>
            <label>Type:<br>
                <select name="Type">
                    <?php foreach ($types as $type): ?>
                        <option value="<?= $type['Id'] ?>" <?= ($type['Id'] == $person['Type']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['Name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label><br><br>

            <label><input type="checkbox" name="plan" <?= $person['plan'] ? 'checked' : '' ?>> Plan-able (Show in Planning)</label><br>
            <label><input type="checkbox" name="WBSO" <?= $person['WBSO'] ? 'checked' : '' ?>> WBSO-eligible</label><br><br>

            <button type="submit">Save</button>
            <a href="personel.php" class="button">Cancel</a>
        </form>
    </div>
</section>

<?php require 'includes/footer.php'; ?>

