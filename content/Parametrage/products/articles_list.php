<?php
include 'db_connect.php';

try {
    // Fetch categories for filter dropdown
    $catStmt = $conn->prepare("SELECT * FROM Categories ORDER BY designation");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
    
    // Get selected category
    $selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
    
    // Prepare articles query with category filter
    $sql = "SELECT a.*, c.designation as category_name 
            FROM Articles a 
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
    $articles = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur de chargement: " . $e->getMessage();
}

// Handle article actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            if (isset($_POST['article_id'])) {
                try {
                    $stmt = $conn->prepare("DELETE FROM Articles WHERE Article_id = ?");
                    $stmt->execute([$_POST['article_id']]);
                    $_SESSION['success_message'] = "Article supprimé avec succès";
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de suppression: " . $e->getMessage();
                }
            }
            break;
    }
}
?>

<div class="container mx-auto px-6 py-8 bg-gray-50">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="errorMessage" class="message-fade bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="successMessage" class="message-fade bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="flex justify-between items-center mb-8">
        <h2 class="text-3xl font-bold text-gray-800 flex items-center">
            <span class="bg-blue-600 text-white p-2 rounded-lg mr-3">
                <i class="fas fa-box"></i>
            </span>
            Liste des Articles
        </h2>
        <div class="flex items-center space-x-6">
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
            <button id="toggleAddArticleForm" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-xl flex items-center">
                <i class="fas fa-plus mr-2"></i> Nouvel Article
            </button>
        </div>
    </div>

    <div id="articleTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
        <?php if (count($articles) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Désignation</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Catégorie</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Conditionnement</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarif</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code-barres P</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Code-barres PI</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Poids</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($articles as $article): ?>
                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($article['designation']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?= htmlspecialchars($article['category_name'] ?? 'Non catégorisé') ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($article['conditionnement'] ?? '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900"><?= number_format($article['tarif'], 2) ?> €</td>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm"><?= htmlspecialchars($article['bardoce_p'] ?? '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap font-mono text-sm"><?= htmlspecialchars($article['barcode_pi'] ?? '-') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap"><?= number_format($article['poids_avec_emballage'], 2) ?> kg</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-3">
                            <a href="#" onclick="showUpdateArticleModal(<?= $article['Article_id'] ?>)" 
                                   class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button data-article-id="<?= $article['Article_id'] ?>"
                                        class="accessories-btn text-purple-600 hover:text-purple-900 transition-colors duration-200"
                                        title="Voir les accessoires">
                                    <i class="fas fa-puzzle-piece"></i>
                                </button>
                                <button onclick="deleteArticle(<?= $article['Article_id'] ?>)" 
                                        class="text-red-600 hover:text-red-900 transition-colors duration-200">
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
            <div class="bg-gray-50 p-4 text-center text-gray-500">
                Aucun article trouvé
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addArticleFormContainer" style="display: none;">
    <?php include 'add_article.php'; ?>
</div>

<div id="editArticleFormContainer" style="display: none;"></div>
<div id="accessoriesContainer" style="display: none;"></div>

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

    // Global message display function
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

    // Container management functions
    function resetContainers() {
        document.getElementById('addArticleFormContainer').style.display = 'none';
        document.getElementById('editArticleFormContainer').style.display = 'none';
        document.getElementById('articleTableContainer').style.display = 'block';
        
        const toggleButton = document.getElementById('toggleAddArticleForm');
        toggleButton.innerHTML = '<i class="fas fa-plus mr-2"></i>Nouvel Article';
        toggleButton.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        toggleButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }

    // Toggle Add Article Form
    const toggleAddArticleFormButton = document.getElementById('toggleAddArticleForm');
    toggleAddArticleFormButton.addEventListener('click', function() {
        const addArticleFormContainer = document.getElementById('addArticleFormContainer');
        const articleTableContainer = document.getElementById('articleTableContainer');
        const editArticleFormContainer = document.getElementById('editArticleFormContainer');

        if (addArticleFormContainer.style.display === 'none') {
            articleTableContainer.style.display = 'none';
            editArticleFormContainer.style.display = 'none';
            addArticleFormContainer.style.display = 'block';
            this.innerHTML = '<i class="fas fa-times mr-2"></i>Annuler';
            this.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            this.classList.add('bg-gray-600', 'hover:bg-gray-700');
        } else {
            resetContainers();
        }
    });

      // Cancel functions
      window.cancelAdd = function() {
        resetContainers();
    };

    window.cancelEdit = function() {
        resetContainers();
    };
    // Delete Article Function
    window.deleteArticle = function(articleId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('article_id', articleId);

            fetch(window.location.href, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.text())
            .then(() => {
                showMessage('Article supprimé avec succès');
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                showMessage('Erreur lors de la suppression: ' + error, true);
            });
        }
    };

    // Load Edit Article Form
    window.loadEditArticleForm = function(articleId) {
        const url = `home.php?section=Parametrage&item=update_article&id=${articleId}&partial=1`;
        
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            return response.text();
        })
        .then(html => {
            const editArticleFormContainer = document.getElementById('editArticleFormContainer');
            const articleTableContainer = document.getElementById('articleTableContainer');
            const addArticleFormContainer = document.getElementById('addArticleFormContainer');

            editArticleFormContainer.innerHTML = html;
            editArticleFormContainer.style.display = 'block';
            articleTableContainer.style.display = 'none';
            addArticleFormContainer.style.display = 'none';

            const editForm = editArticleFormContainer.querySelector('form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    fetch(`home.php?section=Parametrage&item=update_article&partial=1`, {
    method: 'POST',
    headers: { 
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded' 
    },
    body: new URLSearchParams(formData)
})
                    .then(response => response.text())
                    .then(text => {
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                showMessage(data.message || 'Article mis à jour avec succès');
                                setTimeout(() => location.reload(), 2000);
                            } else {
                                showMessage(data.error || 'Une erreur est survenue', true);
                            }
                        } catch (e) {
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    })
                    .catch(error => {
                        showMessage('Erreur lors de la mise à jour: ' + error.message, true);
                        console.error('Error:', error);
                    });
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading form: ' + error.message, true);
        });
    };

    // Category Filter Handling
    const categorySelect = document.getElementById('categorySelect');
    const resetFilter = document.getElementById('resetFilter');
    
    function updateArticlesList(categoryId = '') {
        const url = new URL(window.location.href);
        url.searchParams.set('category', categoryId);
        
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newTable = doc.getElementById('articleTableContainer');
            
            if (newTable) {
                document.getElementById('articleTableContainer').innerHTML = newTable.innerHTML;
            }
            
            window.history.pushState({}, '', url);
        })
        .catch(error => console.error('Error:', error));
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            updateArticlesList(this.value);
        });
    }

    if (resetFilter) {
        resetFilter.addEventListener('click', function() {
            categorySelect.value = '';
            updateArticlesList('');
        });
    }

    // Accessories Handling
    document.querySelectorAll('.accessories-btn').forEach(button => {
        button.addEventListener('click', function() {
            const articleId = this.dataset.articleId;
            showAccessoriesModal(articleId);
        });
    });

    // Accessories modal functions defined in a separate script
    window.showAccessoriesModal = function(articleId) {
        const contentArea = document.getElementById('articleTableContainer');
        
        fetch(`content/Parametrage/products/article_accessories.php?partial=1&id=${articleId}`)
            .then(response => response.text())
            .then(html => {
                const modalContainer = document.createElement('div');
                modalContainer.id = 'accessoriesModalContainer';
                modalContainer.innerHTML = html;
                
                contentArea.innerHTML = '';
                contentArea.appendChild(modalContainer);
                
                document.getElementById('addArticleFormContainer').style.display = 'none';
                document.getElementById('editArticleFormContainer').style.display = 'none';
                
                const toggleButton = document.getElementById('toggleAddArticleForm');
                toggleButton.innerHTML = '<i class="fas fa-times mr-2"></i>Retour';
                toggleButton.onclick = function() {
                    location.reload();
                };
                
                setupAccessoriesFormHandlers(articleId);
                setupAccessoriesCloseHandler();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors du chargement des accessoires');
            });
    };
    // 
window.showUpdateArticleModal = function(articleId) {
    const contentArea = document.getElementById('articleTableContainer');
    
    fetch(`home.php?section=Parametrage&item=update_article&partial=1&id=${articleId}`)
        .then(response => response.text())
        .then(html => {
            const modalContainer = document.createElement('div');
            modalContainer.id = 'updateArticleModalContainer';
            modalContainer.innerHTML = html;
            
            contentArea.innerHTML = '';
            contentArea.appendChild(modalContainer);
            
            document.getElementById('addArticleFormContainer').style.display = 'none';
            document.getElementById('editArticleFormContainer').style.display = 'none';
            
            const toggleButton = document.getElementById('toggleAddArticleForm');
            toggleButton.innerHTML = '<i class="fas fa-times mr-2"></i>Retour';
            toggleButton.onclick = function() {
                location.reload();
            };
            
            setupUpdateFormHandlers(articleId);
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Erreur lors du chargement du formulaire');
        });
};

function setupUpdateFormHandlers(articleId) {
    const form = document.getElementById('updateArticleForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Safely prepare accessories data
        const accessories = Array.isArray(window.selectedAccessories)
            ? window.selectedAccessories.map(acc => ({
                id: parseInt(acc.id),
                quantity: parseInt(acc.quantity)
            }))
            : [];

        // Create form data
        const formData = new FormData(this);
        formData.append('accessories', JSON.stringify(accessories));

        // Submit form
        fetch('home.php?section=Parametrage&item=update_article', {
            method: 'POST',
            headers: { 
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(async response => {
            const text = await response.text();
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid server response');
            }
        })
        .then(data => {
            if (data.success) {
                window.showMessage(data.message || 'Mise à jour réussie');
                setTimeout(() => location.reload(), 1500);
            } else {
                window.showMessage(data.error || 'Erreur inconnue', true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            window.showMessage('Erreur technique: ' + error.message, true);
        });
    });
}
    function setupAccessoriesCloseHandler() {
        const closeButton = document.getElementById('closeAccessoriesModal');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                location.reload();
            });
        }

        const closeIcon = document.querySelector('#closeAccessoriesModal .fa-times');
        if (closeIcon) {
            closeIcon.addEventListener('click', function() {
                location.reload();
            });
        }
    }

    function setupAccessoriesFormHandlers(articleId) {
        const form = document.getElementById('addAccessoryForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);

                fetch('content/Parametrage/products/article_accessories.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAccessoriesModal(articleId);
                    } else {
                        alert(data.error || 'Une erreur est survenue');
                    }
                })
                .catch(error => alert('Une erreur est survenue'));
            });
        }
    }

    // Event listener for delete buttons
    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('.delete-accessory-btn');
        if (deleteBtn) {
            e.preventDefault();
            const articleId = deleteBtn.dataset.articleId;
            const accessoryId = deleteBtn.dataset.accessoryId;
            
            if (isNaN(articleId) || isNaN(accessoryId)) {
                alert('IDs invalides');
                return;
            }
            
            deleteAccessory(parseInt(articleId), parseInt(accessoryId));
        }
    });

    window.deleteAccessory = function(articleId, accessoryId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet accessoire ?')) {
            fetch('content/Parametrage/products/article_accessories.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&article_id=${articleId}&accessory_id=${accessoryId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAccessoriesModal(articleId);
                } else {
                    alert(data.error || 'Erreur inconnue');
                }
            })
            .catch(() => {
                alert('Erreur de connexion');
            });
        }
    };
});
</script>