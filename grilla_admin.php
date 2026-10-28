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
            $stmtClase = $pdo->prepare("INSERT INTO horarios_clase (grilla_id, modulo_id, dia_semana, materia_id, docente_id) VALUES (?, ?, ?, ?, ?)");

            foreach ($_POST['clase'] as $modulo_id => $dias) {
                foreach ($dias as $dia => $datos) {
                    if (!empty($datos['materia']) && !empty($datos['docente'])) {
                        $stmtClase->execute([$grilla_id, $modulo_id, $dia, $datos['materia'], $datos['docente']]);
                    }
                }
            }
        }

        $pdo->commit();
        $mensaje = "Grilla guardada como " . strtoupper($nuevo_estado) . " con éxito.";
    } catch (Exception $e) {
        $pdo->rollBack();
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

// Cargar relación de materias habilitadas por docente para los filtros cruzados
$docente_materias_map = [];
$res_dm = $pdo->query("SELECT docente_id, materia_id FROM docente_materias")->fetchAll();
foreach ($res_dm as $dm) {
    $docente_materias_map[$dm['docente_id']][] = $dm['materia_id'];
}

$grupoActual = null;
$estadoGrilla = 'Nuevo';
$modulos = [];
$clasesGuardadas = [];
$docentes_ocupados = [];

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

    // Detección interna
    $stmtOcupadosInt = $pdo->prepare("SELECT hc.docente_id, hc.modulo_id, hc.dia_semana, g.nombre_grupo 
                                     FROM horarios_clase hc 
                                     JOIN grillas gr ON hc.grilla_id = gr.id 
                                     JOIN grupos g ON gr.grupo_id = g.id 
                                     WHERE gr.grupo_id != ?");
    $stmtOcupadosInt->execute([$grupo_id]);
    foreach ($stmtOcupadosInt->fetchAll() as $row) {
        $docentes_ocupados[$row['docente_id']][$row['modulo_id']][$row['dia_semana']] = "Grupo " . $row['nombre_grupo'];
    }

    // Detección externa
    try {
        $stmtOcupadosExt = $pdo->query("SELECT docente_id, modulo_id, dia_semana, nombre_institucion FROM docentes_horarios_externos");
        foreach ($stmtOcupadosExt->fetchAll() as $row) {
            $docentes_ocupados[$row['docente_id']][$row['modulo_id']][$row['dia_semana']] = "Ext: " . $row['nombre_institucion'];
        }
    } catch (Exception $e) {}
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

        @media print {
            body { background-color: #fff !important; }
            .no-imprimir, .navbar, aside, footer, #chatbot-container, [id*="chatbot"], .chatbot-widget { display: none !important; }
            .container-fluid { width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            table { font-size: 0.75rem !important; }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4 no-imprimir">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold" href="dashboard.php">TimeSchedule</a>
        <a href="dashboard.php" id="btnVolverPanel" class="btn btn-outline-light btn-sm">Volver al Panel de Control</a>
    </div>
</nav>

<div class="container-fluid px-4 pb-5">
    <?php if ($mensaje): ?>
        <div class="alert alert-success fw-bold no-imprimir"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error_msj): ?>
        <div class="alert alert-danger fw-bold no-imprimir"><?= htmlspecialchars($error_msj) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4 p-3 bg-white no-imprimir">
        <form method="GET" class="row align-items-center">
            <div class="col-md-5">
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
                <div class="col-md-7 text-end align-self-end">
                    <button type="button" onclick="window.print()" class="btn btn-primary btn-sm fw-bold me-3 shadow-sm">Imprimir Grilla</button>
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
        <!-- PANEL DE CONTROL DE CUPO DE HORAS POR DOCENTE -->
        <div class="card shadow-sm border-0 mb-4 p-3 bg-white no-imprimir">
            <h5 class="fw-bold text-secondary mb-2" style="font-size: 1rem;">Control de Cupo de Horas por Docente</h5>
            <div class="row g-2 align-items-center">
                <div class="col-md-4">
                    <select id="selectDocenteCupo" class="form-select form-select-sm">
                        <option value="">-- Seleccione Docente --</option>
                        <?php foreach ($docentes as $doc): ?>
                            <option value="<?= $doc['id'] ?>"><?= htmlspecialchars($doc['apellido'] . ', ' . $doc['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="number" id="inputHorasCupo" class="form-control form-control-sm" min="1" max="40" placeholder="Horas a tomar...">
                </div>
                <div class="col-md-2">
                    <button type="button" id="btnAddCupo" class="btn btn-secondary btn-sm w-100 fw-bold">Asignar Cupo</button>
                </div>
            </div>
            <div id="listaCuposAsignados" class="mt-2 d-flex flex-wrap gap-2"></div>
        </div>

        <form method="POST" id="formGrillaAdmin" action="grilla_admin.php?grupo_id=<?= $grupo_id ?>&turno=<?= urlencode($turno_seleccionado) ?>">
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
                                <select name="turno" class="form-select form-select-sm fw-bold border-dark text-center no-imprimir" onchange="window.location.href='grilla_admin.php?grupo_id=<?= $grupo_id ?>&turno=' + this.value">
                                    <option value="Turno 1" <?= $turno_seleccionado == 'Turno 1' ? 'selected' : '' ?>>TURNO 1</option>
                                    <option value="Turno 2" <?= $turno_seleccionado == 'Turno 2' ? 'selected' : '' ?>>TURNO 2</option>
                                    <option value="Turno 3" <?= $turno_seleccionado == 'Turno 3' ? 'selected' : '' ?>>TURNO 3</option>
                                </select>
                                <span class="d-none d-print-inline">TURNO</span>
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
                                            <select name="clase[<?= $mod['id'] ?>][<?= $dia ?>][materia]" class="form-select select-mini border-primary text-uppercase fw-bold select-materia" onchange="filtrarDocentesPorMateria(this)">
                                                <option value="">-- Materia --</option>
                                                <?php foreach ($materias as $mat): ?>
                                                    <option value="<?= $mat['id'] ?>" data-tipo="<?= htmlspecialchars($mat['tipo'] ?? '') ?>" <?= $mat_id == $mat['id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($mat['nombre']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <!-- Selector de Docente -->
                                            <select name="clase[<?= $mod['id'] ?>][<?= $dia ?>][docente]" class="form-select select-mini border-success select-docente" onchange="filtrarMateriasPorDocente(this); controlarCupoHoras(this);">
                                                <option value="">-- Docente --</option>
                                                <?php foreach ($docentes as $doc): ?>
                                                    <?php 
                                                        $es_docente_actual = ($doc_id == $doc['id']);
                                                        $ocupacion = $docentes_ocupados[$doc['id']][$mod['id']][$dia] ?? null;
                                                        $materias_habilitadas = $docente_materias_map[$doc['id']] ?? [];
                                                    ?>
                                                    <option value="<?= $doc['id'] ?>" 
                                                            data-habilitadas='<?= json_encode($materias_habilitadas) ?>'
                                                            <?= $es_docente_actual ? 'selected' : '' ?>
                                                            <?= ($ocupacion && !$es_docente_actual) ? 'disabled' : '' ?>>
                                                        <?= htmlspecialchars($doc['apellido'] . ', ' . $doc['nombre']) ?><?= ($ocupacion && !$es_docente_actual) ? ' (Ocupado)' : '' ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <!-- BOTONERA INFERIOR -->
                <div class="d-flex justify-content-between align-items-center mt-4 no-imprimir">
                    <div class="d-flex gap-2">
                        <button type="button" id="btnVaciar" class="btn btn-outline-danger fw-bold">Vaciar Tabla</button>
                        <button type="button" id="btnDeshacer" class="btn btn-outline-secondary fw-bold" style="display: none;">Volver Atrás</button>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" onclick="document.getElementById('estado_grilla_input').value='Borrador'; return true;" class="btn btn-warning px-4 fw-bold">Guardar Borrador</button>
                        <button type="submit" onclick="document.getElementById('estado_grilla_input').value='Confirmado'; return validarReglas();" class="btn btn-success px-4 fw-bold">Confirmar Grilla</button>
                    </div>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // 1. Detección de cambios sin guardar para el botón "Volver al Panel de Control"
    let grillaModificada = false;

    // Detectar cualquier cambio en los selectores de materias o docentes
    document.querySelectorAll('select.select-mini').forEach(sel => {
        sel.addEventListener('change', () => { 
            grillaModificada = true; 
        });
    });

    // Interceptar el clic en "Volver al Panel"
    document.getElementById('btnVolverPanel').addEventListener('click', function(e) {
        if (grillaModificada) {
            if (!confirm("⚠️ ¡Atención!\nTiene cambios sin guardar en esta grilla. Si sale ahora perderá el progreso.\n\n¿Desea volver al panel de control de todas formas?")) {
                e.preventDefault(); // Cancela la navegación si el usuario elige quedarse
            }
        }
    });

    // 2. Lógica para Vaciar Tabla con confirmación
    let estadoAnterior = {};
    const btnVaciar = document.getElementById('btnVaciar');
    const btnDeshacer = document.getElementById('btnDeshacer');
    const selectsFormulario = document.querySelectorAll('select.select-mini');

    if (btnVaciar && btnDeshacer) {
        btnVaciar.addEventListener('click', () => {
            if (!confirm("¿Está seguro que desea vaciar toda la tabla de la grilla?")) return;
            estadoAnterior = {}; 
            selectsFormulario.forEach(sel => {
                estadoAnterior[sel.name] = sel.value;
                sel.value = "";
            });
            btnVaciar.style.display = 'none';
            btnDeshacer.style.display = 'inline-block';
            reiniciarContadoresCupo();
            grillaModificada = true;
        });

        btnDeshacer.addEventListener('click', () => {
            selectsFormulario.forEach(sel => {
                if(estadoAnterior[sel.name] !== undefined) {
                    sel.value = estadoAnterior[sel.name];
                }
            });
            btnDeshacer.style.display = 'none';
            btnVaciar.style.display = 'inline-block';
            recalcularTodosLosCupos();
        });
    }

    // 3. Sistema de Control de Cupo de Horas por Docente
    let cuposDocentes = {};

    document.getElementById('btnAddCupo').addEventListener('click', () => {
        let docId = document.getElementById('selectDocenteCupo').value;
        let horas = parseInt(document.getElementById('inputHorasCupo').value);
        let selectDoc = document.getElementById('selectDocenteCupo');
        let nombreDoc = selectDoc.options[selectDoc.selectedIndex].text;

        if (!docId || !horas || horas <= 0) {
            alert("Seleccione un docente y un número de horas válido.");
            return;
        }

        cuposDocentes[docId] = {
            total: horas,
            usados: contarHorasUsadasDocente(docId),
            nombre: nombreDoc
        };

        renderizarBadgesCupos();
        document.getElementById('inputHorasCupo').value = '';
        selectDoc.value = '';
        recalcularTodosLosCupos();
    });

    function contarHorasUsadasDocente(docId) {
        let count = 0;
        document.querySelectorAll('select.select-docente').forEach(sel => {
            if (sel.value === docId) count++;
        });
        return count;
    }

    function renderizarBadgesCupos() {
        let contenedor = document.getElementById('listaCuposAsignados');
        contenedor.innerHTML = '';
        for (let id in cuposDocentes) {
            let c = cuposDocentes[id];
            let restante = c.total - c.usados;
            let badgeClass = restante < 0 ? 'bg-danger' : (restante === 0 ? 'bg-success' : 'bg-primary');

            let badge = document.createElement('span');
            badge.className = `badge ${badgeClass} p-2`;
            badge.innerHTML = `${c.nombre}: ${c.usados}/${c.total} horas asignadas (Restan: ${restante})`;
            contenedor.appendChild(badge);
        }
    }

    function reiniciarContadoresCupo() {
        for (let id in cuposDocentes) {
            cuposDocentes[id].usados = 0;
        }
        renderizarBadgesCupos();
    }

    function recalcularTodosLosCupos() {
        for (let id in cuposDocentes) {
            cuposDocentes[id].usados = contarHorasUsadasDocente(id);
        }
        renderizarBadgesCupos();
    }

    function controlarCupoHoras(selectDocente) {
        let docId = selectDocente.value;
        if (!docId) {
            recalcularTodosLosCupos();
            return;
        }

        if (cuposDocentes[docId]) {
            let c = cuposDocentes[docId];
            let usadosActuales = contarHorasUsadasDocente(docId);
            if (usadosActuales > c.total) {
                alert(`⚠️ Límite de horas alcanzado para el docente ${c.nombre}. Tiene asignadas ${c.total} horas como máximo.`);
                selectDocente.value = "";
            }
        }
        recalcularTodosLosCupos();
    }

    // 4. Filtro Cruzado: Materia -> Docente
    function filtrarDocentesPorMateria(selectMateria) {
        let fila = selectMateria.closest('td');
        let selectDocente = fila.querySelector('.select-docente');
        let materiaId = parseInt(selectMateria.value);

        Array.from(selectDocente.options).forEach(opt => {
            if (opt.value === "") return;
            let habilitadas = JSON.parse(opt.getAttribute('data-habilitadas') || '[]');

            if (!materiaId || habilitadas.includes(materiaId)) {
                opt.style.display = "";
            } else {
                opt.style.display = "none";
                if (selectDocente.value === opt.value) {
                    selectDocente.value = "";
                }
            }
        });
        recalcularTodosLosCupos();
    }

    // 5. Filtro Cruzado Simétrico: Docente -> Materia
    function filtrarMateriasPorDocente(selectDocente) {
        let fila = selectDocente.closest('td');
        let selectMateria = fila.querySelector('.select-materia');
        let docenteId = parseInt(selectDocente.value);

        if (!docenteId) {
            Array.from(selectMateria.options).forEach(opt => {
                opt.style.display = "";
            });
            return;
        }

        let optDoc = selectDocente.options[selectDocente.selectedIndex];
        let habilitadas = JSON.parse(optDoc.getAttribute('data-habilitadas') || '[]');

        Array.from(selectMateria.options).forEach(opt => {
            if (opt.value === "") return;
            let matId = parseInt(opt.value);
            if (habilitadas.includes(matId)) {
                opt.style.display = "";
            } else {
                opt.style.display = "none";
                if (parseInt(selectMateria.value) === matId) {
                    selectMateria.value = "";
                }
            }
        });
    }

    // 6. Validación de Reglas, Huecos y Restricciones Inspectivas
    function validarReglas() {
        let errores = [];
        let dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
        
        // Verificación de huecos (espacios libres entre clases del mismo día)
        dias.forEach((dia, dIdx) => {
            let filas = document.querySelectorAll('tbody tr');
            let encontradoInicio = false;
            let huboHueco = false;

            filas.forEach(tr => {
                let cell = tr.querySelectorAll('td')[dIdx + 1];
                if (cell) {
                    let matSel = cell.querySelector('.select-materia');
                    let docSel = cell.querySelector('.select-docente');
                    let tieneClase = matSel && matSel.value !== "" && docSel && docSel.value !== "";

                    if (tieneClase) {
                        if (!encontradoInicio) {
                            encontradoInicio = true;
                        } else if (huboHueco) {
                            errores.push(`⚠️ El día ${dia} presenta un espacio libre (hueco) entre horarios asignados.`);
                        }
                    } else {
                        if (encontradoInicio) {
                            huboHueco = true;
                        }
                    }
                }
            });
        });

        // Estadísticas por materia para pautas de inspección
        let estadisticasMaterias = {};
        dias.forEach((dia, dIdx) => {
            let filas = document.querySelectorAll('tbody tr');
            filas.forEach(tr => {
                let cell = tr.querySelectorAll('td')[dIdx + 1];
                if (cell) {
                    let matSel = cell.querySelector('.select-materia');
                    let docSel = cell.querySelector('.select-docente');
                    if (matSel && matSel.value !== "" && docSel && docSel.value !== "") {
                        let matName = matSel.options[matSel.selectedIndex].text.trim();
                        if (!estadisticasMaterias[matName]) {
                            estadisticasMaterias[matName] = { total: 0, dias: new Set() };
                        }
                        estadisticasMaterias[matName].total++;
                        estadisticasMaterias[matName].dias.add(dia);
                    }
                }
            });
        });

        // Pautas inspectivas de asignaturas
        for (let matName in estadisticasMaterias) {
            let info = estadisticasMaterias[matName];
            let nameUpper = matName.toUpperCase();

            // Matemática: 3 o más horas sugeridas en al menos 2 días
            if (nameUpper.includes("MATEMÁTICA") || nameUpper.includes("MATEMATICA")) {
                if (info.total >= 3 && info.dias.size < 2) {
                    errores.push(`📐 Pauta de Matemática: "${matName}" tiene ${info.total} horas pero se concentra en un único día. Se sugiere distribuir en al menos 2 días.`);
                }
            }

            // Lenguas Extranjeras (Inglés/Portugués): 3 o 4 horas no deben concentrarse en un bloque único
            if (nameUpper.includes("INGLÉS") || nameUpper.includes("INGLES") || nameUpper.includes("PORTUGUÉS") || nameUpper.includes("PORTUGUES")) {
                if (info.total >= 3 && info.dias.size === 1) {
                    errores.push(`🌐 Pauta de Lenguas Extranjeras: "${matName}" tiene ${info.total} horas en un solo día, lo que contraviene la continuidad pedagógica recomendada.`);
                }
            }
        }

        // Control de cupos docentes
        for (let id in cuposDocentes) {
            let c = cuposDocentes[id];
            let usados = contarHorasUsadasDocente(id);
            if (usados > c.total) {
                errores.push(`👤 El docente ${c.nombre} excede su cupo máximo (${usados}/${c.total} horas).`);
            }
        }

        // Si hay errores/advertencias, se los mostramos al adscripto para que decida
        if (errores.length > 0) {
            let mensaje = "⚠️ ADVERTENCIA DE RESTRICCIONES HORARIAS:\n\n" + errores.join("\n") + "\n\n¿Desea confirmar la grilla de todos modos o cancelar para arreglarla?";
            return confirm(mensaje);
        }

        return true;
    }

    document.addEventListener("DOMContentLoaded", () => {
        recalcularTodosLosCupos();
    });
</script>

<?php include 'includes/chatbot_ui.php'; ?>
</body>
</html>