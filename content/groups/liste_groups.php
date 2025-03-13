<?php

include 'db_connect.php';

// Check admin privileges
if (!isset($_SESSION['is_admin'])) {
    header('Location: index.php?error=Unauthorized+access');
    exit();
}

// Get all menus and elements
$menus = $conn->query("
    SELECT m.*, e.element_id, e.element_name 
    FROM rs_menus m
    LEFT JOIN menu_elements e ON m.menu_id = e.menu_id
    ORDER BY m.display_order, e.element_id
")->fetchAll(PDO::FETCH_GROUP);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupName = $_POST['group_name'];
    $description = $_POST['description'];
    $permissions = $_POST['permissions'] ?? [];

    try {
        // Create group
        $stmt = $conn->prepare("INSERT INTO rs_groups 
            (group_name, description) VALUES (?, ?)");
        $stmt->execute([$groupName, $description]);
        $groupId = $conn->lastInsertId();

        // Save permissions
        foreach ($permissions as $permission) {
            // Permission format: menu-X or element-Y
            $parts = explode('-', $permission);
            
            if ($parts[0] === 'menu') {
                $stmt = $conn->prepare("INSERT INTO group_menus 
                    (group_id, menu_id) VALUES (?, ?)");
                $stmt->execute([$groupId, $parts[1]]);
            }
            elseif ($parts[0] === 'element') {
                $stmt = $conn->prepare("INSERT INTO group_elements 
                    (group_id, element_id) VALUES (?, ?)");
                $stmt->execute([$groupId, $parts[1]]);
            }
        }

        $success = "Group created successfully!";
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Group Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Group Management</h1>

        <?php if (isset($success)): ?>
            <div class="bg-green-100 p-3 mb-4 rounded"><?= $success ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="bg-red-100 p-3 mb-4 rounded"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="bg-white p-6 rounded shadow-md">
            <div class="mb-4">
                <label class="block mb-2">Group Name</label>
                <input type="text" name="group_name" required 
                    class="w-full p-2 border rounded">
            </div>

            <div class="mb-4">
                <label class="block mb-2">Description</label>
                <textarea name="description" 
                    class="w-full p-2 border rounded"></textarea>
            </div>

            <div class="mb-4">
                <label class="block mb-2">Permissions</label>
                <div class="space-y-4">
                    <?php foreach ($menus as $menuId => $elements): 
                        $menu = $elements[0]; ?>
                        <div class="border p-4 rounded">
                            <label class="font-bold">
                                <input type="checkbox" 
                                    class="menu-checkbox"
                                    data-menu="<?= $menuId ?>">
                                <?= $menu['menu_name'] ?>
                            </label>
                            
                            <div class="ml-6 mt-2 space-y-2">
                                <?php foreach ($elements as $element): ?>
                                    <?php if ($element['element_id']): ?>
                                        <div class="flex items-center">
                                            <input type="checkbox" 
                                                name="permissions[]"
                                                value="element-<?= $element['element_id'] ?>" 
                                                class="element-checkbox element-<?= $menuId ?>">
                                            <span class="ml-2">
                                                <?= $element['element_name'] ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" 
                class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                Save Group
            </button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle menu checkbox selection
            document.querySelectorAll('.menu-checkbox').forEach(checkbox => {
                const menuId = checkbox.dataset.menu;
                checkbox.addEventListener('change', (e) => {
                    const elements = document.querySelectorAll(`.element-${menuId}`);
                    elements.forEach(el => {
                        el.checked = e.target.checked;
                        el.disabled = e.target.checked;
                    });
                });
            });
        });
    </script>
</body>
</html>