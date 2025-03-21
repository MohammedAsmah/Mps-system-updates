<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'db_connect.php';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        if (empty($_POST['designation'])) {
            throw new Exception("La désignation est obligatoire");
        }

        $conn->beginTransaction();
        
        // Check duplicate designation
        $checkAccessory = $conn->prepare("SELECT COUNT(*) FROM Accessoires WHERE designation = ?");
        $checkAccessory->execute([$_POST['designation']]);
        if ($checkAccessory->fetchColumn() > 0) {
            throw new Exception("Cette désignation existe déjà");
        }

        // Insert accessory
        $stmt = $conn->prepare("
            INSERT INTO Accessoires (
                designation, poids_avec_carotte, poids_sans_carotte, categorie_id
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['designation'],
            $_POST['poids_avec_carotte'] ?? 0.00,
            $_POST['poids_sans_carotte'] ?? 0.00,
            $_POST['categorie_id'] ?: null
        ]);

        $conn->commit();
        echo json_encode(['success' => true, 'message' => "Accessoire créé avec succès"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Initialize response
$response = ['success' => false, 'error' => null];

// Fetch categories for dropdown
try {
    $categories = $conn->query("SELECT * FROM Categories ORDER BY designation")->fetchAll();
} catch (PDOException $e) {
    $response['error'] = "Erreur de chargement des catégories: " . $e->getMessage();
}

// Display form
?>
    <form method="POST" class="bg-white p-6 rounded-lg shadow-lg" id="addAccessoryForm">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800">Ajouter un Accessoire</h2>
            <button type="button" onclick="cancelAdd()" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="messageContainer"></div>

        <div class="grid grid-cols-2 gap-6">
            <!-- Designation -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Désignation *</label>
                <input type="text" name="designation" required
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <!-- Category -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Catégorie</label>
                <select name="categorie_id" 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">Sélectionner une catégorie</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= $category['Categorie_id'] ?>">
                            <?= htmlspecialchars($category['designation']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Weights -->
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Poids avec carotte (kg)</label>
                <input type="number" step="0.01" name="poids_avec_carotte"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Poids sans carotte (kg)</label>
                <input type="number" step="0.01" name="poids_sans_carotte"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
        </div>

        <div class="flex justify-end space-x-4 mt-6">
            <button type="button" onclick="cancelAdd()"
                class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Annuler
            </button>
            <button type="submit" 
                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                Créer l'accessoire
            </button>
        </div>
    </form>

    <script>
    document.getElementById('addAccessoryForm')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        fetch('home.php?section=Parametrage&item=accessories/add_accessory', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('Raw response:', text);
                throw new Error('Invalid JSON response');
            }
        })
        .then(data => {
            if (data.success) {
                window.showMessage(data.message);
                setTimeout(() => window.location.reload(), 2000);
            } else {
                window.showMessage(data.error || 'Une erreur est survenue', true);
            }
        })
        .catch(error => {
            window.showMessage('Erreur: ' + error.message, true);
            console.error('Error:', error);
        });
    });
    </script>
<?php
