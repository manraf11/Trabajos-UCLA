<?php
// download_original.php
require_once './/db_connetion.php';
require_once './/functions.php';

try {
    $carga_id = $_GET['carga_id'] ?? 0;
    
    // Obtener información de la carga
    $stmt = $pdo->prepare("SELECT nombre_archivo, ruta_archivo FROM cargas_masivas WHERE carga_id = ? AND usuario_id = ?");
    $stmt->execute([$carga_id, $user_id]);
    $carga = $stmt->fetch();
    
    if (!$carga || !file_exists($carga['ruta_archivo'])) {
        die('Archivo no encontrado');
    }
    
    // Registrar descarga en el log
    log_action('download', "Descargó archivo original: {$carga['nombre_archivo']}", 'cargas_masivas', $carga_id);
    
    // Configurar headers para descarga
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.basename($carga['nombre_archivo']).'"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($carga['ruta_archivo']));
    readfile($carga['ruta_archivo']);
    exit;
    
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}