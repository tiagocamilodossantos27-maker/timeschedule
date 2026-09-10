<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrativo') {
    header("Location: login.php");
    exit;
}

require_once 'config/conexion.php';

$mensaje = '';
$docente_seleccionado = $_GET['docente_id'] ?? null;

// 1. Guardar Docente Nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_docente'])) {
    $ci = trim($_POST['ci_docente']);
    $nombre = trim($_POST['nombre_docente']);
    $apellido = trim($_POST['apellido_docente']);
    $correo = trim($_POST['correo_docente']);
    $telefono = trim($_POST['telefono_docente']);
    
    if (!empty($ci) && !empty($nombre) && !empty($apellido)) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO docentes (ci, nombre, apellido, correo, telefono) VALUES (?, ?, ?, ?, ?)");
        if ($stmt->execute([$ci, $nombre, $apellido, $correo, $telefono])) {
            $mensaje = "Docente registrado con éxito.";
        }
    }
}

// 1.5. Eliminar Docente de la lista lateral
if (isset($_GET['eliminar_docente'])) {
    try {
        $pdo->prepare("DELETE FROM docentes WHERE id = ?")->execute([(int)$_GET['eliminar_docente']]);
        $mensaje = "Docente eliminado correctamente.";
        if ($docente_seleccionado == $_GET['eliminar_docente']) {
            $docente_seleccionado = null; // Limpiar selección si borramos al que estábamos viendo
        }
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar: el docente tiene horas asignadas.";
    }
}

// 2. Guardar / Eliminar Hora Externa asignada manualmente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_hora_externa'])) {
    $doc_id = $_POST['docente_id'];
    $mod_id = $_POST['modulo_id'];
    $dia_sem = $_POST['dia_semana'];
    $inst_nombre = trim($_POST['nombre_institucion']);

    if (!empty($inst_nombre)) {
        $stmt = $pdo->prepare("INSERT INTO docentes_horarios_externos (docente_id, modulo_id, dia_semana, nombre_institucion) 
                               VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE nombre_institucion = VALUES(nombre_institucion)");
        $stmt->execute([$doc_id, $mod_id, $dia_sem, $inst_nombre]);
        $mensaje = "Hora externa actualizada.";
    }
    $docente_seleccionado = $doc_id;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_hora_externa'])) {
    $ext_id = $_POST['externo_id'];
    $doc_id = $_POST['docente_id'];
    $pdo->prepare("DELETE FROM docentes_horarios_externos WHERE id = ?")->execute([$ext_id]);
    $mensaje = "Hora externa removida.";
    $docente_seleccionado = $doc_id;
}

// Listados
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY apellido ASC, nombre ASC")->fetchAll();
$modulos = $pdo->query("SELECT * FROM modulos_horarios ORDER BY hora_inicio ASC")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Cargar horario unificado del docente
$horario_escuela = [];
$horario_externo = [];

if ($docente_seleccionado) {
    // Horarios en la Escuela Técnica (vienen de la grilla)
    $sqlE = "SELECT hc.modulo_id, hc.dia_semana, m.nombre AS materia, g.nombre_grupo 
             FROM horarios_clase hc
             JOIN grillas gr ON hc.grilla_id = gr.id
             JOIN grupos g ON gr.grupo_id = g.id
             JOIN materias m ON hc.materia_id = m.id
             WHERE hc.docente_id = ?";
    $stmtE = $pdo->prepare($sqlE);
    $stmtE->execute([$docente_seleccionado]);
    foreach ($stmtE->fetchAll() as $row) {
        $horario_escuela[$row['modulo_id']][$row['dia_semana']] = $row;
    }

    // Horarios Externos asignados manualmente
    $sqlExt = "SELECT * FROM docentes_horarios_externos WHERE docente_id = ?";
    $stmtExt = $pdo->prepare($sqlExt);
    $stmtExt->execute([$docente_seleccionado]);
    foreach ($stmtExt->fetchAll() as $row) {
        $horario_externo[$row['modulo_id']][$row['dia_semana']] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Docentes - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .celda-escuela { background-color: #d1e7dd; border-left: 4px solid #198754 !important; font-size: 0.8rem; }
        .celda-externa { background-color: #fff3cd; border-left: 4px solid #ffc107 !important; font-size: 0.8rem; }
        .table-horarios th, .table-horarios td { vertical-align: middle; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php">TimeSchedule - Admin</a>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Volver al Panel de Control</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">
    <?php if ($mensaje): ?>
        <div class="alert alert-success fw-bold alert-dismissible fade show">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Registrar Docente -->
        <div class="col-md-3 mb-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold">Registrar Docente</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-2"><label class="form-label small fw-bold">Cédula</label><input type="text" name="ci_docente" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label small fw-bold">Nombre</label><input type="text" name="nombre_docente" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label small fw-bold">Apellido</label><input type="text" name="apellido_docente" class="form-control form-control-sm" required></div>
                        <div class="mb-2"><label class="form-label small fw-bold">Correo</label><input type="email" name="correo_docente" class="form-control form-control-sm"></div>
                        <div class="mb-3"><label class="form-label small fw-bold">Teléfono</label><input type="text" name="telefono_docente" class="form-control form-control-sm"></div>
                        <button type="submit" name="crear_docente" class="btn btn-success w-100 btn-sm fw-bold">Guardar Docente</button>
                    </form>
                    
                    <!-- AQUÍ ESTÁ LA LISTA AÑADIDA IDÉNTICA A LA DE CATÁLOGO -->
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 250px;">
                        <?php foreach ($docentes as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <?= htmlspecialchars($d['apellido'] . ', ' . $d['nombre']) ?>
                                <a href="gestion_docentes.php?eliminar_docente=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar docente?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <!-- FIN DE LA LISTA -->
                    
                </div>
            </div>
        </div>

        <!-- Tabla de Horarios del Docente -->
        <div class="col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center">
                    <span>Horarios Semanales del Docente</span>
                    <form method="GET" class="m-0 p-0" style="width: 320px;">
                        <select name="docente_id" class="form-select form-select-sm border-dark fw-bold" onchange="this.form.submit()">
                            <option value="">-- Seleccione un Docente --</option>
                            <?php foreach ($docentes as $doc): ?>
                                <option value="<?= $doc['id'] ?>" <?= $doc['id'] == $docente_seleccionado ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($doc['apellido'] . ', ' . $doc['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="card-body p-0">
                    <?php if (!$docente_seleccionado): ?>
                        <div class="p-5 text-center text-muted">
                            <h5>Seleccione un docente arriba para visualizar sus horarios asignados en la Escuela Técnica y sus horas externas.</h5>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered text-center m-0 table-horarios">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 12%;">HORARIOS</th>
                                        <?php foreach ($dias as $d): ?>
                                            <th style="width: 17.6%;"><?= mb_strtoupper($d, 'UTF-8') ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modulos as $mod): ?>
                                        <tr>
                                            <td class="fw-bold bg-light small">
                                                <?= substr($mod['hora_inicio'], 0, 5) ?> - <?= substr($mod['hora_fin'], 0, 5) ?>
                                            </td>
                                            
                                            <?php foreach ($dias as $dia): ?>
                                                <?php 
                                                    $esc = $horario_escuela[$mod['id']][$dia] ?? null;
                                                    $ext = $horario_externo[$mod['id']][$dia] ?? null;
                                                ?>
                                                
                                                <?php if ($esc): ?>
                                                    <!-- Clase en la Escuela Técnica -->
                                                    <td class="celda-escuela p-1">
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($esc['materia']) ?></div>
                                                        <div class="text-success fw-bold small"><?= htmlspecialchars($esc['nombre_grupo']) ?></div>
                                                        <small class="text-muted" style="font-size: 0.7rem;">Escuela Técnica</small>
                                                    </td>

                                                <?php elseif ($ext): ?>
                                                    <!-- Clase en Institución Externa -->
                                                    <td class="celda-externa p-1">
                                                        <div class="fw-bold text-dark">Inst. Externa</div>
                                                        <div class="text-warning-emphasis fw-bold small"><?= htmlspecialchars($ext['nombre_institucion']) ?></div>
                                                        <form method="POST" class="mt-1">
                                                            <input type="hidden" name="docente_id" value="<?= $docente_seleccionado ?>">
                                                            <input type="hidden" name="externo_id" value="<?= $ext['id'] ?>">
                                                            <button type="submit" name="eliminar_hora_externa" class="btn btn-link btn-sm p-0 text-danger" style="font-size: 0.7rem;">[Quitar]</button>
                                                        </form>
                                                    </td>

                                                <?php else: ?>
                                                    <!-- Espacio Libre -> Permitir asignar Institución Externa -->
                                                    <td class="p-1">
                                                        <button type="button" class="btn btn-outline-secondary btn-sm w-100 py-0" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalExt_<?= $mod['id'] ?>_<?= $dia ?>">
                                                            Agregar Hora Externa
                                                        </button>

                                                        <!-- Modal pequeño de asignación rápida -->
                                                        <div class="modal fade" id="modalExt_<?= $mod['id'] ?>_<?= $dia ?>" tabindex="-1">
                                                            <div class="modal-dialog modal-dialog-centered modal-sm">
                                                                <div class="modal-content">
                                                                    <form method="POST">
                                                                        <div class="modal-header py-2 bg-light">
                                                                            <h6 class="modal-title">Asignar Hora Externa</h6>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                                        </div>
                                                                        <div class="modal-body text-start">
                                                                            <input type="hidden" name="docente_id" value="<?= $docente_seleccionado ?>">
                                                                            <input type="hidden" name="modulo_id" value="<?= $mod['id'] ?>">
                                                                            <input type="hidden" name="dia_semana" value="<?= $dia ?>">
                                                                            
                                                                            <label class="form-label small fw-bold">Institución / Nombre:</label>
                                                                            <input type="text" name="nombre_institucion" class="form-control form-control-sm" placeholder="Ej: Liceo 1, UTU..." required>
                                                                        </div>
                                                                        <div class="modal-footer py-1">
                                                                            <button type="submit" name="guardar_hora_externa" class="btn btn-warning btn-sm w-100 fw-bold">Guardar Hora Externa</button>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>