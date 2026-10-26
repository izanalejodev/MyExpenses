<?php
declare(strict_types=1);

// =====================================================
// CONFIGURACIÓN
// =====================================================
// Los movimientos se guardan en un archivo JSON junto a este index.php.
// Las cantidades se guardan en CÉNTIMOS (int) para evitar errores con decimales.
const ARCHIVO_DATOS = __DIR__ . '/movimientos.json';
const TIPOS_VALIDOS = ['gasto', 'ingreso'];

// =====================================================
// FUNCIONES DE DATOS
// =====================================================

// Lee los movimientos del archivo. Si no existe o está roto, devuelve un array vacío.
function cargarMovimientos(): array
{
    if (!file_exists(ARCHIVO_DATOS)) {
        return [];
    }
    $contenido = file_get_contents(ARCHIVO_DATOS);
    $datos = json_decode($contenido, true);
    return is_array($datos) ? $datos : [];
}

// Guarda todos los movimientos en el archivo.
// LOCK_EX evita que dos peticiones a la vez escriban el archivo al mismo tiempo.
function guardarMovimientos(array $movimientos): void
{
    $json = json_encode($movimientos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents(ARCHIVO_DATOS, $json, LOCK_EX);
}

// Suma las cantidades de un tipo ('gasto' o 'ingreso').
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

// Convierte "2026-10-26" en "26/10/2026"
function formatearFecha(string $fecha): string
{
    $objetoFecha = DateTime::createFromFormat('Y-m-d', $fecha);
    return $objetoFecha ? $objetoFecha->format('d/m/Y') : $fecha;
}

// =====================================================
// PROCESAR FORMULARIOS
// =====================================================
$movimientos = cargarMovimientos();
$errores = [];

// Valores que se vuelven a mostrar en el formulario si hay errores
$concepto = '';
$cantidad = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'anadir';

    // ---------- Eliminar un movimiento ----------
    if ($accion === 'eliminar') {
        $idEliminar = $_POST['id'] ?? '';

        // Nos quedamos con todos los movimientos menos el que tiene ese id
        $movimientos = array_values(array_filter(
            $movimientos,
            fn(array $m) => $m['id'] !== $idEliminar
        ));

        guardarMovimientos($movimientos);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    // ---------- Añadir un movimiento ----------
    $concepto = trim($_POST['concepto'] ?? '');
    $cantidad = trim($_POST['cantidad'] ?? '');
    $tipo     = trim($_POST['tipo'] ?? '');

    // Algunos teclados escriben la coma decimal: "12,50" -> "12.50"
    $cantidad = str_replace(',', '.', $cantidad);

    if ($concepto === '') {
        $errores[] = 'El concepto no puede estar vacío.';
    } elseif (mb_strlen($concepto) > 100) {
        $errores[] = 'El concepto no puede tener más de 100 caracteres.';
    }

    if (!is_numeric($cantidad) || (float)$cantidad <= 0) {
        $errores[] = 'La cantidad debe ser un número mayor que 0.';
    }

    if (!in_array($tipo, TIPOS_VALIDOS, true)) {
        $errores[] = 'El tipo no es válido.';
    }

    if (empty($errores)) {
        $movimientos[] = [
            'id'       => bin2hex(random_bytes(8)),            // identificador único
            'concepto' => $concepto,
            'tipo'     => $tipo,
            'cantidad' => (int)round((float)$cantidad * 100),  // euros -> céntimos
            'fecha'    => date('Y-m-d'),
        ];

        guardarMovimientos($movimientos);

        // Post/Redirect/Get: redirigimos para que al recargar (F5)
        // el navegador no vuelva a enviar el formulario y duplique el movimiento.
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// =====================================================
// CÁLCULOS PARA LA VISTA
// =====================================================
$ingresos = sumarPorTipo($movimientos, 'ingreso');
$gastos   = sumarPorTipo($movimientos, 'gasto');
$saldo    = $ingresos - $gastos;

// Los más recientes primero
$movimientosOrdenados = array_reverse($movimientos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MyExpenses</title>
    <style>
        :root {
            --fondo: #f4f5f7;
            --tarjeta: #ffffff;
            --texto: #1d2330;
            --texto-suave: #6b7280;
            --borde: #e3e6eb;
            --verde: #15803d;
            --verde-fondo: #e7f6ec;
            --rojo: #c0262d;
            --rojo-fondo: #fdecec;
            --acento: #2f4bd8;
            --radio: 12px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--fondo);
            color: var(--texto);
            line-height: 1.5;
        }

        .contenedor {
            max-width: 760px;
            margin: 0 auto;
            padding: 32px 16px 64px;
        }

        h1 {
            margin: 0 0 24px;
            font-size: 1.8rem;
            letter-spacing: -0.02em;
        }

        h2 {
            margin: 0 0 16px;
            font-size: 1.1rem;
        }

        .tarjeta {
            background: var(--tarjeta);
            border: 1px solid var(--borde);
            border-radius: var(--radio);
            padding: 20px;
            margin-bottom: 20px;
        }

        /* ---------- Resumen ---------- */
        .resumen {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .dato {
            background: var(--tarjeta);
            border: 1px solid var(--borde);
            border-radius: var(--radio);
            padding: 16px;
        }

        .dato span {
            display: block;
            font-size: 0.85rem;
            color: var(--texto-suave);
        }

        .dato strong {
            font-size: 1.3rem;
            font-variant-numeric: tabular-nums;
        }

        .positivo { color: var(--verde); }
        .negativo { color: var(--rojo); }

        /* ---------- Formulario ---------- */
        .formulario {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 12px;
        }

        .formulario input {
            width: 100%;
            padding: 10px 12px;
            font: inherit;
            border: 1px solid var(--borde);
            border-radius: 8px;
            background: #fff;
        }

        .formulario input:focus {
            outline: 2px solid var(--acento);
            outline-offset: -1px;
            border-color: transparent;
        }

        .botones {
            grid-column: 1 / -1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        button {
            font: inherit;
            cursor: pointer;
            border: none;
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
        }

        .btn-gasto   { background: var(--rojo);  color: #fff; }
        .btn-ingreso { background: var(--verde); color: #fff; }
        .btn-gasto:hover, .btn-ingreso:hover { filter: brightness(1.1); }

        /* ---------- Errores ---------- */
        .errores {
            background: var(--rojo-fondo);
            color: var(--rojo);
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }

        .errores p { margin: 0; }
        .errores p + p { margin-top: 4px; }

        /* ---------- Lista de movimientos ---------- */
        .lista {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .movimiento {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-top: 1px solid var(--borde);
        }

        .movimiento:first-child { border-top: none; }

        .etiqueta {
            flex-shrink: 0;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 999px;
            text-transform: capitalize;
        }

        .etiqueta.gasto   { background: var(--rojo-fondo);  color: var(--rojo); }
        .etiqueta.ingreso { background: var(--verde-fondo); color: var(--verde); }

        .info { flex: 1; min-width: 0; }

        .info .concepto {
            display: block;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .info .fecha {
            font-size: 0.8rem;
            color: var(--texto-suave);
        }

        .importe {
            font-weight: 600;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .btn-eliminar {
            background: transparent;
            color: var(--texto-suave);
            padding: 4px 8px;
            font-size: 1.1rem;
            line-height: 1;
        }

        .btn-eliminar:hover {
            background: var(--rojo-fondo);
            color: var(--rojo);
        }

        .vacio {
            color: var(--texto-suave);
            text-align: center;
            margin: 8px 0;
        }

        /* ---------- Móvil ---------- */
        @media (max-width: 560px) {
            .resumen { grid-template-columns: 1fr; }
            .formulario { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <main class="contenedor">
        <h1>MyExpenses</h1>

        <!-- ---------- RESUMEN ---------- -->
        <section class="resumen">
            <div class="dato">
                <span>Ingresos</span>
                <strong class="positivo"><?= formatearEuros($ingresos) ?></strong>
            </div>
            <div class="dato">
                <span>Gastos</span>
                <strong class="negativo"><?= formatearEuros($gastos) ?></strong>
            </div>
            <div class="dato">
                <span>Dinero restante</span>
                <strong class="<?= $saldo >= 0 ? 'positivo' : 'negativo' ?>">
                    <?= formatearEuros($saldo) ?>
                </strong>
            </div>
        </section>

        <!-- ---------- FORMULARIO ---------- -->
        <section class="tarjeta">
            <h2>Nuevo movimiento</h2>

            <?php if (!empty($errores)): ?>
                <div class="errores">
                    <?php foreach ($errores as $error): ?>
                        <p><?= htmlspecialchars($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" class="formulario">
                <input type="hidden" name="accion" value="anadir">
                <input
                    type="text"
                    name="concepto"
                    placeholder="Concepto (p. ej. Compra semanal)"
                    maxlength="100"
                    value="<?= htmlspecialchars($concepto) ?>"
                    required
                >
                <input
                    type="number"
                    name="cantidad"
                    placeholder="Cantidad (€)"
                    step="0.01"
                    min="0.01"
                    value="<?= htmlspecialchars($cantidad) ?>"
                    required
                >
                <div class="botones">
                    <button type="submit" class="btn-gasto" name="tipo" value="gasto">Añadir gasto</button>
                    <button type="submit" class="btn-ingreso" name="tipo" value="ingreso">Añadir ingreso</button>
                </div>
            </form>
        </section>

        <!-- ---------- LISTA DE MOVIMIENTOS ---------- -->
        <section class="tarjeta">
            <h2>Movimientos</h2>

            <?php if (empty($movimientosOrdenados)): ?>
                <p class="vacio">Todavía no hay movimientos. Añade el primero arriba.</p>
            <?php else: ?>
                <ul class="lista">
                    <?php foreach ($movimientosOrdenados as $movimiento): ?>
                        <?php $esGasto = $movimiento['tipo'] === 'gasto'; ?>
                        <li class="movimiento">
                            <span class="etiqueta <?= htmlspecialchars($movimiento['tipo']) ?>">
                                <?= htmlspecialchars($movimiento['tipo']) ?>
                            </span>

                            <div class="info">
                                <span class="concepto"><?= htmlspecialchars($movimiento['concepto']) ?></span>
                                <span class="fecha"><?= formatearFecha($movimiento['fecha']) ?></span>
                            </div>

                            <span class="importe <?= $esGasto ? 'negativo' : 'positivo' ?>">
                                <?= $esGasto ? '−' : '+' ?><?= formatearEuros($movimiento['cantidad']) ?>
                            </span>

                            <form method="post" onsubmit="return confirm('¿Eliminar este movimiento?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="id" value="<?= htmlspecialchars($movimiento['id']) ?>">
                                <button type="submit" class="btn-eliminar" title="Eliminar">×</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>