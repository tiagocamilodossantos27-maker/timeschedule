<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrativo') {
    header("Location: login.php");
    exit;
}

require_once 'config/conexion.php';

$mensaje = '';
$tipo_alerta = 'success';
$docente_seleccionado = $_GET['docente_id'] ?? ($_POST['docente_id'] ?? null);

// 1. Guardar Docente Nuevo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_docente'])) {
    $ci_raw = trim($_POST['ci_docente']);
    $nombre = trim($_POST['nombre_docente']);
    $apellido = trim($_POST['apellido_docente']);
    $correo = trim($_POST['correo_docente']);
    $telefono = trim($_POST['telefono_docente']);
    $materias_asignadas = $_POST['materias_docente'] ?? [];
    
    $ci_pura = preg_replace('/[^0-9]/', '', $ci_raw);

    $error_validacion = '';
    if (strlen($ci_pura) < 7 || strlen($ci_pura) > 8) {
        $error_validacion = "La Cédula de Identidad debe tener 7 u 8 dígitos.";
    } elseif (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error_validacion = "El formato del correo electrónico no es válido.";
    }

    if (!empty($error_validacion)) {
        $mensaje = $error_validacion;
        $tipo_alerta = 'danger';
    } elseif (!empty($ci_pura) && !empty($nombre) && !empty($apellido)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO docentes (ci, nombre, apellido, correo, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$ci_pura, $nombre, $apellido, $correo, $telefono]);
            $doc_id = $pdo->lastInsertId();

            if ($doc_id && !empty($materias_asignadas)) {
                $stmtMat = $pdo->prepare("INSERT INTO docente_materias (docente_id, materia_id) VALUES (?, ?)");
                foreach ($materias_asignadas as $m_id) {
                    $stmtMat->execute([$doc_id, $m_id]);
                }
            }

            $mensaje = "Docente registrado con éxito.";
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $mensaje = "La Cédula de Identidad ($ci_raw) ya se encuentra registrada.";
            } else {
                $mensaje = "Error al guardar en la BD: " . $e->getMessage();
            }
            $tipo_alerta = 'danger';
        }
    }
}

// 1.2. Actualizar Docente Existente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_docente'])) {
    $doc_id = $_POST['docente_id'];
    $ci_raw = trim($_POST['ci_docente']);
    $nombre = trim($_POST['nombre_docente']);
    $apellido = trim($_POST['apellido_docente']);
    $correo = trim($_POST['correo_docente']);
    $telefono = trim($_POST['telefono_docente']);
    $materias_asignadas = $_POST['materias_docente'] ?? [];
    
    $ci_pura = preg_replace('/[^0-9]/', '', $ci_raw);

    $error_validacion = '';
    if (strlen($ci_pura) < 7 || strlen($ci_pura) > 8) {
        $error_validacion = "La Cédula de Identidad debe tener entre 7 y 8 dígitos.";
    } elseif (!empty($correo) && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error_validacion = "El correo electrónico no es válido.";
    }

    if (!empty($error_validacion)) {
        $mensaje = $error_validacion;
        $tipo_alerta = 'danger';
        $docente_seleccionado = $doc_id;
    } elseif (!empty($ci_pura) && !empty($nombre) && !empty($apellido) && !empty($doc_id)) {
        try {
            $stmt = $pdo->prepare("UPDATE docentes SET ci = ?, nombre = ?, apellido = ?, correo = ?, telefono = ? WHERE id = ?");
            $stmt->execute([$ci_pura, $nombre, $apellido, $correo, $telefono, $doc_id]);

            // Actualizar materias habilitadas
            $pdo->prepare("DELETE FROM docente_materias WHERE docente_id = ?")->execute([$doc_id]);
            if (!empty($materias_asignadas)) {
                $stmtMat = $pdo->prepare("INSERT INTO docente_materias (docente_id, materia_id) VALUES (?, ?)");
                foreach ($materias_asignadas as $m_id) {
                    $stmtMat->execute([$doc_id, $m_id]);
                }
            }

            $mensaje = "Información del docente actualizada con éxito.";
            $docente_seleccionado = $doc_id;
        } catch (PDOException $e) {
            $mensaje = "Error al actualizar información: " . $e->getMessage();
            $tipo_alerta = 'danger';
            $docente_seleccionado = $doc_id;
        }
    }
}

// 1.5. Eliminar Docente
if (isset($_GET['eliminar_docente'])) {
    try {
        $pdo->prepare("DELETE FROM docente_materias WHERE docente_id = ?")->execute([(int)$_GET['eliminar_docente']]);
        $pdo->prepare("DELETE FROM docentes WHERE id = ?")->execute([(int)$_GET['eliminar_docente']]);
        $mensaje = "Docente eliminado correctamente.";
        if ($docente_seleccionado == $_GET['eliminar_docente']) {
            $docente_seleccionado = null;
        }
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar: el docente tiene horas asignadas.";
        $tipo_alerta = 'danger';
    }
}

// 2. Guardar / Eliminar Hora Externa
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
$materias = $pdo->query("SELECT * FROM materias ORDER BY nombre ASC")->fetchAll();
$modulos = $pdo->query("SELECT * FROM modulos_horarios ORDER BY hora_inicio ASC")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Obtener información y materias habilitadas del docente seleccionado
$info_docente_seleccionado = null;
$materias_del_docente = [];
if ($docente_seleccionado) {
    foreach ($docentes as $d) {
        if ($d['id'] == $docente_seleccionado) {
            $info_docente_seleccionado = $d;
            break;
        }
    }
    $stmtMD = $pdo->prepare("SELECT materia_id FROM docente_materias WHERE docente_id = ?");
    $stmtMD->execute([$docente_seleccionado]);
    $materias_del_docente = $stmtMD->fetchAll(PDO::FETCH_COLUMN);
}

// Cargar horario unificado del docente
$horario_escuela = [];
$horario_externo = [];

if ($docente_seleccionado) {
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

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background-color: #fff !important; margin: 0 !important; }
            .no-imprimir, .navbar, aside, footer, #chatbot-container, [id*="chatbot"], .chatbot-widget { display: none !important; }
            .container-fluid { width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            table { font-size: 0.8rem !important; width: 100% !important; }
            .table-dark th { background-color: #212529 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4 no-imprimir">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php">TimeSchedule</a>
        <a href="dashboard.php" class="btn btn-outline-light btn-sm">Volver al Panel de Control</a>
    </div>
</nav>

<datalist id="dominios-correo">
    <option value="@gmail.com">
    <option value="@hotmail.com">
    <option value="@outlook.com">
    <option value="@utec.edu.uy">
    <option value="@anep.edu.uy">
</datalist>

<div class="container-fluid px-4 pb-5">
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_alerta ?> fw-bold alert-dismissible fade show no-imprimir">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Columna Izquierda: Registrar e Información Docente -->
        <div class="col-md-3 mb-4 no-imprimir">
            
            <!-- Tarjeta Registrar Docente -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-success text-white fw-bold">Registrar Docente</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-2">
                            <label class="form-label small fw-bold m-0">Cédula de Identidad</label>
                            <input type="text" name="ci_docente" id="ci_docente" class="form-control form-control-sm" placeholder="Ej: 4.815.116-4" maxlength="11" required>
                            <small class="text-muted" style="font-size: 0.7rem;">Formato automático: puntos y guion se colocan solos.</small>
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
                            <input type="text" name="correo_docente" class="form-control form-control-sm" placeholder="Ej: correo@dominio.com" list="dominios-correo">
                            <small class="text-muted" style="font-size: 0.7rem;">Sugerencia de dominios automática habilitada.</small>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold m-0">Teléfono / Celular</label>
                            <input type="text" name="telefono_docente" id="telefono_docente" class="form-control form-control-sm" placeholder="Ej: 099 123 456" maxlength="11">
                            <small class="text-muted" style="font-size: 0.7rem;">Celular (09X...) o fijo con formato automático.</small>
                        </div>
                        
                        <!-- Materias Habilitadas Dinámicas -->
                        <div class="mb-3">
                            <label class="form-label small fw-bold m-0">Materias Habilitadas</label>
                            <select id="select_materia_reg" class="form-select form-select-sm mb-2" onchange="agregarMateria(this, 'lista_materias_reg')">
                                <option value="">Seleccione para agregar materia...</option>
                                <?php foreach ($materias as $mat): ?>
                                    <option value="<?= $mat['id'] ?>" data-nombre="<?= htmlspecialchars($mat['nombre']) ?>">
                                        <?= htmlspecialchars($mat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="lista_materias_reg" class="border rounded p-2 bg-white d-flex flex-wrap gap-1" style="min-height: 45px; max-height: 100px; overflow-y: auto;">
                                <span class="text-muted small fst-italic placeholder-texto">Ninguna materia seleccionada aún.</span>
                            </div>
                        </div>

                        <button type="submit" name="crear_docente" class="btn btn-success btn-sm w-100 fw-bold">Guardar Docente</button>
                    </form>
                    
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 150px;">
                        <?php foreach ($docentes as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <a href="gestion_docentes.php?docente_id=<?= $d['id'] ?>" class="text-decoration-none text-dark <?= $d['id'] == $docente_seleccionado ? 'fw-bold text-primary' : '' ?>">
                                    <?= htmlspecialchars($d['apellido'] . ', ' . $d['nombre']) ?>
                                </a>
                                <a href="gestion_docentes.php?eliminar_docente=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar docente?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <!-- Tarjeta de Edición de Información del Docente Seleccionado -->
            <?php if ($info_docente_seleccionado): ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-info text-white fw-bold">Editar Información Docente</div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="docente_id" value="<?= $info_docente_seleccionado['id'] ?>">
                            <div class="mb-2">
                                <label class="form-label small fw-bold m-0">Cédula de Identidad</label>
                                <input type="text" name="ci_docente" id="ci_docente_edit" class="form-control form-control-sm" value="<?= htmlspecialchars($info_docente_seleccionado['ci']) ?>" placeholder="Ej: 4.815.116-4" maxlength="11" required>
                                <small class="text-muted" style="font-size: 0.7rem;">Formato automático: puntos y guion se colocan solos.</small>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold m-0">Nombre</label>
                                <input type="text" name="nombre_docente" class="form-control form-control-sm" value="<?= htmlspecialchars($info_docente_seleccionado['nombre']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold m-0">Apellido</label>
                                <input type="text" name="apellido_docente" class="form-control form-control-sm" value="<?= htmlspecialchars($info_docente_seleccionado['apellido']) ?>" required>
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold m-0">Correo Electrónico</label>
                                <input type="text" name="correo_docente" class="form-control form-control-sm" value="<?= htmlspecialchars($info_docente_seleccionado['correo'] ?? '') ?>" placeholder="Ej: correo@dominio.com" list="dominios-correo">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small fw-bold m-0">Teléfono / Celular</label>
                                <input type="text" name="telefono_docente" id="telefono_docente_edit" class="form-control form-control-sm" value="<?= htmlspecialchars($info_docente_seleccionado['telefono'] ?? '') ?>" placeholder="Ej: 099 123 456" maxlength="11">
                            </div>

                            <!-- Materias Habilitadas Dinámicas en Edición -->
                            <div class="mb-3">
                                <label class="form-label small fw-bold m-0">Materias Habilitadas</label>
                                <select id="select_materia_edit" class="form-select form-select-sm mb-2" onchange="agregarMateria(this, 'lista_materias_edit')">
                                    <option value="">Seleccione para agregar materia...</option>
                                    <?php foreach ($materias as $mat): ?>
                                        <option value="<?= $mat['id'] ?>" data-nombre="<?= htmlspecialchars($mat['nombre']) ?>" <?= in_array($mat['id'], $materias_del_docente) ? 'style="display:none;"' : '' ?>>
                                            <?= htmlspecialchars($mat['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="lista_materias_edit" class="border rounded p-2 bg-white d-flex flex-wrap gap-1" style="min-height: 45px; max-height: 100px; overflow-y: auto;">
                                    <?php 
                                        $hay_materias = false;
                                        foreach ($materias as $mat) {
                                            if (in_array($mat['id'], $materias_del_docente)) {
                                                $hay_materias = true;
                                                echo '<span class="badge bg-primary d-inline-flex align-items-center gap-1 p-2 m-1">'.htmlspecialchars($mat['nombre']).'<button type="button" class="btn-close btn-close-white btn-sm" style="font-size: 0.6rem;" onclick="removerMateria(this, \'select_materia_edit\', \''.$mat['id'].'\', \''.htmlspecialchars($mat['nombre']).'\')"></button><input type="hidden" name="materias_docente[]" value="'.$mat['id'].'"></span>';
                                            }
                                        }
                                        if (!$hay_materias) {
                                            echo '<span class="text-muted small fst-italic placeholder-texto">Ninguna materia seleccionada aún.</span>';
                                        }
                                    ?>
                                </div>
                            </div>

                            <button type="submit" name="actualizar_docente" class="btn btn-info w-100 btn-sm fw-bold text-white">Guardar Cambios</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-secondary text-white fw-bold">Información Docente</div>
                    <div class="card-body text-center text-muted small py-4">
                        Seleccione un docente en la lista o en el panel de horarios para editar su información detallada.
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Tabla de Horarios del Docente -->
        <div class="col-md-9">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white fw-bold d-flex justify-content-between align-items-center no-imprimir">
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
                        <!-- Título exclusivo para impresión con el nombre del docente -->
                        <div class="d-none d-print-block p-3 text-center border-bottom mb-2">
                            <h3 class="fw-bold m-0">TimeSchedule - Horario Semanal del Docente</h3>
                            <h4 class="text-dark fw-bold mt-1">Docente: <?= htmlspecialchars($info_docente_seleccionado['apellido'] . ', ' . $info_docente_seleccionado['nombre']) ?> (CI: <?= htmlspecialchars($info_docente_seleccionado['ci']) ?>)</h4>
                        </div>

                        <!-- Botón de Imprimir Horarios del Docente -->
                        <div class="d-flex justify-content-between align-items-center p-3 bg-white no-imprimir border-bottom">
                            <h5 class="m-0 fw-bold text-dark">Docente: <?= htmlspecialchars($info_docente_seleccionado['apellido'] . ', ' . $info_docente_seleccionado['nombre']) ?></h5>
                            <button onclick="window.print()" class="btn btn-primary btn-sm fw-bold shadow-sm">Imprimir Horario Docente</button>
                        </div>

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
                                                <?= substr($mod['hora_inicio'], 0, 5) ?><br>
                                                <?= substr($mod['hora_fin'], 0, 5) ?>
                                            </td>
                                            
                                            <?php foreach ($dias as $dia): ?>
                                                <?php 
                                                    $esc = $horario_escuela[$mod['id']][$dia] ?? null;
                                                    $ext = $horario_externo[$mod['id']][$dia] ?? null;
                                                ?>
                                                
                                                <?php if ($esc): ?>
                                                    <td class="celda-escuela p-1">
                                                        <div class="fw-bold text-dark"><?= htmlspecialchars($esc['materia']) ?></div>
                                                        <div class="text-success fw-bold small"><?= htmlspecialchars($esc['nombre_grupo']) ?></div>
                                                        <small class="text-muted" style="font-size: 0.7rem;">Escuela Técnica</small>
                                                    </td>

                                                <?php elseif ($ext): ?>
                                                    <td class="celda-externa p-1">
                                                        <div class="fw-bold text-dark">Inst. Externa</div>
                                                        <div class="text-warning-emphasis fw-bold small"><?= htmlspecialchars($ext['nombre_institucion']) ?></div>
                                                        <form method="POST" class="mt-1 no-imprimir">
                                                            <input type="hidden" name="docente_id" value="<?= $docente_seleccionado ?>">
                                                            <input type="hidden" name="externo_id" value="<?= $ext['id'] ?>">
                                                            <button type="submit" name="eliminar_hora_externa" class="btn btn-link btn-sm p-0 text-danger" style="font-size: 0.7rem;">[Quitar]</button>
                                                        </form>
                                                    </td>

                                                <?php else: ?>
                                                    <td class="p-1">
                                                        <span class="no-imprimir">
                                                            <button type="button" class="btn btn-outline-secondary btn-sm w-100 py-0" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#modalExt_<?= $mod['id'] ?>_<?= $dia ?>">
                                                                Agregar Hora Externa
                                                            </button>
                                                        </span>

                                                        <div class="modal fade no-imprimir" id="modalExt_<?= $mod['id'] ?>_<?= $dia ?>" tabindex="-1">
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
                                                                            
                                                                            <label class="form-label small fw-bold">Nombre de la Institución:</label>
                                                                            <input type="text" name="nombre_institucion" class="form-control form-control-sm" placeholder="Ej: Liceo 1..." required>
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

<!-- SCRIPT DE MÁSCARAS AUTOMÁTICAS Y SELECCIÓN DE MATERIAS -->
<script>
    document.addEventListener('input', function (e) {
        if (e.target && (e.target.id === 'ci_docente' || e.target.id === 'ci_docente_edit')) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.length > 8) v = v.slice(0, 8);
            if (v.length === 8) {
                e.target.value = v.replace(/(\d{1})(\d{3})(\d{3})(\d{1})/, '$1.$2.$3-$4');
            } else if (v.length === 7) {
                e.target.value = v.replace(/(\d{1})(\d{3})(\d{3})/, '$1.$2.$3');
            } else if (v.length > 4) {
                e.target.value = v.replace(/(\d{1})(\d{3})(\d{0,3})/, function(m, p1, p2, p3) {
                    return p1 + '.' + p2 + (p3 ? '.' + p3 : '');
                });
            } else {
                e.target.value = v;
            }
        }

        if (e.target && (e.target.id === 'telefono_docente' || e.target.id === 'telefono_docente_edit')) {
            let v = e.target.value.replace(/\D/g, '');
            if (v.startsWith('09')) {
                if (v.length > 9) v = v.slice(0, 9);
                if (v.length > 6) {
                    e.target.value = v.replace(/(\d{3})(\d{3})(\d{3})/, '$1 $2 $3');
                } else if (v.length > 3) {
                    e.target.value = v.replace(/(\d{3})(\d{0,3})/, '$1 $2');
                } else {
                    e.target.value = v;
                }
            } else {
                if (v.length > 8) v = v.slice(0, 8);
                if (v.length > 4) {
                    e.target.value = v.replace(/(\d{1})(\d{3})(\d{0,4})/, '$1 $2 $3');
                } else {
                    e.target.value = v;
                }
            }
        }
    });

    function agregarMateria(selectElement, contenedorId) {
        const id = selectElement.value;
        if (!id) return;
        const option = selectElement.options[selectElement.selectedIndex];
        const nombre = option.getAttribute('data-nombre');
        
        const contenedor = document.getElementById(contenedorId);
        const placeholder = contenedor.querySelector('.placeholder-texto');
        if (placeholder) placeholder.remove();

        if (contenedor.querySelector(`input[value="${id}"]`)) {
            selectElement.value = "";
            return;
        }

        const badge = document.createElement('span');
        badge.className = 'badge bg-primary d-inline-flex align-items-center gap-1 p-2 m-1';
        badge.innerHTML = `
            ${nombre}
            <button type="button" class="btn-close btn-close-white btn-sm" style="font-size: 0.6rem;" onclick="removerMateria(this, '${selectElement.id}', '${id}', '${nombre}')"></button>
            <input type="hidden" name="materias_docente[]" value="${id}">
        `;
        
        contenedor.appendChild(badge);
        option.style.display = 'none';
        selectElement.value = "";
    }

    function removerMateria(btn, selectId, id, nombre) {
        const badge = btn.parentElement;
        const contenedor = badge.parentElement;
        badge.remove();

        const selectElement = document.getElementById(selectId);
        for (let option of selectElement.options) {
            if (option.value === id) {
                option.style.display = '';
                break;
            }
        }

        if (contenedor.querySelectorAll('input[name="materias_docente[]"]').length === 0) {
            const span = document.createElement('span');
            span.className = 'text-muted small fst-italic placeholder-texto';
            span.textContent = 'Ninguna materia seleccionada aún.';
            contenedor.appendChild(span);
        }
    }
</script>

<?php include 'includes/chatbot_ui.php'; ?>
</body>
</html>