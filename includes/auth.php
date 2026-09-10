<?php
// includes/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function verificarSesion() {
    if (!isset($_SESSION['usuario_id'])) {
        header("Location: login.php");
        exit;
    }
}

function verificarRol($rolesPermitidos = []) {
    verificarSesion();
    if (!in_array($_SESSION['usuario_rol'], $rolesPermitidos)) {
        echo "<div style='color:red; text-align:center; margin-top:50px;'>
                <h2>Acceso Denegado</h2>
                <p>No tienes permisos para acceder a esta sección.</p>
                <a href='dashboard.php'>Volver al Panel Principal</a>
              </div>";
        exit;
    }
}
?>