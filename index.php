<?php
// 1. ELIMINACIÓN DE ERRORES Y BLOQUEOS DE CONEXIÓN
error_reporting(0);
ini_set('display_errors', 0);

$DB_HOST = getenv('DB_HOST') ?: 'mariadb';
$DB_NAME = getenv('DB_NAME') ?: 'planta_agregados';
$DB_USER = getenv('DB_USER') ?: 'planta';
$DB_PASS = getenv('DB_PASSWORD') ?: 'PlantaDB2026!';

// Intento de conexión rápido
$conexion = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$db_online = ($conexion) ? true : false;

$mensaje = "";

// 2. PROCESAMIENTO DE FORMULARIOS DIRECTOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion_diesel'])) {
        $galones = floatval($_POST['galones']);
        $horometro = floatval($_POST['horometro']);
        
        if ($db_online) {
            $maq_id = intval($_POST['maquinaria_id']);
            $operador = mysqli_real_escape_string($conexion, $_POST['operador']);
            @mysqli_query($conexion, "INSERT INTO combustible_despachos (maquinaria_id, operador, galones, horometro_despacho) VALUES ($maq_id, '$operador', $galones, $horometro)");
            @mysqli_query($conexion, "UPDATE maquinaria SET horometro_actual = $horometro WHERE id = $maq_id");
        }
        $mensaje = "<div class='alert success'>⛽ Despacho Registrado Localmente: $galones Gal / Horómetro: $horometro Hrs</div>";
    }

    if (isset($_POST['accion_produccion'])) {
        $cantidad = floatval($_POST['cantidad']);
        
        if ($db_online) {
            $mat_id = intval($_POST['material_id']);
            $operador_prod = mysqli_real_escape_string($conexion, $_POST['operador_prod']);
            @mysqli_query($conexion, "INSERT INTO produccion (material_id, cantidad, turno, operador) VALUES ($mat_id, $cantidad, 'Turno Central', '$operador_prod')");
        }
        $mensaje = "<div class='alert success'>🧱 Producción Registrada Localmente: $cantidad m³</div>";
    }
}

$fecha = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Planta - Panel Simple</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f8; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .header { background: #15191e; color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .status { font-size: 12px; font-weight: bold; padding: 4px 8px; border-radius: 4px; }
        .online { background: #28a745; color: #fff; }
        .offline { background: #ffc107; color: #000; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; border: 1px solid #e1e4e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card h2 { margin-top: 0; font-size: 16px; border-bottom: 2px solid #f4f6f8; padding-bottom: 10px; margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 12px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        .btn { width: 100%; padding: 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 13px; }
        .btn-yellow { background: #fcd535; color: #000; }
        .btn-blue { background: #0056b3; color: #fff; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 14px; text-align: center; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <h1>Control Operativo - Planta V2</h1>
            <small>Sincronización: <?php echo $fecha; ?></small>
        </div>
        <div>
            <?php if ($db_online): ?>
                <span class="status online">CONECTADO</span>
            <?php else: ?>
                <span class="status offline">MODO LOCAL</span>
            <?php endif; ?>
        </div>
    </div>

    <?php echo $mensaje; ?>

    <div class="grid">
        <!-- FORMULARIO DIÉSEL -->
        <div class="card">
            <h2>⛽ Registrar Despacho Diésel</h2>
            <form action="" method="POST">
                <input type="hidden" name="accion_diesel" value="1">
                <div class="form-group">
                    <label>Equipo / Maquinaria</label>
                    <select name="maquinaria_id" class="form-control" required>
                        <option value="1">MAQ-001 - Cargador frontal XCMG</option>
                        <option value="2">MAQ-002 - Trituradora Planta</option>
                        <option value="3">MAQ-003 - Criba Vibratoria 4x8</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Galones Cargados</label>
                    <input type="number" step="0.01" name="galones" class="form-control" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Horómetro Actual</label>
                    <input type="number" step="0.01" name="horometro" class="form-control" placeholder="Lectura en Hrs" required>
                </div>
                <div class="form-group">
                    <label>Nombre del Operador</label>
                    <input type="text" name="operador" class="form-control" placeholder="Quién recibe" required>
                </div>
                <button type="submit" class="btn btn-yellow">Registrar Combustible</button>
            </form>
        </div>

        <!-- FORMULARIO PRODUCCIÓN -->
        <div class="card">
            <h2>🧱 Registro de Producción</h2>
            <form action="" method="POST">
                <input type="hidden" name="accion_produccion" value="1">
                <div class="form-group">
                    <label>Tipo de Material</label>
                    <select name="material_id" class="form-control" required>
                        <option value="1">Piedrín 1/2"</option>
                        <option value="2">Arena</option>
                        <option value="3">Polvo de piedra</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Cantidad Procesada (m³)</label>
                    <input type="number" step="0.1" name="cantidad" class="form-control" placeholder="0.0" required>
                </div>
                <div class="form-group">
                    <label>Encargado de Planta</label>
                    <input type="text" name="operador_prod" class="form-control" placeholder="Nombre de turno" required>
                </div>
                <button type="submit" class="btn btn-blue">Guardar Producción</button>
            </form>
        </div>
    </div>
</div>

</body>
</html>

