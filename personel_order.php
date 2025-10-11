<?php
$pageSpecificCSS = ['kanban.css'];

require 'includes/header.php';
require 'includes/db.php';

// Fetch personel 
$stmt = $pdo->query("SELECT Id, Shortname AS Name, Ord, Team 
    FROM Personel
    WHERE `Type` > 1 AND plan=1
    ORDER BY Team, Ord, Shortname;
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Teams 
$stmt = $pdo->query("SELECT Id, Name, Ord
    FROM Teams
    ORDER BY Ord;
");
$teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by team
$persons = [];
foreach ($users as $user) {
    $persons[$user['Team']][] = $user;
}
?>

<section id="priority-planning">
    <div class="container">
        <div class="row">
            <?php foreach ($teams as $group): ?>
                <div class="col-md-3">
                    <div class="card card-full mb-3">
                        <div class="card-header bg-secondary text-white text-center">
                            <?= htmlspecialchars($group['Name']) ?>
                        </div>
                        <div class="card-body">
                            <div id="dep-<?= $group['Id'] ?>" class="kanban-cards team-container" data-team-id="<?= $group['Id'] ?>">
                                <?php if (!empty($persons[$group['Id']])): ?>
                                    <?php foreach ($persons[$group['Id']] as $user): ?>
                                        <div class="card mb-3 user-card" data-person-id="<?= $user['Id'] ?>">
                                            <div class="card-body">
                                                <h6 class="card-title"><?= htmlspecialchars($user['Name']) ?></h6>                                    
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const teamContainers = document.querySelectorAll('.team-container');
        
        teamContainers.forEach(container => {
            new Sortable(container, {
                group: 'shared', // Allow cross-team dragging
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    // Get the source and destination teams
                    const fromTeamId = evt.from.dataset.teamId;
                    const toTeamId = evt.to.dataset.teamId;
                    
                    // Collect all user cards in all teams with their new order
                    const updates = [];
                    
                    teamContainers.forEach(depContainer => {
                        const teamId = depContainer.dataset.teamId;
                        const cards = depContainer.querySelectorAll('.user-card');
                        
                        cards.forEach((card, index) => {
                            updates.push({
                                personId: card.dataset.personId,
                                order: index + 1,
                                team: teamId
                            });
                        });
                    });
                    
                    // Send updated data to backend
                    fetch('update_user_order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({users: updates})
                    })
                    .then(response => response.json())
                    .then(data => {
                        console.log('User order updated', data);
                        // You could add a success notification here
                    })
                    .catch(error => {
                        console.error('Error updating user order:', error);
                        // You could add an error notification here
                    });
                }
            });
        });
    });
</script>

<?php require 'includes/footer.php'; ?>