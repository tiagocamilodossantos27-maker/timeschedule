<?php
require_once 'config/conexion.php';

$docente_seleccionado = $_GET['docente_id'] ?? null;

// Listados
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY apellido ASC, nombre ASC")->fetchAll();
$modulos = $pdo->query("SELECT * FROM modulos_horarios ORDER BY hora_inicio ASC")->fetchAll();
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

// Obtener información del docente seleccionado
$info_docente_seleccionado = null;
if ($docente_seleccionado) {
    foreach ($docentes as $d) {
        if ($d['id'] == $docente_seleccionado) {
            $info_docente_seleccionado = $d;
            break;
        }
    }
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
    <title>Consulta de Horarios de Docentes - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .celda-escuela { background-color: #d1e7dd; border-left: 4px solid #198754 !important; font-size: 0.85rem; }
        .celda-externa { background-color: #fff3cd; border-left: 4px solid #ffc107 !important; font-size: 0.85rem; }
        .table-horarios th, .table-horarios td { vertical-align: middle; }

        @media print {
            @page { size: A4 landscape; margin: 10mm; }
            body { background-color: #fff !important; margin: 0 !important; }
            .no-imprimir, .navbar, aside, footer, #chatbot-container, [id*="chatbot"], .chatbot-widget { display: none !important; }
            .container { width: 100% !important; max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
            .card { border: none !important; box-shadow: none !important; }
            table { font-size: 0.8rem !important; width: 100% !important; }
            .table-dark th { background-color: #212529 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4 no-imprimir">
    <div class="container px-4">
        <a class="navbar-brand fw-bold" href="index.php">TimeSchedule</a>
        <a href="index.php" class="btn btn-outline-light btn-sm">Volver al Inicio</a>
    </div>
</nav>

<div class="container pb-5">
    <!-- Selector de Docente -->
    <div class="card shadow-sm border-0 mb-4 no-imprimir">
        <div class="card-body">
            <form method="GET" class="row align-items-center">
                <div class="col-md-9">
                    <label class="form-label fw-bold">Seleccionar Docente:</label>
                    <select name="docente_id" class="form-select border-dark fw-bold" onchange="this.form.submit()">
                        <option value="">-- Seleccione un Docente --</option>
                        <?php foreach ($docentes as $doc): ?>
                            <option value="<?= $doc['id'] ?>" <?= $doc['id'] == $docente_seleccionado ? 'selected' : '' ?>>
                                <?= htmlspecialchars($doc['apellido'] . ', ' . $doc['nombre']) ?> (CI: <?= htmlspecialchars($doc['ci']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 text-end align-self-end mt-3 mt-md-0">
                    <?php if ($docente_seleccionado): ?>
                        <button onclick="window.print()" class="btn btn-success fw-bold w-150 shadow-sm">Imprimir Horario</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Visualización del Horario -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (!$docente_seleccionado): ?>
                <div class="p-5 text-center text-muted">
                    <h5>Seleccione un docente arriba para visualizar y consultar sus horarios semanales.</h5>
                </div>
            <?php else: ?>
                <!-- Título exclusivo para impresión -->
                <div class="d-none d-print-block p-3 text-center border-bottom mb-2">
                    <h3 class="fw-bold m-0">TimeSchedule - Horario Semanal del Docente</h3>
                    <h4 class="text-dark fw-bold mt-1">Docente: <?= htmlspecialchars($info_docente_seleccionado['apellido'] . ', ' . $info_docente_seleccionado['nombre']) ?> (CI: <?= htmlspecialchars($info_docente_seleccionado['ci']) ?>)</h4>
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
                                            <td class="celda-escuela p-2">
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($esc['materia']) ?></div>
                                                <div class="text-success fw-bold"><?= htmlspecialchars($esc['nombre_grupo']) ?></div>
                                                <small class="text-muted" style="font-size: 0.75rem;">Escuela Técnica</small>
                                            </td>

                                        <?php elseif ($ext): ?>
                                            <td class="celda-externa p-2">
                                                <div class="fw-bold text-dark">Inst. Externa</div>
                                                <div class="text-warning-emphasis fw-bold"><?= htmlspecialchars($ext['nombre_institucion']) ?></div>
                                                <small class="text-muted" style="font-size: 0.75rem;">Hora Externa</small>
                                            </td>

                                        <?php else: ?>
                                            <td class="p-2 text-muted small fst-italic">
                                                Libre
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php include 'includes/chatbot_ui.php'; ?>
</body>
</html>