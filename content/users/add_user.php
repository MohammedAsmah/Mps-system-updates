<?php
// Turn off error display for users
error_reporting(0);
ini_set('display_errors', 0);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// Force JSON header at the very top
header('Content-Type: application/json');
// Initialize response array
$response = [
    'success' => false,
    'error' => null,
    'fields' => [
        'first_name' => '',
        'last_name' => '',
        'login' => '',
        'email' => '',
        'groups' => []
    ]
];

// Fetch all groups from the database
try {
    $allGroups = $conn->query("SELECT * FROM rs_groups")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $allGroups = []; // Set to empty array if query fails
    $response['error'] = "Erreur lors du chargement des groupes: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reset any previous messages
    $response['success'] = false;
    $response['error'] = null;
    $response['message'] = '';
    try {
        // Validate inputs
        $requiredFields = ['first_name', 'last_name', 'login', 'email', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ '".ucfirst(str_replace('_', ' ', $field))."' est obligatoire.");
            }
        }

        // Check if login already exists
        $checkLogin = $conn->prepare("SELECT COUNT(*) FROM rs_users WHERE login = ?");
        $checkLogin->execute([$_POST['login']]);
        if ($checkLogin->fetchColumn() > 0) {
            throw new Exception("Ce login est déjà utilisé. Veuillez en choisir un autre.");
        }

        // Hash password
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO rs_users (first_name, last_name, login, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['login'], $_POST['email'], $hashedPassword]);

        if ($stmt->rowCount() > 0) {
            $userId = $conn->lastInsertId();
            
            // Process groups
            if (!empty($_POST['groups']) && is_array($_POST['groups'])) {
                $groupStmt = $conn->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)");
                foreach ($_POST['groups'] as $groupId) {
                    $groupStmt->execute([$userId, $groupId]);
                }
            }
            
            $response['success'] = true;
            $response['message'] = "L'utilisateur a été créé avec succès!";
            $response['error'] = null; // Clear any existing error message
        }
    } catch (PDOException $e) {
        $response['success'] = false;
        $response['error'] = "Une erreur est survenue lors de la création de l'utilisateur. Veuillez réessayer.";
        if ($e->getCode() == 23000) { // Duplicate entry error
            $response['error'] = "Cette adresse email est déjà utilisée. Veuillez en utiliser une autre.";
        }
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    // Return JSON response immediately
    echo json_encode($response);
    exit();
}

// If not a POST request, or if not an AJAX request, display the form
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Utilisateur</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
            input[type="checkbox"] {
                cursor: pointer;} </style>
<body class="bg-gray-100">
   

        <div class="bg-white p-6 rounded-lg shadow-md">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Ajouter un Utilisateur</h2>
                <button onclick="cancelAdd()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Add message container -->
            <div id="messageContainer"></div>

            <form id="addUserForm" class="space-y-4" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Prénom</label>
                        <input type="text" name="first_name" required
                            class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                            value="<?= htmlspecialchars($response['fields']['first_name']) ?>">
                    </div>
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Nom</label>
                        <input type="text" name="last_name" required
                            class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                            value="<?= htmlspecialchars($response['fields']['last_name']) ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Login</label>
                    <input type="text" name="login" required
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        value="<?= htmlspecialchars($response['fields']['login']) ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Email</label>
                    <input type="email" name="email" required
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                        value="<?= htmlspecialchars($response['fields']['email']) ?>">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Mot de passe</label>
                    <input type="password" name="password" required
                        class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>

                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">Groupes</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <?php if (!empty($allGroups)): ?>
                            <?php foreach ($allGroups as $group): ?>
                                <label class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded">
                                    <input type="checkbox" name="groups[]" 
                                        value="<?= $group['group_id'] ?>"
                                        <?= in_array($group['group_id'], $response['fields']['groups']) ? 'checked' : '' ?>
                                        class="form-checkbox h-4 w-4 text-blue-600">
                                    <span><?= htmlspecialchars($group['group_name']) ?></span>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-red-500">Aucun groupe disponible</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" onclick="window.cancelAdd()"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                        Annuler
                    </button>
                    <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                        Créer l'utilisateur
                    </button>
                </div>
            </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const addUserForm = document.getElementById('addUserForm');
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

        addUserForm?.addEventListener('submit', function(e) {
            e.preventDefault();
            messageContainer.innerHTML = '';
            const formData = new FormData(this);

            fetch('home.php?section=users&item=add_user', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFormMessage(data.message || 'Utilisateur créé avec succès');
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
    // Always return JSON for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>