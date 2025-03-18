<?php
// Start session and include DB connection
session_start();
include 'db_connect.php';

// Check permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=Please+log+in');
    exit();
}

// Get fresh admin status from database
$stmt = $conn->prepare("
    SELECT u.is_admin, u.is_superadmin, MAX(g.is_supergroup) as is_supergroup 
    FROM rs_users u
    LEFT JOIN user_groups ug ON u.user_id = ug.user_id
    LEFT JOIN rs_groups g ON ug.group_id = g.group_id
    WHERE u.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$is_admin = $user['is_admin'] || $user['is_superadmin'] || $user['is_supergroup'];

if (!$is_admin) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['error' => 'Accès non autorisé']));
}

// Handle AJAX partial request
$isPartial = isset($_GET['partial']) && $_GET['partial'] == '1';

// Get group ID
$groupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Fetch group data
    $stmt = $conn->prepare("SELECT * FROM rs_groups WHERE group_id = ?");
    $stmt->execute([$groupId]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        header('HTTP/1.1 404 Not Found');
        die("Groupe non trouvé");
    }

    // Fetch associated menus and elements
    $selectedMenus = $conn->query("SELECT menu_id FROM group_menus WHERE group_id = $groupId")
                         ->fetchAll(PDO::FETCH_COLUMN);
    
    $selectedElements = $conn->query("
        SELECT element_id FROM group_elements 
        WHERE group_id = $groupId
    ")->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(['error' => 'Erreur base de données: ' . $e->getMessage()]));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false];
    
    try {
        // Validate inputs
        if (empty($_POST['group_name'])) {
            throw new Exception("Le nom du groupe est obligatoire");
        }

        // Update group
        $stmt = $conn->prepare("
            UPDATE rs_groups 
            SET group_name = ?, description = ?, is_supergroup = ?
            WHERE group_id = ?
        ");
        $stmt->execute([
            $_POST['group_name'],
            $_POST['description'] ?? null,
            isset($_POST['is_supergroup']) ? 1 : 0,
            $groupId
        ]);

 // Update menu access
$conn->beginTransaction();

// Clear existing permissions in correct order
$conn->exec("DELETE FROM group_elements WHERE group_id = $groupId");
$conn->exec("DELETE FROM group_menus WHERE group_id = $groupId");

// Insert new permissions
if (!empty($_POST['menus'])) {
    $menuStmt = $conn->prepare("INSERT INTO group_menus (group_id, menu_id) VALUES (?, ?)");
    $elementStmt = $conn->prepare("INSERT INTO group_elements (group_id, element_id) VALUES (?, ?)");
    
    foreach ($_POST['menus'] as $menuId => $menuData) {
        if (!is_numeric($menuId)) continue;
        
        // Only process if menu checkbox is checked
        if (isset($menuData['menu'])) {
            // Insert menu
            $menuStmt->execute([$groupId, $menuId]);
            
            // Insert elements if any
            if (!empty($menuData['elements'])) {
                foreach ($menuData['elements'] as $elementId) {
                    if (is_numeric($elementId)) {
                        $elementStmt->execute([$groupId, $elementId]);
                    }
                }
            }
        }
    }
}

$conn->commit();
        $response['success'] = true;
        $response['message'] = "Groupe mis à jour avec succès";
        echo json_encode($response);
        exit();

    } catch (Exception $e) {
        $conn->rollBack();
        $response['error'] = $e->getMessage();
        echo json_encode($response);
        exit();
    }
}

// If partial request, return form HTML
if ($isPartial) {
    // Updated query to fetch ALL menus and elements
    $menus = $conn->query("
        SELECT m.*, 
               GROUP_CONCAT(DISTINCT e.element_id) as element_ids,
               GROUP_CONCAT(DISTINCT e.element_name) as element_names
        FROM rs_menus m
        LEFT JOIN menu_elements e ON m.menu_id = e.menu_id
        GROUP BY m.menu_id
        ORDER BY m.menu_name
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Get currently selected menus and elements for this group
    $selectedMenus = $conn->query("
        SELECT menu_id FROM group_menus WHERE group_id = $groupId
    ")->fetchAll(PDO::FETCH_COLUMN);

    // Get currently selected elements grouped by menu_id
$selectedElements = [];
$stmt = $conn->query("SELECT menu_id, element_id FROM group_elements WHERE group_id = $groupId");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $menuId = $row['menu_id'];
    $elementId = $row['element_id'];
    if (!isset($selectedElements[$menuId])) {
        $selectedElements[$menuId] = [];
    }
    $selectedElements[$menuId][] = $elementId;
}
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            input[type="checkbox"] {
                cursor: pointer;}
.element-disabled {
    opacity: 0.5;
    pointer-events: none;
    cursor: not-allowed;
}
</style>
    </head>
    <body class="bg-gray-100">
        <div class="container mx-auto p-6">
            <h2 class="text-xl font-bold mb-4">Modifier le Groupe</h2>
            
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
                        <?php foreach ($menus as $menu): ?>
                            <div class="border rounded p-4">
                                <label class="flex items-center font-medium mb-2">
                                    <input type="checkbox" 
                                        name="menus[<?= $menu['menu_id'] ?>][menu]"
                                        class="menu-checkbox h-4 w-4 text-blue-600 mr-2"
                                        <?= in_array($menu['menu_id'], $selectedMenus) ? 'checked' : '' ?>
                                        onchange="toggleElements(this, <?= $menu['menu_id'] ?>)">
                                    <?= htmlspecialchars($menu['menu_name']) ?>
                                </label>
                                
                                <?php if (!empty($menu['element_ids'])): 
                                    $elementIds = explode(',', $menu['element_ids']);
                                    $elementNames = explode(',', $menu['element_names']);
                                ?>
                                    <div class="ml-6 grid grid-cols-2 gap-2 elements-container" 
                                        id="elements-<?= $menu['menu_id'] ?>">
                                        <?php foreach ($elementIds as $index => $elementId): ?>
    <label class="flex items-center space-x-2">
        <input type="checkbox" 
            name="menus[<?= $menu['menu_id'] ?>][elements][]"
            value="<?= $elementId ?>"
            class="element-checkbox h-4 w-4 text-blue-600"
            <?= (isset($selectedElements[$menu['menu_id']]) && in_array($elementId, $selectedElements[$menu['menu_id']])) ? 'checked' : '' ?>>
        <span><?= htmlspecialchars($elementNames[$index]) ?></span>
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

        <script>
        function toggleElements(checkbox, menuId) {
    const elementsContainer = document.getElementById(`elements-${menuId}`);
    if (elementsContainer) {
        const checkboxes = elementsContainer.querySelectorAll('.element-checkbox');
        
        // Only update the disabled state, don't change checked state
        checkboxes.forEach(el => {
            if (checkbox.checked) {
                // Enable elements when menu is checked
                el.disabled = false;
                el.parentElement.classList.remove('element-disabled');
            } else {
                // Disable and uncheck elements when menu is unchecked
                el.disabled = true;
                el.checked = false;
                el.parentElement.classList.add('element-disabled');
            }
        });
    }
}

// Initialize all menu checkboxes on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.menu-checkbox').forEach(checkbox => {
        const menuId = checkbox.name.match(/\[(\d+)\]/)[1];
        toggleElements(checkbox, menuId);
    });
});

        // Initialize all menu checkboxes on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.menu-checkbox').forEach(checkbox => {
                // Set initial state of element checkboxes
                toggleElements(checkbox, checkbox.closest('div').querySelector('.elements-container').id.split('-')[1]);
            });
        });
        </script>
    </body>
    </html>
    <?php
    exit();
}