<?php
// Inicializar variables para evitar warnings en la primera carga
$cuadro = [];
$datos_calculados = [];
$error = "";

// Valores por defecto (basados en tu imagen)
$capital = $_POST['capital'] ?? 0;
$euribor = $_POST['euribor'] ?? 0;
$diferencial = $_POST['diferencial'] ?? 0;
$plazo_anios = $_POST['plazo'] ?? 0;
$periodicidad = $_POST['periodicidad'] ?? 12;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitización de entradas
    $capital = filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_FLOAT);
    $euribor = filter_input(INPUT_POST, 'euribor', FILTER_VALIDATE_FLOAT);
    $diferencial = filter_input(INPUT_POST, 'diferencial', FILTER_VALIDATE_FLOAT);
    $plazo_anios = filter_input(INPUT_POST, 'plazo', FILTER_VALIDATE_FLOAT);
    $periodicidad = filter_input(INPUT_POST, 'periodicidad', FILTER_VALIDATE_INT);

    if ($capital > 0 && $plazo_anios > 0 && $periodicidad > 0) {
        // 2. Cálculos intermedios (replicando tu tabla superior)
        $tna = $euribor + $diferencial;             // TNA total
        $n_cuotas = $plazo_anios * $periodicidad;   // Total cuotas (15 * 12 = 180)
        $i_anual = $tna / 100;
        $i_periodo = $i_anual / $periodicidad;      // Int. Cuota (0.03 / 12 = 0.0025)

        // Cálculo de Cuota (Sistema Francés)
        if ($i_periodo == 0) {
             $cuota = $capital / $n_cuotas;
        } else {
             // Fórmula: C * (i * (1+i)^n) / ((1+i)^n - 1)
             $cuota = $capital * ($i_periodo * pow(1 + $i_periodo, $n_cuotas)) / (pow(1 + $i_periodo, $n_cuotas) - 1);
        }

        // Guardamos datos calculados para mostrar en la "tabla superior"
        $datos_calculados = [
            'tna' => $tna,
            'n_cuotas' => $n_cuotas,
            'int_cuota' => $i_periodo,
            'cuota_teorica' => $cuota
        ];

        // 3. Generación del Cuadro de Amortización
        $saldo_pendiente = $capital;

        // Fila 0 (inicial)
        $cuadro[] = [
            'periodo' => 0,
            'interes' => 0,
            'amortizacion' => 0,
            'saldo' => $saldo_pendiente
        ];

        for ($k = 1; $k <= $n_cuotas; $k++) {
            $interes = $saldo_pendiente * $i_periodo;
            $amortizacion_capital = $cuota - $interes;

            // Ajuste última cuota para cuadrar a 0 exacto
            if ($k == $n_cuotas) {
                $amortizacion_capital = $saldo_pendiente;
                $cuota_real = $amortizacion_capital + $interes;
            } else {
                $cuota_real = $cuota;
            }

            $saldo_pendiente -= $amortizacion_capital;

            $cuadro[] = [
                'periodo' => $k,
                'cuota' => $cuota_real,
                'interes' => $interes,
                'amortizacion' => $amortizacion_capital,
                'saldo' => max(0, $saldo_pendiente)
            ];
        }
    } else {
        $error = "Por favor, revisa que los valores numéricos sean positivos.";
    }
}

// Función de formateo para coincidir con tu imagen (3 decimales, coma como separador decimal)
function fmt($num) {
    return number_format($num, 3, ',', '.');
}
// Función para porcentajes
function fmt_pct($num) {
    return number_format($num, 2, ',', '.') . ' %';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Simulador Hipoteca - Estilo Excel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .tabla-resumen td { padding: 5px 10px; }
        .bg-excel { background-color: #f8f9fa; border: 1px solid #dee2e6; }
        .input-excel { border: 1px solid #ced4da; background-color: #fff; padding: 3px 5px; width: 100%; }
    </style>
</head>
<body class="p-4 bg-light">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">Entrada de Datos</div>
                    <div class="card-body">
                        <?php if ($error): ?><div class="alert alert-danger py-1"><?= $error ?></div><?php endif; ?>
                        
                        <form method="POST" action="">
                            <table class="table table-borderless tabla-resumen mb-0">
                                <tr>
                                    <td><label>Capital</label></td>
                                    <td><input type="number" step="0.01" name="capital" class="input-excel" value="<?= $capital ?>"></td>
                                </tr>
                                <tr>
                                    <td><label>Diferencial (%)</label></td>
                                    <td><input type="number" step="0.01" name="diferencial" class="input-excel" value="<?= $diferencial ?>"></td>
                                </tr>
                                <tr>
                                    <td><label>Euribor (%)</label></td>
                                    <td><input type="number" step="0.01" name="euribor" class="input-excel" value="<?= $euribor ?>"></td>
                                </tr>
                                <tr>
                                    <td><label>Plazo (años)</label></td>
                                    <td><input type="number" step="1" name="plazo" class="input-excel" value="<?= $plazo_anios ?>"></td>
                                </tr>
                                <tr>
                                    <td><label>Periodicidad</label></td>
                                    <td>
                                        <select name="periodicidad" class="input-excel">
                                            <option value="12" <?= $periodicidad == 12 ? 'selected' : '' ?>>12 (Mensual)</option>
                                            <option value="4" <?= $periodicidad == 4 ? 'selected' : '' ?>>4 (Trimestral)</option>
                                            <option value="2" <?= $periodicidad == 2 ? 'selected' : '' ?>>2 (Semestral)</option>
                                            <option value="1" <?= $periodicidad == 1 ? 'selected' : '' ?>>1 (Anual)</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            <button type="submit" class="btn btn-sm btn-success w-100 mt-3">CALCULAR</button>
                        </form>
                    </div>
                </div>

                <?php if ($_SERVER["REQUEST_METHOD"] == "POST" && !$error): ?>
                <div class="card shadow-sm bg-excel">
                    <div class="card-body p-0">
                        <table class="table table-sm table-borderless tabla-resumen mb-0">
                            <tr class="border-bottom">
                                <td><strong>TNA Total</strong> (Eur+Dif)</td>
                                <td class="text-end"><?= fmt_pct($datos_calculados['tna']) ?></td>
                            </tr>
                             <tr>
                                <td>Int. Cuota (Periodo)</td>
                                <td class="text-end"><?= number_format($datos_calculados['int_cuota'], 6, ',', '.') ?></td>
                            </tr>
                            <tr>
                                <td>Total cuotas (n)</td>
                                <td class="text-end"><?= $datos_calculados['n_cuotas'] ?></td>
                            </tr>
                            <tr class="bg-warning bg-opacity-10">
                                <td><strong>CUOTA</strong></td>
                                <td class="text-end fs-5"><strong><?= fmt($datos_calculados['cuota_teorica']) ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="col-md-7 col-lg-8">
                <?php if (!empty($cuadro)): ?>
                    <div class="card shadow-sm">
                        <div class="card-header">Cuadro de Amortización</div>
                        <div class="table-responsive" style="max-height: 80vh; overflow-y: auto;">
                            <table class="table table-striped table-hover table-sm text-end" style="font-family: monospace;">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="text-center">N.º cuota</th>
                                        <th>Cuota Total</th>
                                        <th>Interés</th>
                                        <th>Amortización</th>
                                        <th>Capital Pendiente</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cuadro as $fila): ?>
                                        <tr class="<?= $fila['periodo'] == 0 ? 'table-secondary fw-bold' : '' ?>">
                                            <td class="text-center"><?= $fila['periodo'] ?></td>
                                            <td><?= ($fila['periodo'] > 0) ? fmt($fila['cuota']) : '-' ?></td>
                                            <td><?= fmt($fila['interes']) ?></td>
                                            <td><?= fmt($fila['amortizacion']) ?></td>
                                            <td><?= fmt($fila['saldo']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
