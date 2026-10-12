<?php
require_once 'config/conexion.php';

// Leer los datos enviados por la ventana de chat
$data = json_decode(file_get_contents('php://input'), true);
$pregunta = $data['mensaje'] ?? '';

if (empty(trim($pregunta))) {
    echo json_encode(['respuesta' => 'Por favor, escribe una pregunta.']);
    exit;
}

// 1. Limpiar la pregunta (pasar a minúsculas para comparar mejor)
$pregunta_limpia = mb_strtolower($pregunta, 'UTF-8');

// 2. Base de preguntas frecuentes y palabras clave (Keywords)
$respuestas_frecuentes = [
    [
        'keywords' => ['horario', 'grilla', 'ver', 'turno'],
        'respuesta' => 'Para consultar los horarios, dirígete a la página "Ver Grillas" desde el inicio. Recuerda que solo verás las grillas en estado "Confirmado".'
    ],
    [
        'keywords' => ['docente', 'profesor', 'profesora', 'asignar', 'agregar'],
        'respuesta' => 'Para asignar docentes, ve a "Grilla Admin". El sistema verificará automáticamente si el docente está libre o si se superpone con otra clase.'
    ],
    [
        'keywords' => ['recreo', 'descanso', 'libre'],
        'respuesta' => 'Los recreos se calculan y muestran de forma automática en la grilla cuando hay un espacio de tiempo libre entre dos módulos.'
    ],
    [
        'keywords' => ['borrador', 'confirmado', 'estado'],
        'respuesta' => 'Las grillas "Borrador" solo las ven los administradores. Cuando la pasas a "Confirmado", se publica para que todos los alumnos la vean.'
    ],
    [
        'keywords' => ['hola', 'buenas', 'buen dia', 'saludos'],
        'respuesta' => '¡Hola! Soy el asistente virtual de TimeSchedule. ¿En qué te puedo ayudar hoy con el sistema?'
    ]
];

// 3. Sistema de clasificación
$respuesta_bot = "Lo siento, no entendí tu pregunta. Intenta usar palabras clave como 'horario', 'docente', 'recreo' o 'estado'.";

foreach ($respuestas_frecuentes as $item) {
    foreach ($item['keywords'] as $keyword) {
        if (strpos($pregunta_limpia, $keyword) !== false) {
            $respuesta_bot = $item['respuesta'];
            break 2; // Detiene la búsqueda porque ya encontró la respuesta
        }
    }
}

// 4. Guardar la interacción en la base de datos (Registro de consultas)
$stmt = $pdo->prepare("INSERT INTO chatbot_logs (pregunta, respuesta) VALUES (:pregunta, :respuesta)");
$stmt->execute([
    ':pregunta' => $pregunta,
    ':respuesta' => $respuesta_bot
]);

// Enviar la respuesta al chat de la pantalla
echo json_encode(['respuesta' => $respuesta_bot]);
?>