<?php

session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMS - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <!-- Top Header -->
    <header class="bg-blue-600 h-20 text-white flex items-center justify-center shadow-md">
        <h1 class="text-3xl font-bold">PMS</h1>
    </header>
    
    <div class="flex items-center justify-center min-h-screen">
        <div class="bg-white p-8 rounded shadow-md w-full max-w-md mt-8">
            <h2 class="text-2xl font-bold mb-6 text-center">Login</h2>
            <?php if (isset($_GET['error'])): ?>
                <div class="mb-4 text-red-500 text-center">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <div class="mb-4">
                    <label for="username" class="block text-gray-700">Username</label>
                    <input type="text" name="username" id="username" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
                </div>
                <div class="mb-6">
                    <label for="password" class="block text-gray-700">Password</label>
                    <input type="password" name="password" id="password" class="w-full px-3 py-2 border rounded focus:outline-none focus:ring focus:border-blue-300" required>
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition duration-200">
                    Log In
                </button>
            </form>
        </div>
    </div>
</body>
</html>
