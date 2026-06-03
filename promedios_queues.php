<?php
date_default_timezone_set('America/Mexico_City');

require_once __DIR__ . '/conexion.php';

$periodo = $_GET['periodo'] ?? 'diario';
$fecha   = $_GET['fecha'] ?? date('Y-m-d');
$mes     = $_GET['mes'] ?? date('Y-m');

$periodosValidos = ['diario', 'semanal', 'mensual'];

if (!in_array($periodo, $periodosValidos)) {
    $periodo = 'diario';
}

$tituloPeriodo = '';
$where = '';
$params = [];
$types = '';

if ($periodo === 'diario') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = date('Y-m-d');
    }

    $tituloPeriodo = "Promedios del día {$fecha}";

    $where = "p.fecha = ?";
    $params[] = $fecha;
    $types .= 's';
}

if ($periodo === 'semanal') {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        $fecha = date('Y-m-d');
    }

    $timestamp = strtotime($fecha);
    $inicioSemana = date('Y-m-d', strtotime('monday this week', $timestamp));
    $finSemana = date('Y-m-d', strtotime('sunday this week', $timestamp));

    $tituloPeriodo = "Promedios semanales del {$inicioSemana} al {$finSemana}";

    $where = "p.fecha BETWEEN ? AND ?";
    $params[] = $inicioSemana;
    $params[] = $finSemana;
    $types .= 'ss';
}

if ($periodo === 'mensual') {
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
        $mes = date('Y-m');
    }

    $inicioMes = $mes . '-01';
    $finMes = date('Y-m-t', strtotime($inicioMes));

    $tituloPeriodo = "Promedios mensuales de {$mes}";

    $where = "p.fecha BETWEEN ? AND ?";
    $params[] = $inicioMes;
    $params[] = $finMes;
    $types .= 'ss';
}

$sql = "
    SELECT
        CASE
            WHEN n.idnodo IN (24, 25, 26) THEN 24
            WHEN n.idnodo IN (35, 37, 38, 39, 40, 42, 44, 45) THEN 1001
            WHEN n.idnodo IN (41, 43) THEN 1002
            ELSE n.idnodo
        END AS idgrupo,

        CASE
            WHEN n.idnodo IN (24, 25, 26) THEN 'Enguaro'
            WHEN n.idnodo IN (35, 37, 38, 39, 40, 42, 44, 45) THEN 'Uriangato'
            WHEN n.idnodo IN (41, 43) THEN 'Moroleón'
            ELSE n.nombre
        END AS nodo,

        p.nombre_queue,

        COUNT(*) AS dias_calculados,
        SUM(p.muestras) AS total_muestras,

        ROUND(AVG(p.promedio_mbps), 2) AS promedio_mbps,
        ROUND(MAX(p.max_mbps), 2) AS max_mbps,
        ROUND(MIN(p.min_mbps), 2) AS min_mbps

    FROM promedios_diarios_queue p
    INNER JOIN nodos n ON n.idnodo = p.idnodo
    WHERE {$where}
    GROUP BY 
        CASE
            WHEN n.idnodo IN (24, 25, 26) THEN 24
            WHEN n.idnodo IN (35, 37, 38, 39, 40, 42, 44, 45) THEN 1001
            WHEN n.idnodo IN (41, 43) THEN 1002
            ELSE n.idnodo
        END,
        CASE
            WHEN n.idnodo IN (24, 25, 26) THEN 'Enguaro'
            WHEN n.idnodo IN (35, 37, 38, 39, 40, 42, 44, 45) THEN 'Uriangato'
            WHEN n.idnodo IN (41, 43) THEN 'Moroleón'
            ELSE n.nombre
        END,
        p.nombre_queue

    ORDER BY nodo ASC, CAST(p.nombre_queue AS UNSIGNED) ASC
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error prepare: " . $conexion->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();

$result = $stmt->get_result();

$datosPorNodo = [];
$totalRegistros = 0;
$sumaPromedios = 0;
$totalMuestras = 0;
$nodosUnicos = [];

while ($row = $result->fetch_assoc()) {
    $nodo = $row['nodo'];

    if (!isset($datosPorNodo[$nodo])) {
        $datosPorNodo[$nodo] = [];
    }

    $datosPorNodo[$nodo][] = $row;

    $totalRegistros++;
    $sumaPromedios += (float)$row['promedio_mbps'];
    $totalMuestras += (int)$row['total_muestras'];
    $nodosUnicos[$row['idgrupo']] = true;
}

$stmt->close();

$promedioGeneral = $totalRegistros > 0
    ? round($sumaPromedios / $totalRegistros, 2)
    : 0;

$totalNodos = count($nodosUnicos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Promedios MikroTik</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>

<body class="min-h-screen bg-[#071322] text-slate-100">

    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-32 -left-32 h-96 w-96 rounded-full bg-cyan-500/20 blur-3xl"></div>
        <div class="absolute top-1/3 -right-32 h-96 w-96 rounded-full bg-blue-600/20 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/3 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl"></div>
    </div>

    <main class="mx-auto max-w-7xl px-4 py-8">

        <header class="mb-8 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <p class="mb-2 inline-flex items-center rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs text-cyan-200">
                    <i class="bi bi-router mr-2"></i>
                    Monitor de queues MikroTik
                </p>

                <h1 class="text-3xl font-bold tracking-tight md:text-4xl">
                    Promedios de descarga
                </h1>

                <p class="mt-2 text-sm text-slate-400">
                    Visualización diaria, semanal y mensual por nodo y paquete.
                </p>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300 shadow-xl">
                <div class="flex items-center gap-2">
                    <i class="bi bi-calendar3 text-cyan-300"></i>
                    <span><?= htmlspecialchars($tituloPeriodo) ?></span>
                </div>
            </div>
        </header>

        <section class="mb-6 grid grid-cols-1 gap-4 md:grid-cols-4">
            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm text-slate-400">Nodos con datos</span>
                    <i class="bi bi-hdd-network text-cyan-300"></i>
                </div>
                <div class="text-3xl font-bold"><?= $totalNodos ?></div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm text-slate-400">Paquetes medidos</span>
                    <i class="bi bi-layers text-blue-300"></i>
                </div>
                <div class="text-3xl font-bold"><?= $totalRegistros ?></div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm text-slate-400">Promedio general</span>
                    <i class="bi bi-speedometer2 text-emerald-300"></i>
                </div>
                <div class="text-3xl font-bold"><?= number_format($promedioGeneral, 2) ?> Mbps</div>
            </div>

            <div class="rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm text-slate-400">Muestras usadas</span>
                    <i class="bi bi-bar-chart-line text-yellow-300"></i>
                </div>
                <div class="text-3xl font-bold"><?= number_format($totalMuestras) ?></div>
            </div>
        </section>

        <section class="mb-8 rounded-2xl border border-white/10 bg-white/5 p-5 shadow-xl">
            <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-4 md:items-end">

                <div>
                    <label class="mb-2 block text-sm text-slate-300">Tipo de reporte</label>
                    <select name="periodo" id="periodo"
                        class="w-full rounded-xl border border-white/10 bg-[#0d1f34] px-4 py-3 text-slate-100 outline-none focus:border-cyan-400">
                        <option value="diario" <?= $periodo === 'diario' ? 'selected' : '' ?>>Diario</option>
                        <option value="semanal" <?= $periodo === 'semanal' ? 'selected' : '' ?>>Semanal</option>
                        <option value="mensual" <?= $periodo === 'mensual' ? 'selected' : '' ?>>Mensual</option>
                    </select>
                </div>

                <div id="campoFecha">
                    <label class="mb-2 block text-sm text-slate-300">Fecha</label>
                    <input type="date" name="fecha" value="<?= htmlspecialchars($fecha) ?>"
                        class="w-full rounded-xl border border-white/10 bg-[#0d1f34] px-4 py-3 text-slate-100 outline-none focus:border-cyan-400">
                </div>

                <div id="campoMes">
                    <label class="mb-2 block text-sm text-slate-300">Mes</label>
                    <input type="month" name="mes" value="<?= htmlspecialchars($mes) ?>"
                        class="w-full rounded-xl border border-white/10 bg-[#0d1f34] px-4 py-3 text-slate-100 outline-none focus:border-cyan-400">
                </div>

                <div>
                    <button type="submit"
                        class="w-full rounded-xl bg-cyan-400 px-5 py-3 font-semibold text-[#071322] shadow-lg shadow-cyan-500/20 transition hover:bg-cyan-300">
                        <i class="bi bi-search mr-2"></i>
                        Consultar
                    </button>
                </div>

            </form>
        </section>

        <?php if (empty($datosPorNodo)): ?>

            <section class="rounded-2xl border border-yellow-400/20 bg-yellow-400/10 p-8 text-center shadow-xl">
                <i class="bi bi-exclamation-triangle text-4xl text-yellow-300"></i>
                <h2 class="mt-4 text-xl font-semibold">No hay datos para este periodo</h2>
                <p class="mt-2 text-sm text-slate-300">
                    Verifica que ya se haya ejecutado el archivo <strong>generar_promedio_diario.php</strong>
                    para las fechas seleccionadas.
                </p>
            </section>

        <?php else: ?>

            <section class="grid grid-cols-1 gap-5 xl:grid-cols-2">
                <?php foreach ($datosPorNodo as $nombreNodo => $paquetes): ?>

                    <?php
                    $promNodo = 0;
                    $maxNodo = 0;
                    $muestrasNodo = 0;

                    foreach ($paquetes as $p) {
                        $promNodo += (float)$p['promedio_mbps'];
                        $maxNodo = max($maxNodo, (float)$p['max_mbps']);
                        $muestrasNodo += (int)$p['total_muestras'];
                    }

                    $promNodo = count($paquetes) > 0 ? round($promNodo / count($paquetes), 2) : 0;
                    ?>

                    <article class="overflow-hidden rounded-2xl border border-white/10 bg-white/5 shadow-xl">
                        <div class="border-b border-white/10 bg-white/5 p-5">
                            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold">
                                        <i class="bi bi-broadcast-pin mr-2 text-cyan-300"></i>
                                        <?= htmlspecialchars($nombreNodo) ?>
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-400">
                                        <?= count($paquetes) ?> paquetes registrados
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-cyan-400/10 px-3 py-1 text-cyan-200">
                                        Prom: <?= number_format($promNodo, 2) ?> Mbps
                                    </span>
                                    <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-emerald-200">
                                        Máx: <?= number_format($maxNodo, 2) ?> Mbps
                                    </span>
                                    <span class="rounded-full bg-blue-400/10 px-3 py-1 text-blue-200">
                                        Muestras: <?= number_format($muestrasNodo) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-[#0d1f34] text-xs uppercase text-slate-400">
                                    <tr>
                                        <th class="px-5 py-3">Paquete</th>
                                        <th class="px-5 py-3 text-right">Promedio</th>
                                        <th class="px-5 py-3 text-right">Máximo</th>
                                        <th class="px-5 py-3 text-right">Mínimo</th>
                                        <th class="px-5 py-3 text-right">Muestras</th>
                                        <th class="px-5 py-3 text-right">Días</th>
                                    </tr>
                                </thead>

                                <tbody class="divide-y divide-white/10">
                                    <?php foreach ($paquetes as $p): ?>
                                        <tr class="hover:bg-white/5">
                                            <td class="px-5 py-3 font-medium text-slate-100">
                                                <?= htmlspecialchars($p['nombre_queue']) ?>
                                            </td>
                                            <td class="px-5 py-3 text-right text-cyan-200">
                                                <?= number_format((float)$p['promedio_mbps'], 2) ?> Mbps
                                            </td>
                                            <td class="px-5 py-3 text-right text-emerald-200">
                                                <?= number_format((float)$p['max_mbps'], 2) ?> Mbps
                                            </td>
                                            <td class="px-5 py-3 text-right text-slate-300">
                                                <?= number_format((float)$p['min_mbps'], 2) ?> Mbps
                                            </td>
                                            <td class="px-5 py-3 text-right text-slate-300">
                                                <?= number_format((int)$p['total_muestras']) ?>
                                            </td>
                                            <td class="px-5 py-3 text-right text-slate-300">
                                                <?= number_format((int)$p['dias_calculados']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>

                <?php endforeach; ?>
            </section>

        <?php endif; ?>

    </main>

    <script>
        const periodo = document.getElementById('periodo');
        const campoFecha = document.getElementById('campoFecha');
        const campoMes = document.getElementById('campoMes');

        function toggleCampos() {
            if (periodo.value === 'mensual') {
                campoFecha.classList.add('hidden');
                campoMes.classList.remove('hidden');
            } else {
                campoFecha.classList.remove('hidden');
                campoMes.classList.add('hidden');
            }
        }

        periodo.addEventListener('change', toggleCampos);
        toggleCampos();
    </script>

</body>
</html>