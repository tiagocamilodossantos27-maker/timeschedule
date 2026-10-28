<?php
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario_rol'] !== 'administrativo') {
    header("Location: login.php");
    exit;
}
require_once 'config/conexion.php';

$mensaje = '';
$tipo_alerta = 'success';

// --- CREACIONES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Crear Materia
    if (isset($_POST['crear_materia'])) {
        $nombre = trim($_POST['nombre_materia']);
        $tipo = trim($_POST['tipo_materia']);
        
        if (!empty($nombre) && !empty($tipo)) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO materias (nombre, tipo) VALUES (?, ?)");
            $stmt->execute([$nombre, $tipo]);
            $mensaje = "Materia agregada correctamente.";
        }
    }

    // 2. Crear Docente
    if (isset($_POST['crear_docente'])) {
        $ci_raw = trim($_POST['ci_docente']);
        $nombre = trim($_POST['nombre_docente']);
        $apellido = trim($_POST['apellido_docente']);
        $correo = trim($_POST['correo_docente']); 
        $telefono = trim($_POST['telefono_docente']);
        $materias_asignadas = $_POST['materias_docente'] ?? [];
        
        // Limpiamos la cédula para guardarla como dígitos puros
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
                // Inserción directa limpia del docente
                $stmt = $pdo->prepare("INSERT INTO docentes (ci, nombre, apellido, correo, telefono) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$ci_pura, $nombre, $apellido, $correo, $telefono]);
                $docente_id = $pdo->lastInsertId();

                // Inserción de materias habilitadas
                if ($docente_id && !empty($materias_asignadas)) {
                    $stmtMat = $pdo->prepare("INSERT INTO docente_materias (docente_id, materia_id) VALUES (?, ?)");
                    foreach ($materias_asignadas as $mat_id) {
                        $stmtMat->execute([$docente_id, $mat_id]);
                    }
                }

                $mensaje = "Docente registrado correctamente.";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    $mensaje = "La Cédula ($ci_raw) ya está registrada en la base de datos.";
                } else {
                    $mensaje = "Error al guardar en la BD: " . $e->getMessage();
                }
                $tipo_alerta = 'danger';
            }
        }
    }

    // 3. Crear Grupo
    if (isset($_POST['crear_grupo'])) {
        $nombre_grupo = trim($_POST['nombre_grupo']);
        if (!empty($nombre_grupo)) {
            $stmt = $pdo->prepare("INSERT INTO grupos (nombre_grupo) VALUES (?)");
            $stmt->execute([$nombre_grupo]);
            $mensaje = "Grupo agregado correctamente.";
        }
    }
}

// --- ELIMINACIONES ---
if (isset($_GET['eliminar_materia'])) {
    try {
        $pdo->prepare("DELETE FROM materias WHERE id = ?")->execute([(int)$_GET['eliminar_materia']]);
        $mensaje = "Materia eliminada correctamente.";
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar: la materia está asignada en una grilla.";
        $tipo_alerta = 'danger';
    }
}

if (isset($_GET['eliminar_docente'])) {
    try {
        $pdo->prepare("DELETE FROM docente_materias WHERE docente_id = ?")->execute([(int)$_GET['eliminar_docente']]);
        $pdo->prepare("DELETE FROM docentes WHERE id = ?")->execute([(int)$_GET['eliminar_docente']]);
        $mensaje = "Docente eliminado correctamente.";
    } catch (PDOException $e) {
        $mensaje = "No se puede eliminar: el docente tiene horas asignadas.";
        $tipo_alerta = 'danger';
    }
}

if (isset($_GET['eliminar_grupo'])) {
    try {
        $id = (int)$_GET['eliminar_grupo'];
        $pdo->prepare("DELETE FROM grillas WHERE grupo_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM grupos WHERE id = ?")->execute([$id]);
        $mensaje = "Grupo eliminado correctamente.";
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar el grupo.";
        $tipo_alerta = 'danger';
    }
}

// Cargar listas
$materias = $pdo->query("SELECT * FROM materias ORDER BY nombre ASC")->fetchAll();
$docentes = $pdo->query("SELECT * FROM docentes ORDER BY apellido ASC")->fetchAll();
$grupos = $pdo->query("SELECT * FROM grupos ORDER BY nombre_grupo ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Catálogos - TimeSchedule</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<nav class="navbar navbar-dark bg-dark mb-4">
    <div class="container">
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

<div class="container pb-5">
    <h3 class="mb-4">Gestión de Materias, Docentes y Grupos</h3>
    
    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- MATERIAS -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-primary text-white fw-bold">Registrar Materia</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nombre de la Materia</label>
                            <input type="text" name="nombre_materia" class="form-control form-control-sm" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Tipo de Espacio Curricular</label>
                            <select name="tipo_materia" class="form-select form-select-sm" required>
                                <option value="">Seleccione el tipo...</option>
                                <option value="Equivalente / Común">Equivalente / Común</option>
                                <option value="Técnico Profesional / Específica">Técnico Profesional / Específica</option>
                                <option value="Autonomía Curricular">Autonomía Curricular</option>
                            </select>
                        </div>
                        <button type="submit" name="crear_materia" class="btn btn-primary btn-sm w-100 fw-bold">Guardar Materia</button>
                    </form>
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 200px;">
                        <?php foreach ($materias as $m): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <div>
                                    <?= htmlspecialchars($m['nombre']) ?>
                                    <br>
                                    <span class="badge bg-secondary" style="font-size: 0.75em; font-weight: normal;">
                                        <?= htmlspecialchars($m['tipo'] ?? 'Sin asignar') ?>
                                    </span>
                                </div>
                                <a href="gestion_catalogo.php?eliminar_materia=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar materia?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- DOCENTES -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-success text-white fw-bold">Registrar Docente</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
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
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 120px;">
                        <?php foreach ($docentes as $d): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <?= htmlspecialchars($d['apellido'] . ', ' . $d['nombre']) ?>
                                <a href="gestion_catalogo.php?eliminar_docente=<?= $d['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar docente?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- GRUPOS -->
        <div class="col-md-4">
            <div class="card shadow-sm h-100 border-0">
                <div class="card-header bg-danger text-white fw-bold">Registrar Curso / Grupo</div>
                <div class="card-body">
                    <form method="POST" class="mb-3">
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Nombre del Curso</label>
                            <input type="text" name="nombre_grupo" class="form-control form-control-sm" placeholder="Ej: 3° MF-BT" required>
                        </div>
                        <button type="submit" name="crear_grupo" class="btn btn-danger btn-sm w-100 fw-bold">Guardar Grupo</button>
                    </form>
                    <hr>
                    <ul class="list-group list-group-flush small overflow-auto" style="max-height: 200px;">
                        <?php foreach ($grupos as $g): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1">
                                <strong><?= htmlspecialchars($g['nombre_grupo']) ?></strong>
                                <a href="gestion_catalogo.php?eliminar_grupo=<?= $g['id'] ?>" class="btn btn-sm btn-outline-danger py-0" onclick="return confirm('¿Eliminar grupo?')">X</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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