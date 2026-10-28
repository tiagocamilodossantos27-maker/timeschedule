<?php
session_start();
require_once 'config/conexion.php';

// Si el usuario estaba logueado, limpiamos su session_id en la base de datos
if (isset($_SESSION['usuario_id'])) {
    $stmt = $pdo->prepare("UPDATE usuarios SET session_id = NULL WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
}

session_unset();
session_destroy();
header("Location: index.php");
exit;
?>