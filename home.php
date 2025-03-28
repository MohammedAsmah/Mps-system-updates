<?php
// Handle add_user POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'add_user') {
    include __DIR__ . '/content/users/add_user.php';
    exit();
}
// Handle add_group POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'add_group') {
    include __DIR__ . '/content/groups/add_group.php';
    exit();
}
// Handle add_article equests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'add_article') {
    include __DIR__ .'/content/Parametrage/products/add_article.php';
    exit();
}
// Handle add_accessory equests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'add_accessory') {
    include __DIR__ .'/content/Parametrage/accessories/add_accessory.php';
    exit();
}

// Handle add_seller POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'add_seller') {
    include __DIR__ . '/content/Parametrage/sellers/add_seller.php';
    exit();
}

// Handle add_bank POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'add_bank') {
    include __DIR__ . '/content/Parametrage/banks/add_bank.php';
    exit();
}

// Handle update_accessory requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'update_accessory') {
    $file_path = __DIR__ . '/content/Parametrage/accessories/update_accessory.php';
    if (file_exists($file_path)) {
        include $file_path;
        exit();
    }
}
// Handle update_seller requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['item']) && $_GET['item'] === 'update_seller') {
    $file_path = __DIR__ . '/content/Parametrage/sellers/update_seller.php';
    if (file_exists($file_path)) {
        include $file_path;
        exit();
    }
}

// Handle update_group requests
if (isset($_GET['item']) && $_GET['item'] === 'update_group') {
    $file_path = __DIR__ . '/content/groups/update_group.php';
    
    if (file_exists($file_path)) {
        include $file_path;
    } else {
        http_response_code(404);
        die("Update group file not found");
    }
}

// Handle update_bank requests
if (isset($_GET['item']) && $_GET['item'] === 'update_bank') {
    $file_path = __DIR__ . '/content/Parametrage/banks/update_bank.php';
    
    if (file_exists($file_path)) {
        include $file_path;
    } else {
        http_response_code(404);
        die("Update bank file not found");
    }
}
// Handle article accessories requests
if (isset($_GET['item']) && $_GET['item'] === 'article_accessories') {
    $file_path = __DIR__ . '/content/Parametrage/products/article_accessories.php';
    if (file_exists($file_path)) {
        include $file_path;
    } else {
        http_response_code(404);
        die("article accessories file not found");
    }
} 
// Handle update_article requests
if (isset($_GET['item']) && $_GET['item'] === 'update_article') {
    $file_path = __DIR__ . '/content/Parametrage/products/update_article.php';
    
    if (file_exists($file_path)) {
        include $file_path;
    } else {
        http_response_code(404);
        die("Update article file not found");
    }
}
// Handle update_user requests immediately
if (isset($_GET['item']) && $_GET['item'] === 'update_user') {
    $file_path = __DIR__ . '/content/users/update_user.php';
    
    if (file_exists($file_path)) {
        include $file_path;
        exit();
    } else {
        http_response_code(404);
        die("Update file not found");
    }
}

// Handle accessories routes
if (isset($_GET['item']) && strpos($_GET['item'], 'accessories/') === 0) {
    $itemPath = substr($_GET['item'], strlen('accessories/'));
    $file_path = __DIR__ . '/content/Parametrage/accessories/' . $itemPath . '.php';
    
    if (file_exists($file_path)) {
        include $file_path;
        exit();
    }
}

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php?error=Please+log+in');
    exit();
}
include 'db_connect.php';

$user_login = $_SESSION['username'];
$user_id = $_SESSION['user_id'];

// Check if user account is still valid
$sql = "SELECT user_id, is_locked FROM rs_users WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user_status = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_status) {
    // User not found, log them out
    session_unset();
    session_destroy();
    header('Location: index.php?error=This+account+has+been+deleted.');
    exit();
}

if ($user_status['is_locked']) {
    // User is locked, log them out
    session_unset();
    session_destroy();
    header('Location: index.php?error=This+account+has+been+locked.+Please+contact+an+administrator.');
    exit();
}

// Get user information including admin status
$sql = "SELECT u.*, 
        MAX(g.is_supergroup) as is_supergroup
        FROM rs_users u
        LEFT JOIN user_groups ug ON u.user_id = ug.user_id
        LEFT JOIN rs_groups g ON ug.group_id = g.group_id
        WHERE u.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user is admin/superadmin
$is_admin = $user['is_admin'] || $user['is_superadmin'] || $user['is_supergroup'];

// Get all menus for admin, or group-based access for regular users
if ($is_admin) {
    // Admin gets all menus
    $stmt = $conn->query("SELECT menu_id, menu_name FROM rs_menus ORDER BY display_order");
    $navigation = [];
    while ($menu = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $navigation[$menu['menu_name']] = [
            'id' => $menu['menu_id'],
            'title' => $menu['menu_name']
        ];
    }

} else {
    // Regular user - get group-based menu access
    $sql = "SELECT DISTINCT m.menu_id, m.menu_name 
            FROM rs_menus m
            JOIN group_menus gm ON m.menu_id = gm.menu_id
            JOIN user_groups ug ON gm.group_id = ug.group_id
            WHERE ug.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$user_id]);
    
    $navigation = [];
    while ($menu = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $navigation[$menu['menu_name']] = [
            'id' => $menu['menu_id'],
            'title' => $menu['menu_name']
        ];
    }
}

// Get current section
$currentSection = isset($_GET['section']) && isset($navigation[$_GET['section']]) 
                ? $_GET['section'] 
                : (count($navigation) ? array_key_first($navigation) : null);

// Get sidebar elements
$sidebar = [];
if ($currentSection) {
    $menu_id = $navigation[$currentSection]['id'];
    
    if ($is_admin) {
        // Admin gets all elements in the menu
        $sql = "SELECT element_key, element_name, file_path FROM menu_elements WHERE menu_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$menu_id]);
    } else {
        // Regular user - get group-based element access
        $sql = "SELECT DISTINCT e.element_key, e.element_name, e.file_path 
                FROM menu_elements e
                JOIN group_elements ge ON e.element_id = ge.element_id
                JOIN user_groups ug ON ge.group_id = ug.group_id
                WHERE e.menu_id = ? AND ug.user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$menu_id, $user_id]);
    }
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $sidebar[$row['element_key']] = [
            'title' => $row['element_name'],
            'file_path' => $row['file_path'],
            'content' => null
        ];
    }
}

// Add currentItem definition after sidebar is populated
$currentItem = isset($_GET['item']) && isset($sidebar[$_GET['item']])
    ? $_GET['item']
    : (count($sidebar) ? array_key_first($sidebar) : null);
// After $currentItem is set
if ($currentItem === 'update_user') {
    $file_path = __DIR__ . '/content/users/update_user.php';
    
    if (file_exists($file_path)) {
        include $file_path;
        exit();
    } else {
        die("Update file not found: " . $file_path);
    }
}
// Define content based on currentItem
$content = '';
if ($currentItem && isset($sidebar[$currentItem])) {
    $file_path = $sidebar[$currentItem]['file_path'];
    if ($file_path && file_exists(__DIR__ . '/' . $file_path)) {
        ob_start();
        include __DIR__ . '/' . $file_path;
        $content = ob_get_clean();
    } else {
        $content = 'Content file not found';
    }
} else {
    $content = 'Welcome to the dashboard';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>data2mjp ui - Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-bg {
            background: linear-gradient(120deg, #1e40af, #3b82f6);
        }
        .hover-scale:hover {
            transform: scale(1.02);
            transition: transform 0.2s ease;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Top Navigation Bar -->
        <header class="gradient-bg h-24 text-white shadow-lg fixed top-0 left-0 right-0 z-10">
            <div class="mx-auto px-6">
                <div class="flex justify-between gap-5 items-center py-2">
                    <div class="flex gap-4 items-center">
                        <div class="text-xl font-bold bg-white/10 p-3 rounded-lg">
                            <?php echo ($user_login) ?> <br> <?php $_SESSION['is_admin']; echo date("y-m-d"); ?>
                        </div>
                        <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors duration-200 hover:shadow-md">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                    <nav class="flex-1 ml-8">
                        <ul class="flex space-x-4 flex-wrap justify-end">
                            <?php foreach ($navigation as $key => $item): ?>
                                <li class="my-2">
                                    <a href="?section=<?php echo $key; ?>" 
                                       class="px-4 py-2 rounded-lg hover:bg-white/20 transition-all duration-200
                                              <?php echo $currentSection === $key ? 'bg-white/25 shadow-md' : ''; ?>">
                                        <?php echo $item['title']; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </header>

        <div class="flex pt-24">
            <!-- Sidebar -->
            <aside class="bg-gray-800 text-white w-64 fixed left-0 h-full pt-4 shadow-xl z-10">
                <nav class="px-4 py-2">
                    <h2 class="text-lg font-semibold mb-6 text-blue-300 border-b border-blue-500/30 pb-2">
                        <?php echo $currentSection; ?> Menu
                    </h2>
                    <ul class="space-y-2">
                        <?php foreach ($sidebar as $key => $item): ?>
                            <li>
                                <a href="?section=<?php echo $currentSection; ?>&item=<?php echo $key; ?>" 
                                   class="block px-4 py-2.5 rounded-lg transition-all duration-200 hover-scale
                                          <?php echo $currentItem === $key ? 'bg-blue-600 shadow-md' : 'hover:bg-gray-700'; ?>">
                                    <?php echo $item['title']; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </aside>
            <!-- Main Content -->
            <main class="flex-1 ml-64 p-8 overflow-y-auto">
                <div class="bg-white rounded-xl shadow-sm p-8 hover:shadow-md transition-shadow duration-200">
                    <h1 class="text-2xl font-bold mb-6 text-gray-800 border-b pb-4">
                        <?php echo $currentSection ? $currentSection : 'Dashboard'; ?>
                    </h1>
                    <div class="content">
                        <?php 
                        if ($currentItem && isset($sidebar[$currentItem])) {
                            $file_path = $sidebar[$currentItem]['file_path'];
                            $file_path = trim($file_path, "'\" /\\");                            
                            
$possible_paths = [
    __DIR__ . '/' . $file_path,
    __DIR__ . '/content/users/' . $file_path, // Explicit users path
    __DIR__ . '/pages/' . $file_path,
    __DIR__ . '/content/' . $file_path,
    __DIR__ . '/content/users/update_user.php' // Direct path for AJAX
];

                            $found = false;
                            foreach ($possible_paths as $path) {
                                $clean_path = str_replace(['\\', '//'], '/', $path);
                                if (file_exists($clean_path)) {
                                    include $clean_path;
                                    $found = true;
                                    break;
                                }
                            }

                            if (!$found) {
                                echo 'Content file not found. Checked paths:<br>';
                                foreach ($possible_paths as $path) {
                                    echo htmlspecialchars(str_replace(['\\', '//'], '/', $path)) . '<br>';
                                }
                            }
                        } else {
                            echo 'Welcome to the dashboard';
                        }
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
        });
    </script>
</body>
</html>