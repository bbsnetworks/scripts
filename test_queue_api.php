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

function imprimirResultado($titulo, $read)
{
    echo "\n==============================\n";
    echo $titulo . "\n";
    echo "==============================\n";

    echo "\nRAW:\n";
    print_r($read);

    $items = parseMikrotikResponse($read);

    echo "\nPARSEADO:\n";
    print_r($items);

    foreach ($items as $item) {
        echo "\n--- ITEM ---\n";
        echo ".id: " . ($item['.id'] ?? 'SIN ID') . "\n";
        echo "name: " . ($item['name'] ?? 'SIN NAME') . "\n";
        echo "parent: " . ($item['parent'] ?? 'SIN PARENT') . "\n";
        echo "packet-mark: " . ($item['packet-mark'] ?? 'SIN PACKET-MARK') . "\n";
        echo "bytes: " . ($item['bytes'] ?? 'SIN BYTES') . "\n";
        echo "rate: " . ($item['rate'] ?? 'SIN RATE') . "\n";
        echo "pcq-queues: " . ($item['pcq-queues'] ?? 'SIN PCQ') . "\n";
    }
}

function consultar($gateway, $user, $password, $nombreQueue, $variante)
{
    $API = new RouterosAPI();
    $API->debug = false;

    if (!$API->connect($gateway, $user, $password)) {
        return ['error' => 'No conectó'];
    }

    if ($variante === 'A') {
        // Forma que ya usamos
        $API->write('/queue/tree/print', false);
        $API->write('=stats=', false);
        $API->write('?name=' . $nombreQueue, true);
    }

    if ($variante === 'B') {
        // Filtro primero, stats al final
        $API->write('/queue/tree/print', false);
        $API->write('?name=' . $nombreQueue, false);
        $API->write('=stats=', true);
    }

    if ($variante === 'C') {
        // Sin filtro, trae todo y luego buscamos en PHP
        $API->write('/queue/tree/print', false);
        $API->write('=stats=', true);
    }

    if ($variante === 'D') {
        // Con proplist completa
        $API->write('/queue/tree/print', false);
        $API->write('=stats=', false);
        $API->write('=.proplist=.id,name,parent,packet-mark,bytes,packets,dropped,rate,packet-rate,queued-bytes,queued-packets,pcq-queues,disabled,invalid', false);
        $API->write('?name=' . $nombreQueue, true);
    }

    $read = $API->read(false);
    $API->disconnect();

    return $read;
}

// Datos de Morelos desde tu tabla nodos
$sql = "
    SELECT idnodo, nombre, gateway, user, password
    FROM nodos
    WHERE idnodo = 45
    LIMIT 1
";

$res = $conexion->query($sql);

if (!$res || $res->num_rows === 0) {
    die("No se encontró el nodo Morelos");
}

$nodo = $res->fetch_assoc();

$gateway = trim($nodo['gateway']);
$user = trim($nodo['user']);
$password = $nodo['password'];

$nombreQueue = '10 Mbps_Down';

echo "Nodo: {$nodo['nombre']} ({$gateway})\n";
echo "Queue probado: {$nombreQueue}\n";

$readA = consultar($gateway, $user, $password, $nombreQueue, 'A');
imprimirResultado('VARIANTE A: stats + ?name', $readA);

sleep(1);

$readB = consultar($gateway, $user, $password, $nombreQueue, 'B');
imprimirResultado('VARIANTE B: ?name + stats', $readB);

sleep(1);

$readD = consultar($gateway, $user, $password, $nombreQueue, 'D');
imprimirResultado('VARIANTE D: stats + proplist + ?name', $readD);

sleep(1);

$readC = consultar($gateway, $user, $password, $nombreQueue, 'C');
$itemsC = parseMikrotikResponse($readC);

$filtrados = [];

foreach ($itemsC as $item) {
    if (($item['name'] ?? '') === $nombreQueue) {
        $filtrados[] = $item;
    }
}

echo "\n==============================\n";
echo "VARIANTE C: traer todo y filtrar en PHP\n";
echo "==============================\n";

echo "\nCoincidencias exactas con {$nombreQueue}:\n";
print_r($filtrados);

foreach ($filtrados as $item) {
    echo "\n--- ITEM FILTRADO ---\n";
    echo ".id: " . ($item['.id'] ?? 'SIN ID') . "\n";
    echo "name: " . ($item['name'] ?? 'SIN NAME') . "\n";
    echo "parent: " . ($item['parent'] ?? 'SIN PARENT') . "\n";
    echo "packet-mark: " . ($item['packet-mark'] ?? 'SIN PACKET-MARK') . "\n";
    echo "bytes: " . ($item['bytes'] ?? 'SIN BYTES') . "\n";
    echo "rate: " . ($item['rate'] ?? 'SIN RATE') . "\n";
    echo "pcq-queues: " . ($item['pcq-queues'] ?? 'SIN PCQ') . "\n";
}

echo "\nFIN DE PRUEBA\n";