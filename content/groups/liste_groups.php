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
                    // Check if group has users
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM user_groups WHERE group_id = ?");
                    $checkStmt->execute([$_POST['group_id']]);
                    $hasUsers = $checkStmt->fetchColumn() > 0;
        
                    if ($hasUsers) {
                        $_SESSION['error_message'] = "Impossible de supprimer ce groupe car il est associé à des utilisateurs";
                        // Make sure we don't show any success message
                        unset($_SESSION['success_message']);
                        break;
                    }
        
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

// chgeckin users group 
if (isset($_GET['check_users']) && isset($_GET['group_id'])) {
    try {
        // Check if group has users
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM user_groups WHERE group_id = ?");
        $checkStmt->execute([$_GET['group_id']]);
        $hasUsers = $checkStmt->fetchColumn() > 0;
        
        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode(['hasUsers' => $hasUsers]);
        exit;
    } catch (PDOException $e) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
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
        <div id="errorMessage" class="message-fade bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4" style="opacity: 1">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="successMessage" class="message-fade bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4" style="opacity: 1">
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
                                
                                <button onclick="toggleSupergroup(<?= $group['group_id'] ?>)" 
                                        class="text-yellow-600 hover:text-yellow-900">
                                    <i class="fas <?= $group['is_supergroup'] ? 'fa-star' : 'fa-star-half-alt' ?>"></i>
                                </button>

                                <button onclick="deleteGroup(<?= $group['group_id'] ?>)" 
                                        class="text-red-600 hover:text-red-900">
                                    <i class="fas fa-trash"></i>
                                </button>
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
    // Message handling functions
    const hideMessages = () => {
        const messages = document.querySelectorAll('#successMessage, #errorMessage');
        messages.forEach(msg => {
            if (msg) {
                setTimeout(() => {
                    msg.style.transition = 'opacity 2s';
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 2000);
                }, 10000);
            }
        });
    };

    hideMessages();

    window.showMessage = function(message, isError = false) {
        document.querySelectorAll('#successMessage, #errorMessage').forEach(msg => msg.remove());
        
        const messageDiv = document.createElement('div');
        messageDiv.id = isError ? 'errorMessage' : 'successMessage';
        messageDiv.className = isError 
            ? 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'
            : 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
        messageDiv.style.opacity = '1';
        messageDiv.innerHTML = message;
        
        const container = document.querySelector('.container');
        container.insertBefore(messageDiv, container.firstChild);
        
        setTimeout(() => {
            messageDiv.style.transition = 'opacity 2s';
            messageDiv.style.opacity = '0';
            setTimeout(() => messageDiv.remove(), 2000);
        }, 10000);
    };

    window.toggleSupergroup = function(groupId) {
        const formData = new FormData();
        formData.append('action', 'toggle_supergroup');
        formData.append('group_id', groupId);

        fetch(window.location.href, {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(response => response.text())
        .then(() => {
            showMessage('Statut supergroupe mis à jour');
            setTimeout(() => location.reload(), 2000);
        })
        .catch(error => {
            showMessage('Erreur lors de la mise à jour: ' + error, true);
        });
    };

    window.deleteGroup = function(groupId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce groupe ?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('group_id', groupId);

        fetch(window.location.href, {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(response => response.text())
        .then(html => {
            // Parse the HTML response to check for error messages
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const errorMessage = doc.querySelector('#errorMessage');
            
            if (errorMessage && errorMessage.textContent.includes('associé à des utilisateurs')) {
                // If there's an error message about users, show it without claiming success
                showMessage('Impossible de supprimer ce groupe car il est associé à des utilisateurs', true);
            } else {
                // No error about users, show success message
                showMessage('Groupe supprimé avec succès');
            }
            
            // Reload the page after a delay to show the updated state
            setTimeout(() => location.reload(), 2000);
        })
        .catch(error => {
            showMessage('Erreur lors de la suppression: ' + error, true);
        });
    }
};

    // Toggle Add Group Form
    const toggleAddGroupFormButton = document.getElementById('toggleAddGroupForm');
    const addGroupFormContainer = document.getElementById('addGroupFormContainer');
    const editGroupFormContainer = document.getElementById('editGroupFormContainer');
    const groupTableContainer = document.getElementById('groupTableContainer');

    // Updated toggle add group form handler
    if (toggleAddGroupFormButton) {
        toggleAddGroupFormButton.addEventListener('click', function() {
            if (addGroupFormContainer.style.display === 'none') {
                addGroupFormContainer.style.display = 'block';
                groupTableContainer.style.display = 'none';
                editGroupFormContainer.style.display = 'none'; // Hide edit form
            } else {
                addGroupFormContainer.style.display = 'none';
                groupTableContainer.style.display = 'block';
            }
        });
    }

    // Load Edit Group Form
    window.loadEditGroupForm = function(groupId) {
        const url = `home.php?section=groups&item=update_group&id=${groupId}&partial=1`;
        
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.text();
        })
        .then(html => {
            editGroupFormContainer.innerHTML = html;
            editGroupFormContainer.style.display = 'block';
            groupTableContainer.style.display = 'none';
            addGroupFormContainer.style.display = 'none'; // Hide add form
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading form: ' + error.message);
        });
    };

    // Cancel Edit
    window.cancelEdit = function() {
        editGroupFormContainer.style.display = 'none';
        groupTableContainer.style.display = 'block';
    };

    // Edit Form Submission
    document.getElementById('editGroupFormContainer').addEventListener('submit', function(e) {
        e.preventDefault();
        
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
                editGroupFormContainer.style.display = 'none';
                groupTableContainer.style.display = 'block';
                showMessage('Groupe mis à jour avec succès');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                showMessage(data.error || 'Une erreur est survenue', true);
            }
        })
        .catch(error => {
            showMessage('Erreur de mise à jour: ' + error.message, true);
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
            return response.json();
        })
        .then(data => {
            if (data.success) {
                setTimeout(() => window.location.reload(), 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });

});

// Cancel Add Function
window.cancelAdd = function() {
    addGroupFormContainer.style.display = 'none';
    groupTableContainer.style.display = 'block';
};
</script>