<?php
// Habilitar visualización de errores para saber exactamente qué pasa si algo falla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$DB_HOST = getenv('DB_HOST') ?: 'mariadb';
$DB_NAME = getenv('DB_NAME') ?: 'planta_agregados';
$DB_USER = getenv('DB_USER') ?: 'planta';
$DB_PASS = getenv('DB_PASSWORD') ?: 'PlantaDB2026!';

$conexion = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conexion->connect_errno) { 
    $conexion = null; 
}

function esc($texto) { 
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8'); 
}

$mensaje_feedback = "";

// PROCESAMIENTO DE ACCIONES EN CAMPO (POST)
if ($conexion && $_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $mensaje_feedback = "<script>alert('⛽ Registro Exitoso: Combustible y Horómetro actualizados.');</script>";
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje_feedback = "<script>alert('❌ Error: " . addslashes($e->getMessage()) . "');</script>";
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
                $mensaje_feedback = "<script>alert('🧱 Registro Exitoso: Inventario de agregados actualizado.');</script>";
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje_feedback = "<script>alert('❌ Error al procesar producción.');</script>";
            }
        }
    }
}

// VALORES POR DEFECTO PARA TELEMETRÍA
$totalComprasDiesel = 4500; 
$totalDespachosDiesel = 0; 
$dieselDisponible = 4500;
$capacidadMaximaCisterna = 5000; 
$porcentajeCisterna = 90;
$ventasHoy = 0.00; 
$produccionHoy = 0; 
$despachosHoy = 0;
$maquinarias = []; 
$materiales = [];

if ($conexion) {
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
        if($qMaq) { while ($row = $qMaq->fetch_assoc()) { $maquinarias[] = $row; } }

        $qMat = $conexion->query("SELECT id, nombre, existencia FROM materiales");
        if($qMat) { while ($row = $qMat->fetch_assoc()) { $materiales[] = $row; } }
    }
}

if (empty($maquinarias)) {
    $maquinarias = [
        ['id'=>1, 'nombre'=>'Cargador frontal XCMG', 'codigo'=>'MAQ-001', 'horometro_actual'=>1240.5, 'estado'=>'Operativa'],
        ['id'=>2, 'nombre'=>'Trituradora Planta', 'codigo'=>'MAQ-002', 'horometro_actual'=>3150.2, 'estado'=>'Operativa'],
        ['id'=>3, 'nombre'=>'Criba Vibratoria 4x8', 'codigo'=>'MAQ-003', 'horometro_actual'=>850.0, 'estado'=>'Operativa']
    ];
}
if (empty($materiales)) {
    $materiales = [
        ['id'=>1, 'nombre'=>'Piedrín 1/2"', 'existencia'=>128],
        ['id'=>2, 'nombre'=>'Arena', 'existencia'=>74],
        ['id'=>3, 'nombre'=>'Polvo de piedra', 'existencia'=>51]
    ];
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
        .tank-bar { height: 24px; background: #2b3139; border-radius: 12px; overflow: hidden; border: 1px solid #475260; }
        .tank-fill { height: 100%; background: linear-gradient(90deg, #f0b90b, var(--brand-color)); width: <?php echo $porcentajeCisterna; ?>%; }
        .actions-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; }
        .quick-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 15px; }
        .btn-quick { background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-primary); padding: 20px; border-radius: 8px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 10px; cursor: pointer; }
        .btn-quick:hover { border-color: var(--brand-color); color: var(--brand-color); }
        .metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .metric-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .metric-value { font-size: 28px; font-weight: 700; margin-top: 5px; }
        .table-panel { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { color: var(--text-secondary); padding: 12px; font-weight: 600; border-bottom: 1px solid var(--border-color); text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #1e2329; font-size: 14px; }

