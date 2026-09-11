<?php
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
                $mensaje_feedback = "<div class='alert success'>⛽ Carga de combustible e horómetro registrados con éxito.</div>";
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje_feedback = "<div class='alert danger'>❌ Error al procesar la carga: " . $e->getMessage() . "</div>";
            }
        }
    }

    if (isset($_POST['accion_produccion'])) {
        $mat_id   = intval($_POST['material_id']);
        $amount = floatval($_POST['cantidad']);
        $turno    = $conexion->real_escape_string($_POST['turno']);
        
        if ($mat_id > 0 && $amount > 0) {
            $conexion->begin_transaction();
            try {
                $stmt1 = $conexion->prepare("INSERT INTO produccion (material_id, cantidad, turno, operador) VALUES (?, ?, ?, 'Operador Planta')");
                $stmt1->bind_param("ids", $mat_id, $amount, $turno);
                $stmt1->execute();
                
                $stmt2 = $conexion->prepare("UPDATE materiales SET existencia = existencia + ? WHERE id = ?");
                $stmt2->bind_param("di", $amount, $mat_id);
                $stmt2->execute();
                
                $conexion->commit();
                $mensaje_feedback = "<div class='alert success'>🧱 Producción guardada e inventario actualizado.</div>";
            } catch (Exception $e) {
                $conexion->rollback();
                $mensaje_feedback = "<div class='alert danger'>❌ Error en registro de producción.</div>";
            }
        }
    }
}

$totalComprasDiesel = 0;
$totalDespachosDiesel = 0;
$dieselDisponible = 0;
$capacidadMaximaCisterna = 5000;
$porcentajeCisterna = 0;
$ventasHoy = 0.00;
$produccionHoy = 0;
$despachosHoy = 0;
$maquinarias = [];
$materiales = [];

if ($conexion) {
    $q1 = $conexion->query("SELECT COALESCE(SUM(galones), 0) AS total FROM combustible_compras");
    $totalComprasDiesel = $q1->fetch_assoc()['total'];

    $q2 = $conexion->query("SELECT COALESCE(SUM(galones), 0) AS total FROM combustible_despachos");
    $totalDespachosDiesel = $q2->fetch_assoc()['total'];
    
    if ($totalComprasDiesel == 0) { $totalComprasDiesel = 4500; }
    
    $dieselDisponible = max(($totalComprasDiesel - $totalDespachosDiesel), 0);
    $porcentajeCisterna = min(($dieselDisponible / $capacidadMaximaCisterna) * 100, 100);

    $q3 = $conexion->query("SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE DATE(fecha) = CURDATE()");
    $ventasHoy = $q3->fetch_assoc()['total'];

    $q4 = $conexion->query("SELECT COALESCE(SUM(cantidad), 0) AS total FROM produccion WHERE DATE(fecha) = CURDATE()");
    $produccionHoy = $q4->fetch_assoc()['total'];

    $q5 = $conexion->query("SELECT COUNT(*) AS total FROM despachos WHERE DATE(fecha) = CURDATE()");
    $despachosHoy = $q5->fetch_assoc()['total'];

    $qMaq = $conexion->query("SELECT id, nombre, codigo, horometro_actual, estado FROM maquinaria");
    while ($row = $qMaq->fetch_assoc()) { $maquinarias[] = $row; }

    $qMat = $conexion->query("SELECT id, nombre, existencia FROM materiales");
    while ($row = $qMat->fetch_assoc()) { $materiales[] = $row; }
}

$fecha = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IMS & Setup Fleet Control | Dashboard Autónomo</title>
    <style>
        :root {
            --bg-main: #0b0e11;
            --bg-card: #15191e;
            --bg-hover: #1e2329;
            --border-color: #2b3139;
            --text-primary: #eaecef;
            --text-secondary: #848e9c;
            --brand-color: #fcd535;
            --success: #0ecb81;
            --danger: #f6465d;
            --warning: #f0b90b;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Roboto, sans-serif; background: var(--bg-main); color: var(--text-primary); padding-bottom: 40px; }
        .layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background: var(--bg-card); border-right: 1px solid var(--border-color); padding: 20px; display: flex; flex-direction: column; justify-content: space-between; }
        .app-title { display: flex; align-items: center; gap: 10px; padding-bottom: 25px; border-bottom: 1px solid var(--border-color); }
        .app-title .badge { background: var(--brand-color); color: #000; padding: 6px; border-radius: 6px; font-weight: 900; }
        .nav-group { margin-top: 20px; }
        .nav-label { font-size: 11px; color: var(--text-secondary); letter-spacing: 1px; margin-bottom: 10px; text-transform: uppercase; }
        .nav-link { display: flex; align-items: center; gap: 12px; color: var(--text-primary); text-decoration: none; padding: 12px; border-radius: 8px; font-size: 14px; }
        .nav-link.active { background: var(--bg-hover); color: var(--brand-color); font-weight: bold; }
        .main-content { flex: 1; display: flex; flex-direction: column; }
        .topbar { height: 70px; background: var(--bg-card); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; padding: 0 30px; }
        .content-body { padding: 30px; max-width: 1600px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .alert { padding: 15px; border-radius: 8px; font-size: 14px; font-weight: 600; margin-bottom: 10px; }
        .alert.success { background: rgba(14, 203, 129, 0.15); color: var(--success); border: 1px solid var(--success); }
        .alert.danger { background: rgba(246, 70, 93, 0.15); color: var(--danger); border: 1px solid var(--danger); }
        .ims-row-critical { display: grid; grid-template-columns: 1.4fr 1fr; gap: 20px; }
        .tank-card, .form-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 22px; }
        .tank-volume { font-size: 38px; font-weight: 800; color: var(--brand-color); margin: 10px 0; }
        .tank-progress-bar { height: 20px; background: #2b3139; border-radius: 10px; overflow: hidden; margin: 15px 0; border: 1px solid #475260; }
        .tank-progress-fill { height: 100%; background: linear-gradient(90deg, var(--warning), var(--brand-color)); width: <?php echo $porcentajeCisterna; ?>%; transition: width 0.5s ease; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-size: 11px; color: var(--text-secondary); text-transform: uppercase; margin-bottom: 5px; font-weight: bold; }
        .form-control { width: 100%; background: var(--bg-main); border: 1px solid var(--border-color); padding: 10px; border-radius: 6px; color: white; font-size: 14px; }
        .form-control:focus { border-color: var(--brand-color); outline: none; }
        .btn-submit { width: 100%; background: var(--brand-color); color: black; border: none; padding: 12px; border-radius: 6px; font-weight: bold; cursor: pointer; text-transform: uppercase; font-size: 12px; margin-top: 5px; }
        .btn-submit:hover { background: #f3ba2f; }
        .ops-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
        .ops-card { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        .ops-value { font-size: 26px; font-weight: 700; margin-top: 5px; }
        .panel-table { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th { color: var(--text-secondary); padding: 12px; font-weight: 600; border-bottom: 1px solid var(--border-color); text-align: left; font-size: 11px; text-transform: uppercase; }
        td { padding: 14px 12px; border-bottom: 1px solid #1e2329; }
        .status-pill { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; }
        .status-pill.op { background: rgba(14, 203, 129, 0.15); color: var(--success); }
        .status-pill.maint { background: rgba(240, 185, 11, 0.15); color: var(--warning); }
        @media (max-width: 1200px) { .ims-row-critical, .ops-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div>
            <div class="app-title">
                <div class="badge">IMS</div>
                <div>
                    <h2 style="font-size: 16px; font-weight:800;">FLEET CONTROL</h2>
                    <span style="font-size: 10px; color: var(--text-secondary);">Sistema Planta V2</span>
                </div>
            </div>
            <div class="nav-group">
                <div class="nav-label">Combustible e Insumos</div>
                <a href="#" class="nav-link active">📊 Telemetría de Cisterna</a>
                <a href="#" class="nav-link">⛽ Historial de Despachos</a>
            </div>
            <div class="nav-group">
                <div class="nav-label">Operaciones Planta</div>
                <a href="#" class="nav-link">⚙️ Estado de Maquinarias</a>
                <a href="#" class="nav-link">🧱 Registro Producción</a>
            </div>
        </div>
        <div style="font-size:12px; color:var(--text-secondary); border-top: 1px solid var(--border-color); padding-top:15px;">
            Term: <strong>Báscula_Central</strong>
        </div>
    </aside>

    <main class="main-content">
        <div class="topbar">
            <div style="font-size: 13px; color: var(--success);">● Servidor de Telemetría Activo</div>
            <div style="font-size: 13px; color: var(--text-secondary);">📅 Sincronización: <?php echo $fecha; ?></div>
        </div>

        <div class="content-body">
            <?php echo $mensaje_feedback; ?>
            <div class="ims-row-critical">
                <div class="tank-card">
                    <h3 style="font-size:13px; text-transform: uppercase; color: var(--text-secondary);">Cisterna Principal de Almacenamiento</h3>
                    <div class="tank-volume"><?php echo number_format($dieselDisponible, 1); ?> <span style="font-size:14px; color:white; font-weight:normal;">Galones Reales</span></div>
                    <div class="tank-progress-bar"><div class="tank-progress-fill"></div></div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; color: var(--text-secondary);">
                        <span>Capacidad Máxima: <?php echo number_format($capacidadMaximaCisterna); ?> Gal</span>
                        <span>Volumen de Capacidad: <?php echo number_format($porcentajeCisterna, 1); ?>%</span>
                    </div>
                    <div style="margin-top: 20px; font-size: 12px; border-top:1px solid var(--border-color); padding-top:10px; color:var(--text-secondary);">
                        Consumo histórico registrado a equipos: <strong><?php echo number_format($totalDespachosDiesel); ?> Galones</strong>
                    </div>
                </div>

                <div class="form-card">
                    <h3 style="font-size:13px; text-transform: uppercase; color: var(--brand-color); margin-bottom:12px;">Despacho de Combustible en Campo</h3>
                    <form action="" method="POST">
                        <input type="hidden" name="accion_diesel" value="1">
                        <div class="form-group">
                            <label>Seleccionar Maquinaria / Equipo</label>
                            <select name="maquinaria_id" class="form-control" required>
                                <option value="">-- Seleccione Unidad --</option>
                                <?php foreach($maquinarias as $maq): ?>
                                    <option value="<?php echo $maq['id']; ?>"><?php echo esc($maq['codigo'] . ' - ' . $maq['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                            <div class="form-group">
                                <label>Galones Despachados</label>
                                <input type="number" step="0.01" name="galones" class="form-control" placeholder="0.00" required>
                            </div>
                            <div class="form-group">
                                <label>Horómetro Actual Unidad</label>
                                <input type="number" step="0.01" name="horometro" class="form-control" placeholder="Hrs" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Nombre Operador de Turno</label>
                            <input type="text" name="operador" class="form-control" placeholder="Nombre completo" required>
                        </div>
                        <button type="submit" class="btn-submit">⚡ Registrar Carga e Horómetro</button>
                    </form>
                </div>
            </div>

            <div class="ops-grid">
                <div class="ops-card">
                    <div>
                        <div style="font-size:11px; color:var(--text-secondary); text-transform:uppercase;">Facturación Ventas Hoy</div>
                        <div class="ops-value">Q<?php echo number_format($ventasHoy, 2); ?></div>
                    </div>
                    <span style="font-size:24px;">🪙</span>
                </div>
                <div class="form-card" style="padding:15px;">
                    <h4 style="font-size:11px; text-transform:uppercase; margin-bottom:8px; color:var(--text-secondary);">Ingreso de Producción Diaria</h4>
                    <form action="" method="POST" style="display:flex; gap:8px;">
                        <input type="hidden" name="accion_produccion" value="1">
                        <select name="material_id" class="form-control" style="padding:6px; font-size:12px;" required>
                            <?php foreach($materiales as $mat): ?>
                                <option value="<?php echo $mat['id']; ?>"><?php echo esc($mat['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" step="0.1" name="amount" class="form-control" style="padding:6px; font-size:12px; width:90px;" placeholder="m³" required>
                        <input type="hidden" name="turno" value="Turno Central">
                        <button type="submit" class="btn-submit" style="margin-top:0; padding:6px; width:70px;">+</button>
                    </form>
                </div>
                <div class="ops-card">
                    <div>
                        <div style="font-size:11px; color:var(--text-secondary); text-transform:uppercase;">Triturado Hoy</div>
                        <div class="ops-value"><?php echo number_format($produccionHoy, 1); ?> m³</div>
                    </div>
                    <span style="font-size:24px;">🏗️</span>
                </div>
            </div>

            <div class="panel-table">
                <h3 style="font-size:13px; text-transform: uppercase; color:var(--text-secondary);">Monitoreo y Mantenimiento de Flota Activa</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Equipo / Maquinaria Industrial</th>
                            <th>Horómetro Actualizado</th>
                            <th>Estatus Operativo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($maquinarias)): ?>
                            <tr><td colspan="4" style="text-align: center; color:var(--text-secondary);">No hay maquinaria mapeada. Ejecuta el archivo 01_schema.sql.</td></tr>
                        <?php else: ?>
                            <?php foreach($maquinarias as $maq): ?>
                            <tr>
                                <td style="font-weight: 700; color:var(--brand-color);"><?php echo esc($maq['codigo']); ?></td>
                                <td><?php echo esc($maq['nombre']); ?></td>
                                <td><strong><?php echo number_format($maq['horometro_actual'], 2); ?> Hrs</strong></td>
                                <td><span class="status-pill <?php echo ($maq['estado'] == 'Operativa') ? 'op' : 'maint'; ?>"><?php echo esc($maq['estado']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>