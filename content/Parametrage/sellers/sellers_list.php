<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/mps_udated_version/db_connect.php';

// Add connection check
if (!isset($conn)) {
    die("Database connection failed");
}

try {
    // Fetch all sellers
    $sql = "SELECT * FROM Sellers ORDER BY designation";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $sellers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur de chargement: " . $e->getMessage();
}

// Handle seller actions
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'delete':
            if (isset($_POST['seller_id'])) {
                try {
                    $stmt = $conn->prepare("DELETE FROM Sellers WHERE Seller_id = ?");
                    $stmt->execute([$_POST['seller_id']]);
                    $_SESSION['success_message'] = "Vendeur supprimé avec succès";
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

    <div class="flex justify-between items-center mb-8">
        <h2 class="text-3xl font-bold text-gray-800 flex items-center">
            <span class="bg-blue-600 text-white p-2 rounded-lg mr-3">
                <i class="fas fa-users"></i>
            </span>
            Liste des Vendeurs
        </h2>
        <button id="toggleAddSellerForm" 
            class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg shadow-lg transition duration-300 ease-in-out transform hover:-translate-y-1 hover:shadow-xl flex items-center">
            <i class="fas fa-plus mr-2"></i> Nouveau Vendeur
        </button>
    </div>

    <div id="sellerTableContainer" class="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-200">
        <?php if (count($sellers) > 0): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                            Désignation
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                            Commission
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                            Plafond
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider bg-gray-100">
                            Actions
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($sellers as $seller): ?>
                    <tr class="hover:bg-blue-50 transition-colors duration-200">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10 flex items-center justify-center bg-blue-100 rounded-full">
                                    <i class="fas fa-user text-blue-600"></i>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($seller['designation']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                <?= number_format($seller['commission'], 2) ?> %
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                <?= number_format($seller['plafond'], 2) ?> Dh
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center space-x-4">
                                <button onclick="loadEditSellerForm(<?= $seller['Seller_id'] ?>)" 
                                        class="text-blue-600 hover:text-blue-900 transition-colors duration-200 bg-blue-100 p-2 rounded-full hover:bg-blue-200">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button onclick="deleteSeller(<?= $seller['Seller_id'] ?>)" 
                                        class="text-red-600 hover:text-red-900 transition-colors duration-200 bg-red-100 p-2 rounded-full hover:bg-red-200">
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
                    <i class="fas fa-users text-4xl text-gray-400"></i>
                </div>
                <p class="text-lg">Aucun vendeur trouvé</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="addSellerFormContainer" style="display: none;">
    <?php include __DIR__ . '/add_seller.php'; ?>
</div>
<div id="editSellerFormContainer" style="display: none;"></div>

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

    const toggleAddSellerFormButton = document.getElementById('toggleAddSellerForm');
    const addSellerFormContainer = document.getElementById('addSellerFormContainer');
    const editSellerFormContainer = document.getElementById('editSellerFormContainer');
    const sellerTableContainer = document.getElementById('sellerTableContainer');

    // Form toggle handler
    if (toggleAddSellerFormButton) {
        toggleAddSellerFormButton.addEventListener('click', function() {
            addSellerFormContainer.style.display = 
                addSellerFormContainer.style.display === 'none' ? 'block' : 'none';
            sellerTableContainer.style.display = 
                addSellerFormContainer.style.display === 'none' ? 'block' : 'none';
            editSellerFormContainer.style.display = 'none';
        });
    }

    // Delete handler
    window.deleteSeller = function(sellerId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce vendeur ?')) {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('seller_id', sellerId);

            fetch(window.location.href, {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.text())
            .then(html => {
                // Parse the HTML response to check for error messages
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const errorMessage = doc.querySelector('#errorMessage');
                const successMessage = doc.querySelector('#successMessage');
                
                if (errorMessage) {
                    showMessage(errorMessage.textContent, true);
                } else if (successMessage) {
                    showMessage(successMessage.textContent);
                }
                
                // Reload the page after a delay
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                showMessage('Erreur lors de la suppression: ' + error, true);
                setTimeout(() => location.reload(), 2000);
            });
        }
    };

    // Edit handler
    window.loadEditSellerForm = function(sellerId) {
        const url = `content/Parametrage/sellers/update_seller.php?partial=1&id=${sellerId}`;
        fetch(url)
            .then(response => response.text())
            .then(html => {
                editSellerFormContainer.style.display = 'block';
                editSellerFormContainer.innerHTML = html;
                sellerTableContainer.style.display = 'none';
                addSellerFormContainer.style.display = 'none';

                // Update form submission handler
                const editForm = editSellerFormContainer.querySelector('form');
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const formData = new FormData(this);
                        
                        fetch('content/Parametrage/sellers/update_seller.php', {
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

    // Cancel handlers
    window.cancelAdd = function() {
        addSellerFormContainer.style.display = 'none';
        sellerTableContainer.style.display = 'block';
    };

    window.cancelEdit = function() {
        editSellerFormContainer.style.display = 'none';
        sellerTableContainer.style.display = 'block';
    };
});
</script>