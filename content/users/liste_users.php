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
            if (isset($_POST['user_id'])) {
                try {
                    $stmt = $conn->prepare("DELETE FROM rs_users WHERE user_id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $_SESSION['success_message'] = "Utilisateur supprimé avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de suppression: " . $e->getMessage();
                }
            }
            break;
            
        case 'toggle_status':
            if (isset($_POST['user_id'])) {
                try {
                    $stmt = $conn->prepare("UPDATE rs_users SET is_locked = NOT is_locked WHERE user_id = ?");
                    $stmt->execute([$_POST['user_id']]);
                    $_SESSION['success_message'] = "Statut mis à jour avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de mise à jour: " . $e->getMessage();
                }
            }
            break;
    }
}

// Récupération des utilisateurs
try {
    $sql = "SELECT u.*, u.is_locked,
            GROUP_CONCAT(g.group_name SEPARATOR ', ') as groupes
            FROM rs_users u
            LEFT JOIN user_groups ug ON u.user_id = ug.user_id
            LEFT JOIN rs_groups g ON ug.group_id = g.group_id
            GROUP BY u.user_id
            ORDER BY u.created_at";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur de chargement des utilisateurs: " . $e->getMessage();
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
        <h2 class="text-2xl font-bold">Liste des Utilisateurs</h2>
        <button id="toggleAddUserForm" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
            <i class="fas fa-plus"></i> Nouvel Utilisateur
        </button>
    </div>

    <div id="userTableContainer">
        <?php if (count($users) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 border-b text-left">Nom complet</th>
                        <th class="px-6 py-3 border-b text-left">Email</th>
                        <th class="px-6 py-3 border-b text-left">Groupes</th>
                        <th class="px-6 py-3 border-b text-left">Statut</th>
                        <th class="px-6 py-3 border-b text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($user['first_name']." ".$user['last_name']) ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($user['email']) ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <?= htmlspecialchars($user['groupes'] ?: 'Aucun groupe') ?>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                <?= $user['is_locked'] ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
                                <?= $user['is_locked'] ? 'Verrouillé' : 'Actif' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 border-b">
                            <div class="flex space-x-2">
                                <a href="#" onclick="loadEditForm(<?= $user['user_id'] ?>)" 
                                   class="text-blue-600 hover:text-blue-900">
                                    <i class="fas fa-edit"></i>
                                </a>
                                
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <button type="submit" class="text-yellow-600 hover:text-yellow-900">
                                        <i class="fas <?= $user['is_locked'] ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                                    </button>
                                </form>

                                <form method="POST" class="inline" 
                                      onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
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
                Aucun utilisateur trouvé
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addUserFormContainer" style="display: none;">
    <?php include 'add_user.php'; ?>
</div>



<div id="editUserFormContainer" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggleAddUserFormButton = document.getElementById('toggleAddUserForm');
    const addUserFormContainer = document.getElementById('addUserFormContainer');
    const userTableContainer = document.getElementById('userTableContainer');

    // Toggle add user form
    toggleAddUserFormButton.addEventListener('click', function() {
        if (addUserFormContainer.style.display === 'none') {
            addUserFormContainer.style.display = 'block';
            userTableContainer.style.display = 'none';
            editUserFormContainer.style.display = 'none';
        } else {
            addUserFormContainer.style.display = 'none';
            userTableContainer.style.display = 'block';
        }
    });

    // Add form submission handler
    const addUserForm = document.querySelector('#addUserFormContainer form');
    if (addUserForm) {
        addUserForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            // Add header to identify as AJAX request
            fetch('content/users/add_user.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
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
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
                    messageDiv.textContent = data.message || 'Utilisateur créé avec succès';
                    addUserForm.prepend(messageDiv);
                    
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    const messageDiv = document.createElement('div');
                    messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
                    messageDiv.textContent = data.error || 'Une erreur est survenue';
                    
                    const existingError = addUserForm.querySelector('.error-message');
                    if (existingError) {
                        existingError.remove();
                    }
                    
                    messageDiv.classList.add('error-message');
                    addUserForm.prepend(messageDiv);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const messageDiv = document.createElement('div');
                messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 error-message';
                messageDiv.textContent = 'Erreur lors de la création: ' + error.message;
                
                const existingError = addUserForm.querySelector('.error-message');
                if (existingError) {
                    existingError.remove();
                }
                
                addUserForm.prepend(messageDiv);
            });
        });
    }

    // Load edit form
    window.loadEditForm = function(userId) {
        const baseUrl = window.location.origin + '/mps_udated_version/'; // Correct project name
        const absoluteUrl = `${baseUrl}home.php?section=users&item=update_user&id=${userId}&partial=1`;
        
        console.log('Request URL:', absoluteUrl); // Debug
        
        fetch(absoluteUrl)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.text();
            })
            .then(html => {
                document.getElementById('editUserFormContainer').innerHTML = html;
                document.getElementById('editUserFormContainer').style.display = 'block';
                document.getElementById('userTableContainer').style.display = 'none';
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                alert(`Failed to load form: ${error.message}`);
            });
    };

    // Cancel edit
    window.cancelEdit = function() {
        document.getElementById('editUserFormContainer').style.display = 'none';
        userTableContainer.style.display = 'block';
        window.location.reload(); // Refresh to get updated data
    };

    // ================================================================
    // FORM SUBMISSION HANDLER
    // ================================================================
    document.querySelector('#editUserFormContainer')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const userId = form.querySelector('input[name="id"]').value;

        fetch(`home.php?section=Utulisateurs/Groups&item=update_user&id=${userId}`, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            // Hide the form and show the table immediately
            document.getElementById('editUserFormContainer').style.display = 'none';
            document.getElementById('userTableContainer').style.display = 'block';

            // Create success message div
            const messageDiv = document.createElement('div');
            messageDiv.className = 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
            messageDiv.textContent = 'Utilisateur mis à jour avec succès';
            
            // Insert message at the top of the container
            const container = document.querySelector('.container');
            container.insertBefore(messageDiv, container.firstChild);
            
            // Remove message and reload page after 2 seconds
            setTimeout(() => {
                messageDiv.remove();
                window.location.reload();
            }, 2000);
        })
        .catch(error => {
            console.error('Error:', error);
            const messageDiv = document.createElement('div');
            messageDiv.className = 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
            messageDiv.textContent = 'Erreur lors de la mise à jour: ' + error.message;
            
            const container = document.querySelector('.container');
            container.insertBefore(messageDiv, container.firstChild);
            
            setTimeout(() => messageDiv.remove(), 3000);
        });
    });
});
</script>