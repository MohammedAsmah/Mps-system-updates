<?php

header('Content-Type: application/json');
session_start();
include 'db_connect.php';

// Initialize variables
$error = null;
$success = null;
$group = null;
$groupMenus = [];
$allMenus = [];

try {
    // Validate group ID
    $groupId = $_GET['id'] ?? null;
    if (!$groupId || !is_numeric($groupId)) {
        throw new Exception("ID de groupe invalide");
    }

    // Fetch group data
    $stmt = $conn->prepare("SELECT * FROM rs_groups WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$group) {
        throw new Exception("Groupe non trouvé");
    }

    // Get existing permissions
    $groupMenus = $conn->query("
        SELECT gm.menu_id, 
        GROUP_CONCAT(ge.element_id) AS elements
        FROM group_menus gm
        LEFT JOIN group_elements ge ON gm.group_id = ge.group_id AND gm.menu_id = ge.menu_id
        WHERE gm.group_id = $groupId
        GROUP BY gm.menu_id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Get all menus with elements
    $allMenus = $conn->query("
        SELECT m.*, 
        GROUP_CONCAT(e.element_id) AS element_ids
        FROM rs_menus m
        LEFT JOIN menu_elements e ON m.menu_id = e.menu_id
        GROUP BY m.menu_id
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Erreur base de données: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $groupId = $_POST['id'];
        $groupName = trim($_POST['group_name']);
        $description = trim($_POST['description']);
        $isSupergroup = isset($_POST['is_supergroup']) ? 1 : 0;
        $menus = $_POST['menus'] ?? [];

        // Validate inputs
        if (empty($groupName)) {
            throw new Exception("Le nom du groupe est obligatoire");
        }

        // Start transaction
        $conn->beginTransaction();

        // Update group
        $stmt = $conn->prepare("
            UPDATE rs_groups 
            SET group_name = ?, description = ?, is_supergroup = ?
            WHERE group_id = ?
        ");
        $stmt->execute([$groupName, $description, $isSupergroup, $groupId]);

        // Update permissions
        $conn->prepare("DELETE FROM group_menus WHERE group_id = ?")->execute([$groupId]);
        $conn->prepare("DELETE FROM group_elements WHERE group_id = ?")->execute([$groupId]);

        if (!empty($menus)) {
            $menuStmt = $conn->prepare("INSERT INTO group_menus (group_id, menu_id) VALUES (?, ?)");
            $elementStmt = $conn->prepare("INSERT INTO group_elements (group_id, element_id, menu_id) VALUES (?, ?, ?)");
            
            foreach ($menus as $menuId => $elements) {
                if (!is_numeric($menuId)) continue;
                
                $menuStmt->execute([$groupId, $menuId]);
                
                if (is_array($elements)) {
                    foreach ($elements as $elementId) {
                        if (!is_numeric($elementId)) continue;
                        $elementStmt->execute([$groupId, $elementId, $menuId]);
                    }
                }
            }
        }

        $conn->commit();
        ob_end_clean();
        echo json_encode(['success' => true, 'message' => 'Groupe mis à jour avec succès']);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        ob_end_clean(); // Clear buffered output
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
        exit();
    }
}

// Display form
if (isset($_GET['partial'])) {
    ob_end_clean();
?>
<div class="container mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">Modifier Groupe</h1>

    <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" class="bg-white p-6 rounded shadow-md">
        <input type="hidden" name="id" value="<?= htmlspecialchars($groupId) ?>">

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Nom du groupe</label>
            <input type="text" name="group_name" required
                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                value="<?= htmlspecialchars($group['group_name']) ?>">
        </div>

        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
            <textarea name="description"
                class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
            ><?= htmlspecialchars($group['description']) ?></textarea>
        </div>

        <div class="mb-4">
            <label class="flex items-center space-x-2">
                <input type="checkbox" name="is_supergroup"
                    class="form-checkbox h-4 w-4 text-blue-600"
                    <?= $group['is_supergroup'] ? 'checked' : '' ?>>
                <span>Supergroupe</span>
            </label>
        </div>

        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-3">Permissions</h3>
            <div class="space-y-4">
                <?php foreach ($allMenus as $menu): 
                    $hasAccess = isset($groupMenus[$menu['menu_id']]);
                ?>
                    <div class="border rounded p-4">
                        <label class="flex items-center font-medium mb-2">
                            <input type="checkbox" 
                                name="menus[<?= $menu['menu_id'] ?>][]"
                                class="menu-checkbox h-4 w-4 text-blue-600 mr-2"
                                <?= $hasAccess ? 'checked' : '' ?>
                                onchange="toggleElements(this, <?= $menu['menu_id'] ?>)">
                            <?= htmlspecialchars($menu['menu_name']) ?>
                        </label>
                        
                        <?php if (!empty($menu['element_ids'])): 
                            $elements = explode(',', $menu['element_ids']);
                            $selectedElements = $hasAccess ? explode(',', $groupMenus[$menu['menu_id']]) : [];
                        ?>
                            <div class="ml-6 grid grid-cols-2 gap-2 elements-container" 
                                id="elements-<?= $menu['menu_id'] ?>" 
                                style="display: <?= $hasAccess ? 'block' : 'none' ?>;">
                                <?php foreach ($elements as $elementId): 
                                    $element = $conn->query("
                                        SELECT * FROM menu_elements 
                                        WHERE element_id = $elementId
                                    ")->fetch(PDO::FETCH_ASSOC);
                                ?>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" 
                                            name="menus[<?= $menu['menu_id'] ?>][]"
                                            value="<?= $elementId ?>"
                                            class="element-checkbox h-4 w-4 text-blue-600"
                                            <?= in_array($elementId, $selectedElements) ? 'checked' : '' ?>
                                            <?= !$hasAccess ? 'disabled' : '' ?>>
                                        <span><?= htmlspecialchars($element['element_name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="flex justify-end space-x-4 mt-6">
            <button type="button" onclick="cancelEdit()"
                class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                Annuler
            </button>
            <button type="submit" 
                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                Enregistrer
            </button>
        </div>
    </form>
</div>

<script>
function toggleElements(checkbox, menuId) {
    const elementsContainer = document.getElementById(`elements-${menuId}`);
    if (elementsContainer) {
        elementsContainer.style.display = checkbox.checked ? 'block' : 'none';
        elementsContainer.querySelectorAll('.element-checkbox').forEach(el => {
            el.disabled = !checkbox.checked;
            if (!checkbox.checked) el.checked = false;
        });
    }
}
</script>
<?php
exit();
}
ob_end_flush();
?>