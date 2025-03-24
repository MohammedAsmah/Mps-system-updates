<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Update the include path to use the correct location
require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_updated_version/db_connect.php';

// Add connection check
if (!isset($conn)) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

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
        // Validate seller ID
        if (!isset($_POST['seller_id']) || empty($_POST['seller_id'])) {
            throw new Exception("ID de vendeur manquant");
        }
        
        $seller_id = (int)$_POST['seller_id'];
        
        // Validate required fields
        $required = ['designation', 'commission', 'plafond'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("Le champ $field est obligatoire");
            }
        }

        $conn->beginTransaction();

        // Check if seller exists
        $check = $conn->prepare("SELECT COUNT(*) FROM Sellers WHERE Seller_id = ?");
        $check->execute([$seller_id]);
        if ($check->fetchColumn() == 0) {
            throw new Exception("Vendeur non trouvé");
        }

        // Check for duplicate designation excluding current seller
        $checkDuplicate = $conn->prepare("
            SELECT COUNT(*) 
            FROM Sellers 
            WHERE designation = ? 
            AND Seller_id != ?
        ");
        $checkDuplicate->execute([
            trim($_POST['designation']),
            $seller_id
        ]);
        
        if ($checkDuplicate->fetchColumn() > 0) {
            throw new Exception("Un vendeur avec cette désignation existe déjà");
        }

        // Update the seller
        $stmt = $conn->prepare("
            UPDATE Sellers 
            SET designation = :designation,
                commission = :commission,
                plafond = :plafond
            WHERE Seller_id = :seller_id
        ");
        
        $result = $stmt->execute([
            ':designation' => trim($_POST['designation']),
            ':commission' => (float)$_POST['commission'],
            ':plafond' => (float)$_POST['plafond'],
            ':seller_id' => $seller_id
        ]);

        if (!$result) {
            throw new Exception("Échec de la mise à jour");
        }

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Vendeur mis à jour avec succès']);
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
        $seller_id = (int)$_GET['id'];
        if ($seller_id < 1) {
            throw new Exception("ID invalide");
        }

        // Fetch seller data
        $seller = $conn->query("
            SELECT * 
            FROM Sellers 
            WHERE Seller_id = $seller_id
        ")->fetch(PDO::FETCH_ASSOC);

        if (!$seller) {
            throw new Exception("Vendeur non trouvé");
        }

        // Output form
        ?>
        <div class="bg-white p-8 rounded-xl shadow-lg">
            <div class="flex items-center mb-8 pb-4 border-b border-gray-200">
                <div class="bg-blue-100 p-3 rounded-full mr-4">
                    <i class="fas fa-user-edit text-blue-600 text-xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Modifier le Vendeur</h3>
            </div>

            <form method="POST" id="editSellerForm" class="max-w-4xl mx-auto">
                <input type="hidden" name="seller_id" value="<?= $seller['Seller_id'] ?>">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-tag mr-2 text-blue-500"></i>Désignation *
                        </label>
                        <input type="text" name="designation" required
                            class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-400 focus:outline-none transition-colors"
                            value="<?= htmlspecialchars($seller['designation']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-percentage mr-2 text-green-500"></i>Commission (%) *
                        </label>
                        <input type="number" step="0.01" name="commission" required
                            class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-green-400 focus:outline-none transition-colors"
                            value="<?= htmlspecialchars($seller['commission']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">
                            <i class="fas fa-coins mr-2 text-yellow-500"></i>Plafond (Dh) *
                        </label>
                        <input type="number" step="0.01" name="plafond" required
                            class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-yellow-400 focus:outline-none transition-colors"
                            value="<?= htmlspecialchars($seller['plafond']) ?>">
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-8 pt-4 border-t border-gray-200">
                    <button type="button" onclick="cancelEdit()"
                        class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-colors duration-200">
                        <i class="fas fa-times mr-2"></i>Annuler
                    </button>
                    <button type="submit"
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors duration-200">
                        <i class="fas fa-save mr-2"></i>Mettre à jour
                    </button>
                </div>
            </form>
        </div>
        <?php

    } catch (Exception $e) {
        echo "<div class='text-red-500 p-4'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    exit();
}

// Invalid request
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Requête invalide']);