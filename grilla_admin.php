<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edición de Grilla - TimeSchedule</title>
    <!-- Cargamos Bootstrap 5 desde su CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .recreo-row {
            background-color: #e9ecef !important;
            font-weight: bold;
            font-size: 0.85rem;
            text-align: center;
            letter-spacing: 2px;
        }
        .celda-clase {
            cursor: pointer;
            transition: background-color 0.2s;
            height: 80px;
            vertical-align: middle;
        }
        .celda-clase:hover {
            background-color: #f8f9fa;
            border: 2px solid #0d6efd;
        }
        .materia-title { font-weight: bold; font-size: 0.9rem; margin-bottom: 2px; }
        .docente-name { font-size: 0.8rem; color: #6c757d; }
    </style>
</head>
<body class="bg-light">

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">CURSO: 3º MF. TEC. DE LA INFORMACIÓN - BT</h3>
        <div>
            <span class="badge bg-warning text-dark me-2">Estado: Borrador</span>
            <button class="btn btn-success">Guardar y Enviar a Dirección</button>
        </div>
    </div>

    <div class="table-responsive shadow-sm bg-white p-3 rounded">
        <table class="table table-bordered text-center align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th style="width: 10%;">TURNO 1</th>
                    <th style="width: 18%;">LUNES</th>
                    <th style="width: 18%;">MARTES</th>
                    <th style="width: 18%;">MIÉRCOLES</th>
                    <th style="width: 18%;">JUEVES</th>
                    <th style="width: 18%;">VIERNES</th>
                </tr>
            </thead>
            <tbody>
                <!-- Módulo 1 -->
                <tr>
                    <td class="fw-bold text-muted">07:30<br>08:15</td>
                    <td class="celda-clase" onclick="abrirModal('Lunes', 1)">
                        <div class="materia-title">EMPRENDEDURISMO</div>
                        <div class="docente-name">PRATS MONICA</div>
                    </td>
                    <td class="celda-clase bg-warning bg-opacity-25" onclick="abrirModal('Martes', 1)">
                        <div class="materia-title">PROGRAMACIÓN</div>
                        <div class="docente-name">BRUNO, CORNELIUS</div>
                    </td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Miércoles', 1)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Jueves', 1)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Viernes', 1)">+ Asignar</td>
                </tr>
                
                <!-- Módulo 2 -->
                <tr>
                    <td class="fw-bold text-muted">08:15<br>09:00</td>
                    <td class="celda-clase" onclick="abrirModal('Lunes', 2)">
                        <div class="materia-title">EMPRENDEDURISMO</div>
                        <div class="docente-name">PRATS MONICA</div>
                    </td>
                    <td class="celda-clase" onclick="abrirModal('Martes', 2)">
                        <div class="materia-title">MATEMATICA CTS</div>
                        <div class="docente-name">PEREIRA SAUL</div>
                    </td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Miércoles', 2)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Jueves', 2)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Viernes', 2)">+ Asignar</td>
                </tr>

                <!-- RECREO -->
                <tr>
                    <td class="fw-bold text-muted" style="font-size: 0.8rem;">09:00 - 09:05</td>
                    <td colspan="5" class="recreo-row">RECREO</td>
                </tr>

                <!-- Módulo 3 -->
                <tr>
                    <td class="fw-bold text-muted">09:05<br>09:50</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Lunes', 3)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Martes', 3)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Miércoles', 3)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Jueves', 3)">+ Asignar</td>
                    <td class="celda-clase text-muted" onclick="abrirModal('Viernes', 3)">+ Asignar</td>
                </tr>
                <!-- Aquí continuarían los demás módulos siguiendo la misma lógica -->
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para Asignar Clase -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Asignar Clase - <span id="modalDiaModulo"></span></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formAsignar">
            <div class="mb-3">
                <label class="form-label">Materia</label>
                <select class="form-select" required>
                    <option value="">Seleccione una materia...</option>
                    <option value="Programacion">Programación</option>
                    <option value="Fisica">Física</option>
                    <option value="Matematica">Matemática CTS</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Docente Disponible</label>
                <!-- Aquí, usando PHP (AJAX), solo se mostrarían los docentes libres -->
                <select class="form-select" required>
                    <option value="">Seleccione un docente...</option>
                    <option value="1">BRUNO, Cornelius</option>
                    <option value="2">KAISER, Marcos</option>
                    <option value="3" disabled>TULIPANO, Martín (Ocupado en Liceo 1)</option>
                </select>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary">Guardar Asignación</button>
      </div>
    </div>
  </div>
</div>

<!-- Scripts de Bootstrap y lógica del Modal -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Inicializar el modal de Bootstrap
    const modalAsignar = new bootstrap.Modal(document.getElementById('modalAsignar'));
    
    function abrirModal(dia, modulo) {
        // Cambiar el título del modal dinámicamente
        document.getElementById('modalDiaModulo').innerText = dia + " - Módulo " + modulo;
        // Mostrar el modal
        modalAsignar.show();
    }
</script>

</body>
</html>