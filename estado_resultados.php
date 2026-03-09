<?php
require_once 'db_connection.php';
require_once 'auth.php';

// Verificar si se proporcionó un período
$id_periodo = $_GET['periodo'] ?? null;
if (!$id_periodo) {
    die('No se especificó un período contable');
}

// Obtener información del período
$periodo = $pdo->prepare("SELECT * FROM periodos_contables WHERE id_periodo = ?");
$periodo->execute([$id_periodo]);
$periodo_data = $periodo->fetch();

if (!$periodo_data) {
    die('Período contable no encontrado');
}

// Obtener ingresos y gastos del período
$ingresos = $pdo->prepare("
    SELECT SUM(monto) as total 
    FROM movimientos_contables 
    WHERE tipo_movimiento = 'ingreso' 
    AND fecha BETWEEN ? AND ?
");
$ingresos->execute([$periodo_data['fecha_inicio'], $periodo_data['fecha_fin']]);
$total_ingresos = $ingresos->fetchColumn();

$gastos = $pdo->prepare("
    SELECT SUM(monto) as total 
    FROM movimientos_contables 
    WHERE tipo_movimiento = 'egreso' 
    AND fecha BETWEEN ? AND ?
");
$gastos->execute([$periodo_data['fecha_inicio'], $periodo_data['fecha_fin']]);
$total_gastos = $gastos->fetchColumn();

$resultado = $total_ingresos - $total_gastos;

// Configurar PDF
require_once '../lib/fpdf/fpdf.php';

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Encabezado
$pdf->Cell(0, 10, 'Estado de Resultados', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Periodo: ' . $periodo_data['nombre_periodo'], 0, 1, 'C');
$pdf->Cell(0, 10, 'Del ' . date('d/m/Y', strtotime($periodo_data['fecha_inicio'])) . ' al ' . date('d/m/Y', strtotime($periodo_data['fecha_fin'])), 0, 1, 'C');
$pdf->Ln(10);

// Ingresos
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'INGRESOS', 0, 1);
$pdf->SetFont('Arial', '', 12);

$ingresos_detalle = $pdo->prepare("
    SELECT cc.nombre_cuenta, SUM(mc.monto) as total
    FROM movimientos_contables mc
    JOIN cuentas_contables cc ON mc.id_cuenta_debito = cc.id_cuenta
    WHERE mc.tipo_movimiento = 'ingreso'
    AND mc.fecha BETWEEN ? AND ?
    GROUP BY cc.nombre_cuenta
    ORDER BY total DESC
");
$ingresos_detalle->execute([$periodo_data['fecha_inicio'], $periodo_data['fecha_fin']]);

while ($row = $ingresos_detalle->fetch()) {
    $pdf->Cell(140, 8, $row['nombre_cuenta'], 0, 0);
    $pdf->Cell(40, 8, '$' . number_format($row['total'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 8, 'TOTAL INGRESOS', 0, 0);
$pdf->Cell(40, 8, '$' . number_format($total_ingresos, 2), 0, 1, 'R');
$pdf->Ln(10);

// Gastos
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'GASTOS', 0, 1);
$pdf->SetFont('Arial', '', 12);

$gastos_detalle = $pdo->prepare("
    SELECT cc.nombre_cuenta, SUM(mc.monto) as total
    FROM movimientos_contables mc
    JOIN cuentas_contables cc ON mc.id_cuenta_debito = cc.id_cuenta
    WHERE mc.tipo_movimiento = 'egreso'
    AND mc.fecha BETWEEN ? AND ?
    GROUP BY cc.nombre_cuenta
    ORDER BY total DESC
");
$gastos_detalle->execute([$periodo_data['fecha_inicio'], $periodo_data['fecha_fin']]);

while ($row = $gastos_detalle->fetch()) {
    $pdf->Cell(140, 8, $row['nombre_cuenta'], 0, 0);
    $pdf->Cell(40, 8, '$' . number_format($row['total'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(140, 8, 'TOTAL GASTOS', 0, 0);
$pdf->Cell(40, 8, '$' . number_format($total_gastos, 2), 0, 1, 'R');
$pdf->Ln(10);

// Resultado
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(140, 10, 'RESULTADO DEL PERIODO', 0, 0);
$pdf->Cell(40, 10, '$' . number_format($resultado, 2), 0, 1, 'R');

// Pie de página
$pdf->SetY(-15);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 10, 'Generado el ' . date('d/m/Y H:i:s'), 0, 0, 'C');

$pdf->Output('I', 'Estado_Resultados_' . $periodo_data['nombre_periodo'] . '.pdf');
?>