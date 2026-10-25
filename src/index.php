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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $concepto = trim($_POST['concepto'] ?? '');
    $cantidad = trim($_POST['cantidad'] ?? '');
    $tipo = trim($_POST['tipo'] ?? '');

    $errores = [];

    if ($concepto === '') {
        $errores[] = 'El concepto no es valido.';
    }
    if (!is_numeric($cantidad) || (float)$cantidad <= 0) {
    $errores[] = 'La cantidad debe ser un número mayor que 0.';
} 
    if (!in_array($tipo, ['gasto', 'ingreso'], true)){
         $errores[] = 'El tipo no puede estar vacío.';
    }
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
    <?php foreach($errores):?>

    <form method="post">
        <input type="text" name="concepto" placeholder="Concepto" required step="">
        <input type="number" name="cantidad" placeholder="Cantidad" required step="0.1">
        <button class="anadirGasto" name="tipo" value="gasto">Añadir gasto </button>
        <button class="anadirIngreso" name="tipo" value="ingreso">Añadir ingreso </button>
    </form><br><br>
    <h2>Resumen de datos</h2>
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