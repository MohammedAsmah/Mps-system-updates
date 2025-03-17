<?php
// Vérification des permissions
if (!$is_admin) {
    echo "<div class='text-red-500'>Accès non autorisé</div>";
    exit();
}

// Traitement des actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            if (isset($_POST['group_id'])) {
                try {
                    $stmt = $conn->prepare("DELETE FROM rs_groups WHERE group_id = ?");
                    $stmt->execute([$_POST['group_id']]);
                    $_SESSION['success_message'] = "Groupe supprimé avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de suppression: " . $e->getMessage();
                }
            }
            break;
            
        case 'toggle_supergroup':
            if (isset($_POST['group_id'])) {
                try {
                    $stmt = $conn->prepare("UPDATE rs_groups SET is_supergroup = NOT is_supergroup WHERE group_id = ?");
                    $stmt->execute([$_POST['group_id']]);
                    $_SESSION['success_message'] = "Statut supergroupe mis à jour";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de mise à jour: " . $e->getMessage();
                }
            }
            break;
    }
}

// Récupération des groupes
try {
    $sql = "SELECT g.*, 
    COUNT(DISTINCT ug.user_id) as members_count,
    GROUP_CONCAT(DISTINCT m.menu_name SEPARATOR ', ') as menus_access
    FROM rs_groups g
    LEFT JOIN group_menus gm ON g.group_id = gm.group_id
    LEFT JOIN rs_menus m ON gm.menu_id = m.menu_id
    LEFT JOIN user_groups ug ON g.group_id = ug.group_id
    GROUP BY g.group_id
    ORDER BY g.group_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $groups = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur de chargement des groupes: " . $e->getMessage();
}
?>

<div class="container mx-auto">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">Liste des Groupes</h2>
        <button id="toggleAddGroupForm" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus"></i> Nouveau Groupe
        </button>
    </div>

    <div id="groupTableContainer">
        <?php if (count($groups) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 border-b text-left">Nom du groupe</th>
                        <th class="px-6 py-3 border-b text-left">Description</th>
                        <th class="px-6 py-3 border-b text-left">Membres</th>
                        <th class="px-6 py-3 border-b text-left">Accès menus</th>
                        <th class="px-6 py-3 border-b text-left">Statut</th>
                        <th class="px-6 py-3 border-b text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($group['group_name']) ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($group['description'] ?? 'Aucune description') ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($group['members_count']) ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($group['menus_access'] ?? 'Aucun accès') ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $group['is_supergroup'] ? 'bg-purple-100 text-purple-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $group['is_supergroup'] ? 'Supergroupe' : 'Standard' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <div class="flex space-x-2">
                                <a href="#" onclick="loadEditGroupForm(<?= $group['group_id'] ?>)" 
                                   class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_supergroup">
                                    <input type="hidden" name="group_id" value="<?= $group['group_id'] ?>">
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                        <i class="fas <?= $group['is_supergroup'] ? 'fa-star' : 'fa-star-half-alt' ?>"></i>
                                    </button>
                                </form>

                                <form method="POST" class="inline" 
                                      onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="group_id" value="<?= $group['group_id'] ?>">
                                    <button type="submit" class="text-red-600 hover:text-red-900">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="bg-gray-100 p-4 text-center">
                Aucun groupe trouvé
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addGroupFormContainer" style="display: none;">
    <?php include 'add_group.php'; ?>
</div>

<div id="editGroupFormContainer" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle Add Group Form
    const toggleAddGroupFormButton = document.getElementById('toggleAddGroupForm');
    const addGroupFormContainer = document.getElementById('addGroupFormContainer');
    const groupTableContainer = document.getElementById('groupTableContainer');

    if (toggleAddGroupFormButton) {
        toggleAddGroupFormButton.addEventListener('click', function() {
            addGroupFormContainer.style.display = addGroupFormContainer.style.display === 'none' ? 'block' : 'none';
            groupTableContainer.style.display = groupTableContainer.style.display === 'none' ? 'block' : 'none';
        });
    }

    // Load Edit Group Form
    window.loadEditGroupForm = function(groupId) {
    // Use relative path instead of absolute
    const url = `home.php?section=groups&item=update_group&id=${groupId}&partial=1`;
    
    fetch(url, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.text();
    })
    .then(html => {
        const editContainer = document.getElementById('editGroupFormContainer');
        editContainer.innerHTML = html;
        editContainer.style.display = 'block';
        groupTableContainer.style.display = 'none';
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading form: ' + error.message);
    });
};

    // Cancel Edit
    window.cancelEdit = function() {
        document.getElementById('editGroupFormContainer').style.display = 'none';
        groupTableContainer.style.display = 'block';
    };

    // Edit Form Submission
document.getElementById('editGroupFormContainer').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get the actual form element
    const form = e.target.closest('form');
    if (!form) return;

    const formData = new FormData(form);
    const groupId = form.querySelector('input[name="id"]').value;

    fetch(`home.php?section=groups&item=update_group&id=${groupId}`, {
        method: 'POST',
        headers: { 
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(formData)
    })
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erreur: ' + (data.error || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur de mise à jour: ' + error.message);
    });
});

    // Add Group Form Submission
document.querySelector('#addGroupFormContainer form')?.addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('home.php?section=groups&item=add_group', {
    method: 'POST',
    headers: { 
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: new URLSearchParams(formData)
})
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Response:', text);
                throw new Error('Invalid JSON response');
            }
        });
    })
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Erreur lors de la création du groupe');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Erreur lors de la création du groupe: ' + error.message);
    });
});

});

// Cancel Add Function
window.cancelAdd = function() {
    document.getElementById('addGroupFormContainer').style.display = 'none';
    document.getElementById('groupTableContainer').style.display = 'block';
};
</script>