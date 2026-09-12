<?php
error_reporting(0);
ini_set('display_errors', 0);

$DB_HOST = getenv('DB_HOST') ?: 'mariadb';
$DB_NAME = getenv('DB_NAME') ?: 'planta_agregados';
$DB_USER = getenv('DB_USER') ?: 'planta';
$DB_PASS = getenv('DB_PASSWORD') ?: 'PlantaDB2026!';

$conexion = @mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
$db_online = ($conexion) ? true : false;

$mensaje = "";
$ticket_data = null; 

// PROCESAMIENTO DE FORMULARIOS DIRECTOS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. CONTROL DE COMPRA - INGRESO MAESTRO DE FACTURA
    if (isset($_POST['accion_compra'])) {
        $galones = floatval($_POST['galones_compra']);
        $factura = mysqli_real_escape_string($conexion, $_POST['factura']);
        $proveedor = mysqli_real_escape_string($conexion, $_POST['proveedor']);
        $llevado_en = mysqli_real_escape_string($conexion, $_POST['llevado_en']);
        $encargado = mysqli_real_escape_string($conexion, $_POST['encargado_registro']);
        
        $obs = "Control de Compra - Transportado en: " . $llevado_en . " | Registrado por: " . $encargado;

        if ($db_online) {
            $sql = "INSERT INTO combustible_compras (fecha, proveedor, galones, factura, observaciones) 
                    VALUES (CURDATE(), '$proveedor', $galones, '$factura', '$obs')";
            if (@mysqli_query($conexion, $sql)) {
                $mensaje = "<div class='alert success'>✅ Control de Compra Registrado: Factura $factura guardada. Imprimiendo vale...</div>";
                
                $ticket_data = [
                    'titulo' => 'VALE DE CONTROL DE COMPRA E INGRESO',
                    'factura' => $factura,
                    'galones' => $galones,
                    'proveedor' => $proveedor,
                    'llevado_en' => $llevado_en,
                    'encargado' => $encargado,
                    'fecha' => date('d/m/Y H:i:s')
                ];
            } else {
                $mensaje = "<div class='alert error'>❌ Error al registrar en la base de datos.</div>";
            }
        } else {
            $mensaje = "<div class='alert success'>⛽ [Simulación Local] Generando Comprobante de Control de Compra...</div>";
            $ticket_data = [
                'titulo' => 'VALE DE CONTROL DE COMPRA (MODO LOCAL)',
                'factura' => $factura,
                'galones' => $galones,
                'proveedor' => $proveedor,
                'llevado_en' => $llevado_en,
                'encargado' => $encargado,
                'fecha' => date('d/m/Y H:i:s')
            ];
        }
    }

    // 2. DESPACHO INDIVIDUAL A MAQUINARIA
    if (isset($_POST['accion_diesel'])) {
        $galones = floatval($_POST['galones']);
        $horometro = floatval($_POST['horometro']);
        $maq_id = intval($_POST['maquinaria_id']);
        $operador = mysqli_real_escape_string($conexion, $_POST['operador']);
        
        if ($db_online) {
            @mysqli_query($conexion, "INSERT INTO combustible_despachos (maquinaria_id, operador, galones, horometro_despacho) VALUES ($maq_id, '$operador', $galones, $horometro)");
            @mysqli_query($conexion, "UPDATE maquinaria SET horometro_actual = $horometro WHERE id = $maq_id");
        }
        $mensaje = "<div class='alert success'>⛽ Combustible Despachado: $galones Gal consumidos por Equipo ID $maq_id.</div>";
    }

    // 3. REGISTRO DE PRODUCCIÓN
    if (isset($_POST['accion_produccion'])) {
        $cantidad = floatval($_POST['cantidad']);
        if ($db_online) {
            $mat_id = intval($_POST['material_id']);
            $operador_prod = mysqli_real_escape_string($conexion, $_POST['operador_prod']);
            @mysqli_query($conexion, "INSERT INTO produccion (material_id, cantidad, turno, operador) VALUES ($mat_id, $cantidad, 'Turno Central', '$operador_prod')");
        }
        $mensaje = "<div class='alert success'>🧱 Producción Registrada: $cantidad m³ guardados.</div>";
    }
}

// CÁLCULO DE BALANCE GENERAL
$totalIngresado = 0.00; 
$totalConsumido = 0.00;
$disponible = 0.00;

if ($db_online) {
    $res1 = mysqli_query($conexion, "SELECT SUM(galones) as total FROM combustible_compras");
    if ($res1) {
        $row = mysqli_fetch_assoc($res1);
        if ($row['total'] > 0) $totalIngresado = floatval($row['total']);
    }
    $res2 = mysqli_query($conexion, "SELECT SUM(galones) as total FROM combustible_despachos");
    if ($res2) {
        $row = mysqli_fetch_assoc($res2);
        if ($row['total'] > 0) $totalConsumido = floatval($row['total']);
    }
    $disponible = max(($totalIngresado - $totalConsumido), 0);
} else {
    $totalIngresado = 4500.00; $disponible = 4500.00;
}

$fecha = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Planta - Gestión de Diésel</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f4f6f8; color: #333; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { background: #15191e; color: #fff; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .status { font-size: 12px; font-weight: bold; padding: 4px 8px; border-radius: 4px; }
        .online { background: #28a745; color: #fff; }
        .offline { background: #ffc107; color: #000; }
        
        .balance-bar { background: #fff; border: 1px solid #e1e4e8; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; gap: 20px; }
        .balance-item { flex: 1; text-align: center; border-right: 1px solid #eee; }
        .balance-item:last-child { border-right: none; }
        .balance-item h3 { margin: 0; font-size: 12px; color: #666; text-transform: uppercase; }
        .balance-item p { margin: 5px 0 0; font-size: 22px; font-weight: bold; color: #15191e; }
        .balance-item p.highlight { color: #fcd535; background: #15191e; padding: 2px 8px; border-radius: 4px; display: inline-block; }

        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .card { background: #fff; border: 1px solid #e1e4e8; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .card h2 { margin-top: 0; font-size: 14px; border-bottom: 2px solid #f4f6f8; padding-bottom: 10px; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 0.3px; color: #111; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-size: 11px; font-weight: bold; margin-bottom: 5px; text-transform: uppercase; color: #555; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 14px; }
        
        .btn { width: 100%; padding: 12px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 12px; }
        .btn-black { background: #15191e; color: #fff; }
        .btn-yellow { background: #fcd535; color: #000; }
        .btn-blue { background: #0056b3; color: #fff; }
        
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; font-size: 14px; text-align: center; }
        .alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        #seccion-ticket-imprimible { display: none; }

        @media print {
            body * { display: none !important; }
            #seccion-ticket-imprimible, #seccion-ticket-imprimible * { display: block !important; }
            #seccion-ticket-imprimible { position: absolute; left: 0; top: 0; width: 76mm; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #000; padding: 5px; line-height: 1.4; }
            .ticket-lineas { border-top: 1px dashed #000; margin: 10px 0; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .espacio-firma { margin-top: 35px; text-align: center; font-size: 11px; }
        }
    </style>
</head>
<body>

<?php if ($ticket_data): ?>
<div id="seccion-ticket-imprimible">
    <div class="text-center">
        <strong>*** PLANTA DE AGREGADOS V2 ***</strong><br>
        <small>AUDITORÍA DE COMBUSTIBLE E INSUMOS</small><br>
        <span>--------------------------------</span><br>
        <strong><?php echo $ticket_data['titulo']; ?></strong>
    </div>
    <div class="ticket-lineas"></div>
    <div>
        <strong>Fecha/Hora:</strong> <?php echo $ticket_data['fecha']; ?><br>
        <strong>No. Factura:</strong> <?php echo $ticket_data['factura']; ?><br>
        <strong>Proveedor:</strong> <?php echo $ticket_data['proveedor']; ?><br>
        <strong>Llevado En:</strong> <?php echo $ticket_data['llevado_en']; ?><br>
        <strong>Registrado Por:</strong> <?php echo $ticket_data['encargado']; ?><br>
    </div>
    <div class="ticket-lineas"></div>
    <div style="font-size: 14px; font-weight: bold; display: flex; justify-content: space-between;">
        <span>TOTAL COMPRADO:</span>
        <span class="text-right"><?php echo number_format($ticket_data['galones'], 2); ?> GAL</span>
    </div>
    <div class="ticket-lineas"></div>
    
    <div class="espacio-firma">
        <span>___________________________</span><br>
        <span>Firma Encargado Planta</span>
    </div>
    <div class="espacio-firma">
        <span>___________________________</span><br>
        <span>Firma Piloto / Distribuidor</span>
    </div>
    
    <div class="text-center" style="margin-top: 25px;">
        <small>SISTEMA DE CONTROL DE COMPRAS</small><br>
