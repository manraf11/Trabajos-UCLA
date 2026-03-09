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

// Obtener cuentas por tipo
$cuentas = $pdo->query("SELECT * FROM cuentas_contables WHERE activa = true ORDER BY codigo_cuenta")->fetchAll();

// Clasificar cuentas por tipo
$tipos_cuenta = [
    'activo' => [],
    'pasivo' => [],
    'patrimonio' => [],
    'ingreso' => [],
    'gasto' => []
];

foreach ($cuentas as $cuenta) {
    $tipos_cuenta[$cuenta['tipo_cuenta']][] = $cuenta;
}

// Calcular totales
$total_activos = array_sum(array_column($tipos_cuenta['activo'], 'saldo_actual'));
$total_pasivos = array_sum(array_column($tipos_cuenta['pasivo'], 'saldo_actual'));
$total_patrimonio = array_sum(array_column($tipos_cuenta['patrimonio'], 'saldo_actual'));

// Configurar PDF
require_once '../lib/fpdf/fpdf.php';

$pdf = new FPDF('P', 'mm', 'A4');
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Encabezado
$pdf->Cell(0, 10, 'Balance General', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Periodo: ' . $periodo_data['nombre_periodo'], 0, 1, 'C');
$pdf->Cell(0, 10, 'Del ' . date('d/m/Y', strtotime($periodo_data['fecha_inicio'])) . ' al ' . date('d/m/Y', strtotime($periodo_data['fecha_fin'])), 0, 1, 'C');
$pdf->Ln(10);

// Activos
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'ACTIVOS', 0, 1);
$pdf->SetFont('Arial', '', 12);

foreach ($tipos_cuenta['activo'] as $cuenta) {
    $pdf->Cell(100, 8, $cuenta['codigo_cuenta'] . ' - ' . $cuenta['nombre_cuenta'], 0, 0);
    $pdf->Cell(40, 8, '$' . number_format($cuenta['saldo_actual'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 8, 'TOTAL ACTIVOS', 0, 0);
$pdf->Cell(40, 8, '$' . number_format($total_activos, 2), 0, 1, 'R');
$pdf->Ln(10);

// Pasivos
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'PASIVOS', 0, 1);
$pdf->SetFont('Arial', '', 12);

foreach ($tipos_cuenta['pasivo'] as $cuenta) {
    $pdf->Cell(100, 8, $cuenta['codigo_cuenta'] . ' - ' . $cuenta['nombre_cuenta'], 0, 0);
    $pdf->Cell(40, 8, '$' . number_format($cuenta['saldo_actual'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 8, 'TOTAL PASIVOS', 0, 0);
$pdf->Cell(40, 8, '$' . number_format($total_pasivos, 2), 0, 1, 'R');
$pdf->Ln(10);

// Patrimonio
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'PATRIMONIO', 0, 1);
$pdf->SetFont('Arial', '', 12);

foreach ($tipos_cuenta['patrimonio'] as $cuenta) {
    $pdf->Cell(100, 8, $cuenta['codigo_cuenta'] . ' - ' . $cuenta['nombre_cuenta'], 0, 0);
    $pdf->Cell(40, 8, '$' . number_format($cuenta['saldo_actual'], 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 8, 'TOTAL PATRIMONIO', 0, 0);
$pdf->Cell(40, 8, '$' . number_format($total_patrimonio, 2), 0, 1, 'R');
$pdf->Ln(10);

// Totales
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(100, 10, 'TOTAL PASIVOS + PATRIMONIO', 0, 0);
$pdf->Cell(40, 10, '$' . number_format($total_pasivos + $total_patrimonio, 2), 0, 1, 'R');

// Pie de página
$pdf->SetY(-15);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 10, 'Generado el ' . date('d/m/Y H:i:s'), 0, 0, 'C');

$pdf->Output('I', 'Balance_General_' . $periodo_data['nombre_periodo'] . '.pdf');
?>