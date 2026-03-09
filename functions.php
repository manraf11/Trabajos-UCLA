<?php
// includes/functions.php
require_once 'db_connection.php';

/**
 * Registra una acción en el log
 */
function log_action($accion, $descripcion, $tabla = null, $registro_id = null) {
    global $pdo, $user_id;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $pdo->prepare("INSERT INTO logs_carga 
        (usuario_id, accion, descripcion, ip_origen, tabla_afectada, registro_id) 
        VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $accion, $descripcion, $ip, $tabla, $registro_id]);
}

/**
 * Valida el formato de un archivo
 */
function validar_formato_archivo($archivo, $formato_permitido) {
    $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    
    $formatos = [
        'excel' => ['xlsx', 'xls'],
        'txt' => ['txt'],
        'csv' => ['csv']
    ];
    
    return in_array($extension, $formatos[$formato_permitido]);
}

/**
 * Obtiene información de un socio
 */
function obtener_info_socio($codigoemp) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT nombre, saldo FROM socios WHERE codigoemp = ?");
    $stmt->execute([$codigoemp]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}