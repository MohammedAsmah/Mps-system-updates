<?php

// Place these at the very top
error_reporting(0);
ini_set('display_errors', 0);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db_connect.php';

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
    isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    ob_clean(); // Clear any output buffers
    header('Content-Type: application/json');
    
    try {
        if (empty($_POST['group_name'])) {
            throw new Exception("Le nom du groupe est obligatoire");
        }

        $conn->beginTransaction();
        
        // Check duplicate group name
        $checkGroup = $conn->prepare("SELECT COUNT(*) FROM rs_groups WHERE group_name = ?");
        $checkGroup->execute([$_POST['group_name']]);
        if ($checkGroup->fetchColumn() > 0) {
            throw new Exception("Ce nom de groupe existe déjà");
        }

        // Insert group
        $stmt = $conn->prepare("INSERT INTO rs_groups (group_name, description, is_supergroup) VALUES (?, ?, ?)");
        $stmt->execute([
            $_POST['group_name'],
            $_POST['description'] ?? null,
            isset($_POST['is_supergroup']) ? 1 : 0
        ]);
        $groupId = $conn->lastInsertId();

        // Process menu access
        if (!empty($_POST['menus']) && is_array($_POST['menus'])) {
            $menuStmt = $conn->prepare("INSERT INTO group_menus (group_id, menu_id) VALUES (?, ?)");
            $elementStmt = $conn->prepare("INSERT INTO group_elements (group_id, element_id, menu_id) VALUES (?, ?, ?)");
            
            foreach ($_POST['menus'] as $menuId => $elements) {
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
        echo json_encode(['success' => true, 'message' => "Groupe créé avec succès"]);
    } catch (Exception $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}

// Initialize response
$response = [
    'success' => false,
    'error' => null,
    'fields' => [
        'group_name' => '',
        'description' => '',
        'menus' => []
    ]
];

// Fetch all menus and elements
try {
    $menus = $conn->query("
        SELECT m.*, 
        GROUP_CONCAT(e.element_id ORDER BY e.element_id) AS element_ids
        FROM rs_menus m
        LEFT JOIN menu_elements e ON m.menu_id = e.menu_id
        GROUP BY m.menu_id
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $response['error'] = "Erreur de chargement des menus: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response['success'] = false;
    $response['error'] = null;

    try {
        // Validate inputs
        if (empty($_POST['group_name'])) {
            throw new Exception("Le nom du groupe est obligatoire");
        }

        // Check duplicate group name
        $checkGroup = $conn->prepare("SELECT COUNT(*) FROM rs_groups WHERE group_name = ?");
        $checkGroup->execute([$_POST['group_name']]);
        if ($checkGroup->fetchColumn() > 0) {
            throw new Exception("Ce nom de groupe existe déjà");
        }

        // Start transaction
        $conn->beginTransaction();

        // Insert group
        $stmt = $conn->prepare("
            INSERT INTO rs_groups (group_name, description, is_supergroup)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $_POST['group_name'],
            $_POST['description'] ?? null,
            isset($_POST['is_supergroup']) ? 1 : 0
        ]);
        $groupId = $conn->lastInsertId();

        // Process menu access
        if (!empty($_POST['menus']) && is_array($_POST['menus'])) {
            $menuStmt = $conn->prepare("INSERT INTO group_menus (group_id, menu_id) VALUES (?, ?)");
            $elementStmt = $conn->prepare("INSERT INTO group_elements (group_id, element_id, menu_id) VALUES (?, ?, ?)");
            
            foreach ($_POST['menus'] as $menuId => $elements) {
                if (!is_numeric($menuId)) continue;
                
                // Add menu access
                $menuStmt->execute([$groupId, $menuId]);
                
                // Add elements if any
                if (is_array($elements)) {
                    foreach ($elements as $elementId) {
                        if (!is_numeric($elementId)) continue;
                        $elementStmt->execute([$groupId, $elementId, $menuId]);
                    }
                }
            }
        }

        $conn->commit();
        $response['success'] = true;
        $response['message'] = "Groupe créé avec succès";

    } catch (PDOException $e) {
        $conn->rollBack();
        $response['error'] = "Erreur base de données: " . $e->getMessage();
    } catch (Exception $e) {
        $conn->rollBack();
        $response['error'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Display form if not AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest')  {
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau Groupe</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Nouveau Groupe</h1>

        <form method="POST" class="bg-white p-6 rounded shadow-md" id="addGroupForm">
            <?php if ($response['success']): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($response['message']) ?>
                </div>
            <?php elseif ($response['error']): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($response['error']) ?>
                </div>
            <?php endif; ?>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Nom du groupe</label>
                <input type="text" name="group_name" required
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                    value="<?= htmlspecialchars($response['fields']['group_name']) ?>">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                <textarea name="description"
                    class="w-full px-3 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-blue-400"
                ><?= htmlspecialchars($response['fields']['description']) ?></textarea>
            </div>

            <div class="mb-4">
                <label class="flex items-center space-x-2">
                    <input type="checkbox" name="is_supergroup"
                        class="form-checkbox h-4 w-4 text-blue-600">
                    <span>Supergroupe (accès complet)</span>
                </label>
            </div>

            <div class="mb-4">
                <h3 class="text-lg font-semibold mb-3">Permissions</h3>
                <div class="space-y-4">
                    <?php foreach ($menus as $menu): ?>
                        <div class="border rounded p-4">
                            <label class="flex items-center font-medium mb-2">
                                <input type="checkbox" name="menus[<?= $menu['menu_id'] ?>][]"
                                    class="menu-checkbox h-4 w-4 text-blue-600 mr-2"
                                    onchange="toggleElements(this, <?= $menu['menu_id'] ?>)">
                                <?= htmlspecialchars($menu['menu_name']) ?>
                            </label>
                            
                            <?php if (!empty($menu['element_ids'])): ?>
                                <div class="ml-6 grid grid-cols-2 gap-2 elements-container" 
                                    id="elements-<?= $menu['menu_id'] ?>" style="display: none;">
                                    <?php 
                                    $elements = $conn->query("
                                        SELECT * FROM menu_elements 
                                        WHERE menu_id = " . $menu['menu_id']
                                    )->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($elements as $element): ?>
                                        <label class="flex items-center space-x-2">
                                            <input type="checkbox" 
                                                name="menus[<?= $menu['menu_id'] ?>][]"
                                                value="<?= $element['element_id'] ?>"
                                                class="element-checkbox h-4 w-4 text-blue-600">
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
                <button type="button" onclick="window.cancelAdd()"
                    class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium">
                    Annuler
                </button>
                <button type="submit" 
                    class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium">
                    Créer le groupe
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
            });
        }
    }
    </script>
</body>
</html>
<?php }
 else {
    // Ensure no HTML output for AJAX requests
    echo json_encode($response);
    exit();
} ?>