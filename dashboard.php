<?php
require_once 'includes/auth.php';
verificarSesion();

$rol = $_SESSION['usuario_rol'];
$nombre = $_SESSION['usuario_nombre'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Principal - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">TimeSchedule</a>
        <div class="d-flex align-items-center text-white">
            <span class="me-3">Bienvenida, <strong><?= htmlspecialchars($nombre) ?></strong> (<?= ucfirst($rol) ?>)</span>
            <a href="logout.php" class="btn btn-outline-light btn-sm">Cerrar Sesión</a>
        </div>
    </div>
</nav>

<div class="container">
    <h2 class="mb-4">Panel de Control</h2>

    <div class="row g-4">
        <!-- Vistas para Administrativo -->
        <?php if ($rol === 'administrativo'): ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title text-primary">Crear / Editar Grillas</h5>
                        <p class="card-text text-muted">Arma las grillas horararias de los grupos, asigna materias y docentes.</p>
                        <a href="grilla_admin.php" class="btn btn-primary btn-sm">Ir a Grillas</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title text-success">Gestión de Docentes</h5>
                        <p class="card-text text-muted">Registra docentes y carga su disponibilidad horaria e instituciones externas.</p>
                        <a href="gestion_docentes.php" class="btn btn-success btn-sm">Gestionar Docentes</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title text-info">Gestión de Materias, Docentes y Grupos</h5>
                        <p class="card-text text-muted">Registra grupos (ej: 3° MF-BT), docentes con su información personal y materias.</p>
                        <a href="gestion_catalogo.php" class="btn btn-info btn-sm text-white">Gestionar Oferta</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Vistas para Dirección -->
        <?php if ($rol === 'direccion'): ?>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title text-warning">Aprobación de Borradores</h5>
                        <p class="card-text text-muted">Revisa las grillas horarias enviadas por administración y confirmalas para su publicación.</p>
                        <a href="#" class="btn btn-warning btn-sm">Revisar Borradores</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Vistas para Adscripción -->
        <?php if ($rol === 'adscripcion'): ?>
            <div class="col-md-6">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-body">
                        <h5 class="card-title text-secondary">Consultar Grillas Horarias</h5>
                        <p class="card-text text-muted">Visualiza los horarios confirmados por dirección para cada grupo.</p>
                        <a href="#" class="btn btn-secondary btn-sm">Ver Horarios</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>