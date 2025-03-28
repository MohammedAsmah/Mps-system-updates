<?php
include 'db_connect.php';

try {
    $catStmt = $conn->prepare("SELECT * FROM Categories ORDER BY designation");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
    
    $selectedCategory = isset($_GET['category']) ? $_GET['category'] : '';
    
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

if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['article_id'])) {
    try {
        $stmt = $conn->prepare("DELETE FROM Articles WHERE Article_id = ?");
        $stmt->execute([$_POST['article_id']]);
        $_SESSION['success_message'] = "Article supprimé avec succès";
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Erreur de suppression: " . $e->getMessage();
    }
}
?>
<div class="container mx-auto px-4 sm:px-2 md:px-1 py-1 sm:py-4 md:py-2 bg-gray-50">
    <!-- Responsive Messages Section -->
    <div class="space-y-3 sm:space-y-4">
        <?php if (isset($_SESSION['error_message'])): ?>
            <div id="errorMessage" class="message-fade bg-red-100 border border-red-400 text-red-700 px-3 sm:px-4 py-2 sm:py-3 rounded text-sm sm:text-base mb-3 sm:mb-4">
                <div class="flex items-start">
                    <svg class="h-4 w-4 sm:h-5 sm:w-5 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                    <span class="ml-2"><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </div>

    <!-- Header with responsive adjustments -->
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 gap-4">
        <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 flex items-center">
            <span class="bg-blue-600 text-white p-2 rounded-lg mr-3">
                <i class="fas fa-box"></i>
            </span>
            Liste des Articles
        </h2>
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 w-full sm:w-auto">
            <form id="categoryFilter" class="flex items-center bg-white rounded-lg shadow-sm p-2 w-full sm:w-auto">
                <select name="category" id="categorySelect" 
                    class="form-select rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition-shadow duration-200 text-sm sm:text-base w-full">
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
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 sm:py-3 px-4 sm:px-6 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-xl flex items-center justify-center w-full sm:w-auto">
                <i class="fas fa-plus mr-2"></i> Nouvel Article
            </button>
        </div>
    </div>

    <!-- Improved responsive table -->
    <div id="articleTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
        <?php if (count($articles) > 0): ?>
            <!-- Table for larger screens -->
            <div class="hidden sm:block overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Désignation</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Catégorie</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Condition.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Tarif</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">CB P</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">CB PI</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Poids</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($articles as $article): ?>
                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($article['designation']) ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                    <?= htmlspecialchars($article['category_name'] ?? 'Non catégorisé') ?>
                                </span>
                            </td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($article['conditionnement'] ?? '-') ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= number_format($article['tarif'], 2) ?> €</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-mono text-gray-500"><?= htmlspecialchars($article['bardoce_p'] ?? '-') ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-mono text-gray-500"><?= htmlspecialchars($article['barcode_pi'] ?? '-') ?></td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500"><?= number_format($article['poids_avec_emballage'], 2) ?> kg</td>
                            <td class="px-4 py-4 whitespace-nowrap text-sm font-medium">
                                <div class="flex items-center space-x-2">
                                    <a href="#" onclick="event.preventDefault(); showUpdateArticleModal(<?= $article['Article_id'] ?>)" 
                                       class="text-blue-600 hover:text-blue-900 transition-colors duration-200 p-1" title="Modifier">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                    <button data-article-id="<?= $article['Article_id'] ?>"
                                            class="accessories-btn text-purple-600 hover:text-purple-900 transition-colors duration-200 p-1"
                                            title="Voir les accessoires">
                                        <i class="fas fa-puzzle-piece text-sm"></i>
                                    </button>
                                    <button onclick="deleteArticle(<?= $article['Article_id'] ?>)" 
                                            class="text-red-600 hover:text-red-900 transition-colors duration-200 p-1" title="Supprimer">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card layout for smaller screens -->
            <div class="block sm:hidden space-y-4 p-4">
                <?php foreach ($articles as $article): ?>
                    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 hover:bg-gray-50 transition-colors duration-200">
                        <div class="grid grid-cols-1 gap-2 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">Désignation:</span>
                                <span class="ml-2 text-gray-900"><?= htmlspecialchars($article['designation']) ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Catégorie:</span>
                                <span class="ml-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 px-2 py-1">
                                    <?= htmlspecialchars($article['category_name'] ?? 'Non catégorisé') ?>
                                </span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Conditionnement:</span>
                                <span class="ml-2 text-gray-500"><?= htmlspecialchars($article['conditionnement'] ?? '-') ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Tarif:</span>
                                <span class="ml-2 font-medium text-gray-900"><?= number_format($article['tarif'], 2) ?> €</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Code-barres P:</span>
                                <span class="ml-2 font-mono text-gray-500"><?= htmlspecialchars($article['bardoce_p'] ?? '-') ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Code-barres PI:</span>
                                <span class="ml-2 font-mono text-gray-500"><?= htmlspecialchars($article['barcode_pi'] ?? '-') ?></span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Poids:</span>
                                <span class="ml-2 text-gray-500"><?= number_format($article['poids_avec_emballage'], 2) ?> kg</span>
                            </div>
                            <div class="flex items-center justify-start space-x-3 pt-2 border-t border-gray-200">
                                <a href="#" onclick="event.preventDefault(); showUpdateArticleModal(<?= $article['Article_id'] ?>)" 
                                   class="text-blue-600 hover:text-blue-900 transition-colors duration-200 p-1" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button data-article-id="<?= $article['Article_id'] ?>"
                                        class="accessories-btn text-purple-600 hover:text-purple-900 transition-colors duration-200 p-1"
                                        title="Voir les accessoires">
                                    <i class="fas fa-puzzle-piece"></i>
                                </button>
                                <button onclick="deleteArticle(<?= $article['Article_id'] ?>)" 
                                        class="text-red-600 hover:text-red-900 transition-colors duration-200 p-1" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="bg-gray-50 p-4 text-center text-gray-500 text-sm sm:text-base">
                Aucun article trouvé
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addArticleFormContainer" style="display: none;">
    <?php include 'add_article.php'; ?>
</div>

<div id="editArticleFormContainer" style="display: none;"></div> <!-- Empty until fetched -->
<div id="accessoriesContainer" style="display: none;"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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

    function resetContainers() {
        document.getElementById('addArticleFormContainer').style.display = 'none';
        document.getElementById('editArticleFormContainer').style.display = 'none';
        document.getElementById('articleTableContainer').style.display = 'block';
        const toggleButton = document.getElementById('toggleAddArticleForm');
        toggleButton.innerHTML = '<i class="fas fa-plus mr-2"></i>Nouvel Article';
        toggleButton.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        toggleButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }

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

    window.cancelAdd = function() {
        resetContainers();
    };

    window.cancelEdit = function() {
        resetContainers();
    };

    window.deleteArticle = function(articleId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet article ?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('article_id', articleId);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
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

    window.showUpdateArticleModal = function(articleId) {
    const editArticleFormContainer = document.getElementById('editArticleFormContainer');
    const articleTableContainer = document.getElementById('articleTableContainer');
    const addArticleFormContainer = document.getElementById('addArticleFormContainer');

    console.log('Fetching update form for article ID:', articleId);
    fetch(`content/Parametrage/products/update_article.php?id=${articleId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => {
        console.log('Fetch status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        return response.text();
    })
    .then(html => {
        console.log('Fetched HTML length:', html.length);
        editArticleFormContainer.innerHTML = html;
        editArticleFormContainer.style.display = 'block';
        articleTableContainer.style.display = 'none';
        addArticleFormContainer.style.display = 'none';

        const toggleButton = document.getElementById('toggleAddArticleForm');
        toggleButton.innerHTML = '<i class="fas fa-times mr-2"></i>Retour';
        toggleButton.classList.remove('bg-blue-600', 'hover:bg-blue-700');
        toggleButton.classList.add('bg-gray-600', 'hover:bg-gray-700');
        toggleButton.onclick = function() {
            resetContainers();
        };

        if (typeof initializeUpdateForm === 'function') {
            initializeUpdateForm();
            console.log('Form initialized successfully');
        } else {
            console.error('initializeUpdateForm not found in fetched HTML');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showMessage('Erreur lors du chargement du formulaire: ' + error.message, true);
    });
};

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

    document.querySelectorAll('.accessories-btn').forEach(button => {
        button.addEventListener('click', function() {
            const articleId = this.dataset.articleId;
            showAccessoriesModal(articleId);
        });
    });

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

    function setupAccessoriesCloseHandler() {
        const closeButton = document.getElementById('closeAccessoriesModal');
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                location.reload();
            });
        }
    }

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