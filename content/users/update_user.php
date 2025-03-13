<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
include 'db_connect.php';

// Check admin privileges
if (isset($_SESSION['is_admin'])===0) {
    die("Unauthorized access");
}

// Get user ID from URL
$userId = $_GET['id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    die("Invalid user ID");
}

// Initialize variables
$error = null;
$success = null;
$user = null;
$userGroups = [];

// Fetch user data
try {
    $stmt = $conn->prepare("SELECT * FROM rs_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        die("User not found");
    }

    // Fetch user's groups
    $stmt = $conn->prepare("SELECT group_id FROM user_groups WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $groups = $_POST['groups'] ?? [];

    try {
        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new Exception("All fields are required");
        }

        // Check email uniqueness (excluding current user)
        $stmt = $conn->prepare("SELECT COUNT(*) FROM rs_users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $userId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Email already in use");
        }

        // Begin transaction
        $conn->beginTransaction();

        // Update user
        if (!empty($password)) {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE rs_users SET 
                                  first_name = ?, last_name = ?, email = ?, password = ?
                                  WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $email, $passwordHash, $userId]);
        } else {
            $stmt = $conn->prepare("UPDATE rs_users SET 
                                  first_name = ?, last_name = ?, email = ?
                                  WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $email, $userId]);
        }

        // Update groups
        $conn->prepare("DELETE FROM user_groups WHERE user_id = ?")->execute([$userId]);
        foreach ($groups as $groupId) {
            $conn->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)")
                 ->execute([$userId, $groupId]);
        }

        $conn->commit();
        $success = "User updated successfully";
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// Fetch all groups
try {
    $allGroups = $conn->query("SELECT * FROM rs_groups")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error loading groups: " . $e->getMessage());
}

// If this is an AJAX request, only output the form
if (isset($_GET['partial'])) {
    ?>
    <div class="container mx-auto p-6">
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <form id="updateUserForm" method="POST" class="bg-white p-6 rounded shadow-md" onsubmit="handleUpdateSubmit(event)">
            <div class="grid grid-cols-2 gap-4">
                <div class="mb-4">
                    <label class="block mb-2">First Name</label>
                    <input type="text" name="first_name" required
                           value="<?= htmlspecialchars($user['first_name']) ?>"
                           class="w-full p-2 border rounded">
                </div>
                
                <div class="mb-4">
                    <label class="block mb-2">Last Name</label>
                    <input type="text" name="last_name" required
                           value="<?= htmlspecialchars($user['last_name']) ?>"
                           class="w-full p-2 border rounded">
                </div>
            </div>

            <div class="mb-4">
                <label class="block mb-2">Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($user['email']) ?>"
                       class="w-full p-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block mb-2">Password (leave empty to keep current)</label>
                <input type="password" name="password"
                       class="w-full p-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block mb-2">Groups</label>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($allGroups as $group): ?>
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="groups[]" 
                                   value="<?= $group['group_id'] ?>"
                                   <?= in_array($group['group_id'], $userGroups) ? 'checked' : '' ?>>
                            <span class="ml-2"><?= htmlspecialchars($group['group_name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex justify-end space-x-4">
                <button type="button" onclick="cancelEdit()" 
                        class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                    Cancel
                </button>
                <button type="submit" 
                        class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    Update User
                </button>
            </div>
        </form>
    </div>

    <script>
    function handleUpdateSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            if (html.includes('User updated successfully')) {
                // Refresh the main users table
                loadContent('content/users/users.php');
                // Close the modal or edit form
                cancelEdit();
            } else {
                // If there's an error, update the form content
                document.querySelector('.container').innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating the user');
        });
    }
    </script>
    <?php
    exit();
}
?>