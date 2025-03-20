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
            throw new Exception("Cette désignation existe déjà");
        }

        // Check duplicate barcode
        $checkBarcode = $conn->prepare("SELECT COUNT(*) FROM Articles WHERE bardoce_p = ?");
        $checkBarcode->execute([$_POST['bardoce_p']]);
        if ($checkBarcode->fetchColumn() > 0) {
            throw new Exception("Ce code-barres existe déjà");
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

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Article créé avec succès"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
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

            fetch('home.php?section=articles&item=add_article', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (error) {
                    console.error("JSON parsing error:", error);
                    console.log("Raw response:", text);
                    throw new Error("Invalid JSON response from server");
                }
            }))
            .then(data => {
                if (data.success) {
                    showFormMessage(data.message || 'Article créé avec succès');
                    this.reset();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showFormMessage(data.error || 'Une erreur est survenue', true);
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