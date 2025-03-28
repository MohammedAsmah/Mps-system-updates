<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_updated_version/db_connect.php';

// Handle AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    file_put_contents(__DIR__ . '/update_debug.log', date('Y-m-d H:i:s') . " - POST received: " . json_encode($_POST) . "\n", FILE_APPEND);
    ob_clean();
    header('Content-Type: application/json');

    try {
        if (!isset($_POST['article_id'])) {
            throw new Exception("ID de l'article manquant");
        }

        $articleId = (int)$_POST['article_id'];

        if (empty($_POST['designation'])) {
            throw new Exception("La désignation est obligatoire");
        }
        if (empty($_POST['bardoce_p'])) {
            throw new Exception("Le code-barres P est obligatoire");
        }

        $conn->beginTransaction();

        $checkArticle = $conn->prepare("SELECT COUNT(*) FROM Articles WHERE designation = ? AND Article_id != ?");
        $checkArticle->execute([$_POST['designation'], $articleId]);
        if ($checkArticle->fetchColumn() > 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => "Cette désignation existe déjà"]);
            exit();
        }

        $checkBarcode = $conn->prepare("SELECT COUNT(*) FROM Articles WHERE bardoce_p = ? AND Article_id != ?");
        $checkBarcode->execute([$_POST['bardoce_p'], $articleId]);
        if ($checkBarcode->fetchColumn() > 0) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'error' => "Ce code-barres existe déjà"]);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE Articles SET
                designation = ?, conditionnement = ?, palette = ?, tarif = ?, 
                bardoce_p = ?, barcode_pi = ?, poids_sans_emballage = ?, 
                poids_avec_emballage = ?, categorie_id = ?
            WHERE Article_id = ?
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
            $_POST['categorie_id'] ?? null,
            $articleId
        ]);

        if (isset($_POST['accessories'])) {
            $accessoriesData = json_decode($_POST['accessories'], true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($accessoriesData)) {
                throw new Exception("Format d'accessoires invalide");
            }

            $conn->prepare("DELETE FROM ArticleAccessoiries WHERE article_id = ?")->execute([$articleId]);
            $accessoryStmt = $conn->prepare("
                INSERT INTO ArticleAccessoiries (article_id, Accessoire_id, quantity)
                VALUES (?, ?, ?)
            ");

            foreach ($accessoriesData as $accessory) {
                if (!isset($accessory['id']) || !isset($accessory['quantity'])) {
                    throw new Exception("Données d'accessoire incomplètes");
                }
                $accessoryStmt->execute([$articleId, (int)$accessory['id'], (int)$accessory['quantity']]);
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Article mis à jour avec succès"]);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        // Log the full error for debugging
        error_log($e->getMessage());
        file_put_contents(__DIR__ . '/update_errors.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit();
    }
}

// GET request to render form
$articleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($articleId === 0) {
    echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">ID d\'article requis</div>';
    exit;
}

$article = null;
$categories = [];
$accessories = [];
$articleAccessories = [];

try {
    $stmt = $conn->prepare("SELECT * FROM Articles WHERE Article_id = ?");
    $stmt->execute([$articleId]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$article) {
        throw new Exception("Article non trouvé");
    }

    $catStmt = $conn->prepare("SELECT * FROM Categories ORDER BY designation");
    $catStmt->execute();
    $categories = $catStmt->fetchAll();

    $accStmt = $conn->prepare("SELECT Accessoire_id, designation FROM Accessoires ORDER BY designation");
    $accStmt->execute();
    $accessories = $accStmt->fetchAll();

    $accStmt = $conn->prepare("
        SELECT a.Accessoire_id, a.designation, aa.quantity
        FROM ArticleAccessoiries aa
        JOIN Accessoires a ON aa.Accessoire_id = a.Accessoire_id
        WHERE aa.article_id = ?
    ");
    $accStmt->execute([$articleId]);
    $articleAccessories = $accStmt->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur: " . $e->getMessage();
}
?>

<div class="bg-white p-6 rounded shadow-md" id="updateArticleContainer">
    <form method="POST" id="updateArticleForm">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold">Modifier l'Article</h2>
            <button type="button" onclick="window.cancelEdit()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="messageContainer" class="mb-4"></div>

        <input type="hidden" name="article_id" value="<?= $article['Article_id'] ?>">

        <div class="grid grid-cols-2 gap-4">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Désignation *</label>
                <input type="text" name="designation" required value="<?= htmlspecialchars($article['designation']) ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Catégorie</label>
                <select name="categorie_id" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['Categorie_id'] ?>" <?= $article['categorie_id'] == $category['Categorie_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['designation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Code-barres P *</label>
                <input type="text" name="bardoce_p" required value="<?= htmlspecialchars($article['bardoce_p']) ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Code-barres PI</label>
                <input type="text" name="barcode_pi" value="<?= htmlspecialchars($article['barcode_pi'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Conditionnement</label>
                <input type="text" name="conditionnement" value="<?= htmlspecialchars($article['conditionnement'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Palette</label>
                <input type="text" name="palette" value="<?= htmlspecialchars($article['palette'] ?? '') ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tarif (€)</label>
                <input type="number" step="0.01" name="tarif" value="<?= htmlspecialchars($article['tarif'] ?? '0.00') ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Poids sans emballage (kg)</label>
                <input type="number" step="0.01" name="poids_sans_emballage" value="<?= htmlspecialchars($article['poids_sans_emballage'] ?? '0.00') ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Poids avec emballage (kg)</label>
                <input type="number" step="0.01" name="poids_avec_emballage" value="<?= htmlspecialchars($article['poids_avec_emballage'] ?? '0.00') ?>"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
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
                    <?php foreach ($articleAccessories as $index => $acc): ?>
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded border border-gray-200">
                            <span class="font-medium"><?= htmlspecialchars($acc['designation']) ?></span>
                            <div class="flex items-center space-x-4">
                                <span class="text-gray-600">Quantité: <?= $acc['quantity'] ?></span>
                                <button type="button" onclick="removeAccessory(<?= $index ?>)" 
                                    class="text-red-600 hover:text-red-800 px-2">
                                    ×
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-4 mt-6">
            <button type="button" onclick="window.cancelEdit()"
                class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                Annuler
            </button>
            <button type="submit" 
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                Mettre à jour
            </button>
        </div>
    </form>

    <script>
    window.selectedAccessories = <?php echo json_encode(array_map(function($acc) {
        return ['id' => $acc['Accessoire_id'], 'name' => $acc['designation'], 'quantity' => $acc['quantity']];
    }, $articleAccessories)); ?>;

function addAccessory() {
    const select = document.getElementById('accessorySelect');
    const quantity = document.getElementById('accessoryQuantity');
    if (!select || !quantity) {
        console.error('Accessory select or quantity input not found');
        return;
    }
    const accessoryId = select.value; // Remove .trim() as it's unnecessary for select values
    const selectedOption = select.options[select.selectedIndex];
    // Ensure data-name is correctly set in the option
    const accessoryName = selectedOption.getAttribute('data-name') || '';

    if (!accessoryId) {
        alert('Veuillez sélectionner un accessoire');
        return;
    }

    const accessory = {
        id: accessoryId,
        name: accessoryName,
        quantity: parseInt(quantity.value, 10) || 1
    };

    window.selectedAccessories.push(accessory);
    displayAccessories();
    select.value = '';
    quantity.value = '1';
}

    function removeAccessory(index) {
        window.selectedAccessories.splice(index, 1);
        displayAccessories();
    }

    function displayAccessories() {
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
    }

    function initializeUpdateForm() {
        const updateArticleForm = document.getElementById('updateArticleForm');
        const messageContainer = document.getElementById('messageContainer');
        console.log('Initializing - Form found:', !!updateArticleForm);
        console.log('Initializing - Message container found:', !!messageContainer);

        if (!updateArticleForm || !messageContainer) {
            console.error('Form or message container not found');
            return;
        }

        function showFormMessage(message, isError = false) {
            console.log('Showing message:', message, 'Is error:', isError);
            messageContainer.innerHTML = '';
            const messageDiv = document.createElement('div');
            messageDiv.className = isError 
                ? 'bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded'
                : 'bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded';
            messageDiv.innerHTML = message;
            messageContainer.appendChild(messageDiv);
        }

        updateArticleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('accessories', JSON.stringify(window.selectedAccessories.map(acc => ({
    id: acc.id,
    quantity: acc.quantity
}))));
            console.log('Submitting:', Object.fromEntries(formData));

            fetch('/mps_updated_version/content/Parametrage/products/update_article.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) throw new Error('Network response not OK');
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    showFormMessage(data.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showFormMessage(data.error, true);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showFormMessage('Erreur lors de la mise à jour: ' + error.message, true);
            });
        });
        window.selectedAccessories = <?php 
    echo json_encode(array_map(function($acc) {
        return [
            'id' => (string)$acc['Accessoire_id'], // Ensure ID is a string if needed
            'name' => $acc['designation'],
            'quantity' => (int)$acc['quantity']
        ];
    }, $articleAccessories)); 
?>;
    }

    initializeUpdateForm();
    </script>
</div>