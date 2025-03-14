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
    try {
        // Validate inputs
        $requiredFields = ['first_name', 'last_name', 'login', 'email', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ '$field' est requis");
            }
        }

        // Hash password
        $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

        // Insert user
        $stmt = $conn->prepare("INSERT INTO rs_users (first_name, last_name, login, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['first_name'], $_POST['last_name'], $_POST['login'], $_POST['email'], $hashedPassword]);

        $response['success'] = true;
        $response['message'] = "Utilisateur créé avec succès";
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
    }

    // Always return JSON for AJAX requests
    header('Content-Type: application/json');
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
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Ajouter un Utilisateur</h1>

        <form method="POST" class="bg-white p-6 rounded shadow-md" id="addUserForm">
            <?php if ($response['error']): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 alert-message">
                    <?= htmlspecialchars($response['error']) ?>
                </div>
            <?php endif; ?>

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