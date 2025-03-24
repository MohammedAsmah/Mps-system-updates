<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_updated_version/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required = ['bank_name', 'line_oc', 'line_aval'];
        foreach ($required as $field) {
            if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                throw new Exception("Le champ $field est obligatoire");
            }
        }

        // Check for duplicate bank name
        $stmt = $conn->prepare("SELECT COUNT(*) FROM banks WHERE bank_name = ?");
        $stmt->execute([trim($_POST['bank_name'])]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Une banque avec ce nom existe déjà");
        }

        // Insert new bank
        $stmt = $conn->prepare("
            INSERT INTO banks (bank_name, line_oc, line_aval) 
            VALUES (:bank_name, :line_oc, :line_aval)
        ");
        
        $result = $stmt->execute([
            ':bank_name' => trim($_POST['bank_name']),
            ':line_oc' => (float)$_POST['line_oc'],
            ':line_aval' => (float)$_POST['line_aval']
        ]);

        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Banque ajoutée avec succès']);
        } else {
            throw new Exception("Erreur lors de l'ajout de la banque");
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}
?>

<form id="addBankForm" method="POST" class="bg-white p-8 rounded-xl shadow-lg">
    <div class="flex items-center mb-8 pb-4 border-b border-gray-200">
        <div class="bg-blue-100 p-3 rounded-full mr-4">
            <i class="fas fa-university text-blue-600 text-xl"></i>
        </div>
        <h3 class="text-2xl font-bold text-gray-800">Ajouter une Banque</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                <i class="fas fa-building mr-2 text-blue-500"></i>Nom de la Banque *
            </label>
            <input type="text" name="bank_name" required
                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-blue-400 focus:outline-none transition-colors">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                <i class="fas fa-credit-card mr-2 text-green-500"></i>Ligne OC (Dh) *
            </label>
            <input type="number" step="0.01" min="0" name="line_oc" required
                class="w-full px-4 py-3 rounded-lg border-2 border-gray-200 focus:border-green-400 focus:outline-none transition-colors">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">
                <i class="fas fa-money-check mr-2 text-yellow-500"></i>Ligne Aval (Dh) *
            </label>
            <input type="number" step="0.01" min="0" name="line_aval" required
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
document.getElementById('addBankForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('content/Parametrage/banks/add_bank.php', {
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
        showMessage('Erreur lors de l\'ajout de la banque', true);
    });
});
</script>
