<?php
declare(strict_types=1);

// ---------- DATOS ----------
// Cada movimiento es un array asociativo.
// Las cantidades van en CÉNTIMOS (int) para evitar errores de decimales con float.
$movimientos = [
    ['concepto' => 'Nómina',   'tipo' => 'ingreso', 'cantidad' => 100000], // 1000,00 €
    ['concepto' => 'Alquiler', 'tipo' => 'gasto',   'cantidad' => 50000],  //  500,00 €
    ['concepto' => 'Compra',   'tipo' => 'gasto',   'cantidad' => 20000],  //  200,00 €
];

// ---------- LÓGICA ----------
// Suma todas las cantidades de un tipo ('ingreso' o 'gasto')
function sumarPorTipo(array $movimientos, string $tipo): int
{
    $suma = 0;
    foreach ($movimientos as $movimiento) {
        if ($movimiento['tipo'] === $tipo) {
            $suma += $movimiento['cantidad'];
        }
    }
    return $suma;
}

// Convierte céntimos a texto: 123456 -> "1.234,56 €"
function formatearEuros(int $centimos): string
{
    return number_format($centimos / 100, 2, ',', '.') . ' €';
}

// Los totales se CALCULAN a partir de los movimientos, no se guardan aparte
$ingresos = sumarPorTipo($movimientos, 'ingreso');
$gastos   = sumarPorTipo($movimientos, 'gasto');
$saldo    = $ingresos - $gastos;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>MyExpenses</title>
</head>
<body>
    <h1>MyExpenses</h1>

    <!-- ---------- VISTA: solo muestra, no calcula ---------- -->
    <table border="1" cellpadding="6">
        <tr>
            <th>Concepto</th>
            <th>Tipo</th>
            <th>Cantidad</th>
        </tr>
        <?php foreach ($movimientos as $movimiento): ?>
            <tr>
                <td><?= htmlspecialchars($movimiento['concepto']) ?></td>
                <td><?= htmlspecialchars($movimiento['tipo']) ?></td>
                <td><?= formatearEuros($movimiento['cantidad']) ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <p>Ingresos totales: <?= formatearEuros($ingresos) ?></p>
    <p>Gastos totales: <?= formatearEuros($gastos) ?></p>
    <p><strong>Dinero restante: <?= formatearEuros($saldo) ?></strong></p>
</body>
</html>