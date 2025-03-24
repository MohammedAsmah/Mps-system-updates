<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_updated_version/db_connect.php';

// Handle AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs
        if (!isset($_POST['bank_id']) || !isset($_POST['bank_name']) || !isset($_POST['line_oc']) || !isset($_POST['line_aval'])) {
            throw new Exception("Tous les champs sont obligatoires");
        }

        // Check for duplicate name but exclude current bank
        $stmt = $conn->prepare("SELECT COUNT(*) FROM banks WHERE bank_name = ? AND banque_id != ?");
        $stmt->execute([trim($_POST['bank_name']), $_POST['bank_id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Une banque avec ce nom existe déjà");
        }

        // Update bank
        $stmt = $conn->prepare("
            UPDATE banks 
            SET bank_name = ?, line_oc = ?, line_aval = ? 
            WHERE banque_id = ?
        ");
        
        $result = $stmt->execute([
            trim($_POST['bank_name']),
            (float)$_POST['line_oc'],
            (float)$_POST['line_aval'],
            $_POST['bank_id']
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Banque mise à jour avec succès']);
        } else {
            throw new Exception("Erreur lors de la mise à jour de la banque");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Only show the form content if it's an AJAX request
if (isset($_GET['partial'])) {
    // Fetch bank data for display
    $bank_id = isset($_GET['id']) ? $_GET['id'] : null;
    if ($bank_id) {
        $stmt = $conn->prepare("SELECT * FROM banks WHERE banque_id = ?");
        $stmt->execute([$bank_id]);
        $bank = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$bank) {
        echo "<div class='text-red-600 p-4'>Banque non trouvée</div>";
        exit;
    }
?>
    <div class="bg-white p-8 rounded-xl shadow-lg">
        <div class="flex items-center mb-8 pb-4 border-b border-gray-200">
            <div class="bg-blue-100 p-3 rounded-full mr-4">
                <i class="fas fa-university text-blue-600 text-xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-gray-800">Modifier la Banque</h3>
        </div>

        <form id="updateBankForm" method="POST">
            <input type="hidden" name="bank_id" value="<?= htmlspecialchars($bank['banque_id']) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-building mr-2 text-blue-500"></i>Nom de la Banque *
                    </label>
                    <input type="text" name="bank_name" required
                        value="<?= htmlspecialchars($bank['bank_name']) ?>"
                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-400 focus:outline-none transition-colors">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-credit-card mr-2 text-green-500"></i>Ligne OC (Dh) *
                    </label>
                    <input type="number" step="0.01" min="0" name="line_oc" required
                        value="<?= htmlspecialchars($bank['line_oc']) ?>"
                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-green-400 focus:outline-none transition-colors">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">
                        <i class="fas fa-money-check mr-2 text-yellow-500"></i>Ligne Aval (Dh) *
                    </label>
                    <input type="number" step="0.01" min="0" name="line_aval" required
                        value="<?= htmlspecialchars($bank['line_aval']) ?>"
                        class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-yellow-400 focus:outline-none transition-colors">
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
}
?>
