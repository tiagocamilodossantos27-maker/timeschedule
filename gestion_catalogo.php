<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrativo') {
    header("Location: login.php");
    exit;
}
require_once 'config/conexion.php';

$mensaje = '';
$tipo_alerta = 'success';

// --- CREACIONES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Crear Materia
    if (isset($_POST['crear_materia'])) {
        $nombre = trim($_POST['nombre_materia']);
        if (!empty($nombre)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO materias (nombre) VALUES (?)");
            $stmt->execute([$nombre]);
            $mensaje = "Materia agregada correctamente.";
        }
    }

    // 2. Crear Docente
    if (isset($_POST['crear_docente'])) {
        $ci = trim($_POST['ci_docente']);
        $nombre = trim($_POST['nombre_docente']);
        $apellido = trim($_POST['apellido_docente']);
        $correo = trim($_POST['correo_docente']); 
        $telefono = trim($_POST['telefono_docente']); 
        
        if (!empty($ci) && !empty($nombre) && !empty($apellido)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO docentes (ci, nombre, apellido, correo, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ci, $nombre, $apellido, $correo, $telefono]);
            $mensaje = "Docente agregado correctamente.";
        }
    }

    // 3. Crear Grupo (Solo Nombre)
    if (isset($_POST['crear_grupo'])) {
        $nombre_grupo = trim($_POST['nombre_grupo']);
        if (!empty($nombre_grupo)) {
            $stmt = $pdo->prepare("INSERT INTO grupos (nombre_grupo) VALUES (?)");
            $stmt->execute([$nombre_grupo]);
            $mensaje = "Grupo agregado correctamente.";
        }
    }
}

// --- ELIMINACIONES ---
if (isset($_GET['eliminar_materia'])) {
    try {
        $pdo->prepare("DELETE FROM materias WHERE id = ?")->execute([(int)$_GET['eliminar_materia']]);
        $mensaje = "Materia eliminada correctamente.";
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar: la materia está asignada en una grilla.";
        $tipo_alerta = 'danger';
    }
}

if (isset($_GET['eliminar_docente'])) {
    try {
        $pdo->prepare("DELETE FROM docentes WHERE id = ?")->execute([(int)$_GET['eliminar_docente']]);
        $mensaje = "Docente eliminado correctamente.";
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar: el docente tiene horas asignadas.";
        $tipo_alerta = 'danger';
    }
}

if (isset($_GET['eliminar_grupo'])) {
    try {
        $id = (int)$_GET['eliminar_grupo'];
        $pdo->prepare("DELETE FROM grillas WHERE grupo_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$id]);
        $mensaje = "Grupo eliminado correctamente.";
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar el grupo.";
        $tipo_alerta = 'danger';
    }
}

// Cargar listas
$materias = $pdo->query("SELECT * FROM materias ORDER BY nombre ASC")->fetchAll();
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY apellido ASC")->fetchAll();
$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nombre_grupo ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Catálogos - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">TimeSchedule - Admin</a>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Volver al Panel de Control</a>
    </div>
</nav>

<div class="container pb-5">
    <h3 class="mb-4">Gestión de Materias, Docentes y Grupos</h3>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- MATERIAS -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-primary text-white fw-bold">Registrar Materia</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nombre de la Materia</label>
                            <input type="text" name="nombre_materia" class="form-control form-control-sm" required>
                        </div>
                        <button type="submit" name="crear_materia" class="btn btn-primary btn-sm w-100 fw-bold">Guardar Materia</button>
                    </form>
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 200px;">
                        <?php foreach ($materias as $m): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <?= htmlspecialchars($m['nombre']) ?>
                                <a href="gestion_catalogo.php?eliminar_materia=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar materia?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- DOCENTES (Formulario Unificado) -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-success text-white fw-bold">Registrar Docente</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <div class="mb-2">
                            <label class="form-label small fw-bold m-0">Cédula</label>
                            <input type="text" name="ci_docente" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold m-0">Nombre</label>
                            <input type="text" name="nombre_docente" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold m-0">Apellido</label>
                            <input type="text" name="apellido_docente" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold m-0">Correo Electrónico</label>
                            <input type="email" name="correo_docente" class="form-control form-control-sm">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold m-0">Teléfono / Celular</label>
                            <input type="text" name="telefono_docente" class="form-control form-control-sm">
                        </div>
                        
                        <button type="submit" name="crear_docente" class="btn btn-success btn-sm w-100 fw-bold">Guardar Docente</button>
                    </form>
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 150px;">
                        <?php foreach ($docentes as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <?= htmlspecialchars($d['apellido'] . ', ' . $d['nombre']) ?>
                                <a href="gestion_catalogo.php?eliminar_docente=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar docente?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- GRUPOS -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-danger text-white fw-bold">Registrar Curso / Grupo</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nombre del Curso</label>
                            <input type="text" name="nombre_grupo" class="form-control form-control-sm" placeholder="Ej: 3° MF-BT" required>
                        </div>
                        <button type="submit" name="crear_grupo" class="btn btn-danger btn-sm w-100 fw-bold">Guardar Grupo</button>
                    </form>
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 200px;">
                        <?php foreach ($grupos as $g): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <strong><?= htmlspecialchars($g['nombre_grupo']) ?></strong>
                                <a href="gestion_catalogo.php?eliminar_grupo=<?= $g['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar grupo?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>