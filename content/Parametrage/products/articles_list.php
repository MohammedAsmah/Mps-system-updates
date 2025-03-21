<?php
include 'db_connect.php';

// Add this near the top, before the articles query
try {
    // Fetch all categories for the filter dropdown
    $catStmt = $conn->prepare("SELECT * FROM Categories ORDER BY designation");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
    
    // Get selected category from GET parameter
    $selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
    
    // Modify the articles query to include category filter
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

<div class="container mx-auto px-4">
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
        <h2 class="text-2xl font-bold text-gray-800">Liste des Articles</h2>
        <div class="flex items-center space-x-4">
            <!-- Modify the category filter form -->
            <form id="categoryFilter" class="flex items-center">
                <select name="category" id="categorySelect" class="form-select rounded-md border-gray-300 shadow-sm mr-2">
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
                            class="text-sm text-gray-600 hover:text-gray-900">
                        <i class="fas fa-times-circle"></i> Réinitialiser
                    </button>
                <?php endif; ?>
            </form>
            <!-- Existing Add Article button -->
            <button id="toggleAddArticleForm" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition duration-300 ease-in-out transform hover:scale-105">
                <i class="fas fa-plus mr-2"></i> Nouvel Article
            </button>
        </div>
    </div>

    <div id="articleTableContainer" class="bg-white rounded-lg shadow-lg">
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
                                <a href="#" onclick="loadEditArticleForm(<?= $article['Article_id'] ?>)" 
                                   class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="loadAccessories(<?= $article['Article_id'] ?>)"
                                        class="text-purple-600 hover:text-purple-900 transition-colors duration-200"
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
    // Message handling from liste_groups.php
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

    // Toggle Add Article Form
    const toggleAddArticleFormButton = document.getElementById('toggleAddArticleForm');
    const addArticleFormContainer = document.getElementById('addArticleFormContainer');
    const editArticleFormContainer = document.getElementById('editArticleFormContainer');
    const articleTableContainer = document.getElementById('articleTableContainer');

    if (toggleAddArticleFormButton) {
        toggleAddArticleFormButton.addEventListener('click', function() {
            addArticleFormContainer.style.display = 
                addArticleFormContainer.style.display === 'none' ? 'block' : 'none';
            articleTableContainer.style.display = 
                addArticleFormContainer.style.display === 'none' ? 'block' : 'none';
            editArticleFormContainer.style.display = 'none';
        });
    }

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
            editArticleFormContainer.innerHTML = html;
            editArticleFormContainer.style.display = 'block';
            articleTableContainer.style.display = 'none';
            addArticleFormContainer.style.display = 'none';

            // Add form submission handler
            const editForm = editArticleFormContainer.querySelector('form');
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);

                    fetch(`home.php?section=Parametrage&item=update_article`, {
                        method: 'POST',
                        headers: { 
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => response.text())
                    .then(text => {
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    })
                    .then(data => {
                        if (data.success) {
                            showMessage(data.message || 'Article mis à jour avec succès');
                            setTimeout(() => location.reload(), 2000);
                        } else {
                            showMessage(data.error || 'Une erreur est survenue', true);
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

    // Cancel functions
    window.cancelAdd = function() {
        addArticleFormContainer.style.display = 'none';
        articleTableContainer.style.display = 'block';
    };

    window.cancelEdit = function() {
        editArticleFormContainer.style.display = 'none';
        articleTableContainer.style.display = 'block';
    };

    // Add new category filter handling
    const categorySelect = document.getElementById('categorySelect');
    const resetFilter = document.getElementById('resetFilter');
    
    function updateArticlesList(categoryId = '') {
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
            const newTable = doc.getElementById('articleTableContainer');
            
            if (newTable) {
                document.getElementById('articleTableContainer').innerHTML = newTable.innerHTML;
            }
            
            // Update URL without page refresh
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

    // Add accessories handling
    window.loadAccessories = function(articleId) {
        const url = `home.php?section=Parametrage&item=article_accessories&id=${articleId}&partial=1`;
        const accessoriesContainer = document.getElementById('accessoriesContainer');
        
        fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            accessoriesContainer.innerHTML = html;
            accessoriesContainer.style.display = 'block';
            articleTableContainer.style.display = 'none';
            addArticleFormContainer.style.display = 'none';
            editArticleFormContainer.style.display = 'none';
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Error loading accessories: ' + error.message, true);
        });
    };

    window.closeAccessories = function() {
        document.getElementById('accessoriesContainer').style.display = 'none';
        articleTableContainer.style.display = 'block';
    };

    // ...rest of existing code...
});
</script>