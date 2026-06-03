<?php
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/conexion.php';

echo "<pre>";

$fecha = $_GET['fecha'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    die("Fecha inválida. Usa formato YYYY-MM-DD");
}

echo "Generando promedio diario para: {$fecha}\n";

$sql = "
    INSERT INTO promedios_diarios_queue (
        idnodo,
        idpaquete,
        nombre_queue,
        fecha,
        muestras,
        promedio_bps,
        promedio_mbps,
        max_bps,
        max_mbps,
        min_bps,
        min_mbps
    )
    SELECT
        idnodo,
        idpaquete,
        nombre_queue,
        DATE(fecha_medicion) AS fecha,

        COUNT(*) AS muestras,

        ROUND(AVG(rate_bps)) AS promedio_bps,
        ROUND(AVG(rate_bps) / 1000000, 2) AS promedio_mbps,

        MAX(rate_bps) AS max_bps,
        ROUND(MAX(rate_bps) / 1000000, 2) AS max_mbps,

        MIN(rate_bps) AS min_bps,
        ROUND(MIN(rate_bps) / 1000000, 2) AS min_mbps

    FROM mediciones_queue
    WHERE DATE(fecha_medicion) = ?
    GROUP BY idnodo, idpaquete, nombre_queue, DATE(fecha_medicion)

    ON DUPLICATE KEY UPDATE
        muestras = VALUES(muestras),
        promedio_bps = VALUES(promedio_bps),
        promedio_mbps = VALUES(promedio_mbps),
        max_bps = VALUES(max_bps),
        max_mbps = VALUES(max_mbps),
        min_bps = VALUES(min_bps),
        min_mbps = VALUES(min_mbps),
        updated_at = CURRENT_TIMESTAMP
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error prepare: " . $conexion->error);
}

$stmt->bind_param("s", $fecha);

if (!$stmt->execute()) {
    die("Error execute: " . $stmt->error);
}

echo "Promedios diarios guardados correctamente.\n";
echo "Registros afectados: " . $stmt->affected_rows . "\n";

$stmt->close();

echo "\nProceso terminado.\n";