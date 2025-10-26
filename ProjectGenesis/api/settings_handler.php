<?php
// /ProjectGenesis/api/settings_handler.php

include '../config/config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Acción no válida.'];

// --- VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Acceso denegado. No has iniciado sesión.';
    echo json_encode($response);
    exit;
}
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// --- FUNCIÓN Generador de Código (Alfanumérico) (AÑADIDA) ---
function generateVerificationCode() {
    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < 12; $i++) {
        $code .= $chars[random_int(0, $max)];
    }
    return substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
}

// --- FUNCIÓN HELPER PARA GENERAR AVATAR POR DEFECTO ---
// (Esta lógica se ha extraído de api/auth_handler.php para reutilizarla)
function generateDefaultAvatar($pdo, $userId, $username, $basePath) {
    try {
        $savePathDir = dirname(__DIR__) . '/assets/uploads/avatars';
        $fileName = "user-{$userId}.png";
        $fullSavePath = $savePathDir . '/' . $fileName;
        $publicUrl = $basePath . '/assets/uploads/avatars/' . $fileName;

        if (!is_dir($savePathDir)) {
            mkdir($savePathDir, 0755, true);
        }
        
        $avatarColors = ['206BD3', 'D32029', '28A745', 'E91E63', 'F57C00'];
        $selectedColor = $avatarColors[array_rand($avatarColors)];
        $nameParam = urlencode($username);
        
        $apiUrl = "https://ui-avatars.com/api/?name={$nameParam}&size=256&background={$selectedColor}&color=ffffff&bold=true&length=1";
        
        $imageData = @file_get_contents($apiUrl);

        if ($imageData === false) {
            // Fallback si la API falla
            return null;
        }

        file_put_contents($fullSavePath, $imageData);
        return $publicUrl;

    } catch (Exception $e) {
        // No hacer nada si falla el avatar
        return null;
    }
}

// --- FUNCIÓN HELPER PARA ELIMINAR AVATAR ANTIGUO ---
function deleteOldAvatar($oldUrl, $basePath, $userId) {
    // No eliminar si es un avatar por defecto de ui-avatars
    if (strpos($oldUrl, 'ui-avatars.com') !== false) {
        return;
    }
    
    // No eliminar si es el avatar por defecto (user-ID.png)
    if (strpos($oldUrl, 'user-' . $userId . '.png') !== false) {
        return;
    }

    // Convertir URL pública a ruta de servidor
    // URL: /ProjectGenesis/assets/uploads/avatars/user-1-12345.png
    // Ruta: .../ProjectGenesis/assets/uploads/avatars/user-1-12345.png
    $relativePath = str_replace($basePath, '', $oldUrl);
    $serverPath = dirname(__DIR__) . $relativePath;

    if (file_exists($serverPath)) {
        @unlink($serverPath);
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- VALIDACIÓN CSRF ---
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($submittedToken)) {
        $response['message'] = 'Error de seguridad. Por favor, recarga la página.';
        echo json_encode($response);
        exit;
    }

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // --- ACCIÓN: SUBIR NUEVO AVATAR ---
        if ($action === 'upload-avatar') {
            try {
                // 1. Validar el archivo subido
                if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Error al subir el archivo. Código: ' . ($_FILES['avatar']['error'] ?? 'N/A'));
                }

                $file = $_FILES['avatar'];
                $fileSize = $file['size'];
                $fileTmpName = $file['tmp_name'];

                // 2. Validar tamaño (MODIFICADO: max 2MB)
                if ($fileSize > 2 * 1024 * 1024) {
                    throw new Exception('El archivo es demasiado grande (máx 2MB).');
                }

                // 3. Validar tipo de imagen (con GIF y WebP)
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->file($fileTmpName);
                
                $allowedTypes = [
                    'image/png'  => 'png',
                    'image/jpeg' => 'jpg',
                    'image/gif'  => 'gif',
                    'image/webp' => 'webp'
                ];
                
                if (!array_key_exists($mimeType, $allowedTypes)) {
                    throw new Exception('Formato de archivo no válido (solo PNG, JPEG, GIF o WebP).');
                }
                $extension = $allowedTypes[$mimeType]; 

                // 4. Obtener el avatar antiguo para borrarlo después
                $stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldUrl = $stmt->fetchColumn();

                // 5. Generar nuevo nombre y ruta
                $newFileName = "user-{$userId}-" . time() . "." . $extension;
                $saveDir = dirname(__DIR__) . '/assets/uploads/avatars/';
                $newFilePath = $saveDir . $newFileName;
                $newPublicUrl = $basePath . '/assets/uploads/avatars/' . $newFileName;

                // 6. Mover el archivo
                if (!move_uploaded_file($fileTmpName, $newFilePath)) {
                    throw new Exception('No se pudo guardar el archivo en el servidor.');
                }

                // 7. Actualizar la base de datos
                $stmt = $pdo->prepare("UPDATE users SET profile_image_url = ? WHERE id = ?");
                $stmt->execute([$newPublicUrl, $userId]);

                // 8. Eliminar el avatar antiguo (si era personalizado)
                if ($oldUrl) {
                    deleteOldAvatar($oldUrl, $basePath, $userId);
                }

                $response['success'] = true;
                $response['message'] = 'Avatar actualizado con éxito.';
                $response['newAvatarUrl'] = $newPublicUrl;

            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - upload-avatar');
                    $response['message'] = 'Error al guardar en la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }

        // --- ACCIÓN: ELIMINAR AVATAR PERSONALIZADO ---
        elseif ($action === 'remove-avatar') {
            try {
                // 1. Obtener el avatar antiguo para borrarlo
                $stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldUrl = $stmt->fetchColumn();

                // 2. Generar un nuevo avatar por defecto (con iniciales)
                // ¡Esto se hace ANTES de borrar el antiguo!
                $newDefaultUrl = generateDefaultAvatar($pdo, $userId, $username, $basePath);

                if (!$newDefaultUrl) {
                    throw new Exception('No se pudo generar el nuevo avatar por defecto desde la API.');
                }

                // 3. Actualizar la base de datos con el NUEVO avatar por defecto
                $stmt = $pdo->prepare("UPDATE users SET profile_image_url = ? WHERE id = ?");
                $stmt->execute([$newDefaultUrl, $userId]);

                // 4. Eliminar el avatar antiguo (si era personalizado)
                if ($oldUrl) {
                    deleteOldAvatar($oldUrl, $basePath, $userId);
                }

                $response['success'] = true;
                $response['message'] = 'Avatar eliminado. Se ha restaurado el avatar por defecto.';
                $response['newAvatarUrl'] = $newDefaultUrl;

            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - remove-avatar');
                    $response['message'] = 'Error al actualizar la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }

        // --- ACCIÓN: ACTUALIZAR NOMBRE DE USUARIO (MODIFICADA) ---
        elseif ($action === 'update-username') {
            try {
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE RATE LIMIT (USERNAME) ▼▼▼ ---
                define('USERNAME_CHANGE_COOLDOWN_DAYS', 30);

                $stmt_check = $pdo->prepare(
                    "SELECT changed_at FROM user_audit_logs 
                     WHERE user_id = ? AND change_type = 'username' 
                     ORDER BY changed_at DESC LIMIT 1"
                );
                $stmt_check->execute([$userId]);
                $lastLog = $stmt_check->fetch();

                if ($lastLog) {
                    $lastChangeTime = new DateTime($lastLog['changed_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $interval = $currentTime->diff($lastChangeTime);
                    $daysPassed = (int)$interval->format('%a'); // Obtener días totales

                    if ($daysPassed < USERNAME_CHANGE_COOLDOWN_DAYS) {
                        $daysRemaining = USERNAME_CHANGE_COOLDOWN_DAYS - $daysPassed;
                        $plural = ($daysRemaining == 1) ? 'día' : 'días';
                        throw new Exception("Debes esperar. Podrás cambiar tu nombre de usuario de nuevo en {$daysRemaining} {$plural}.");
                    }
                }
                // --- ▲▲▲ FIN DE LA LÓGICA DE RATE LIMIT ▲▲▲ ---

                $newUsername = trim($_POST['username'] ?? '');
                $oldUsername = $_SESSION['username']; // Capturar el valor antiguo ANTES de validar

                // 1. Validar longitud
                if (empty($newUsername)) {
                    throw new Exception('El nombre de usuario no puede estar vacío.');
                }
                if (strlen($newUsername) < 6) {
                    throw new Exception('El nombre de usuario debe tener al menos 6 caracteres.');
                }
                
                // 1.5 Validar si el nombre es el mismo
                if ($newUsername === $oldUsername) {
                     throw new Exception('Este ya es tu nombre de usuario.');
                }

                // 2. Validar que el nombre no esté en uso POR OTRO USUARIO
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$newUsername, $userId]);
                if ($stmt->fetch()) {
                    throw new Exception('Ese nombre de usuario ya está en uso.');
                }
                
                // 3. Obtener el avatar actual
                $stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $oldUrl = $stmt->fetchColumn();

                // 4. Comprobar si es un avatar por defecto
                $isDefaultAvatar = false;
                if ($oldUrl) {
                    $isDefaultAvatar = strpos($oldUrl, 'ui-avatars.com') !== false || 
                                       strpos($oldUrl, 'user-' . $userId . '.png') !== false;
                } else {
                    $isDefaultAvatar = true; 
                }

                $newAvatarUrl = null;

                // --- ▼▼▼ INICIO DE LA CORRECCIÓN ▼▼▼ ---
                // 5. Si es por defecto, regenerarlo SÓLO SI LA INICIAL CAMBIA
                if ($isDefaultAvatar) {
                    
                    // Usamos mb_substr para seguridad con caracteres multibyte (ej. tildes)
                    $oldInitial = mb_substr($oldUsername, 0, 1, 'UTF-8');
                    $newInitial = mb_substr($newUsername, 0, 1, 'UTF-8');

                    // Comparamos las iniciales (ignorando mayúsculas/minúsculas)
                    if (strcasecmp($oldInitial, $newInitial) !== 0) {
                        // Solo si las iniciales son DIFERENTES, regeneramos el avatar
                        $newAvatarUrl = generateDefaultAvatar($pdo, $userId, $newUsername, $basePath);
                    }
                    // Si las iniciales son iguales (Jop -> Jorge), $newAvatarUrl se queda en null
                    // y la BD no actualizará la URL, conservando el avatar y color original.
                }
                // --- ▲▲▲ FIN DE LA CORRECCIÓN ▲▲▲ ---


                // 6. Actualizar la base de datos
                if ($newAvatarUrl) {
                    $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_image_url = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $newAvatarUrl, $userId]);
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                    $stmt->execute([$newUsername, $userId]);
                }
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE AUDITORÍA (USERNAME) ▼▼▼ ---
                $stmt_log = $pdo->prepare(
                    "INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip) 
                     VALUES (?, 'username', ?, ?, ?)"
                );
                // getIpAddress() viene de config.php
                $stmt_log->execute([$userId, $oldUsername, $newUsername, getIpAddress()]); 
                // --- ▲▲▲ FIN DE LA LÓGICA DE AUDITORÍA ▲▲▲ ---
                
                // 7. Actualizar la sesión
                $_SESSION['username'] = $newUsername;
                if ($newAvatarUrl) {
                    $_SESSION['profile_image_url'] = $newAvatarUrl;
                }

                $response['success'] = true;
                $response['message'] = 'Nombre de usuario actualizado con éxito.';
                $response['newUsername'] = $newUsername;
                
                if ($newAvatarUrl) {
                    $response['newAvatarUrl'] = $newAvatarUrl; 
                }

            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-username');
                    $response['message'] = 'Error al actualizar la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }
        
        // --- ACCIÓN: SOLICITAR CÓDIGO PARA CAMBIAR EMAIL ---
        elseif ($action === 'request-email-change-code') {
            try {
                $identifier = $userId; 
                $codeType = 'email_change';

                // 1. Limpiar códigos viejos de este tipo para este usuario
                $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE identifier = ? AND code_type = ?");
                $stmt->execute([$identifier, $codeType]);

                // 2. Generar nuevo código
                $verificationCode = str_replace('-', '', generateVerificationCode());

                // 3. Insertar en la tabla
                $stmt = $pdo->prepare(
                    "INSERT INTO verification_codes (identifier, code_type, code) 
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$identifier, $codeType, $verificationCode]);

                // 4. (Simulación de envío de email)

                $response['success'] = true;
                $response['message'] = 'Se ha generado un código de verificación.';

            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - request-email-change-code');
                    if (strpos($e->getMessage(), 'Data truncated') !== false) {
                         $response['message'] = "Error de BD: El tipo '{$codeType}' no existe en el ENUM 'code_type' de la tabla 'verification_codes'. Asegúrate de añadirlo.";
                    } else {
                        $response['message'] = 'Error al generar el código en la base de datos.';
                    }
                } else {
                    $response['message'] = 'Error: ' . $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }

        // --- ACCIÓN: VERIFICAR CÓDIGO PARA CAMBIAR EMAIL (MODIFICADA) ---
        elseif ($action === 'verify-email-change-code') {
            try {
                $submittedCode = str_replace('-', '', $_POST['verification_code'] ?? '');
                $identifier = $userId; // $userId is from session
                $codeType = 'email_change';
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE RATE LIMIT ▼▼▼ ---
                $ip = getIpAddress(); // Obtener IP (de config.php)

                // 1. VERIFICAR BLOQUEO
                // (Usamos el $identifier = $userId para el log)
                if (checkLockStatus($pdo, $identifier, $ip)) {
                    throw new Exception('Demasiados intentos fallidos. Por favor, inténtalo de nuevo en ' . LOCKOUT_TIME_MINUTES . ' minutos.');
                }
                // --- ▲▲▲ FIN DE LA LÓGICA DE RATE LIMIT ▼▼▼ ---


                if (empty($submittedCode)) {
                    throw new Exception('Por favor, introduce el código de verificación.');
                }

                // 2. Buscar el código válido (15 min de validez)
                $stmt = $pdo->prepare(
                    "SELECT * FROM verification_codes 
                     WHERE identifier = ? 
                     AND code_type = ?
                     AND created_at > (NOW() - INTERVAL 15 MINUTE)"
                );
                $stmt->execute([$identifier, $codeType]);
                $codeData = $stmt->fetch();

                // 3. Comparar
                if (!$codeData || strtolower($codeData['code']) !== strtolower($submittedCode)) {
                    
                    // --- ▼▼▼ INICIO DE LA LÓGICA DE RATE LIMIT ▼▼▼ ---
                    // 3.5 LOG DE INTENTO FALLIDO
                    // --- MODIFICACIÓN: El ENUM 'email_change_fail' no existe, usamos 'password_verify_fail' que es genérico ---
                    logFailedAttempt($pdo, $identifier, $ip, 'password_verify_fail'); 
                    // --- FIN DE LA MODIFICACIÓN ---
                    
                    throw new Exception('El código es incorrecto o ha expirado.');
                }

                // 4. ¡Éxito!
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE RATE LIMIT ▼▼▼ ---
                // 4.1 LIMPIAR INTENTOS FALLIDOS (al tener éxito)
                clearFailedAttempts($pdo, $identifier);
                // --- ▲▲▲ FIN DE LA LÓGICA DE RATE LIMIT ▼▼▼ ---
                
                // 4.2 Limpiar el código usado
                $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE id = ?");
                $stmt->execute([$codeData['id']]);

                $response['success'] = true;
                $response['message'] = 'Verificación correcta. Ya puedes editar tu email.';

            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - verify-email-change-code');
                    $response['message'] = 'Error al verificar en la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }

        // --- ACCIÓN: ACTUALIZAR EMAIL (MODIFICADA) ---
        elseif ($action === 'update-email') {
            try {
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE RATE LIMIT (EMAIL) ▼▼▼ ---
                define('EMAIL_CHANGE_COOLDOWN_DAYS', 12);

                $stmt_check_email = $pdo->prepare(
                    "SELECT changed_at FROM user_audit_logs 
                     WHERE user_id = ? AND change_type = 'email' 
                     ORDER BY changed_at DESC LIMIT 1"
                );
                $stmt_check_email->execute([$userId]);
                $lastLogEmail = $stmt_check_email->fetch();

                if ($lastLogEmail) {
                    $lastChangeTime = new DateTime($lastLogEmail['changed_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $interval = $currentTime->diff($lastChangeTime);
                    $daysPassed = (int)$interval->format('%a'); // Obtener días totales

                    if ($daysPassed < EMAIL_CHANGE_COOLDOWN_DAYS) {
                        $daysRemaining = EMAIL_CHANGE_COOLDOWN_DAYS - $daysPassed;
                        $plural = ($daysRemaining == 1) ? 'día' : 'días';
                        throw new Exception("Debes esperar. Podrás cambiar tu correo de nuevo en {$daysRemaining} {$plural}.");
                    }
                }
                // --- ▲▲▲ FIN DE LA LÓGICA DE RATE LIMIT ▲▲▲ ---
                
                $newEmail = trim($_POST['email'] ?? '');
                $oldEmail = $_SESSION['email']; // Capturar el valor antiguo
                
                // 1. Validar formato de email
                if (empty($newEmail) || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('El formato de correo no es válido.');
                }
                
                // 1.5 Validar si el email es el mismo
                if ($newEmail === $oldEmail) {
                    throw new Exception('Este ya es tu correo electrónico.');
                }
        
                // 2. Validar dominio (misma lógica que en el registro)
                $allowedDomains = ['gmail.com', 'outlook.com', 'hotmail.com', 'yahoo.com', 'icloud.com'];
                $emailDomain = substr($newEmail, strrpos($newEmail, '@') + 1);
                
                if (!in_array(strtolower($emailDomain), $allowedDomains)) {
                    throw new Exception('Solo se permiten correos @gmail, @outlook, @hotmail, @yahoo o @icloud.');
                }
        
                // 3. Validar que el email no esté en uso POR OTRO USUARIO
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$newEmail, $userId]);
                
                if ($stmt->fetch()) {
                    throw new Exception('Ese correo electrónico ya está en uso.');
                }
        
                // 4. Actualizar la base de datos
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$newEmail, $userId]);
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE AUDITORÍA (EMAIL) ▼▼▼ ---
                $stmt_log_email = $pdo->prepare(
                    "INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip) 
                     VALUES (?, 'email', ?, ?, ?)"
                );
                // getIpAddress() viene de config.php
                $stmt_log_email->execute([$userId, $oldEmail, $newEmail, getIpAddress()]);
                // --- ▲▲▲ FIN DE LA LÓGICA DE AUDITORÍA ▲▲▲ ---
        
                // 5. Actualizar la sesión
                $_SESSION['email'] = $newEmail;
        
                $response['success'] = true;
                $response['message'] = 'Correo electrónico actualizado con éxito.';
                $response['newEmail'] = $newEmail;
        
            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-email');
                    $response['message'] = 'Error al actualizar la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }

        // --- ▼▼▼ INICIO: NUEVA ACCIÓN (VERIFICAR CONTRASEÑA ACTUAL) ▼▼▼ ---
        elseif ($action === 'verify-current-password') {
            try {
                $ip = getIpAddress();
                $identifier = $userId; // Usamos el ID de usuario como identificador para el rate limit
                $currentPassword = $_POST['current_password'] ?? '';

                // 1. VERIFICAR BLOQUEO
                if (checkLockStatus($pdo, $identifier, $ip)) {
                    throw new Exception('Demasiados intentos fallidos. Por favor, inténtalo de nuevo en ' . LOCKOUT_TIME_MINUTES . ' minutos.');
                }
                
                if (empty($currentPassword)) {
                    throw new Exception('Por favor, introduce tu contraseña actual.');
                }

                // 2. Obtener hash actual de la BD
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $hashedPassword = $stmt->fetchColumn();

                // 3. Verificar
                if ($hashedPassword && password_verify($currentPassword, $hashedPassword)) {
                    // Éxito
                    clearFailedAttempts($pdo, $identifier);
                    $response['success'] = true;
                    $response['message'] = 'Contraseña actual correcta.';
                } else {
                    // Fallo
                    logFailedAttempt($pdo, $identifier, $ip, 'password_verify_fail');
                    throw new Exception('La contraseña actual es incorrecta.');
                }

            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - verify-current-password');
                    $response['message'] = 'Error al verificar en la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        }
        // --- ▲▲▲ FIN: NUEVA ACCIÓN (VERIFICAR CONTRASEÑA ACTUAL) ▲▲▲ ---

        // --- ACCIÓN: ACTUALIZAR CONTRASEÑA (MODIFICADA) ---
        elseif ($action === 'update-password') {
            try {
                
                // --- ▼▼▼ INICIO DE LA LÓGICA DE RATE LIMIT (PASSWORD) ▼▼▼ ---
                define('PASSWORD_CHANGE_COOLDOWN_HOURS', 24);

                $stmt_check_pass = $pdo->prepare(
                    "SELECT changed_at FROM user_audit_logs 
                     WHERE user_id = ? AND change_type = 'password' 
                     ORDER BY changed_at DESC LIMIT 1"
                );
                $stmt_check_pass->execute([$userId]);
                $lastLogPass = $stmt_check_pass->fetch();

                if ($lastLogPass) {
                    $lastChangeTime = new DateTime($lastLogPass['changed_at'], new DateTimeZone('UTC'));
                    $currentTime = new DateTime('now', new DateTimeZone('UTC'));
                    $interval = $currentTime->diff($lastChangeTime);
                    
                    // Calcular horas totales
                    $hoursPassed = ($interval->d * 24) + $interval->h;
                    
                    if ($hoursPassed < PASSWORD_CHANGE_COOLDOWN_HOURS) {
                        $hoursRemaining = PASSWORD_CHANGE_COOLDOWN_HOURS - $hoursPassed;
                        $plural = ($hoursRemaining == 1) ? 'hora' : 'horas';
                        throw new Exception("Debes esperar. Podrás cambiar tu contraseña de nuevo en {$hoursRemaining} {$plural}.");
                    }
                }
                // --- ▲▲▲ FIN DE LA LÓGICA DE RATE LIMIT ▲▲▲ ---

                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                // 1. Validaciones
                if (empty($newPassword) || empty($confirmPassword)) {
                    throw new Exception('Por favor, completa ambos campos de nueva contraseña.');
                }
                if (strlen($newPassword) < 8) {
                    throw new Exception('La nueva contraseña debe tener al menos 8 caracteres.');
                }
                if ($newPassword !== $confirmPassword) {
                    throw new Exception('Las nuevas contraseñas no coinciden.');
                }

                // 2. Obtener hash antiguo (para auditoría)
                $stmt_get_old = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt_get_old->execute([$userId]);
                $oldHashedPassword = $stmt_get_old->fetchColumn();
                if (!$oldHashedPassword) {
                    $oldHashedPassword = 'hash_desconocido'; // Fallback
                }

                // 3. Hashear y actualizar BD
                $newHashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$newHashedPassword, $userId]);

                // 4. Registrar en auditoría
                $stmt_log_pass = $pdo->prepare(
                    "INSERT INTO user_audit_logs (user_id, change_type, old_value, new_value, changed_by_ip) 
                     VALUES (?, 'password', ?, ?, ?)"
                );
                $stmt_log_pass->execute([$userId, $oldHashedPassword, $newHashedPassword, getIpAddress()]);

                $response['success'] = true;
                $response['message'] = '¡Contraseña actualizada con éxito!';

            } catch (Exception $e) {
                // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-password');
                    // Comprobación específica por si falta el ENUM 'password'
                    if (strpos($e->getMessage(), "Data truncated for column 'change_type'") !== false) {
                        $response['message'] = "Error de BD: Asegúrate de añadir 'password' al ENUM 'change_type' en la tabla 'user_audit_logs'.";
                    } else {
                        $response['message'] = 'Error al actualizar la base de datos.';
                    }
                } else {
                    $response['message'] = $e->getMessage();
                }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            }
        }

        // --- ▼▼▼ ¡INICIO DE LA NUEVA ACCIÓN (TOGGLE 2FA)! ▼▼▼ ---
        elseif ($action === 'toggle-2fa') {
            try {
                // 1. Obtener el estado actual
                $stmt_get = $pdo->prepare("SELECT is_2fa_enabled FROM users WHERE id = ?");
                $stmt_get->execute([$userId]);
                $currentState = (int)$stmt_get->fetchColumn();

                // 2. Determinar el nuevo estado (invertirlo)
                $newState = $currentState === 1 ? 0 : 1;

                // 3. Actualizar la base de datos
                $stmt_set = $pdo->prepare("UPDATE users SET is_2fa_enabled = ? WHERE id = ?");
                $stmt_set->execute([$newState, $userId]);

                $response['success'] = true;
                $response['newState'] = $newState; // Devolvemos el nuevo estado al JS
                $response['message'] = $newState === 1 
                    ? 'Verificación de dos pasos activada.' 
                    : 'Verificación de dos pasos desactivada.';

            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - toggle-2fa');
                    $response['message'] = 'Error al actualizar la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        }
        // --- ▲▲▲ ¡FIN DE LA NUEVA ACCIÓN (TOGGLE 2FA)! ▲▲▲ ---

        // --- ▼▼▼ ¡INICIO DE LA ACCIÓN MODIFICADA (UPDATE PREFERENCE)! ▼▼▼ ---
        elseif ($action === 'update-preference') {
            try {
                $field = $_POST['field'] ?? '';
                $value = $_POST['value'] ?? '';

                // 1. Validar el campo (para prevenir inyección SQL en el nombre de la columna)
                $allowedFields = [
                    'language' => ['en-us', 'fr-fr', 'es-latam', 'es-mx'],
                    'theme' => ['system', 'light', 'dark'],
                    'usage_type' => ['personal', 'student', 'teacher', 'small_business', 'large_company'],
                    // --- ¡NUEVOS CAMPOS! ---
                    'open_links_in_new_tab' => ['0', '1'],
                    'increase_message_duration' => ['0', '1']
                ];

                if (!array_key_exists($field, $allowedFields)) {
                    throw new Exception('Configuración de preferencia no válida.');
                }

                // 2. Validar el valor
                if (!in_array($value, $allowedFields[$field])) {
                    // --- ¡NUEVA VALIDACIÓN! ---
                    // Comprobación específica para booleanos por si acaso
                    if ($field === 'open_links_in_new_tab' || $field === 'increase_message_duration') {
                         throw new Exception('El valor para un interruptor debe ser 0 o 1.');
                    }
                    throw new Exception('Valor de preferencia no válido.');
                }

                // 3. Actualizar la base de datos
                // Es seguro concatenar $field aquí porque ha sido validado contra una lista estricta.
                $sql = "UPDATE user_preferences SET $field = ? WHERE user_id = ?";
                $stmt = $pdo->prepare($sql);
                
                // --- ¡NUEVA CONVERSIÓN! ---
                // Convertir el valor a entero si es un campo booleano, si no, dejarlo como string
                $finalValue = ($field === 'open_links_in_new_tab' || $field === 'increase_message_duration') 
                                ? (int)$value 
                                : $value;
                
                $stmt->execute([$finalValue, $userId]);

                $response['success'] = true;
                $response['message'] = 'Preferencia actualizada.';

            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - update-preference');
                    $response['message'] = 'Error al guardar la preferencia en la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        }
        // --- ▲▲▲ ¡FIN DE LA ACCIÓN MODIFICADA (UPDATE PREFERENCE)! ▲▲▲ ---

        // --- ▼▼▼ ¡INICIO DE LA ACCIÓN (LOGOUT ALL DEVICES) - CORREGIDA! ▼▼▼ ---
        elseif ($action === 'logout-all-devices') {
            try {
                // 1. Generar un nuevo token de autenticación universal
                // --- ¡ESTA ES LA LÍNEA CORREGIDA! ---
                $newAuthToken = bin2hex(random_bytes(32));
                
                // 2. Actualizar este token en la base de datos para el usuario actual
                $stmt = $pdo->prepare("UPDATE users SET auth_token = ? WHERE id = ?");
                $stmt->execute([$newAuthToken, $userId]);
                
                // 3. Actualizar la sesión ACTUAL con este nuevo token
                // Esto asegura que la sesión actual siga siendo válida (para "Cerrar en otros")
                // mientras que todas las demás sesiones (que tienen el token antiguo) se invalidarán
                // la próxima vez que carguen index.php.
                $_SESSION['auth_token'] = $newAuthToken;

                $response['success'] = true;
                $response['message'] = 'Se han invalidado todas las demás sesiones.';

            } catch (Exception $e) {
                if ($e instanceof PDOException) {
                    logDatabaseError($e, 'settings_handler - logout-all-devices');
                    $response['message'] = 'Error al actualizar el token de sesión en la base de datos.';
                } else {
                    $response['message'] = $e->getMessage();
                }
            }
        }
        // --- ▲▲▲ ¡FIN DE LA ACCIÓN (LOGOUT ALL DEVICES) - CORREGIDA! ▲▲▲ ---

    }
}

echo json_encode($response);
exit;
?>