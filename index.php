<?php
// 1. BLINDAJE DE ERRORES (Evita que PHP colapse o se quede en negro si falla la base de datos)
error_reporting(0);
ini_set('display_errors', 0);

$DB_HOST = getenv('DB_HOST') ?: 'mariadb';
$DB_NAME = getenv('DB_NAME') ?: 'planta_agregados';
$DB_USER = getenv('DB_USER') ?: 'planta';
$DB_PASS = getenv('DB_PASSWORD') ?: 'PlantaDB2026!';

// Intentamos conectar con un límite de tiempo estricto de 1 segundo
$conexion = mysqli_init();
$db_online = false;

if ($conexion) {
    mysqli_options($conexion, MYSQLI_OPT_CONNECT_TIMEOUT, 1);
    // El símbolo @ evita que PHP imprima advertencias internas en el navegador
    if (@mysqli_real_connect($conexion, $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME)) {
        $db_online = true;
    } else {
        $conexion = null;
    }
}

function esc($texto) { 
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); 
}

$mensaje_feedback = "";
$status_badge = "<span style='padding: 5px 10px; border-radius: 20px; background: rgba(14, 203, 129, 0.15); color: #0ecb81; font-size: 12px; font-weight: bold;'>● TELEMETRÍA EN VIVO</span>";

if (!$db_online) {
    $status_badge = "<span style='padding: 5px 10px; border-radius: 20px; background: rgba(246, 70, 93, 0.15); color: #f6465d; font-size: 12px; font-weight: bold;'>⚠️ MODO RESPALDO (SINCRO BD PENDIENTE)</span>";
}

// 2. PROCESAMIENTO DE FORMULARIOS (Solo si la BD está activa)
if ($db_online && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['accion_diesel'])) {
        $maq_id   = intval($_POST['maquinaria_id']);
        $galones  = floatval($_POST['galones']);
        $horometro = floatval($_POST['horometro']);
        $operador  = $conexion->real_escape_string($_POST['operador']);
        
        if ($maq_id > 0 && $galones > 0 && $horometro > 0) {
            $conexion->begin_transaction();
            try {
                $stmt1 = $conexion->prepare("INSERT INTO combustible_despachos (maquinaria_id, operador, galones, horometro_despacho) VALUES (?, ?, ?, ?)");
                $stmt1->bind_param("isdd", $maq_id, $operador, $galones, $horometro);
                $stmt1->execute();
                
                $stmt2 = $conexion->prepare("UPDATE maquinaria SET horometro_actual = ? WHERE id = ?");
                $stmt2->bind_param("di", $horometro, $maq_id);
                $stmt2->execute();
                
                $conexion->commit();
                $mensaje_feedback = "<script>alert('⛽ Registro Exitoso: Combustible e Horómetro actualizados.');</script>";
            } catch (Exception $e) {
                $conexion->rollback();
            }
        }
    }

    if (isset($_POST['accion_produccion'])) {
        $mat_id   = intval($_POST['material_id']);
        $cantidad = floatval($_POST['cantidad']);
        $turno    = $conexion->real_escape_string($_POST['turno']);
        $operador_prod = $conexion->real_escape_string($_POST['operador_prod']);
        
        if ($mat_id > 0 && $cantidad > 0) {
            $conexion->begin_transaction();
            try {
                $stmt1 = $conexion->prepare("INSERT INTO produccion (material_id, cantidad, turno, operador) VALUES (?, ?, ?, ?)");
                $stmt1->bind_param("idss", $mat_id, $cantidad, $turno, $operador_prod);
                $stmt1->execute();
                
                $stmt2 = $conexion->prepare("UPDATE materiales SET existencia = existencia + ? WHERE id = ?");
                $stmt2->bind_param("di", $cantidad, $mat_id);
                $stmt2->execute();
                
                $conexion->commit();
                $mensaje_feedback = "<script>alert('🧱 Registro Exitoso: Producción añadida al inventario.');</script>";
            } catch (Exception $e) {
                $conexion->rollback();
            }
        }
    }
}

// 3. VALORES SEMILLA DE CONTROL (Garantizan que la pantalla cargue pase lo que pase)
$totalComprasDiesel = 4500; $totalDespachosDiesel = 0; $dieselDisponible = 4500;
$capacidadMaximaCisterna = 5000; $porcentajeCisterna = 90;
$ventasHoy = 0.00; $produccionHoy = 0; $despachosHoy = 0;

$maquinarias = [
    ['id'=>1, 'nombre'=>'Cargador frontal XCMG', 'codigo'=>'MAQ-001', 'horometro_actual'=>1240.5, 'estado'=>'Operativa'],
    ['id'=>2, 'nombre'=>'Trituradora Planta', 'codigo'=>'MAQ-002', 'horometro_actual'=>3150.2, 'estado'=>'Operativa'],
    ['id'=>3, 'nombre'=>'Criba Vibratoria 4x8', 'codigo'=>'MAQ-003', 'horometro_actual'=>850.0, 'estado'=>'Operativa']
];
$materiales = [
    ['id'=>1, 'nombre'=>'Piedrín 1/2"', 'existencia'=>128],
    ['id'=>2, 'nombre'=>'Arena', 'existencia'=>74],
    ['id'=>3, 'nombre'=>'Polvo de piedra', 'existencia'=>51]
];

// 4. EXTRACCIÓN EN TIEMPO REAL (Solo si la base de datos responde)
if ($db_online) {
    $testTablas = $conexion->query("SHOW TABLES LIKE 'combustible_compras'");
    if ($testTablas && $testTablas->num_rows > 0) {
        $q1 = $conexion->query("SELECT COALESCE(SUM(galones), 0) AS total FROM combustible_compras");
        if($q1) { $totalComprasDiesel = $q1->fetch_assoc()['total']; }

        $q2 = $conexion->query("SELECT COALESCE(SUM(galones), 0) AS total FROM combustible_despachos");
        if($q2) { $totalDespachosDiesel = $q2->fetch_assoc()['total']; }
        
        $dieselDisponible = max(($totalComprasDiesel - $totalDespachosDiesel), 0);
        $porcentajeCisterna = min(($dieselDisponible / $capacidadMaximaCisterna) * 100, 100);

        $q3 = $conexion->query("SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE DATE(fecha) = CURDATE()");
        if($q3) { $ventasHoy = $q3->fetch_assoc()['total']; }

        $q4 = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) AS total FROM produccion WHERE DATE(fecha) = CURDATE()");
        if($q4) { $produccionHoy = $q4->fetch_assoc()['total']; }

        $q5 = $conexion->query("SELECT COUNT(*) AS total FROM despachos WHERE DATE(fecha) = CURDATE()");
        if($q5) { $despachosHoy = $q5->fetch_assoc()['total']; }

        $qMaq = $conexion->query("SELECT id, nombre, codigo, horometro_actual, estado FROM maquinaria");
        if($qMaq && $qMaq->num_rows > 0) { 
            $maquinarias = [];
            while ($row = $qMaq->fetch_assoc()) { $maquinarias[] = $row; } 
        }

        $qMat = $conexion->query("SELECT id, nombre, existencia FROM materiales");
        if($qMat && $qMat->num_rows > 0) { 
            $materiales = [];
            while ($row = $qMat->fetch_assoc()) { $materiales[] = $row; } 
        }
    }
}
$fecha = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Fleet & IMS | Panel Industrial</title>
    <style>
        :root { --bg-main: #0b0e11; --bg-card: #15191e; --bg-hover: #1e2329; --border-color: #2b3139; --text-primary: #eaecef; --text-secondary: #848e9c; --brand-color: #fcd535; --success: #0ecb81; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: var(--bg-main); color: var(--text-primary); }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--bg-card); border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
        .app-title { display: flex; align-items: center; gap: 10px; padding-bottom: 25px; border-bottom: 1px solid var(--border-color); }
        .app-title .badge { background: var(--brand-color); color: #000; padding: 6px; border-radius: 6px; font-weight: 900; }
        .nav-label { font-size: 11px; color: var(--text-secondary); letter-spacing: 1px; margin: 20px 0 10px; text-transform: uppercase; display: block; }
        .nav-link { display: flex; align-items: center; gap: 12px; color: var(--text-primary); text-decoration: none; padding: 12px; border-radius: 8px; font-size: 14px; }
        .nav-link.active { background: var(--bg-hover); color: var(--brand-color); font-weight: bold; }
        .main-content { flex: 1; display: flex; flex-direction: column; }
        .topbar { height: 70px; background: var(--bg-card); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 30px; }
        .content-body { padding: 30px; max-width: 1600px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .ims-critical-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 20px; }
        .tank-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 25px; }
        .tank-volume { font-size: 42px; font-weight: 800; color: var(--brand-color); margin: 10px 0; }
        .tank-bar { height: 24px; background: #2b3139; border-radius: 12px; overflow: hidden; border: 1px solid #475260; margin: 12px 0; }
        .tank-fill { height: 100%; background: linear-gradient(90deg, #f0b90b, var(--brand-color)); width: <?php echo $porcentajeCisterna; ?>%; }
        .actions-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; }
        .quick-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 15px; }
        .btn-quick { background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); padding: 20px; border-radius: 8px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer; }
        .btn-quick:hover { border-color: var(--brand-color); color: var(--brand-color); }
        .metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
