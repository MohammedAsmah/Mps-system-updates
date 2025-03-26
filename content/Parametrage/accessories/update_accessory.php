<?php
// update_accessory.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

// Check admin permissions
if (!isset($_SESSION['is_admin'])) {
    die(json_encode(['success' => false, 'error' => 'Accès non autorisé']));
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        // Validate accessory ID
        if (!isset($_POST['accessoire_id']) || empty($_POST['accessoire_id'])) {
            throw new Exception("ID d'accessoire manquant");
        }
        
        $accessoire_id = (int)$_POST['accessoire_id'];
        
        // Validate required fields
        $required = ['designation', 'poids_avec_carotte', 'poids_sans_carotte'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("Le champ $field est obligatoire");
            }
        }

        $conn->beginTransaction();

        // Check if accessory exists
        $check = $conn->prepare("SELECT COUNT(*) FROM Accessoires WHERE Accessoire_id = ?");
        $check->execute([$accessoire_id]);
        if ($check->fetchColumn() == 0) {
            throw new Exception("Accessoire non trouvé");
        }

        // Check for duplicate designation excluding current accessory
        $checkDuplicate = $conn->prepare("
            SELECT COUNT(*) 
            FROM Accessoires 
            WHERE designation = ? 
            AND Accessoire_id != ?
        ");
        $checkDuplicate->execute([
            trim($_POST['designation']),
            $accessoire_id
        ]);
        
        if ($checkDuplicate->fetchColumn() > 0) {
            throw new Exception("Un accessoire avec cette désignation existe déjà");
        }

        // Update the accessory
        $stmt = $conn->prepare("
            UPDATE Accessoires 
            SET designation = :designation,
                poids_avec_carotte = :poids_avec_carotte,
                poids_sans_carotte = :poids_sans_carotte,
                categorie_id = :categorie_id
            WHERE Accessoire_id = :accessoire_id
        ");
        
        $result = $stmt->execute([
            ':designation' => trim($_POST['designation']),
            ':poids_avec_carotte' => (float)$_POST['poids_avec_carotte'],
            ':poids_sans_carotte' => (float)$_POST['poids_sans_carotte'],
            ':categorie_id' => !empty($_POST['categorie_id']) ? (int)$_POST['categorie_id'] : null,
            ':accessoire_id' => $accessoire_id
        ]);

        if (!$result) {
            throw new Exception("Échec de la mise à jour");
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Accessoire mis à jour avec succès']);
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle GET request (form display)
if (isset($_GET['partial']) && $_GET['partial'] == '1') {
    try {
        $accessoire_id = (int)$_GET['id'];
        if ($accessoire_id < 1) {
            throw new Exception("ID invalide");
        }

        // Fetch accessory data
        $accessoire = $conn->query("
            SELECT * 
            FROM Accessoires 
            WHERE Accessoire_id = $accessoire_id
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$accessoire) {
            throw new Exception("Accessoire non trouvé");
        }

        // Fetch categories
        $categories = $conn->query("
            SELECT * 
            FROM Categories 
            ORDER BY designation
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Output form
        ?>
        <form method="POST" id="editAccessoryForm" action="home.php?section=Parametrage&item=accessories/update_accessory">
            <input type="hidden" name="accessoire_id" value="<?= $accessoire['Accessoire_id'] ?>">
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Désignation *</label>
                    <input type="text" name="designation" required
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        value="<?= htmlspecialchars($accessoire['designation']) ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Catégorie</label>
                    <select name="categorie_id" 
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="">Non catégorisé</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['Categorie_id'] ?>"
                                <?= $cat['Categorie_id'] == $accessoire['categorie_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Poids avec carotte (kg) *</label>
                    <input type="number" step="0.01" name="poids_avec_carotte" required
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        value="<?= htmlspecialchars($accessoire['poids_avec_carotte']) ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Poids sans carotte (kg) *</label>
                    <input type="number" step="0.01" name="poids_sans_carotte" required
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        value="<?= htmlspecialchars($accessoire['poids_sans_carotte']) ?>">
                </div>
            </div>

            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="cancelEdit()"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                    Annuler
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    Mettre à jour
                </button>
            </div>
        </form>
        <?php

    } catch (Exception $e) {
        echo "<div class='text-red-500 p-4'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit();
}

// Invalid request
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Requête invalide']);