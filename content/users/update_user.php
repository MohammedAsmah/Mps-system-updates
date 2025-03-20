<?php
include 'db_connect.php';

// Initialize variables
$error = null;
$success = null;
$user = null;
$userGroups = [];
$allGroups = [];

try {
    // =================================================================
    // 1. VALIDATE USER ID
    // =================================================================
    $userId = $_GET['id'] ?? null;
    if (!$userId || !is_numeric($userId)) {
        throw new Exception("Invalid user ID");
    }

    // =================================================================
    // 2. FETCH EXISTING DATA
    // =================================================================
    // Get user data
    $stmt = $conn->prepare("SELECT * FROM rs_users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }

    // Get user's current groups
    $stmt = $conn->prepare("SELECT group_id FROM user_groups WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get all available groups
    $stmt = $conn->query("SELECT * FROM rs_groups");
    $allGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// =================================================================
// 3. HANDLE FORM SUBMISSION
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $groups = $_POST['groups'] ?? [];
        $userId = $_POST['id'];

        // Validate inputs
        if (empty($firstName) || empty($lastName) || empty($email)) {
            throw new Exception("All required fields must be filled");
        }

        // Start transaction
        $conn->beginTransaction();

        // =================================================================
        // 4. UPDATE USER DETAILS
        // =================================================================
        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE rs_users SET 
                                  first_name = ?, last_name = ?, email = ?, password = ?
                                  WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $email, $hashedPassword, $userId]);
        } else {
            $stmt = $conn->prepare("UPDATE rs_users SET 
                                  first_name = ?, last_name = ?, email = ?
                                  WHERE user_id = ?");
            $stmt->execute([$firstName, $lastName, $email, $userId]);
        }

        // =================================================================
        // 5. UPDATE USER GROUPS
        // =================================================================
        // Clear existing groups
        $conn->prepare("DELETE FROM user_groups WHERE user_id = ?")->execute([$userId]);

        // Insert new groups with validation
        foreach ($groups as $groupId) {
            if (!is_numeric($groupId)) continue;
            
            // Verify group exists
            $checkStmt = $conn->prepare("SELECT group_id FROM rs_groups WHERE group_id = ?");
            $checkStmt->execute([$groupId]);
            
            if ($checkStmt->fetch()) {
                $conn->prepare("INSERT INTO user_groups (user_id, group_id) VALUES (?, ?)")
                     ->execute([$userId, $groupId]);
            }
        }

        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => 'User updated successfully']);
exit();

    } catch (PDOException $e) {
        $conn->rollBack();
        $error = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $error = $e->getMessage();
    }
}

// =================================================================
// 6. DISPLAY FORM (AJAX/PARTIAL MODE)
// =================================================================
if (isset($_GET['partial'])) {
    ?>
    <style>
            input[type="checkbox"] {
                cursor: pointer;} </style>
    <div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Modifier utilisateur</h1>
        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?= htmlspecialchars($_SESSION['success_message']) ?>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded shadow-md">
            <input type="hidden" name="id" value="<?= htmlspecialchars($userId) ?>">
            
            <div class="grid grid-cols-2 gap-4">
                <div class="mb-4">
                    <label class="block mb-2 font-medium">First Name</label>
                    <input type="text" name="first_name" required
                           value="<?= htmlspecialchars($user['first_name']) ?>"
                           class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-400">
                </div>
                
                <div class="mb-4">
                    <label class="block mb-2 font-medium">Last Name</label>
                    <input type="text" name="last_name" required
                           value="<?= htmlspecialchars($user['last_name']) ?>"
                           class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-400">
                </div>
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-medium">Email</label>
                <input type="email" name="email" required
                       value="<?= htmlspecialchars($user['email']) ?>"
                       class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-400">
            </div>

            <div class="mb-4">
                <label class="block mb-2 font-medium">Password</label>
                <input type="password" name="password"
                       placeholder="Leave empty to keep current password"
                       class="w-full p-2 border rounded focus:ring-2 focus:ring-blue-400">
            </div>

            <?php if (!empty($allGroups)): ?>
                <div class="mb-4">
                    <label class="block mb-2 font-medium">Group Memberships</label>
                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($allGroups as $group): ?>
                            <label class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded">
                                <input type="checkbox" name="groups[]" 
                                       value="<?= $group['group_id'] ?>"
                                       <?= in_array($group['group_id'], $userGroups) ? 'checked' : '' ?>
                                       class="form-checkbox h-4 w-4 text-blue-600">
                                <span><?= htmlspecialchars($group['group_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="mb-4 text-yellow-600">No groups available in the system</div>
            <?php endif; ?>

            <div class="flex justify-end space-x-4 mt-6">
                <button type="button" onclick="cancelEdit()"
                        class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    Update User
                </button>
            </div>
        </form>
    </div>
    <?php
    exit();
}
?>