<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrativo') {
    header("Location: login.php");
    exit;
}

require_once 'config/conexion.php';

$mensaje = '';
$error_msj = '';
$grupo_id = $_GET['grupo_id'] ?? null;
$turno_seleccionado = $_GET['turno'] ?? 'Turno 1';

// 1. Guardar Grilla (Borrador o Confirmado) con Transacción Segura
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_grilla_accion'])) {
    $grupo_id_post = $_POST['grupo_id'];
    $turno_post = $_POST['turno'] ?? $turno_seleccionado;
    $nuevo_estado = $_POST['estado_grilla'];
    $creado_por = $_SESSION['usuario_id'];

    try {
        // Iniciar transacción: si algo falla, no se guarda nada a medias
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE grupos SET turno = ? WHERE id = ?")->execute([$turno_post, $grupo_id_post]);

        $stmtG = $pdo->prepare("SELECT id FROM grillas WHERE grupo_id = ? LIMIT 1");
        $stmtG->execute([$grupo_id_post]);
        $grillaExistente = $stmtG->fetch();

        if ($grillaExistente) {
            $grilla_id = $grillaExistente['id'];
            $pdo->prepare("UPDATE grillas SET estado = ?, confirmado_por = ?, fecha_confirmacion = NOW() WHERE id = ?")->execute([$nuevo_estado, $creado_por, $grilla_id]);
            $pdo->prepare("DELETE FROM horarios_clase WHERE grilla_id = ?")->execute([$grilla_id]);
        } else {
            $pdo->prepare("INSERT INTO grillas (grupo_id, estado, creado_por, confirmado_por, fecha_confirmacion) VALUES (?, ?, ?, ?, NOW())")->execute([$grupo_id_post, $nuevo_estado, $creado_por, $creado_por]);
            $grilla_id = $pdo->lastInsertId();
        }

        if (isset($_POST['clase'])) {
            // CORRECCIÓN: Se eliminó 'tipo_ubicacion' para evitar el fallo de base de datos que dejaba la grilla vacía
            $stmtClase = $pdo->prepare("INSERT INTO horarios_clase (grilla_id, modulo_id, dia_semana, materia_id, docente_id) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($_POST['clase'] as $modulo_id => $dias) {
                foreach ($dias as $dia => $datos) {
                    if (!empty($datos['materia']) && !empty($datos['docente'])) {
                        $stmtClase->execute([$grilla_id, $modulo_id, $dia, $datos['materia'], $datos['docente']]);
                    }
                }
            }
        }

        $pdo->commit(); // Confirmar guardado
        $mensaje = "Grilla guardada como " . strtoupper($nuevo_estado) . " con éxito.";
    } catch (Exception $e) {
        $pdo->rollBack(); // Deshacer cambios en caso de error
        $error_msj = "Error en la base de datos: " . $e->getMessage();
    }
    
    $grupo_id = $grupo_id_post;
    $turno_seleccionado = $turno_post;
}

// 2. Cargar Datos
$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nombre_grupo ASC")->fetchAll();
$materias = $pdo->query("SELECT * FROM materias ORDER BY nombre ASC")->fetchAll();
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY apellido ASC")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

$grupoActual = null;
$estadoGrilla = 'Nuevo';
$modulos = [];
$clasesGuardadas = [];
$docentes_ocupados = []; // Array para control de superposiciones

if ($grupo_id) {
    $stmtGrupo = $pdo->prepare("SELECT * FROM grupos WHERE id = ?");
    $stmtGrupo->execute([$grupo_id]);
    $grupoActual = $stmtGrupo->fetch();

    if ($grupoActual && !isset($_GET['turno']) && !empty($grupoActual['turno'])) {
        $turno_seleccionado = $grupoActual['turno'];
    }

    $stmtGrilla = $pdo->prepare("SELECT * FROM grillas WHERE grupo_id = ? LIMIT 1");
    $stmtGrilla->execute([$grupo_id]);
    $grillaDatos = $stmtGrilla->fetch();
    if ($grillaDatos) {
        $estadoGrilla = $grillaDatos['estado'];
        
        $stmtClases = $pdo->prepare("SELECT * FROM horarios_clase WHERE grilla_id = ?");
        $stmtClases->execute([$grillaDatos['id']]);
        foreach ($stmtClases->fetchAll() as $row) {
            $clasesGuardadas[$row['modulo_id']][$row['dia_semana']] = $row;
        }
    }

    $stmtMod = $pdo->prepare("SELECT * FROM modulos_horarios WHERE turno = ? ORDER BY hora_inicio ASC");
    $stmtMod->execute([$turno_seleccionado]);
    $modulos = $stmtMod->fetchAll();

    // --- DETECCIÓN DE SUPERPOSICIONES HORARIAS ---
    // 1. Buscar en qué otros grupos internos de la escuela están dando clase
    $stmtOcupadosInt = $pdo->prepare("SELECT hc.docente_id, hc.modulo_id, hc.dia_semana, g.nombre_grupo 
                                      FROM horarios_clase hc 
                                      JOIN grillas gr ON hc.grilla_id = gr.id 
                                      JOIN grupos g ON gr.grupo_id = g.id 
                                      WHERE gr.grupo_id != ?"); // Excluimos el grupo actual
    $stmtOcupadosInt->execute([$grupo_id]);
    foreach ($stmtOcupadosInt->fetchAll() as $row) {
        $docentes_ocupados[$row['docente_id']][$row['modulo_id']][$row['dia_semana']] = "Grupo " . $row['nombre_grupo'];
    }

    // 2. Buscar si tienen horarios en instituciones externas en ese módulo/día
    try {
        $stmtOcupadosExt = $pdo->query("SELECT docente_id, modulo_id, dia_semana, nombre_institucion FROM docentes_horarios_externos");
        foreach ($stmtOcupadosExt->fetchAll() as $row) {
            $docentes_ocupados[$row['docente_id']][$row['modulo_id']][$row['dia_semana']] = "Ext: " . $row['nombre_institucion'];
        }
    } catch (Exception $e) {
        // En caso de que la tabla de externos no exista todavía, el sistema no se rompe
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear y Administrar Grilla - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tabla-impresion { border: 2px solid #333 !important; }
        .tabla-impresion th, .tabla-impresion td { border: 1px solid #444 !important; vertical-align: middle; }
        .header-curso { background-color: #f2f2f2; font-weight: bold; font-size: 1.2rem; text-align: center; letter-spacing: 1px; }
        .select-mini { font-size: 0.78rem; padding: 2px 4px; margin-bottom: 2px; }
        .hora-celda { font-size: 0.85rem; font-weight: bold; background-color: #f9f9f9; }
        .fila-recreo { background-color: #e9ecef; color: #495057; font-weight: bold; font-size: 0.9rem; letter-spacing: 2px; }
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
        <div class="alert alert-success fw-bold"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error_msj): ?>
        <div class="alert alert-danger fw-bold"><?= htmlspecialchars($error_msj) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4 p-3 bg-white">
        <form method="GET" class="row align-items-center">
            <div class="col-md-6">
                <label class="fw-bold form-label">Seleccionar Curso / Grupo:</label>
                <select name="grupo_id" class="form-select form-select-lg border-primary" onchange="this.form.submit()">
                    <option value="">-- Seleccione un curso para editar grilla --</option>
                    <?php foreach ($grupos as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $g['id'] == $grupo_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nombre_grupo']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($grupoActual): ?>
                <div class="col-md-6 text-end align-self-end">
                    <span class="fs-6 me-2">Estado de Grilla:</span>
                    <?php if ($estadoGrilla === 'Confirmado'): ?>
                        <span class="badge bg-success fs-6">CONFIRMADO</span>
                    <?php elseif ($estadoGrilla === 'Borrador'): ?>
                        <span class="badge bg-warning text-dark fs-6">BORRADOR</span>
                    <?php else: ?>
                        <span class="badge bg-secondary fs-6">SIN CREAR</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($grupoActual): ?>
        <form method="POST" action="grilla_admin.php?grupo_id=<?= $grupo_id ?>&turno=<?= urlencode($turno_seleccionado) ?>">
            <input type="hidden" name="guardar_grilla_accion" value="1">
            <input type="hidden" name="grupo_id" value="<?= $grupoActual['id'] ?>">
            <input type="hidden" id="estado_grilla_input" name="estado_grilla" value="Confirmado">

            <div class="table-responsive bg-white p-3 shadow-sm rounded border">
                <table class="table table-bordered tabla-impresion text-center m-0">
                    <thead>
                        <tr>
                            <th colspan="6" class="header-curso py-2 text-uppercase">
                                CURSO: <?= htmlspecialchars($grupoActual['nombre_grupo']) ?>
                            </th>
                        </tr>
                        <tr class="table-light">
                            <th style="width: 13%;" class="p-2">
                                <select name="turno" class="form-select form-select-sm fw-bold border-dark text-center" onchange="window.location.href='grilla_admin.php?grupo_id=<?= $grupo_id ?>&turno=' + this.value">
                                    <option value="Turno 1" <?= $turno_seleccionado == 'Turno 1' ? 'selected' : '' ?>>TURNO 1</option>
                                    <option value="Turno 2" <?= $turno_seleccionado == 'Turno 2' ? 'selected' : '' ?>>TURNO 2</option>
                                    <option value="Turno 3" <?= $turno_seleccionado == 'Turno 3' ? 'selected' : '' ?>>TURNO 3</option>
                                </select>
                            </th>
                            <?php foreach ($dias as $d): ?>
                                <th style="width: 17.4%;" class="fw-bold align-middle"><?= mb_strtoupper($d, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modulos)): ?>
                            <tr><td colspan="6" class="text-danger fw-bold py-4">No hay módulos registrados en este turno.</td></tr>
                        <?php else: ?>
                            <?php 
                            $num_modulos = count($modulos);
                            for ($i = 0; $i < $num_modulos; $i++): 
                                $mod = $modulos[$i];
                            ?>
                                <tr>
                                    <td class="hora-celda align-middle">
                                        <?= substr($mod['hora_inicio'], 0, 5) ?><br>
                                        <?= substr($mod['hora_fin'], 0, 5) ?>
                                    </td>
                                    
                                    <?php foreach ($dias as $dia): ?>
                                        <?php 
                                            $claseActual = $clasesGuardadas[$mod['id']][$dia] ?? null;
                                            $mat_id = $claseActual['materia_id'] ?? '';
                                            $doc_id = $claseActual['docente_id'] ?? '';
                                        ?>
                                        <td class="p-1 align-middle">
                                            <!-- Selector de Materia -->
                                            <select name="clase[<?= $mod['id'] ?>][<?= $dia ?>][materia]" class="form-select select-mini border-primary text-uppercase fw-bold">
                                                <option value="">- Materia -</option>
                                                <?php foreach ($materias as $mat): ?>
                                                    <option value="<?= $mat['id'] ?>" <?= $mat_id == $mat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mat['nombre']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <!-- Selector de Docente con Validación de Superposición -->
                                            <select name="clase[<?= $mod['id'] ?>][<?= $dia ?>][docente]" class="form-select select-mini border-success">
                                                <option value="">- Docente -</option>
                                                <?php foreach ($docentes as $doc): ?>
                                                    <?php 
                                                        $es_docente_actual = ($doc_id == $doc['id']);
                                                        $ocupacion = $docentes_ocupados[$doc['id']][$mod['id']][$dia] ?? null;
                                                        
                                                        // Si está ocupado en otro lado, bloqueamos la opción
                                                        if ($ocupacion && !$es_docente_actual): ?>
                                                            <option value="" disabled class="bg-light text-danger fw-bold">
                                                                ❌ <?= htmlspecialchars($doc['apellido']) ?> (Ocupado en <?= htmlspecialchars($ocupacion) ?>)
                                                            </option>
                                                        <?php else: ?>
                                                            <option value="<?= $doc['id'] ?>" <?= $es_docente_actual ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($doc['apellido'] . ', ' . $doc['nombre']) ?>
                                                            </option>
                                                        <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>

                                <?php 
                                // LOGICA DE RECREO: Calcular minutos entre el fin de este módulo y el inicio del siguiente
                                if ($i < $num_modulos - 1) {
                                    $mod_next = $modulos[$i + 1];
                                    $fin_actual = strtotime($mod['hora_fin']);
                                    $inicio_siguiente = strtotime($mod_next['hora_inicio']);
                                    $diff_min = round(($inicio_siguiente - $fin_actual) / 60);

                                    // Si hay espacio de tiempo mayor a 0 minutos, mostrar franja de recreo
                                    if ($diff_min > 0) {
                                        echo "<tr class='fila-recreo'>";
                                        echo "<td colspan='6' class='text-center py-2'> RECREO ({$diff_min} MINUTOS)</td>";
                                        echo "</tr>";
                                    }
                                }
                                ?>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="submit" onclick="document.getElementById('estado_grilla_input').value='Borrador'" class="btn btn-warning px-4 fw-bold">Guardar Borrador</button>
                    <button type="submit" onclick="document.getElementById('estado_grilla_input').value='Confirmado'" class="btn btn-success px-4 fw-bold">Confirmar Grilla</button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

</body>
</html>