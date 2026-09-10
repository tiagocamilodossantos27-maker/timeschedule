<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TimeSchedule - Escuela Técnica de Artigas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container text-center">
    <div class="mb-5">
        <h1 class="fw-bold text-primary display-4">TimeSchedule</h1>
        <p class="lead text-muted">Sistema de Gestión de Grillas Horarias - Escuela Técnica de Artigas</p>
    </div>

    <div class="row justify-content-center g-4">
        <!-- Opción 1: Visualización Pública -->
        <div class="col-md-5">
            <div class="card h-100 shadow border-0 py-4 px-3">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h3 class="card-title text-success fw-bold mb-3">Consultar Grillas Horarias</h3>
                        <p class="card-text text-muted">Acceso rápido para adscriptos. Consulta las grillas horarias por curso.</p>
                    </div>
                    <a href="ver_grillas.php" class="btn btn-success btn-lg mt-4">Ver Grillas Horarias</a>
                </div>
            </div>
        </div>

        <!-- Opción 2: Gestión Administrativa -->
        <div class="col-md-5">
            <div class="card h-100 shadow border-0 py-4 px-3">
                <div class="card-body d-flex flex-column justify-content-between">
                    <div>
                        <h3 class="card-title text-primary fw-bold mb-3">Acceso Administrativo</h3>
                        <p class="card-text text-muted">Panel exclusivo para el personal administrativo. Creación de grillas y disponibilidad docente.</p>
                    </div>
                    <a href="login.php" class="btn btn-primary btn-lg mt-4">Iniciar Sesión</a>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>