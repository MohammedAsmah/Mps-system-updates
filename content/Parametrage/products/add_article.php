<?php
// Place these at the very top
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        // Validate required fields
        if (empty($_POST['designation'])) {
            throw new Exception("La désignation est obligatoire");
        }
        if (empty($_POST['bardoce_p'])) {
            throw new Exception("Le code-barres P est obligatoire");
        }

        $conn->beginTransaction();
        
        // Check duplicate designation
        $checkArticle = $conn->prepare("SELECT COUNT(*) FROM Articles WHERE designation = ?");
        $checkArticle->execute([$_POST['designation']]);
        if ($checkArticle->fetchColumn() > 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => "Cette désignation existe déjà"]);
            exit();
        }

        // Check duplicate barcode
        $checkBarcode = $conn->prepare("SELECT COUNT(*) FROM Articles WHERE bardoce_p = ?");
        $checkBarcode->execute([$_POST['bardoce_p']]);
        if ($checkBarcode->fetchColumn() > 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => "Ce code-barres existe déjà"]);
            exit();
        }

        // Insert article
        $stmt = $conn->prepare("
            INSERT INTO Articles (
                designation, conditionnement, palette, tarif, 
                bardoce_p, barcode_pi, poids_sans_emballage, 
                poids_avec_emballage, categorie_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['designation'],
            $_POST['conditionnement'] ?? null,
            $_POST['palette'] ?? null,
            $_POST['tarif'] ?? 0.00,
            $_POST['bardoce_p'],
            $_POST['barcode_pi'] ?? null,
            $_POST['poids_sans_emballage'] ?? 0.00,
            $_POST['poids_avec_emballage'] ?? 0.00,
            $_POST['categorie_id'] ?? null
        ]);

        $articleId = $conn->lastInsertId();

        // Handle accessories if present
        if (isset($_POST['accessories']) && is_array($_POST['accessories'])) {
            $accessoryStmt = $conn->prepare("
                INSERT INTO articleaccessoiries (article_id, Accessoire_id, quantity)
                VALUES (?, ?, ?)
            ");

            foreach ($_POST['accessories'] as $accessory) {
                $accessoryStmt->execute([
                    $articleId,
                    $accessory['id'],
                    $accessory['quantity']
                ]);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Article créé avec succès"]);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// Initialize response
$response = [
    'success' => false,
    'error' => null
];

// Fetch categories
try {
    $catStmt = $conn->prepare("SELECT * FROM Categories ORDER BY designation");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();
} catch (PDOException $e) {
    $response['error'] = "Erreur de chargement des catégories: " . $e->getMessage();
}

// Fetch accessories
try {
    $accStmt = $conn->prepare("SELECT Accessoire_id, designation FROM Accessoires ORDER BY designation");
    $accStmt->execute();
    $accessories = $accStmt->fetchAll();
} catch (PDOException $e) {
    $response['error'] = "Erreur de chargement des accessoires: " . $e->getMessage();
}

// Display form if not AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvel Article</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <form method="POST" class="bg-white p-6 rounded shadow-md" id="addArticleForm">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Ajouter un Article</h2>
            <button type="button" onclick="cancelAdd()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="messageContainer">
            <?php if ($response['error']): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($response['error']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <!-- Designation -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Désignation *</label>
                <input type="text" name="designation" required
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Category -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Catégorie</label>
                <select name="categorie_id" 
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['Categorie_id'] ?>">
                            <?= htmlspecialchars($category['designation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Barcode P -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Code-barres P *</label>
                <input type="text" name="bardoce_p" required
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Barcode PI -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Code-barres PI</label>
                <input type="text" name="barcode_pi"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Conditionnement -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Conditionnement</label>
                <input type="text" name="conditionnement"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Palette -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Palette</label>
                <input type="text" name="palette"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Tarif -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tarif (€)</label>
                <input type="number" step="0.01" name="tarif"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Weights -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Poids sans emballage (kg)</label>
                <input type="number" step="0.01" name="poids_sans_emballage"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Poids avec emballage (kg)</label>
                <input type="number" step="0.01" name="poids_avec_emballage"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Add this before the submit button -->
            <div class="col-span-2 mb-4">
                <h3 class="text-lg font-semibold mb-2">Accessoires</h3>
                <div class="flex space-x-2 mb-2">
                    <select id="accessorySelect" class="flex-1 px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">Sélectionner un accessoire</option>
                        <?php foreach ($accessories as $accessory): ?>
                            <option value="<?= $accessory['Accessoire_id'] ?>" data-name="<?= htmlspecialchars($accessory['designation']) ?>">
                                <?= htmlspecialchars($accessory['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" id="accessoryQuantity" min="1" value="1" 
                        class="w-24 px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        placeholder="Qté">
                    <button type="button" onclick="addAccessory()" 
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                        Ajouter
                    </button>
                </div>
                <div id="selectedAccessories" class="space-y-2">
                    <!-- Selected accessories will be displayed here -->
                </div>
            </div>

        </div>

        <div class="flex justify-end space-x-4 mt-6">
            <button type="button" onclick="window.cancelAdd()"
                class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                Annuler
            </button>
            <button type="submit" 
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                Créer l'article
            </button>
        </div>
    </form>

    <script>
    let selectedAccessories = [];

    function addAccessory() {
        const select = document.getElementById('accessorySelect');
        const quantity = document.getElementById('accessoryQuantity');
        const accessoryId = select.value;
        const accessoryName = select.options[select.selectedIndex].dataset.name;
        
        if (!accessoryId) {
            alert('Veuillez sélectionner un accessoire');
            return;
        }

        const accessory = {
            id: accessoryId,
            name: accessoryName,
            quantity: parseInt(quantity.value, 10)
        };

        selectedAccessories.push(accessory);
        displayAccessories();
        select.value = '';
        quantity.value = '1';
    }

    function removeAccessory(index) {
        selectedAccessories.splice(index, 1);
        displayAccessories();
    }

    function displayAccessories() {
        const container = document.getElementById('selectedAccessories');
        container.innerHTML = selectedAccessories.map((acc, index) => `
            <div class="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200">
                <span class="font-medium">${acc.name}</span>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600">Quantité: ${acc.quantity}</span>
                    <button type="button" onclick="removeAccessory(${index})" 
                        class="text-red-600 hover:text-red-800 px-2">
                        ×
                    </button>
                </div>
                <input type="hidden" name="accessories[${index}][id]" value="${acc.id}">
                <input type="hidden" name="accessories[${index}][quantity]" value="${acc.quantity}">
            </div>
        `).join('');
    }

    document.addEventListener('DOMContentLoaded', function() {
        const addArticleForm = document.querySelector('#addArticleForm');
        const messageContainer = document.getElementById('messageContainer');

        function showFormMessage(message, isError = false) {
            messageContainer.innerHTML = '';
            const messageDiv = document.createElement('div');
            messageDiv.className = isError 
                ? 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4'
                : 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
            messageDiv.innerHTML = message;
            messageContainer.appendChild(messageDiv);
        }

        addArticleForm?.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('accessories', JSON.stringify(selectedAccessories));

            fetch('home.php?section=articles&item=add_article', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFormMessage(data.message);
                    selectedAccessories = [];
                    displayAccessories();
                    this.reset();
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showFormMessage(data.error, true);
                }
            })
            .catch(error => {
                showFormMessage('Erreur lors de la création: ' + error.message, true);
            });
        });
    });
    </script>
</body>
</html>
<?php 
} else {
    echo json_encode($response);
    exit();
} 
?>