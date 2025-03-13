<?php

include 'db_connect.php';

// Check admin privileges
if (!isset($_SESSION['is_admin'])) {
    header('Location: index.php?error=Unauthorized+access');
    exit();
}

// Initialize variables
$error = null;
$success = null;
$firstName = $_POST['first_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';
$login = $_POST['login'] ?? '';
$email = $_POST['email'] ?? '';
$groups = $_POST['groups'] ?? [];
$formSubmitted = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formSubmitted = true;
    try {
        // Validation
        if (empty($_POST['password'])) {
            throw new Exception("Password is required");
        }

        // Check if email already exists
        $checkEmail = $conn->prepare("SELECT COUNT(*) FROM rs_users WHERE email = ?");
        $checkEmail->execute([$email]);
        if ($checkEmail->fetchColumn() > 0) {
            throw new Exception("Error: Email already in use");
        }

        // Check if login already exists
        $checkLogin = $conn->prepare("SELECT COUNT(*) FROM rs_users WHERE login = ?");
        $checkLogin->execute([$login]);
        if ($checkLogin->fetchColumn() > 0) {
            throw new Exception("Error: Login already in use");
        }

        // If we get here, we can create the user
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Insert user
            $stmt = $conn->prepare("INSERT INTO rs_users 
                (first_name, last_name, login, email, password) 
                VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$firstName, $lastName, $login, $email, $password]);
            
            $userId = $conn->lastInsertId();

            // Assign groups
            foreach ($groups as $groupId) {
                $stmt = $conn->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)");
                $stmt->execute([$userId, $groupId]);
            }

            $conn->commit();
            // Only redirect on successful creation
            header("Location: ?section=users&item=liste_users");
            exit();
        } catch (Exception $e) {
            $conn->rollBack();
            throw $e;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all groups
$allGroups = $conn->query("SELECT * FROM rs_groups")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">User Management</h1>
        
        <?php if ($success): ?>
            <div class="bg-green-100 p-3 mb-4 rounded"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded shadow-md" autocomplete="off">
            <div class="grid grid-cols-2 gap-4">
                <div class="mb-4">
                    <label class="block mb-2">First Name</label>
                    <input type="text" name="first_name" required 
                        class="w-full p-2 border rounded"
                        value="<?= htmlspecialchars($firstName) ?>">
                </div>
                
                <div class="mb-4">
                    <label class="block mb-2">Last Name</label>
                    <input type="text" name="last_name" required 
                        class="w-full p-2 border rounded"
                        value="<?= htmlspecialchars($lastName) ?>">
                </div>
            </div>

            <div class="mb-4">
                <label class="block mb-2">Login</label>
                <input type="text" name="login" required 
                    class="w-full p-2 border rounded"
                    value="<?= htmlspecialchars($login) ?>">
            </div>

            <div class="mb-4">
                <label class="block mb-2">Email</label>
                <input type="email" name="email" required 
                    class="w-full p-2 border rounded"
                    value="<?= htmlspecialchars($email) ?>">
            </div>

            <div class="mb-4">
                <label class="block mb-2">Password</label>
                <input type="password" name="password" required 
                    class="w-full p-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block mb-2">Groups</label>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($allGroups as $group): ?>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="groups[]" 
                                value="<?= $group['group_id'] ?>"
                                <?= in_array($group['group_id'], $groups) ? 'checked' : '' ?>>
                            <span class="ml-2"><?= $group['group_name'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" 
                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Create User
            </button>
        </form>
    </div>
</body>
</html>