<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_updated_version/db_connect.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        if (!isset($_POST['action'])) {
            throw new Exception("Action non spécifiée");
        }

        if ($_POST['action'] === 'add') {
            if (!isset($_POST['article_id']) || !isset($_POST['accessory_id']) || !isset($_POST['quantity'])) {
                throw new Exception("Données manquantes");
            }

            $articleId = (int)$_POST['article_id'];
            $accessoryId = (int)$_POST['accessory_id'];
            $quantity = (float)$_POST['quantity'];
            
            // Check for duplicates
            $stmt = $conn->prepare("SELECT COUNT(*) FROM ArticleAccessoiries WHERE article_id = ? AND Accessoire_id = ?");
            $stmt->execute([$articleId, $accessoryId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cet accessoire existe déjà pour cet article");
            }

            // Insert accessory
            $stmt = $conn->prepare("INSERT INTO ArticleAccessoiries (article_id, Accessoire_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$articleId, $accessoryId, $quantity]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Accessoire ajouté avec succès'
            ]);
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            try {
                $articleId = (int)$_POST['article_id'];
                $accessoryId = (int)$_POST['accessory_id'];
                
                $stmt = $conn->prepare("DELETE FROM articleaccessoiries WHERE article_id = ? AND Accessoire_id = ?");
                $stmt->execute([$articleId, $accessoryId]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Accessoire supprimé avec succès'
                ]);
                exit;
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Échec de la suppression'
                ]);
                exit;
            }
        }

        throw new Exception("Action invalide");
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Helper function to get article accessories
function getArticleAccessories($conn, $articleId) {
    return $conn->query("
        SELECT a.*, aa.quantity, c.designation as category_name,
               COALESCE(a.poids_avec_carotte, 0) as poids_avec_carotte,
               COALESCE(a.poids_sans_carotte, 0) as poids_sans_carotte
        FROM Accessoires a
        INNER JOIN ArticleAccessoiries aa ON a.Accessoire_id = aa.Accessoire_id
        LEFT JOIN Categories c ON a.categorie_id = c.Categorie_id
        WHERE aa.article_id = $articleId
        ORDER BY a.designation
    ")->fetchAll(PDO::FETCH_ASSOC);
}


// Handle partial content request
if (isset($_GET['partial']) && $_GET['partial'] == '1') {
    try {
        $articleId = (int)($_GET['id'] ?? 0);
        if ($articleId < 1) throw new Exception("ID d'article invalide");

        $article = $conn->query("SELECT * FROM Articles WHERE Article_id = $articleId")->fetch(PDO::FETCH_ASSOC);
        if (!$article) throw new Exception("Article non trouvé");

        $allAccessories = $conn->query("SELECT Accessoire_id, designation FROM Accessoires ORDER BY designation")->fetchAll(PDO::FETCH_ASSOC);
        $accessories = getArticleAccessories($conn, $articleId);
?>

    <div class="bg-white rounded-lg shadow-lg p-6">
        <!-- Header -->
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">
                Accessoires de l'article: <?= htmlspecialchars($article['designation']) ?>
            </h2>
            <button type="button" id="closeAccessoriesModal" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Add accessory form -->
        <form id="addAccessoryForm" class="mb-6 p-4 bg-gray-50 rounded">
            <div class="flex gap-4 items-end">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="article_id" value="<?= $articleId ?>">
                
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700">Accessoire</label>
                    <select name="accessory_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Sélectionner un accessoire</option>
                        <?php foreach ($allAccessories as $acc): ?>
                            <option value="<?= $acc['Accessoire_id'] ?>">
                                <?= htmlspecialchars($acc['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="w-32">
                    <label class="block text-sm font-medium text-gray-700">Quantité</label>
                    <input type="number" name="quantity" required min="0" step="0.01" value="1"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Ajouter
                </button>
            </div>
        </form>

        <!-- Accessories table -->
        <div id="accessoriesTableContainer">
            <?php if (count($accessories) > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Désignation</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Catégorie</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Quantité</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Poids avec carotte</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Poids sans carotte</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">actions</th>
                                </tr>
                            </thead>
                        <tbody>
                            <?php foreach ($accessories as $accessory): ?>
                                <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                            <?= htmlspecialchars($accessory['designation']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                <?= htmlspecialchars($accessory['category_name'] ?? 'Non catégorisé') ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?= htmlspecialchars($accessory['quantity']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?= number_format($accessory['poids_avec_carotte'], 2) ?> kg
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <?= number_format($accessory['poids_sans_carotte'], 2) ?> kg
                                        </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <button type="button" 
        data-article-id="<?= htmlspecialchars($article['Article_id']) ?>" 
        data-accessory-id="<?= htmlspecialchars($accessory['Accessoire_id']) ?>" 
        class="delete-accessory-btn text-red-600 hover:text-red-900">
    <i class="fas fa-trash"></i>
</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
            <?php else: ?>
                <div class="text-center py-4 text-gray-500">
                    Aucun accessoire associé à cet article
                </div>
            <?php endif; ?>
        </div>
    </div>
     <script>
        // Close accessories modal function
function closeAccessoriesModal() {
    const container = document.getElementById('accessoriesContainer');
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
    document.getElementById('articleTableContainer').style.display = 'block';
    
    // Reset the add article button if needed
    const addBtn = document.getElementById('toggleAddArticleForm');
    if (addBtn) {
        addBtn.innerHTML = '<i class="fas fa-plus mr-2"></i> Nouvel Article';
        addBtn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        addBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }
}

// Event listener for close button
document.addEventListener('click', function(e) {
    if (e.target.closest('#closeAccessoriesModal') || 
        e.target.classList.contains('fa-times')) {
        closeAccessoriesModal();
    }
});

// Also make sure this function is available globally
window.closeAccessoriesModal = closeAccessoriesModal;
     </script>
<?php
    } catch (Exception $e) {
        echo "<div class='text-red-500 p-4'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit();
}
?>
