<?php
die(">>> EDITANDO EL ARCHIVO CORRECTO EN VS CODE <<<");
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrativo') {
    header("Location: login.php");
    exit;
}

require_once 'config/conexion.php';

$mensaje = '';
$grupo_id = $_GET['grupo_id'] ?? null;
$turno_seleccionado = $_GET['turno'] ?? 'Turno 1';

// 1. Guardar y Confirmar la grilla automáticamente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_grilla'])) {
    $grupo_id_post = $_POST['grupo_id'];
    $turno_post = $_POST['turno'];
    $creado_por = $_SESSION['usuario_id'];
    
    // Guardar/Actualizar el turno asignado al grupo
    $pdo->prepare("UPDATE grupos SET turno = ? WHERE id = ?")->execute([$turno_post, $grupo_id_post]);

    // Borrar grilla previa del grupo si existía
    $pdo->prepare("DELETE FROM grillas WHERE grupo_id = ?")->execute([$grupo_id_post]);
    
    // Crear nueva grilla confirmada
    $stmtGrilla = $pdo->prepare("INSERT INTO grillas (grupo_id, estado, creado_por, confirmado_por, fecha_confirmacion) VALUES (?, 'Confirmado', ?, ?, NOW())");
    $stmtGrilla->execute([$grupo_id_post, $creado_por, $creado_por]);
    $grilla_id = $pdo->lastInsertId();
    
    // Insertar clases seleccionadas
    if (isset($_POST['clase'])) {
        $stmtClase = $pdo->prepare("INSERT INTO horarios_clase (grilla_id, modulo_id, dia_semana, materia_id, docente_id) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($_POST['clase'] as $modulo_id => $dias) {
            foreach ($dias as $dia => $datos) {
                if (!empty($datos['materia']) && !empty($datos['docente'])) {
                    $stmtClase->execute([$grilla_id, $modulo_id, $dia, $datos['materia'], $datos['docente']]);
                }
            }
        }
    }
    $mensaje = "Grilla guardada y confirmada con éxito.";
    $grupo_id = $grupo_id_post;
    $turno_seleccionado = $turno_post;
}

// 2. Obtener datos
$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nombre_grupo ASC")->fetchAll();
$materias = $pdo->query("SELECT * FROM materias ORDER BY nombre ASC")->fetchAll();
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY apellido ASC")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// 3. Cargar módulos del turno seleccionado
$modulos = [];
$clasesGuardadas = [];
$grupoActual = null;

if ($grupo_id) {
    $stmtG = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
    $stmtG->execute([$grupo_id]);
    $grupoActual = $stmtG->fetch();

    if ($grupoActual && !empty($grupoActual['turno']) && !isset($_GET['turno'])) {
        $turno_seleccionado = $grupoActual['turno'];
    }

    // Cargar exactamente los 8 módulos del turno seleccionado
    $stmtMod = $pdo->prepare("SELECT * FROM modulos_horarios WHERE turno = ? ORDER BY numero_modulo ASC");
    $stmtMod->execute([$turno_seleccionado]);
    $modulos = $stmtMod->fetchAll();

    // Cargar clases previas si existen
    $stmtClases = $pdo->prepare("SELECT hc.* FROM horarios_clase hc JOIN grillas g ON hc.grilla_id = g.id WHERE g.grupo_id = ?");
    $stmtClases->execute([$grupo_id]);
    foreach ($stmtClases->fetchAll() as $row) {
        $clasesGuardadas[$row['modulo_id']][$row['dia_semana']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Grilla - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .select-sm { font-size: 0.8rem; padding: 0.25rem; margin-bottom: 3px; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">TimeSchedule - Admin</a>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Volver al Dashboard</a>
    </div>
</nav>

<div class="container pb-5">
    <!-- Marca visual para confirmar que este es el archivo nuevo -->
    <h3 class="mb-4">Asignación de Grilla Horaria <span class="badge bg-success fs-6">VERSIÓN NUEVA</span></h3>

    <?php if ($mensaje): ?>
        <div class="alert alert-success fw-bold"><?= $mensaje ?></div>
    <?php endif; ?>

    <!-- Selector de Grupo y Turno -->
    <div class="card shadow-sm border-0 mb-4 p-3 bg-white">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-5">
                <label class="fw-bold form-label">1. Seleccione el Curso/Grupo:</label>
                <select name="grupo_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Elija un grupo --</option>
                    <?php foreach ($grupos as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $g['id'] == $grupo_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nombre_grupo']) ?>
                            <?= !empty($g['turno']) ? ' (' . htmlspecialchars($g['turno']) . ')' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($grupo_id): ?>
                <div class="col-md-5">
                    <label class="fw-bold form-label">2. Seleccione el Turno Horario:</label>
                    <select name="turno" class="form-select border-primary" onchange="this.form.submit()">
                        <option value="Turno 1" <?= $turno_seleccionado == 'Turno 1' ? 'selected' : '' ?>>Turno 1 (Matutino: 07:30 a 13:50)</option>
                        <option value="Turno 2" <?= $turno_seleccionado == 'Turno 2' ? 'selected' : '' ?>>Turno 2 (Vespertino: 13:05 a 19:20)</option>
                        <option value="Turno 3" <?= $turno_seleccionado == 'Turno 3' ? 'selected' : '' ?>>Turno 3 (Nocturno: 17:50 a 23:35)</option>
                    </select>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Tabla del Horario -->
    <?php if ($grupoActual): ?>
        <form method="POST" action="crear_grilla.php">
            <input type="hidden" name="guardar_grilla" value="1">
            <input type="hidden" name="grupo_id" value="<?= $grupoActual['id'] ?>">
            <input type="hidden" name="turno" value="<?= htmlspecialchars($turno_seleccionado) ?>">

            <div class="table-responsive shadow-sm bg-white p-3 rounded border">
                <h5 class="text-primary fw-bold text-center mb-3">
                    Horario para: <?= htmlspecialchars($grupoActual['nombre_grupo']) ?> — <span class="text-dark"><?= htmlspecialchars($turno_seleccionado) ?></span>
                </h5>
                
                <table class="table table-bordered text-center align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 12%;">Hora</th>
                            <?php foreach ($dias as $d): ?>
                                <th style="width: 17.6%;"><?= $d ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modulos)): ?>
                            <tr>
                                <td colspan="6" class="text-danger fw-bold py-4">
                                    No se encontraron módulos para <?= htmlspecialchars($turno_seleccionado) ?>. Verifica la tabla modulos_horarios en MySQL.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modulos as $mod): ?>
                                <tr>
                                    <td class="fw-bold text-muted small bg-light">
                                        <?= substr($mod['hora_inicio'], 0, 5) ?> - <?= substr($mod['hora_fin'], 0, 5) ?>
                                    </td>
                                    
                                    <?php foreach ($dias as $dia): ?>
                                        <?php 
                                            $claseActual = $clasesGuardadas[$mod['id']][$dia] ?? null;
                                            $mat_id = $claseActual['materia_id'] ?? '';
                                            $doc_id = $claseActual['docente_id'] ?? '';
                                        ?>
                                        <td>
                                            <select name="clase[<?= $mod['id'] ?>][<?= $dia ?>][materia]" class="form-select select-sm border-primary">
                                                <option value="">- Materia -</option>
                                                <?php foreach ($materias as $mat): ?>
                                                    <option value="<?= $mat['id'] ?>" <?= $mat_id == $mat['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($mat['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <select name="clase[<?= $mod['id'] ?>][<?= $dia ?>][docente]" class="form-select select-sm border-success">
                                                <option value="">- Docente -</option>
                                                <?php foreach ($docentes as $doc): ?>
                                                    <option value="<?= $doc['id'] ?>" <?= $doc_id == $doc['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($doc['apellido'] . ' ' . $doc['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="text-end mt-3">
                    <button type="submit" class="btn btn-success btn-lg px-5">Guardar y Confirmar Grilla</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>