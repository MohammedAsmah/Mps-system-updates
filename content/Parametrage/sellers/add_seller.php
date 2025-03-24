<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_udated_version/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = ['designation', 'commission', 'plafond'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("Le champ $field est obligatoire");
            }
        }

        // Check for duplicate designation
        $stmt = $conn->prepare("SELECT COUNT(*) FROM Sellers WHERE designation = ?");
        $stmt->execute([trim($_POST['designation'])]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Un vendeur avec cette désignation existe déjà");
        }

        // Insert new seller
        $stmt = $conn->prepare("
            INSERT INTO Sellers (designation, commission, plafond) 
            VALUES (:designation, :commission, :plafond)
        ");
        
        $result = $stmt->execute([
            ':designation' => trim($_POST['designation']),
            ':commission' => (float)$_POST['commission'],
            ':plafond' => (float)$_POST['plafond']
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Vendeur ajouté avec succès']);
        } else {
            throw new Exception("Erreur lors de l'ajout du vendeur");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<form id="addSellerForm" method="POST" class="bg-white p-8 rounded-xl shadow-lg max-w-4xl mx-auto">
    <div class="flex items-center mb-8 pb-4 border-b border-gray-200">
        <div class="bg-blue-100 p-3 rounded-full mr-4">
            <i class="fas fa-user-plus text-blue-600 text-xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800">Ajouter un Vendeur</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                <i class="fas fa-tag mr-2 text-blue-500"></i>Désignation *
            </label>
            <input type="text" name="designation" required
                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-400 focus:outline-none transition-colors">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                <i class="fas fa-percentage mr-2 text-green-500"></i>Commission (%) *
            </label>
            <input type="number" step="0.01" name="commission" required
                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-green-400 focus:outline-none transition-colors">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                <i class="fas fa-coins mr-2 text-yellow-500"></i>Plafond (Dh) *
            </label>
            <input type="number" step="0.01" name="plafond" required
                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-yellow-400 focus:outline-none transition-colors">
        </div>
    </div>

    <div class="flex justify-end space-x-4 mt-8 pt-4 border-t border-gray-200">
        <button type="button" onclick="cancelAdd()"
            class="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-colors duration-200">
            <i class="fas fa-times mr-2"></i>Annuler
        </button>
        <button type="submit"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors duration-200">
            <i class="fas fa-plus mr-2"></i>Ajouter
        </button>
    </div>
</form>

<script>
document.getElementById('addSellerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('content/Parametrage/sellers/add_seller.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage(data.message);
            this.reset();
            cancelAdd();
            setTimeout(() => location.reload(), 1000);
        } else {
            showMessage(data.message, true);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Erreur lors de l\'ajout du vendeur', true);
    });
});
</script>
