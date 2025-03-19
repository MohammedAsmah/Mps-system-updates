<?php
// Start session without output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Prevent accidental output
ob_start();

// Include database connection
require 'db_connect.php';

// Handle AJAX request (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    // Clear any output buffers
    ob_clean();
    
    // Set JSON header
    header('Content-Type: application/json');
    
    // Initialize response
    $response = ['success' => false, 'error' => null];
    
    try {
        // Validate session and permissions
        if (!isset($_SESSION['user_id'])) {
            throw new Exception("Session expirée, veuillez vous reconnecter");
        }

        // Validate group ID
        $groupId = (int)($_POST['id'] ?? 0);
        if ($groupId < 1) {
            throw new Exception("ID de groupe invalide");
        }

        // Verify permissions
        $stmt = $conn->prepare("
            SELECT u.is_admin, u.is_superadmin, MAX(g.is_supergroup) as is_supergroup 
            FROM rs_users u
            LEFT JOIN user_groups ug ON u.user_id = ug.user_id
            LEFT JOIN rs_groups g ON ug.group_id = g.group_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!($user['is_admin'] || $user['is_superadmin'] || $user['is_supergroup'])) {
            throw new Exception("Accès non autorisé");
        }

        // Validate form data
        if (empty($_POST['group_name'])) {
            throw new Exception("Le nom du groupe est obligatoire");
        }

        // Begin transaction
        $conn->beginTransaction();

        // Update group details
        $stmt = $conn->prepare("
            UPDATE rs_groups 
            SET group_name = ?, description = ?, is_supergroup = ?
            WHERE group_id = ?
        ");
        $stmt->execute([
            trim($_POST['group_name']),
            trim($_POST['description'] ?? ''),
            isset($_POST['is_supergroup']) ? 1 : 0,
            $groupId
        ]);

        // Clear existing permissions IN CORRECT ORDER
        $conn->exec("DELETE FROM group_elements WHERE group_id = $groupId");
        $conn->exec("DELETE FROM group_menus WHERE group_id = $groupId");

        // Insert new permissions if any
        if (!empty($_POST['menus']) && is_array($_POST['menus'])) {
            $menuStmt = $conn->prepare("INSERT INTO group_menus (group_id, menu_id) VALUES (?, ?)");
            $elementStmt = $conn->prepare("INSERT INTO group_elements (group_id, menu_id, element_id) VALUES (?, ?, ?)");

            foreach ($_POST['menus'] as $menuId => $menuData) {
                $menuId = (int)$menuId;
                if ($menuId < 1) continue;

                if (isset($menuData['menu'])) {
                    // Insert menu access
                    $menuStmt->execute([$groupId, $menuId]);

                    // Insert elements if selected
                    if (!empty($menuData['elements']) && is_array($menuData['elements'])) {
                        foreach ($menuData['elements'] as $elementId) {
                            $elementId = (int)$elementId;
                            if ($elementId > 0) {
                                $elementStmt->execute([$groupId, $menuId, $elementId]);
                            }
                        }
                    }
                }
            }
        }

        // Commit transaction
        $conn->commit();

        // Success response
        $response['success'] = true;
        $response['message'] = "Groupe mis à jour avec succès";

    } catch (Exception $e) {
        // Rollback on error
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // Error response
        $response['error'] = $e->getMessage();
    }

    // Send JSON response
    echo json_encode($response);
    exit();
}

// Handle GET request (form display)
if (isset($_GET['partial']) && $_GET['partial'] == '1') {
    // Clean buffer before HTML output
    ob_end_clean();
    
    try {
        $groupId = (int)($_GET['id'] ?? 0);
        if ($groupId < 1) {
            throw new Exception("ID de groupe invalide");
        }

        // Fetch group data
        $group = $conn->query("SELECT * FROM rs_groups WHERE group_id = $groupId")->fetch(PDO::FETCH_ASSOC);
        if (!$group) {
            throw new Exception("Groupe non trouvé");
        }

        // Fetch permissions
        $selectedMenus = $conn->query("SELECT menu_id FROM group_menus WHERE group_id = $groupId")
                             ->fetchAll(PDO::FETCH_COLUMN);
        
        $selectedElements = $conn->query("
            SELECT CONCAT(menu_id, '-', element_id) 
            FROM group_elements 
            WHERE group_id = $groupId
        ")->fetchAll(PDO::FETCH_COLUMN);

        // Fetch menu structure
        $menus = $conn->query("
            SELECT m.*, 
            GROUP_CONCAT(e.element_id ORDER BY e.element_id) AS element_ids,
            GROUP_CONCAT(e.element_name ORDER BY e.element_id) AS element_names
            FROM rs_menus m
            LEFT JOIN menu_elements e ON m.menu_id = e.menu_id
            GROUP BY m.menu_id
            ORDER BY m.display_order
        ")->fetchAll(PDO::FETCH_ASSOC);

        // Display form
        ?>
        <div class="container mx-auto p-6">
    <form method="POST" class="bg-white p-6 rounded shadow-md">
        <input type="hidden" name="id" value="<?= $group['group_id'] ?>">
        
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
            ><?= htmlspecialchars($group['description'] ?? '') ?></textarea>
        </div>

        <div class="mb-4">
            <label class="flex items-center space-x-2">
                <input type="checkbox" name="is_supergroup" 
                    <?= $group['is_supergroup'] ? 'checked' : '' ?>
                    class="form-checkbox h-4 w-4 text-blue-600">
                <span>Supergroupe</span>
            </label>
        </div>

        <div class="mb-4">
            <h3 class="text-lg font-semibold mb-3">Permissions</h3>
            <div class="space-y-4">
                <?php foreach ($menus as $menu): 
                    $elements = array_combine(
                        explode(',', $menu['element_ids']), 
                        explode(',', $menu['element_names'])
                    );
                ?>
                <div class="border rounded p-4">
                    <label class="flex items-center font-medium mb-2">
                        <input type="checkbox" 
                            name="menus[<?= $menu['menu_id'] ?>][menu]"
                            class="menu-checkbox h-4 w-4 text-blue-600 mr-2"
                            <?= in_array($menu['menu_id'], $selectedMenus) ? 'checked' : '' ?>
                            onchange="toggleElements(this, <?= $menu['menu_id'] ?>)">
                        <?= htmlspecialchars($menu['menu_name']) ?>
                    </label>
                    
                    <?php if (!empty($elements)): ?>
                        <div class="ml-6 grid grid-cols-2 gap-2 elements-container" 
                            id="elements-<?= $menu['menu_id'] ?>">
                            <?php foreach ($elements as $elementId => $elementName): 
                                $elementKey = $menu['menu_id'] . '-' . $elementId;
                            ?>
                                <label class="flex items-center space-x-2">
                                    <input type="checkbox" 
                                        name="menus[<?= $menu['menu_id'] ?>][elements][]"
                                        value="<?= $elementId ?>"
                                        class="element-checkbox h-4 w-4 text-blue-600"
                                        <?= in_array($elementKey, $selectedElements) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($elementName) ?></span>
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
                Mettre à jour
            </button>
        </div>
    </form>
</div>
        <?php

    } catch (Exception $e) {
        echo "<div class='text-red-500'>Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    exit();
}

// Invalid request handler
ob_end_clean();
header('HTTP/1.1 400 Bad Request');
echo json_encode(['success' => false, 'error' => 'Requête invalide']);
exit();