<?php
require_once 'config/conexion.php';

$grupo_id = $_GET['grupo_id'] ?? null;
$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nombre_grupo ASC")->fetchAll();

if (!$grupo_id && count($grupos) > 0) {
    $grupo_id = $grupos[0]['id'];
}

$grupoActual = null;
$modulos = [];
$clasesGuardadas = [];
$grillaConfirmada = false;

if ($grupo_id) {
    $stmtG = $pdo->prepare("SELECT * FROM grupos WHERE id = :id");
    $stmtG->execute([':id' => $grupo_id]);
    $grupoActual = $stmtG->fetch();

    if ($grupoActual) {
        // Verificar si el grupo tiene una grilla en estado 'Confirmado'
        $stmtGrillaCheck = $pdo->prepare("SELECT id FROM grillas WHERE grupo_id = :grupo_id AND estado = 'Confirmado' LIMIT 1");
        $stmtGrillaCheck->execute([':grupo_id' => $grupo_id]);
        $grillaData = $stmtGrillaCheck->fetch();

        if ($grillaData) {
            $grillaConfirmada = true;

            // Cargar módulos del turno asignado al grupo (ordenados por hora_inicio para el cálculo de recreos)
            $stmtMod = $pdo->prepare("SELECT * FROM modulos_horarios WHERE turno = :turno ORDER BY hora_inicio ASC");
            $stmtMod->execute([':turno' => $grupoActual['turno']]);
            $modulos = $stmtMod->fetchAll();

            // Cargar horarios del grupo filtrando únicamente grillas confirmadas
            $sqlClases = "SELECT hc.*, m.nombre AS materia_nombre, d.nombre AS docente_nombre, d.apellido AS docente_apellido 
                          FROM horarios_clase hc
                          JOIN grillas gr ON hc.grilla_id = gr.id
                          JOIN materias m ON hc.materia_id = m.id
                          JOIN docentes d ON hc.docente_id = d.id
                          WHERE gr.grupo_id = :grupo_id AND gr.estado = 'Confirmado'";
            $stmtC = $pdo->prepare($sqlClases);
            $stmtC->execute([':grupo_id' => $grupo_id]);
            foreach ($stmtC->fetchAll() as $row) {
                $clasesGuardadas[$row['modulo_id']][$row['dia_semana']] = $row;
            }
        }
    }
}
$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Consulta de Horarios - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .materia-title { font-weight: bold; font-size: 0.9rem; color: #0d6efd; }
        .docente-name { font-size: 0.8rem; color: #495057; }
        .fila-recreo { background-color: #e9ecef; color: #495057; font-weight: bold; font-size: 0.9rem; letter-spacing: 2px; }
    </style>
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">TimeSchedule</a>
        <a href="index.php" class="btn btn-outline-light btn-sm">Volver al Inicio</a>
    </div>
</nav>

<div class="container">
    <div class="card shadow-sm border-0 mb-4 p-3">
        <form method="GET" class="row align-items-center">
            <label class="col-auto fw-bold">Seleccionar Curso / Grupo:</label>
            <div class="col-md-4">
                <select name="grupo_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach ($grupos as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $g['id'] == $grupo_id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($g['nombre_grupo']) ?> (<?= $g['turno'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <?php if ($grupoActual): ?>
        <div class="table-responsive shadow-sm bg-white p-3 rounded">
            <h4 class="text-center fw-bold mb-3">CURSO: <?= htmlspecialchars($grupoActual['nombre_grupo']) ?> - <?= $grupoActual['turno'] ?></h4>
            
            <?php if (!$grillaConfirmada): ?>
                <div class="alert alert-warning text-center fw-bold py-4 mb-0">
                    Este grupo aún no cuenta con una grilla confirmada disponible para la visualización pública.
                </div>
            <?php else: ?>
                <table class="table table-bordered text-center align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 12%;">HORARIOS</th>
                            <?php foreach ($dias as $d): ?>
                                <th><?= strtoupper($d) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $num_modulos = count($modulos);
                        for ($i = 0; $i < $num_modulos; $i++): 
                            $mod = $modulos[$i];
                        ?>
                            <tr>
                                <td class="fw-bold text-muted small">
                                    <?= substr($mod['hora_inicio'], 0, 5) ?> a <?= substr($mod['hora_fin'], 0, 5) ?>
                                </td>
                                <?php foreach ($dias as $dia): ?>
                                    <?php $clase = $clasesGuardadas[$mod['id']][$dia] ?? null; ?>
                                    <td style="height: 70px;">
                                        <?php if ($clase): ?>
                                            <div class="materia-title"><?= htmlspecialchars($clase['materia_nombre']) ?></div>
                                            <div class="docente-name"><?= htmlspecialchars($clase['docente_apellido'] . ', ' . $clase['docente_nombre']) ?></div>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>

                            <?php 
                            // LÓGICA DE RECREO: Calcular minutos entre el fin de este módulo y el inicio del siguiente
                            if ($i < $num_modulos - 1) {
                                $mod_next = $modulos[$i + 1];
                                $fin_actual = strtotime($mod['hora_fin']);
                                $inicio_siguiente = strtotime($mod_next['hora_inicio']);
                                $diff_min = round(($inicio_siguiente - $fin_actual) / 60);

                                if ($diff_min > 0) {
                                    echo "<tr class='fila-recreo'>";
                                    echo "<td colspan='6' class='text-center py-2'>RECREO ({$diff_min} MINUTOS)</td>";
                                    echo "</tr>";
                                }
                            }
                            ?>
                        <?php endfor; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>