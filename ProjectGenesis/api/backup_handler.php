<?php
// FILE: api/backup_handler.php

// Incluir configuración (esto trae $pdo, DB_HOST, etc., y las funciones de log)
include '../config/config.php'; 
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'js.api.invalidAction'];

// --- 1. Validación de Seguridad Estricta ---

// Solo los "Fundadores" pueden acceder a esta API.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'founder') {
    $response['message'] = 'js.admin.errorAdminTarget'; // "Sin permiso"
    echo json_encode($response);
    exit;
}

// Validar Token CSRF
$submittedToken = $_POST['csrf_token'] ?? '';
if (!validateCsrfToken($submittedToken)) {
    $response['message'] = 'js.api.errorSecurityRefresh';
    echo json_encode($response);
    exit;
}

// --- 2. Definición de Rutas y Constantes ---

// Directorio de backups (fuera del root público)
// Sube 2 niveles (api/ -> projectgenesis/) y luego entra a /backups
$backupDir = dirname(__DIR__) . '/backups';

// Asegurarse de que el directorio exista y se pueda escribir en él
if (!is_dir($backupDir)) {
    if (!@mkdir($backupDir, 0755, true)) {
        $response['message'] = 'admin.backups.errorDirCreate';
        logDatabaseError(new Exception("API: No se pudo crear el directorio de backups: " . $backupDir), 'backup_handler');
        echo json_encode($response);
        exit;
    }
}
if (!is_writable($backupDir)) {
    $response['message'] = 'admin.backups.errorDirWrite';
    logDatabaseError(new Exception("API: El directorio de backups no tiene permisos de escritura: " . $backupDir), 'backup_handler');
    echo json_encode($response);
    exit;
}

// --- 3. Manejador de Acciones ---

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create-backup') {
        // Generar un nombre de archivo único
        $filename = 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backupDir . '/' . $filename;

        // Construir el comando mysqldump
        // (Asegúrate de que mysqldump esté en el PATH de tu servidor)
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --result-file=%s %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg($filepath),
            escapeshellarg(DB_NAME)
        );

        // Ejecutar el comando
        exec($command . ' 2>&1', $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception(getTranslation('admin.backups.errorExecCreate') . ' ' . implode('; ', $output));
        }

        $response['success'] = true;
        $response['message'] = 'admin.backups.successCreate';
    
    } elseif ($action === 'restore-backup') {
        $filename = $_POST['filename'] ?? '';
        
        // ¡¡¡CRÍTICO!!! Sanear el nombre del archivo para evitar Path Traversal
        $safeFilename = basename($filename);
        $filepath = $backupDir . '/' . $safeFilename;

        if (empty($safeFilename) || $safeFilename !== $filename || !file_exists($filepath)) {
            throw new Exception('admin.backups.errorFileNotFound');
        }

        // Construir el comando mysql para restaurar
        $command = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASS),
            escapeshellarg(DB_NAME),
            escapeshellarg($filepath)
        );
        
        // Ejecutar el comando
        exec($command . ' 2>&1', $output, $return_var);

        if ($return_var !== 0) {
            throw new Exception(getTranslation('admin.backups.errorExecRestore') . ' ' . implode('; ', $output));
        }

        $response['success'] = true;
        $response['message'] = 'admin.backups.successRestore';

    } elseif ($action === 'delete-backup') {
        $filename = $_POST['filename'] ?? '';
        
        // ¡¡¡CRÍTICO!!! Sanear el nombre del archivo
        $safeFilename = basename($filename);
        $filepath = $backupDir . '/' . $safeFilename;

        if (empty($safeFilename) || $safeFilename !== $filename || !file_exists($filepath)) {
            throw new Exception('admin.backups.errorFileNotFound');
        }
        
        if (!@unlink($filepath)) {
             throw new Exception('admin.backups.errorDelete');
        }

        $response['success'] = true;
        $response['message'] = 'admin.backups.successDelete';

    } else {
        $response['message'] = 'js.api.invalidAction';
    }

} catch (Exception $e) {
    logDatabaseError(new Exception($e->getMessage()), 'backup_handler - action: ' . $action);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>