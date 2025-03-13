<?php
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include 'db_connect.php';

    if (!$conn) {
        header("Location: index.php?error=Database connection error");
        exit();
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        $sql = "SELECT user_id, login, password, is_locked FROM rs_users WHERE login = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists and password is correct
        if ($user && (password_verify($password, $user['password']) || $user['password'] === $password)) {
            // Check if user is locked
            if ($user['is_locked']) {
                header("Location: index.php?error=This+account+is+locked.+Please+contact+an+administrator.");
                exit();
            }

            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['login'];
            session_start();

            // After verifying credentials
            if ($user['is_admin']) {
                $_SESSION['is_admin'] = true;
            } else {
                $_SESSION['is_admin'] = false; // Or don't set it at all
            }
            header("Location: home.php");
            exit();
        } else {
            header("Location: index.php?error=Invalid+username+or+password");
            exit();
        }
    } catch (Exception $e) {
        header("Location: index.php?error=" . urlencode("Error: " . $e->getMessage()));
        exit();
    }

    $conn = null;
} else {
    header("Location: index.php?error=Invalid+request");
    exit();
}
?>