<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_updated_version/db_connect.php';

// Add connection check
if (!isset($conn)) {
    die("Database connection failed");
}

try {
    // Fetch all banks
    $sql = "SELECT * FROM banks ORDER BY bank_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $banks = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Loading Error: " . $e->getMessage();
}

// Handle bank actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            if (isset($_POST['bank_id'])) {
                try {
                    $stmt = $conn->prepare("DELETE FROM banks WHERE banque_id = ?");
                    $stmt->execute([$_POST['bank_id']]);
                    $_SESSION['success_message'] = "Banque supprimée avec succès";
                    unset($_SESSION['error_message']);
                } catch (PDOException $e) {
                    $_SESSION['error_message'] = "Erreur de suppression: " . $e->getMessage();
                    unset($_SESSION['success_message']);
                }
            }
            break;
    }
}
?>

<div class="container mx-auto px-6 py-8 bg-gray-50">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="errorMessage" class="message-fade bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-r mb-4" style="opacity: 1">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="successMessage" class="message-fade bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-r mb-4" style="opacity: 1">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div id="mainContent">
        <div class="flex justify-between items-center mb-8">
            <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                <span class="bg-blue-600 text-white p-2 rounded-lg mr-3">
                    <i class="fas fa-university"></i>
                </span>
                Liste des Banques
            </h2>
            <button id="toggleAddBankForm" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-xl flex items-center">
                <i class="fas fa-plus mr-2"></i> Nouvelle Banque
            </button>
        </div>

        <!-- Content containers -->
        <div id="mainContainer">
            <div id="bankTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
                <?php if (count($banks) > 0): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                                        Nom de la Banque
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                                        Ligne OC
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                                        Ligne Aval
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($banks as $bank): ?>
                                <tr class="hover:bg-blue-50 transition-colors duration-200">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-blue-100 rounded-full">
                                                <i class="fas fa-university text-blue-600"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($bank['bank_name']) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            <?= number_format($bank['line_oc'], 2) ?> Dh
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            <?= number_format($bank['line_aval'], 2) ?> Dh
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center space-x-3">
                                            <button onclick="loadEditBankForm(<?= $bank['banque_id'] ?>)" 
                                                    class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteBank(<?= $bank['banque_id'] ?>)" 
                                                    class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center text-gray-500">
                        <div class="bg-gray-100 rounded-full h-20 w-20 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-university text-4xl text-gray-400"></i>
                        </div>
                        <p class="text-lg">Aucune banque trouvée</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="addBankFormContainer" style="display: none;">
                <?php include __DIR__ . '/add_bank.php'; ?>
            </div>
            
            <div id="editBankFormContainer" style="display: none;"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Message handling
    const hideMessages = () => {
        const messages = document.querySelectorAll('#successMessage, #errorMessage');
        messages.forEach(msg => {
            if (msg) {
                setTimeout(() => {
                    msg.style.transition = 'opacity 2s';
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 2000);
                }, 10000);
            }
        });
    };

    hideMessages();

    window.showMessage = function(message, isError = false) {
        document.querySelectorAll('#successMessage, #errorMessage').forEach(msg => msg.remove());
        
        const messageDiv = document.createElement('div');
        messageDiv.id = isError ? 'errorMessage' : 'successMessage';
        messageDiv.className = isError 
            ? 'bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-r mb-4'
            : 'bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-r mb-4';
        messageDiv.style.opacity = '1';
        messageDiv.innerHTML = message;
        
        const container = document.querySelector('.container');
        container.insertBefore(messageDiv, container.firstChild);
    };

    const toggleAddBankFormButton = document.getElementById('toggleAddBankForm');
    const addBankFormContainer = document.getElementById('addBankFormContainer');
    const editBankFormContainer = document.getElementById('editBankFormContainer');
    const bankTableContainer = document.getElementById('bankTableContainer');
    
    // Reset all containers to default state
    function resetContainers() {
        addBankFormContainer.style.display = 'none';
        editBankFormContainer.style.display = 'none';
        bankTableContainer.style.display = 'block';
        
        // Reset button text and colors
        const toggleButton = document.getElementById('toggleAddBankForm');
        toggleButton.innerHTML = '<i class="fas fa-plus mr-2"></i>Nouvelle Banque';
        toggleButton.classList.remove('bg-gray-600', 'hover:bg-gray-700');
        toggleButton.classList.add('bg-blue-600', 'hover:bg-blue-700');
    }

    // Form toggle handler
    if (toggleAddBankFormButton) {
        toggleAddBankFormButton.addEventListener('click', function() {
            if (addBankFormContainer.style.display === 'none') {
                bankTableContainer.style.display = 'none';
                editBankFormContainer.style.display = 'none';
                addBankFormContainer.style.display = 'block';
                this.innerHTML = '<i class="fas fa-times mr-2"></i>Annuler';
                this.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                this.classList.add('bg-gray-600', 'hover:bg-gray-700');
            } else {
                resetContainers();
                this.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                this.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }
        });
    }

    window.cancelAdd = function() {
        resetContainers();
    };

    window.loadEditBankForm = function(bankId) {
        const url = `content/Parametrage/banks/update_bank.php?partial=1&id=${bankId}`;
        fetch(url)
            .then(response => response.text())
            .then(html => {
                editBankFormContainer.style.display = 'block';
                editBankFormContainer.innerHTML = html;
                bankTableContainer.style.display = 'none';
                addBankFormContainer.style.display = 'none';

                // Update form submission handler
                const editForm = editBankFormContainer.querySelector('form');
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        
                        fetch('content/Parametrage/banks/update_bank.php', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showMessage(data.message);
                                cancelEdit();
                                setTimeout(() => location.reload(), 1000);
                            } else {
                                showMessage(data.message || 'Erreur lors de la mise à jour', true);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessage('Erreur lors de la mise à jour', true);
                        });
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Error loading form: ' + error.message, true);
            });
    };

    window.cancelEdit = function() {
        resetContainers();
    };

    // ...rest of existing code...
});

function deleteBank(bankId) {
    if (confirm('Are you sure you want to delete this bank?')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('bank_id', bankId);

        fetch(window.location.href, {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const errorMessage = doc.querySelector('#errorMessage');
            const successMessage = doc.querySelector('#successMessage');
            
            if (errorMessage) {
                showMessage(errorMessage.textContent, true);
            } else if (successMessage) {
                showMessage(successMessage.textContent);
            }
            
            setTimeout(() => location.reload(), 2000);
        })
        .catch(error => {
            showMessage('Erreur lors de la suppression: ' + error, true);
            setTimeout(() => location.reload(), 2000);
        });
    }
}
</script>
