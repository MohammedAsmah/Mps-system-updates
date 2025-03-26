<?php
// Start session without output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent accidental output
ob_start();

// Include database connection
require 'db_connect.php';

// Handle AJAX request (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ensure we only output JSON
    ob_clean();
    header('Content-Type: application/json');
    
    $articleId = (int)($_POST['id'] ?? 0);
    if ($articleId < 1) {
        echo json_encode(['success' => false, 'error' => "ID d'article invalide"]);
        exit();
    }
    
    $response = ['success' => false, 'error' => null];
    $changesMade = false;

    try {
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Session expirée, veuillez vous reconnecter");
        }

        $conn->beginTransaction();

        // 1. Process Article Updates
        $currentArticle = $conn->query("SELECT * FROM Articles WHERE Article_id = $articleId")->fetch();
        $updateFields = [];
        $params = [];
        
        $fieldsToCheck = [
            'designation', 'conditionnement', 'palette', 'tarif',
            'bardoce_p', 'barcode_pi', 'poids_sans_emballage', 
            'poids_avec_emballage', 'categorie_id'
        ];
        
        foreach ($fieldsToCheck as $field) {
            $newValue = $_POST[$field] ?? null;
            $currentValue = $currentArticle[$field];
            
            if (in_array($field, ['tarif', 'poids_sans_emballage', 'poids_avec_emballage'])) {
                $newValue = (float)$newValue;
                $currentValue = (float)$currentValue;
            }
            
            if ($newValue != $currentValue) {
                $updateFields[] = "$field = ?";
                $params[] = $newValue;
                $changesMade = true;
            }
        }
        
        if (!empty($updateFields)) {
            $sql = "UPDATE Articles SET " . implode(', ', $updateFields) . " WHERE Article_id = ?";
            $params[] = $articleId;
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
        }

// Process Accessories
$accessories = json_decode($_POST['accessories'] ?? '[]', true);
if (json_last_error() !== JSON_ERROR_NONE) {
    throw new Exception("Format des accessoires invalide");
}

// Get current accessories
$currentAccessories = $conn->query("
    SELECT Accessoire_id, quantity 
    FROM ArticleAccessoiries 
    WHERE article_id = $articleId
")->fetchAll(PDO::FETCH_ASSOC);

// Convert to comparable format [id => quantity]
$currentMap = [];
foreach ($currentAccessories as $acc) {
    $currentMap[(int)$acc['Accessoire_id']] = (int)$acc['quantity'];
}

// Prepare new accessories in same format
$newMap = [];
foreach ($accessories as $acc) {
    if (isset($acc['id']) && isset($acc['quantity'])) {
        $id = (int)$acc['id'];
        $qty = (int)$acc['quantity'];
        if ($id > 0) {
            $newMap[$id] = $qty;
        }
    }
}

// Calculate differences
$toDelete = array_diff_key($currentMap, $newMap);
$toUpdate = array_intersect_key(array_diff($newMap, $currentMap), $newMap);
$toInsert = array_diff_key($newMap, $currentMap);

// Process changes
if (!empty($toDelete) || !empty($toUpdate) || !empty($toInsert)) {
    $changesMade = true;

    // Delete removed accessories
    if (!empty($toDelete)) {
        $deleteStmt = $conn->prepare("
            DELETE FROM ArticleAccessoiries 
            WHERE article_id = ? AND Accessoire_id = ?
        ");
        foreach ($toDelete as $id => $_) {
            $deleteStmt->execute([$articleId, $id]);
        }
    }

    // Update existing accessories
    if (!empty($toUpdate)) {
        $updateStmt = $conn->prepare("
            UPDATE ArticleAccessoiries 
            SET quantity = ? 
            WHERE article_id = ? AND Accessoire_id = ?
        ");
        foreach ($toUpdate as $id => $qty) {
            $updateStmt->execute([$qty, $articleId, $id]);
        }
    }

    // Insert new accessories
    if (!empty($toInsert)) {
        $insertStmt = $conn->prepare("
            INSERT INTO ArticleAccessoiries 
            (article_id, Accessoire_id, quantity) 
            VALUES (?, ?, ?)
        ");
        foreach ($toInsert as $id => $qty) {
            $insertStmt->execute([$articleId, $id, $qty]);
        }
    }
}

        $conn->commit();
        
        $response['success'] = true;
        $response['message'] = $changesMade 
            ? "Article mis à jour avec succès" 
            : "Aucune modification détectée";

    } catch (Exception $e) {
        $conn->rollBack();
        $response['error'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Handle GET request (form display)
if (isset($_GET['partial']) && $_GET['partial'] == '1') {
    
    try {
        $articleId = (int)($_GET['id'] ?? 0);
        if ($articleId < 1) {
            throw new Exception("ID d'article invalide");
        }

        // Fetch article data
        $article = $conn->query("
            SELECT * 
            FROM Articles 
            WHERE Article_id = $articleId
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$article) {
            throw new Exception("Article non trouvé");
        }

        // Fetch categories
        $categories = $conn->query("
            SELECT * 
            FROM Categories 
            ORDER BY designation
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Fetch accessories
        try {
            $accStmt = $conn->prepare("SELECT Accessoire_id, designation FROM Accessoires ORDER BY designation");
            $accStmt->execute();
            $accessories = $accStmt->fetchAll();

            // Fetch existing accessories for this article
            $existingAccStmt = $conn->prepare("
                SELECT a.Accessoire_id, a.designation, aa.quantity 
                FROM ArticleAccessoiries aa 
                JOIN Accessoires a ON aa.Accessoire_id = a.Accessoire_id 
                WHERE aa.article_id = ?
            ");
            $existingAccStmt->execute([$article['Article_id']]);
            $existingAccessories = $existingAccStmt->fetchAll();
        } catch (PDOException $e) {
            $response['error'] = "Erreur de chargement des accessoires: " . $e->getMessage();
        }

        // Display form
        ?>
        <div class="container mx-auto p-6">
            <form method="POST" class="bg-white p-6 rounded-lg shadow-lg" id="updateArticleForm">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-800">
                        Modifier l'article: <?= htmlspecialchars($article['designation']) ?>
                    </h2>
                    <button type="button" onclick="cancelEdit()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <input type="hidden" name="id" value="<?= $article['Article_id'] ?>">
                
                <!-- Basic Information Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 pb-2 border-b">Informations de base</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Désignation *</label>
                            <input type="text" name="designation" required
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= htmlspecialchars($article['designation']) ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Catégorie *</label>
                            <select name="categorie_id" required
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
                                <option value="">Sélectionner une catégorie</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['Categorie_id'] ?>"
                                        <?= $category['Categorie_id'] == $article['categorie_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['designation']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Identification Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 pb-2 border-b">Identification</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Code-barres P *</label>
                            <input type="text" name="bardoce_p" required
                                class="w-full px-4 py-2 border rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= htmlspecialchars($article['bardoce_p']) ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Code-barres PI</label>
                            <input type="text" name="barcode_pi"
                                class="w-full px-4 py-2 border rounded-lg font-mono focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= htmlspecialchars($article['barcode_pi']) ?>">
                        </div>
                    </div>
                </div>

                <!-- Specifications Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 pb-2 border-b">Spécifications</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Conditionnement</label>
                            <input type="text" name="conditionnement"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= htmlspecialchars($article['conditionnement']) ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Palette</label>
                            <input type="text" name="palette"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= htmlspecialchars($article['palette']) ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Tarif (€)</label>
                            <input type="number" step="0.01" name="tarif"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= number_format($article['tarif'], 2, '.', '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Weights Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 pb-2 border-b">Poids</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Poids sans emballage (kg)</label>
                            <input type="number" step="0.01" name="poids_sans_emballage"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= number_format($article['poids_sans_emballage'], 2, '.', '') ?>">
                        </div>

                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2">Poids avec emballage (kg)</label>
                            <input type="number" step="0.01" name="poids_avec_emballage"
                                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400"
                                value="<?= number_format($article['poids_avec_emballage'], 2, '.', '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Accessories Section -->
                <div class="col-span-2 mb-4">
                    <h3 class="text-lg font-semibold mb-2">Accessoires</h3>
                    <div class="flex space-x-2 mb-2">
                        <select id="accessorySelect" class="flex-1 px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                            <option value="">Sélectionner un accessoire</option>
                            <?php foreach ($accessories as $acc): ?>
                                <option value="<?= $acc['Accessoire_id'] ?>" 
                                    data-name="<?= htmlspecialchars($acc['designation']) ?>">
                                    <?= htmlspecialchars($acc['designation']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" id="accessoryQuantity" min="1" value="1" class="w-24 px-3 py-2 border rounded">
                        <button type="button" onclick="addAccessory()" 
    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
    Ajouter
</button>
                    </div>
                    <div id="selectedAccessories" class="space-y-2">
                        <!-- Accessories will be displayed here -->
                    </div>
                </div>

                <div class="flex justify-end space-x-4 pt-6 border-t">
                    <button type="button" onclick="cancelEdit()"
                        class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 font-medium transition-colors duration-200">
                        Annuler
                    </button>
                    <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors duration-200">
                        Mettre à jour
                    </button>
                </div>
            </form>
        </div>

        <script>
/<script>
// Initialize selected accessories properly
document.addEventListener('DOMContentLoaded', function() {
    // Convert PHP data to JS format
    window.selectedAccessories = <?= json_encode(array_map(function($acc) {
        return [
            'id' => (int)$acc['Accessoire_id'],
            'name' => $acc['designation'],
            'quantity' => (int)$acc['quantity']
        ];
    }, $existingAccessories)) ?>;

    // Ensure array type
    if (!Array.isArray(window.selectedAccessories)) {
        window.selectedAccessories = [];
    }
    
    // Initial display
    window.displayAccessories();
});

// Accessory management functions
window.addAccessory = function() {
    const select = document.getElementById('accessorySelect');
    const quantityInput = document.getElementById('accessoryQuantity');
    const accessoryId = parseInt(select.value);
    const accessoryName = select.options[select.selectedIndex].dataset.name;
    const quantity = parseInt(quantityInput.value) || 1;

    if (!accessoryId || isNaN(accessoryId)) {
        alert('Veuillez sélectionner un accessoire valide');
        return;
    }

    // Check if already exists
    const existingIndex = window.selectedAccessories.findIndex(a => a.id === accessoryId);
    if (existingIndex >= 0) {
        window.selectedAccessories[existingIndex].quantity = quantity;
    } else {
        window.selectedAccessories.push({
            id: accessoryId,
            name: accessoryName,
            quantity: quantity
        });
    }

    window.displayAccessories();
    select.value = '';
    quantityInput.value = '1';
};

window.removeAccessory = function(index) {
    window.selectedAccessories.splice(index, 1);
    window.displayAccessories();
};

window.displayAccessories = function() {
    const container = document.getElementById('selectedAccessories');
    container.innerHTML = window.selectedAccessories.map((acc, index) => `
        <div class="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200">
            <span class="font-medium">${acc.name}</span>
            <div class="flex items-center space-x-4">
                <span class="text-gray-600">Quantité: ${acc.quantity}</span>
                <button type="button" onclick="removeAccessory(${index})" 
                    class="text-red-600 hover:text-red-800 px-2">
                    ×
                </button>
            </div>
        </div>
    `).join('');
};

// Initialize display when page loads
document.addEventListener('DOMContentLoaded', function() {
    window.displayAccessories();

    // Form submission handler
    document.getElementById('updateArticleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Clear previous accessory inputs
        const existingInputs = document.querySelectorAll('[name^="accessories"]');
        existingInputs.forEach(input => input.remove());

        // Initialize selected accessories with proper numeric IDs
window.selectedAccessories = <?= json_encode(array_map(function($acc) {
    return [
        'id' => (int)$acc['Accessoire_id'],
        'name' => $acc['designation'],
        'quantity' => (int)$acc['quantity']
    ];
}, $existingAccessories)) ?>;

// Ensure it's always an array
if (!Array.isArray(window.selectedAccessories)) {
    window.selectedAccessories = [];
}

        // Submit form
        const formData = new FormData(this);
        console.log([...formData.entries()]); 
        fetch('home.php?section=Parametrage&item=update_article', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.showMessage(data.message || 'Article mis à jour avec succès');
                setTimeout(() => window.location.reload(), 2000);
            } else {
                window.showMessage(data.error || 'Une erreur est survenue', true);
            }
        })
        .catch(error => {
            window.showMessage('Erreur lors de la mise à jour: ' + error.message, true);
            console.error('Error:', error);
        });
    });
});
</script>
<?php

} catch (Exception $e) {
    echo "<div class='text-red-500 p-4'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
}

exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
echo json_encode(['success' => false, 'error' => 'Données manquantes']);
} else {
header('Location: liste_articles.php');
}
exit();