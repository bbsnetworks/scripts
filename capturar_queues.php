<?php
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/routeros_api.class.php';

echo "<pre>";

function parseMikrotikResponse($read)
{
    $items = [];
    $current = [];

    foreach ($read as $line) {
        if ($line === '!re') {
            if (!empty($current)) {
                $items[] = $current;
                $current = [];
            }
            continue;
        }

        if ($line === '!done') {
            if (!empty($current)) {
                $items[] = $current;
            }
            break;
        }

        if (strpos($line, '=') === 0) {
            $parts = explode('=', $line, 3);

            if (count($parts) === 3) {
                $current[$parts[1]] = $parts[2];
            }
        }
    }

    return $items;
}

function guardarMedicion($conexion, $idnodo, $idpaquete, $nombreQueue, $rateRaw, $rateBps)
{
    $sql = "
        INSERT INTO mediciones_queue 
        (idnodo, idpaquete, nombre_queue, rate_raw, rate_bps, fecha_medicion)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";

    $stmt = $conexion->prepare($sql);

    if (!$stmt) {
        echo "❌ Error prepare guardarMedicion: " . $conexion->error . "\n";
        return false;
    }

    $stmt->bind_param("iissi", $idnodo, $idpaquete, $nombreQueue, $rateRaw, $rateBps);

    if (!$stmt->execute()) {
        echo "❌ Error execute guardarMedicion: " . $stmt->error . "\n";
        $stmt->close();
        return false;
    }

    $stmt->close();
    return true;
}

/**
 * Consulta un queue específico abriendo y cerrando conexión.
 * Variante usada:
 * /queue/tree/print
 * ?name=QUEUE
 * =stats=
 */
function consultarQueuePorNombre($gateway, $user, $password, $nombreQueue)
{
    $API = new RouterosAPI();
    $API->debug = false;

    if (!$API->connect($gateway, $user, $password)) {
        return [
            'ok' => false,
            'error' => 'No se pudo conectar al MikroTik',
            'read' => null,
            'data' => null
        ];
    }

    /*
        Equivalente buscado:
        queue tree print stats where name="10 Mbps_Down"

        Probamos mandando primero el filtro y luego stats.
    */
    $API->write('/queue/tree/print', false);
    $API->write('?name=' . $nombreQueue, false);
    $API->write('=stats=', true);

    $READ = $API->read(false);
    $API->disconnect();

    if (!is_array($READ)) {
        return [
            'ok' => false,
            'error' => 'Respuesta inválida',
            'read' => $READ,
            'data' => null
        ];
    }

    $queues = parseMikrotikResponse($READ);

    if (empty($queues)) {
        return [
            'ok' => false,
            'error' => 'Queue no encontrado',
            'read' => $READ,
            'data' => null
        ];
    }

    return [
        'ok' => true,
        'error' => null,
        'read' => $READ,
        'data' => $queues[0]
    ];
}

$sqlNodos = "
    SELECT idnodo, nombre, gateway, user, password
    FROM nodos
    WHERE gateway IS NOT NULL 
      AND gateway != ''
      AND user IS NOT NULL 
      AND user != ''
      AND password IS NOT NULL
";

$resultNodos = $conexion->query($sqlNodos);

if (!$resultNodos) {
    die("Error al consultar nodos: " . $conexion->error);
}

while ($nodo = $resultNodos->fetch_assoc()) {

    $idnodo = (int)$nodo['idnodo'];
    $nombreNodo = trim($nodo['nombre']);
    $gateway = trim($nodo['gateway']);
    $user = trim($nodo['user']);
    $password = $nodo['password'];

    echo "Conectando a nodo {$nombreNodo} ({$gateway})...\n";

    $sqlPaquetes = "
        SELECT idpaquete, nombre_queue
        FROM paquetes_nodo
        WHERE idnodo = ?
          AND activo = 1
          AND nombre_queue LIKE '%\\_Down'
        ORDER BY idpaquete ASC
    ";

    $stmtPaquetes = $conexion->prepare($sqlPaquetes);

    if (!$stmtPaquetes) {
        echo "❌ Error prepare paquetes: " . $conexion->error . "\n";
        continue;
    }

    $stmtPaquetes->bind_param("i", $idnodo);
    $stmtPaquetes->execute();
    $resultPaquetes = $stmtPaquetes->get_result();

    while ($paquete = $resultPaquetes->fetch_assoc()) {

        $idpaquete = (int)$paquete['idpaquete'];
        $nombreQueue = trim($paquete['nombre_queue']);

        $resultado = consultarQueuePorNombre(
            $gateway,
            $user,
            $password,
            $nombreQueue
        );

        if (!$resultado['ok']) {
            echo "⚠️ {$resultado['error']}: {$nombreNodo} | {$nombreQueue}\n";
            continue;
        }

        $data = $resultado['data'];

        $rateRaw = $data['rate'] ?? '0';
        $rateBps = (int)$rateRaw;
        $rateMbps = round($rateBps / 1000000, 2);

        guardarMedicion(
            $conexion,
            $idnodo,
            $idpaquete,
            $nombreQueue,
            $rateRaw,
            $rateBps
        );

        echo "✅ Guardado: {$nombreNodo} | {$nombreQueue} | {$rateMbps} Mbps\n";

        // Pausa pequeña entre consultas para no saturar el MikroTik
        usleep(300000); // 0.3 segundos
    }

    $stmtPaquetes->close();
}

echo "\n🚀 Proceso terminado.\n";