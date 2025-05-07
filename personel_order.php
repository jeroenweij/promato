<?php
$pageSpecificCSS = ['kanban.css'];

require 'includes/header.php';
require 'includes/db.php';

// Fetch personel 
$stmt = $pdo->query("SELECT Id, Shortname AS Name, Ord, Department 
    FROM Personel
    WHERE `Type` > 1 AND plan=1
    ORDER BY Department, Ord, Shortname;
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Departments 
$stmt = $pdo->query("SELECT Id, Name, Ord
    FROM Departments
    ORDER BY Ord;
");
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by department
$persons = [];
foreach ($users as $user) {
    $persons[$user['Department']][] = $user;
}
?>

<section id="priority-planning">
    <div class="container">
        <div class="row">
            <?php foreach ($departments as $group): ?>
                <div class="col-md-3">
                    <div class="card mb-3">
                        <div class="card-header bg-primary text-white text-center">
                            <?= htmlspecialchars($group['Name']) ?>
                        </div>
                    </div>
                    <div id="dep-<?= $group['Id'] ?>" class="kanban-cards department-container" data-department-id="<?= $group['Id'] ?>">
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
            <?php endforeach; ?>
        </div>
    </div>
</section>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const departmentContainers = document.querySelectorAll('.department-container');
        
        departmentContainers.forEach(container => {
            new Sortable(container, {
                group: 'shared', // Allow cross-department dragging
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    // Get the source and destination departments
                    const fromDepartmentId = evt.from.dataset.departmentId;
                    const toDepartmentId = evt.to.dataset.departmentId;
                    
                    // Collect all user cards in all departments with their new order
                    const updates = [];
                    
                    departmentContainers.forEach(depContainer => {
                        const departmentId = depContainer.dataset.departmentId;
                        const cards = depContainer.querySelectorAll('.user-card');
                        
                        cards.forEach((card, index) => {
                            updates.push({
                                personId: card.dataset.personId,
                                order: index + 1,
                                department: departmentId
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