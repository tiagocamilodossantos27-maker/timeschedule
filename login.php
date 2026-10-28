<?php
session_start();
require_once 'config/conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ci = trim($_POST['ci']);
    $password = trim($_POST['password']);

    if (!empty($ci) && !empty($password)) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE ci = :ci");
        $stmt->execute([':ci' => $ci]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password_hash'])) {
            
            // RESTRICCIÓN DE SESIÓN ÚNICA SIMULTÁNEA
            // Verificamos si ya tiene un session_id activo en la BD y si esa sesión sigue viva (opcionalmente podríamos validar, 
            // pero si tiene valor y es distinto al actual, significa que está abierto en otra parte).
            if (!empty($usuario['session_id'])) {
                // Comprobamos si la sesión guardada pertenece a otra pestaña/dispositivo activo
                // Una forma limpia es bloquear el ingreso directo:
                $error = "⚠️ Este usuario ya tiene una sesión activa en otro dispositivo o pestaña. Debe cerrar sesión primero en el otro lugar.";
            } else {
                // Guardar datos en la sesión PHP
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];
                $_SESSION['usuario_rol'] = $usuario['rol'];

                // Registrar el ID de sesión actual en la base de datos para bloquear duplicados
                $sessionIdActual = session_id();
                $updateSesion = $pdo->prepare("UPDATE usuarios SET session_id = ? WHERE id = ?");
                $updateSesion->execute([$sessionIdActual, $usuario['id']]);

                header("Location: dashboard.php");
                exit;
            }

        } else {
            $error = "Cédula o contraseña incorrectas.";
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TimeSchedule UTU</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    
                    <!-- BOTÓN VOLVER AL INICIO -->
                    <div class="mb-3">
                        <a href="index.php" class="text-decoration-none text-muted small fw-bold btn btn-light btn-sm">
                            ← Volver al Inicio
                        </a>
                    </div>

                    <h3 class="text-center mb-1 fw-bold text-primary">TimeSchedule</h3>
                    <p class="text-center text-muted small mb-4">Escuela Técnica de Artigas</p>

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form action="login.php" method="POST">
                        <div class="mb-3">
                            <label class="form-label small text-muted">Cédula de Identidad</label>
                            <input type="text" name="ci" class="form-control" placeholder="Sin puntos ni guiones" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small text-muted">Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mt-2">Iniciar Sesión</button>
                    </form>
                </div>
            </div>
            
            <!-- INFORMACIÓN DE CREDENCIALES ABAJO -->
            <div class="text-center text-muted small mt-3">
                <p class="mb-1"><strong>María Galarza:</strong> CI: <code>11111111</code> / Contraseña: <code>123456</code></p>
                <p class="mb-0"><strong>Administrativo 2:</strong> CI: <code>22222222</code> / Contraseña: <code>admin2password</code></p>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/chatbot_ui.php'; ?>
</body>
</html>