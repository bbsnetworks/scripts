<?php
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/conexion.php';

echo "<pre>";

// Cambia este valor según lo que quieras conservar
$diasConservar = 15;

echo "Limpiando mediciones con más de {$diasConservar} días...\n";

$sql = "
    DELETE FROM mediciones_queue
    WHERE fecha_medicion < DATE_SUB(NOW(), INTERVAL ? DAY)
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error prepare: " . $conexion->error);
}

$stmt->bind_param("i", $diasConservar);

if (!$stmt->execute()) {
    die("Error execute: " . $stmt->error);
}

echo "Registros eliminados: " . $stmt->affected_rows . "\n";
echo "Limpieza terminada.\n";

$stmt->close();