<?php
include 'db_connect.php';

try {
    // Get selected category from GET parameter
    $selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
    
    // Fetch all categories for the filter dropdown
    $catStmt = $conn->prepare("SELECT * FROM Categories ORDER BY designation");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
    
    // Build query with category filter
    $sql = "SELECT a.*, c.designation as category_name 
            FROM Accessoires a 
            LEFT JOIN Categories c ON a.categorie_id = c.Categorie_id
            WHERE 1=1 ";
    if ($selectedCategory) {
        $sql .= " AND a.categorie_id = :category_id";
    }
    $sql .= " ORDER BY a.designation";
    
    $stmt = $conn->prepare($sql);
    if ($selectedCategory) {
        $stmt->bindParam(':category_id', $selectedCategory);
    }
    $stmt->execute();
    $accessories = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur de chargement: " . $e->getMessage();
}

// Handle accessory actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            if (isset($_POST['accessory_id'])) {
                try {
                    // Check if accessory is used in any compositions
                    $checkStmt = $conn->prepare("
                        SELECT COUNT(*) 
                        FROM ArticleAccessoiries 
                        WHERE Accessoire_id = ?
                    ");
                    $checkStmt->execute([$_POST['accessory_id']]);
                    
                    if ($checkStmt->fetchColumn() > 0) {
                        $_SESSION['error_message'] = "Impossible de supprimer cet accessoire car il est utilisé dans des compositions d'articles.";
                        // Make sure we don't show any success message
                        unset($_SESSION['success_message']);
                        break;
                    }

                    $stmt = $conn->prepare("DELETE FROM Accessoires WHERE Accessoire_id = ?");
                    $stmt->execute([$_POST['accessory_id']]);
                    $_SESSION['success_message'] = "Accessoire supprimé avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de suppression: " . $e->getMessage();
                }
            }
            break;
    }
}
?>

<div class="container mx-auto px-4 py-6 bg-gray-50 ">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="errorMessage" class="message-fade bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-r mb-4" style="opacity: 1">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="successMessage" class="message-fade bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-r mb-4" style="opacity: 1">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-8">
        <h2 class="text-3xl font-bold text-gray-800">
            <i class="fas fa-boxes mr-2"></i>Liste des Accessoires
        </h2>
        <div class="flex items-center space-x-6">
            <!-- Category filter form -->
            <form id="categoryFilter" class="flex items-center bg-white rounded-lg shadow-sm p-2">
                <select name="category" id="categorySelect" 
                    class="form-select rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition-shadow duration-200">
                    <option value="">Toutes les catégories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['Categorie_id'] ?>" 
                                <?= $selectedCategory == $category['Categorie_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['designation']) ?>
                        </option>
                    <?php endforeach; ?> 
                </select>
                <?php if ($selectedCategory): ?>
                    <button type="button" id="resetFilter" 
                            class="ml-2 text-gray-500 hover:text-gray-700 transition-colors duration-200">
                        <i class="fas fa-times-circle"></i>
                    </button>
                <?php endif; ?>
            </form>

            <button id="toggleAddAccessoryForm" 
                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-5 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 flex items-center">
                <i class="fas fa-plus mr-2"></i> Nouvel Accessoire
            </button>
        </div>
    </div>

    <div id="accessoryTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden">
        <?php if (count($accessories) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Désignation</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Catégorie</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Poids avec carotte</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Poids sans carotte</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($accessories as $accessory): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900">
                            <?= htmlspecialchars($accessory['designation']) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-medium rounded-full bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($accessory['category_name'] ?? 'Non catégorisé') ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                            <?= number_format($accessory['poids_avec_carotte'], 2) ?> kg
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-700">
                            <?= number_format($accessory['poids_sans_carotte'], 2) ?> kg
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-4">
                                <button onclick="loadEditAccessoryForm(<?= $accessory['Accessoire_id'] ?>)" 
                                        class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                    <i class="fas fa-edit text-lg"></i>
                                </button>
                                <button onclick="deleteAccessory(<?= $accessory['Accessoire_id'] ?>)" 
                                        class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                    <i class="fas fa-trash text-lg"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-gray-500">
                <i class="fas fa-box-open text-4xl mb-4"></i>
                <p>Aucun accessoire trouvé</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addAccessoryFormContainer" style="display: none;">
    <?php include __DIR__ . '/add_accessory.php'; ?>
</div>
<div id="editAccessoryFormContainer" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Message handling
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

    // Form toggle handlers
    const toggleAddAccessoryFormButton = document.getElementById('toggleAddAccessoryForm');
    const addAccessoryFormContainer = document.getElementById('addAccessoryFormContainer');
    const editAccessoryFormContainer = document.getElementById('editAccessoryFormContainer');
    const accessoryTableContainer = document.getElementById('accessoryTableContainer');

    if (toggleAddAccessoryFormButton) {
        toggleAddAccessoryFormButton.addEventListener('click', function() {
            addAccessoryFormContainer.style.display = 
                addAccessoryFormContainer.style.display === 'none' ? 'block' : 'none';
            accessoryTableContainer.style.display = 
                addAccessoryFormContainer.style.display === 'none' ? 'block' : 'none';
            editAccessoryFormContainer.style.display = 'none';
        });
    }

    // Delete handler
    window.deleteAccessory = function(accessoryId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet accessoire ?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('accessory_id', accessoryId);

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
                const successMessage = doc.querySelector('#successMessage');
                
                if (errorMessage) {
                    showMessage(errorMessage.textContent, true);
                } else if (successMessage) {
                    showMessage(successMessage.textContent);
                }
                
                // Reload the page after a delay
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                showMessage('Erreur lors de la suppression: ' + error, true);
            });
        }
    };

    // Edit handler
    window.loadEditAccessoryForm = function(accessoryId) {
        const url = `home.php?section=Parametrage&item=accessories/update_accessory&id=${accessoryId}&partial=1`;
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            editAccessoryFormContainer.innerHTML = html;
            editAccessoryFormContainer.style.display = 'block';
            accessoryTableContainer.style.display = 'none';
            addAccessoryFormContainer.style.display = 'none';

            // Update form submission handler
            const editForm = editAccessoryFormContainer.querySelector('form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(editForm);
                    
                    fetch('home.php?section=Parametrage&item=accessories/update_accessory', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                    .then(response => response.text())
                    .then(result => {
                        try {
                            const data = JSON.parse(result);
                            if (data.success) {
                                showMessage(data.message || 'Accessoire mis à jour avec succès');
                                setTimeout(() => window.location.reload(), 1000);
                            } else {
                                showMessage(data.message || 'Erreur lors de la mise à jour', true);
                            }
                        } catch (e) {
                            console.error('Parse error:', e);
                            showMessage('Erreur lors de la mise à jour', true);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showMessage('Erreur lors de la mise à jour: ' + error.message, true);
                    });
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading form: ' + error.message, true);
        });
    };

    // Cancel handlers
    window.cancelAdd = function() {
        addAccessoryFormContainer.style.display = 'none';
        accessoryTableContainer.style.display = 'block';
    };

    window.cancelEdit = function() {
        editAccessoryFormContainer.style.display = 'none';
        accessoryTableContainer.style.display = 'block';
    };

    // Category filter handling
    const categorySelect = document.getElementById('categorySelect');
    const resetFilter = document.getElementById('resetFilter');

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            updateAccessoriesList(this.value);
        });
    }

    if (resetFilter) {
        resetFilter.addEventListener('click', function() {
            categorySelect.value = '';
            updateAccessoriesList('');
        });
    }

    function updateAccessoriesList(categoryId = '') {
        const url = new URL(window.location.href);
        url.searchParams.set('category', categoryId);
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('accessoryTableContainer');
            
            if (newTable) {
                document.getElementById('accessoryTableContainer').innerHTML = newTable.innerHTML;
            }
            
            window.history.pushState({}, '', url);
        })
        .catch(error => console.error('Error:', error));
    }
});
</script>
