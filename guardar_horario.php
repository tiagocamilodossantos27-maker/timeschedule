<?php
// guardar_horario.php
require_once 'includes/auth.php';
require_once 'config/conexion.php';
header('Content-Type: application/json');

verificarRol(['administrativo']);

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['exito' => false, 'mensaje' => 'Datos inválidos.']);
    exit;
}

$grilla_id = $data['grilla_id'];
$modulo_id = $data['modulo_id'];
$dia_semana = $data['dia_semana'];
$materia_id = $data['materia_id'];
$docente_id = $data['docente_id'];

try {
    // 1. Validar que el docente no esté en otro grupo en ese mismo día y módulo
    $sqlCruce = "SELECT g.nombre_grupo 
                 FROM horarios_clase hc
                 JOIN grillas gr ON hc.grilla_id = gr.id
                 JOIN grupos g ON gr.grupo_id = g.id
                 WHERE hc.docente_id = :docente_id 
                   AND hc.modulo_id = :modulo_id 
                   AND hc.dia_semana = :dia_semana
                   AND hc.grilla_id != :grilla_id";
    
    $stmtCruce = $pdo->prepare($sqlCruce);
    $stmtCruce->execute([
        ':docente_id' => $docente_id,
        ':modulo_id'  => $modulo_id,
        ':dia_semana' => $dia_semana,
        ':grilla_id'  => $grilla_id
    ]);

    if ($cruce = $stmtCruce->fetch()) {
        echo json_encode([
            'exito' => false, 
            'mensaje' => 'El docente ya dicta clases en el grupo "' . $cruce['nombre_grupo'] . '" en este mismo horario.'
        ]);
        exit;
    }

    // 2. Si la casilla ya tiene una materia asignada en esta grilla, la actualizamos; si no, la insertamos.
    $sqlExiste = "SELECT id FROM horarios_clase WHERE grilla_id = :grilla_id AND modulo_id = :modulo_id AND dia_semana = :dia_semana";
    $stmtExiste = $pdo->prepare($sqlExiste);
    $stmtExiste->execute([':grilla_id' => $grilla_id, ':modulo_id' => $modulo_id, ':dia_semana' => $dia_semana]);
    $existe = $stmtExiste->fetch();

    if ($existe) {
        $sqlUpdate = "UPDATE horarios_clase SET materia_id = :materia_id, docente_id = :docente_id WHERE id = :id";
        $stmtUp = $pdo->prepare($sqlUpdate);
        $stmtUp->execute([':materia_id' => $materia_id, ':docente_id' => $docente_id, ':id' => $existe['id']]);
    } else {
        $sqlInsert = "INSERT INTO horarios_clase (grilla_id, modulo_id, dia_semana, materia_id, docente_id) VALUES (:grilla_id, :modulo_id, :dia_semana, :materia_id, :docente_id)";
        $stmtIn = $pdo->prepare($sqlInsert);
        $stmtIn->execute([
            ':grilla_id'  => $grilla_id,
            ':modulo_id'  => $modulo_id,
            ':dia_semana' => $dia_semana,
            ':materia_id' => $materia_id,
            ':docente_id' => $docente_id
        ]);
    }

    echo json_encode(['exito' => true, 'mensaje' => 'Horario asignado correctamente.']);

} catch (Exception $e) {
    echo json_encode(['exito' => false, 'mensaje' => 'Error en la base de datos: ' . $e->getMessage()]);
}