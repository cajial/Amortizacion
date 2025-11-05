<?php
// Inicializar variables
$cuadro = [];
$cuota = 0;
$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Recogida y sanitización de datos
    $capital = filter_input(INPUT_POST, 'capital', FILTER_VALIDATE_FLOAT);
    $tna = filter_input(INPUT_POST, 'tna', FILTER_VALIDATE_FLOAT);
    $periodicidad = filter_input(INPUT_POST, 'periodicidad', FILTER_VALIDATE_INT);
    $n_cuotas = filter_input(INPUT_POST, 'n', FILTER_VALIDATE_INT);

    if ($capital > 0 && $tna >= 0 && $periodicidad > 0 && $n_cuotas > 0) {
        // 2. Cálculos Sistema Francés
        // i = Tasa del periodo (TNA / 100 / periodicidad)
        $i = ($tna / 100) / $periodicidad;

        // Fórmula de la cuota (A): A = C * (i * (1 + i)^n) / ((1 + i)^n - 1)
        // Si la tasa es 0, la división es directa.
        if ($i == 0) {
             $cuota = $capital / $n_cuotas;
        } else {
             $cuota = $capital * ($i * pow(1 + $i, $n_cuotas)) / (pow(1 + $i, $n_cuotas) - 1);
        }

        // 3. Generación del Cuadro de Amortización
        $saldo_pendiente = $capital;

        for ($k = 1; $k <= $n_cuotas; $k++) {
            // Interés del periodo: Saldo anterior * i
            $interes = round($saldo_pendiente * $i, 2); // Redondeo bancario estándar a 2 decimales

            // Amortización de capital: Cuota - Interés
            // En la última cuota, ajustamos para evitar decimales sueltos
            if ($k == $n_cuotas) {
                $amortizacion_capital = $saldo_pendiente;
                $cuota = $amortizacion_capital + $interes; // Ajuste final leve de la última cuota
            } else {
                $amortizacion_capital = round($cuota - $interes, 2);
            }

            $saldo_pendiente -= $amortizacion_capital;

            // Guardar fila
            $cuadro[] = [
                'periodo' => $k,
                'cuota' => $cuota,
                'interes' => $interes,
                'amortizacion' => $amortizacion_capital,
                'saldo' => max(0, $saldo_pendiente) // Evitar -0.00
            ];
        }
    } else {
        $error = "Por favor, introduce valores válidos mayores a cero.";
    }
}

// Función auxiliar para formato moneda europeo (ej: 1.234,56)
function fmt($num) {
    return number_format($num, 2, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calculadora Amortización Francés</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <h1 class="mb-4 text-center">🇫🇷 Calculadora Sistema Francés</h1>

        <div class="row">
            <div class="col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Datos del Préstamo</h5>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Capital (€)</label>
                                <input type="number" step="0.01" name="capital" class="form-control" required value="<?= $_POST['capital'] ?? '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">TNA Anual (%)</label>
                                <input type="number" step="0.01" name="tna" class="form-control" required value="<?= $_POST['tna'] ?? '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Periodicidad de pago</label>
                                <select name="periodicidad" class="form-select">
                                    <option value="12" <?= (isset($_POST['periodicidad']) && $_POST['periodicidad'] == 12) ? 'selected' : '' ?>>Mensual (12)</option>
                                    <option value="4" <?= (isset($_POST['periodicidad']) && $_POST['periodicidad'] == 4) ? 'selected' : '' ?>>Trimestral (4)</option>
                                    <option value="3" <?= (isset($_POST['periodicidad']) && $_POST['periodicidad'] == 3) ? 'selected' : '' ?>>Cuatrimestral (3)</option>
                                    <option value="2" <?= (isset($_POST['periodicidad']) && $_POST['periodicidad'] == 2) ? 'selected' : '' ?>>Semestral (2)</option>
                                    <option value="1" <?= (isset($_POST['periodicidad']) && $_POST['periodicidad'] == 1) ? 'selected' : '' ?>>Anual (1)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nº Total de Cuotas (n)</label>
                                <input type="number" name="n" class="form-control" required value="<?= $_POST['n'] ?? '' ?>">
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Calcular Tabla</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <?php if (!empty($cuadro)): ?>
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h4 class="text-success">Cuota Teórica: <?= fmt($cuadro[0]['cuota']) ?> €</h4>
                            <hr>
                            <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                                <table class="table table-striped table-hover table-sm">
                                    <thead class="table-dark sticky-top">
                                        <tr>
                                            <th>#</th>
                                            <th>Cuota Total</th>
                                            <th>Intereses</th>
                                            <th>Amortización Capital</th>
                                            <th>Capital Pendiente</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cuadro as $fila): ?>
                                            <tr>
                                                <td><?= $fila['periodo'] ?></td>
                                                <td><?= fmt($fila['cuota']) ?></td>
                                                <td class="text-danger"><?= fmt($fila['interes']) ?></td>
                                                <td class="text-success"><?= fmt($fila['amortizacion']) ?></td>
                                                <td><strong><?= fmt($fila['saldo']) ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
