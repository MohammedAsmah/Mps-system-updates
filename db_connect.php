<?php
$dsn = 'mysql:host=localhost;dbname=datamjpjaouda_updated';
$username = 'simo';
$password = 'simo';

try {
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}
?>
