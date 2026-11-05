<?php
require_once 'config/conexion.php';

// Leer los datos enviados por la ventana de chat mediante JSON
$data = json_decode(file_get_contents('php://input'), true);
$pregunta = $data['mensaje'] ?? '';

if (empty(trim($pregunta))) {
    echo json_encode(['respuesta' => 'Por favor, escribe una pregunta o consulta sobre el sistema.']);
    exit;
}

// 1. Limpiar la pregunta (pasar a minúsculas y quitar acentos básicos para una mejor coincidencia)
$pregunta_limpia = mb_strtolower(trim($pregunta), 'UTF-8');

// 2. Base de preguntas frecuentes, manual y palabras clave (Keywords) con categorías ampliadas
$base_conocimiento = [
    [
        'id' => 'manual',
        'keywords' => ['manual', 'guia', 'como usar', 'instrucciones', 'ayuda', 'empezar', 'tutorial'],
        'respuesta' => "**Manual Rápido de TimeSchedule:**\n1. **Acceso:** Ingresa al login con tu cédula sin puntos ni guiones (ej: 11111111).\n2. **Grillas:** Ve a 'Grilla Admin', selecciona el grupo y turno. Asigna materias y docentes módulo a módulo.\n3. **Cupos:** Usa el control superior para limitar las horas máximas por materia.\n4. **Estados:** Guarda como 'Borrador' (uso interno) o 'Confirmado' (visible para alumnos en 'Ver Grillas')."
    ],
    [
        'id' => 'horarios',
        'keywords' => ['horario', 'grilla', 'ver', 'turno', 'clase', 'salón', 'aula'],
        'respuesta' => "Para consultar los horarios oficiales, dirígete a la sección 'Ver Grillas' desde el inicio. Recuerda que el sistema solo muestra públicamente las grillas que se encuentran en estado **'Confirmado'**."
    ],
    [
        'id' => 'docentes',
        'keywords' => ['docente', 'profesor', 'profesora', 'asignar', 'agregar', 'cedula'],
        'respuesta' => "Para dar de alta profesores o gestionar sus materias habilitadas, ve a 'Gestión de Docentes' o 'Gestión de Catálogos'. Al armar la grilla, el sistema filtra automáticamente las materias que cada docente está habilitado a dictar y te avisa si ya está ocupado."
    ],
    [
        'id' => 'cupos',
        'keywords' => ['cupo', 'limite', 'horas', 'maximo', 'cantidad'],
        'respuesta' => "En el panel superior de 'Grilla Admin' cuentas con el **Control de Cupo de Horas por Materia**. Te permite seleccionar una asignatura y establecer su límite de horas semanales para llevar un control exacto y evitar excedentes."
    ],
    [
        'id' => 'recreos',
        'keywords' => ['recreo', 'descanso', 'libre', 'hueco', 'espacio'],
        'respuesta' => "Los recreos y espacios institucionales de descanso se calculan y estructuran de forma automática en los turnos según los bloques horarios definidos en el sistema."
    ],
    [
        'id' => 'estados',
        'keywords' => ['borrador', 'confirmado', 'estado', 'publicar', 'guardar'],
        'respuesta' => "Una grilla en estado **'Borrador'** es exclusiva para la edición interna de los administradores. Al cambiarla a **'Confirmado'**, el sistema valida las pautas pedagógicas y la publica de inmediato para toda la comunidad educativa."
    ],
    [
        'id' => 'seguridad',
        'keywords' => ['sesion', 'bloqueo', 'usuario', 'contraseña', 'clave', 'error'],
        'respuesta' => "TimeSchedule protege los datos mediante control de roles (Administrativo / Adscripto) y validación de sesión única. Si cierras la pestaña o sales de forma imprevista, al volver a iniciar sesión el sistema sobrescribirá tu acceso de forma limpia y segura."
    ],
    [
        'id' => 'saludo',
        'keywords' => ['hola', 'buenas', 'buen dia', 'buenos dias', 'buenas tardes', 'saludos', 'que tal'],
        'respuesta' => "¡Hola! Soy el asistente virtual inteligente de **TimeSchedule**. ¿En qué te puedo ayudar hoy? Puedes preguntarme sobre horarios, asignación de docentes, estados de grillas o pedirme el manual de uso."
    ]
];

// 3. Sistema de clasificación inteligente por puntaje de coincidencias
$mejor_respuesta = "Lo siento, no he podido comprender del todo tu consulta. Intenta redactarla usando palabras clave como **'horario'**, **'docente'**, **'cupo'** o escribe **'manual'** para ver la guía rápida del sistema.";
$max_coincidencias = 0;

foreach ($base_conocimiento as $item) {
    $coincidencias_actuales = 0;
    foreach ($item['keywords'] as $keyword) {
        // Si la palabra clave está contenida en la pregunta del usuario
        if (mb_strpos($pregunta_limpia, $keyword) !== false) {
            $coincidencias_actuales++;
        }
    }
    
    // Si esta categoría tiene más coincidencias que la anterior, se convierte en la mejor respuesta
    if ($coincidencias_actuales > $max_coincidencias) {
        $max_coincidencias = $coincidencias_actuales;
        $mejor_respuesta = $item['respuesta'];
    }
}

// 4. Guardar la interacción en la base de datos (Registro histórico en chatbot_logs)
try {
    $stmt = $pdo->prepare("INSERT INTO chatbot_logs (pregunta, respuesta) VALUES (:pregunta, :respuesta)");
    $stmt->execute([
        ':pregunta' => $pregunta,
        ':respuesta' => $mejor_respuesta
    ]);
} catch (Exception $e) {
    // Evitamos que un fallo en el log detenga la respuesta visual del chat
}

// 5. Devolver la respuesta en formato JSON al cliente (AJAX / Fetch API)
echo json_encode([
    'exito' => true,
    'respuesta' => $mejor_respuesta
]);
?>