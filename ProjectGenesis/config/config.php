<?php
// /ProjectGenesis/config/config.php

// --- ▼▼▼ SOLUCIÓN DE SEGURIDAD: CONFIGURAR COOKIES DE SESIÓN ▼▼▼ ---

// 1. Usar solo cookies (previene ataques de fijación de sesión)
ini_set('session.use_only_cookies', 1);

// 2. Cookie HttpOnly (previene acceso por JavaScript - XSS)
ini_set('session.cookie_httponly', 1);

// 3. Cookie Secure (solo enviar sobre HTTPS)
// ¡IMPORTANTE! Descomenta esto en producción cuando tengas SSL/HTTPS.
// En un entorno 'localhost' sin HTTPS, dejarlo activo puede impedir el login.
// ini_set('session.cookie_secure', 1);

// 4. Cookie SameSite (previene CSRF)
// 'Lax' es un estándar moderno y balanceado.
ini_set('session.cookie_samesite', 'Lax');

// --- ▲▲▲ FIN DE LA SOLUCIÓN DE SEGURIDAD ▲▲▲ ---


// 1. INICIAR LA SESIÓN
// Ahora se inicia después de la configuración segura.
session_start();

// --- ¡¡¡ESTA ES LA LÍNEA CORREGIDA!!! ---
// Forzar la zona horaria del servidor a UTC.
// Esto soluciona los problemas de expiración de códigos.
date_default_timezone_set('UTC');
// --- FIN DE LA CORRECCIÓN ---

// ======================================
// === ▼▼▼ I18N (Traducciones) ▼▼▼ ===
// ======================================

/**
 * @var array Contenedor global de traducciones
 */
$translations = [];

/**
 * Obtiene la preferencia de idioma del navegador.
 * (Movido desde auth_handler.php)
 * @param string $acceptLanguage El string HTTP_ACCEPT_LANGUAGE.
 * @return string El código de idioma preferido (ej. 'es-419').
 */
function getPreferredLanguage($acceptLanguage) {
    $supportedLanguages = [
        'en-us' => 'en-us',
        'es-mx' => 'es-mx',
        'es-419' => 'es-419', // Usando es-419
        'fr-fr' => 'fr-fr'
    ];
    
    $primaryLanguageMap = [
        'es' => 'es-419', // Default 'es' a es-419
        'en' => 'en-us',
        'fr' => 'fr-fr'
    ];
    
    $defaultLanguage = 'es-419'; // Default base

    if (empty($acceptLanguage)) {
        return $defaultLanguage;
    }

    $langs = [];
    preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $acceptLanguage, $matches);

    if (!empty($matches[1])) {
        $langs = array_map('strtolower', $matches[1]);
    }

    $primaryMatch = null;
    foreach ($langs as $lang) {
        if (isset($supportedLanguages[$lang])) {
            return $supportedLanguages[$lang];
        }
        
        $primary = substr($lang, 0, 2);
        if ($primaryMatch === null && isset($primaryLanguageMap[$primary])) {
            $primaryMatch = $primaryLanguageMap[$primary];
        }
    }
    
    return $primaryMatch ?? $defaultLanguage;
}


/**
 * Carga el archivo de traducción basado en el código de idioma.
 * Asume que los archivos están en /translations/es-419.json
 * @param string $langCode Código de idioma (ej. 'es-419', 'en-us')
 * @return array Las traducciones cargadas.
 */
function loadTranslations($langCode) {
    // El usuario solicitó 'es-419' como el único por ahora
    $defaultLangCode = 'es-419'; 
    $filePath = dirname(__DIR__) . '/translations/' . $langCode . '.json';

    if (!file_exists($filePath)) {
        // Si el idioma solicitado no existe, intenta cargar el por defecto
        $filePath = dirname(__DIR__) . '/translations/' . $defaultLangCode . '.json';
        if (!file_exists($filePath)) {
            // Si ni siquiera el por defecto existe, retorna un array vacío
            return []; 
        }
    }

    $jsonContent = @file_get_contents($filePath);
    $decodedJson = json_decode($jsonContent, true);

    return (json_last_error() === JSON_ERROR_NONE) ? $decodedJson : [];
}

/**
 * Obtiene un string de traducción por su clave.
 * @param string $key La clave de traducción (ej. 'auth.login.title')
 * @param array $replacements Un array asociativo de valores a reemplazar (ej. ['username' => 'John'])
 * @return string El string traducido o la clave si no se encuentra.
 */
function __($key, $replacements = []) {
    // Usa $GLOBALS para acceder a la variable definida fuera del scope
    $translation = $GLOBALS['translations'][$key] ?? $key;
    
    if (!empty($replacements) && is_array($replacements)) {
        foreach ($replacements as $placeholder => $value) {
            // Reemplaza placeholders con formato :placeholder
            $translation = str_replace(':' . $placeholder, $value, $translation);
        }
    }
    
    return $translation;
}

// --- Determinar qué idioma cargar ---

// 1. Usar el idioma de la sesión si existe (establecido por el usuario en 'settings')
$langToLoad = $_SESSION['language'] ?? null;

// 2. Si no está en la sesión, detectar del navegador
if (empty($langToLoad)) {
    $langToLoad = getPreferredLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'es-419');
}

// 3. Cargar las traducciones globalmente
$translations = loadTranslations($langToLoad);

// ======================================
// === ▲▲▲ FIN I18N (Traducciones) ▲▲▲ ===
// ======================================


// 2. CONFIGURACIÓN DE LA BASE DE DATOS
define('DB_HOST', 'localhost');
define('DB_NAME', 'project_genesis'); 
define('DB_USER', 'root');
define('DB_PASS', '');

// 3. CREAR CONEXIÓN PDO
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // --- ▼▼▼ ¡¡¡MODIFICACIÓN IMPORTANTE!!! ▼▼▼ ---
    // Forzar la zona horaria de la CONEXIÓN PDO a UTC.
    // Esto asegura que NOW() y CURRENT_TIMESTAMP usen UTC.
    $pdo->exec("SET time_zone = '+00:00'");
    // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---

} catch (PDOException $e) {
    // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
    // En lugar de exponer el error, lo guardamos en un log y mostramos un error genérico.
    logDatabaseError($e, 'PDO Connection');
    // Usamos la función de traducción __() que acabamos de definir
    die(__("config.dbError"));
    // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
}

// 4. BASE PATH (¡ESTA ES LA LÍNEA CRÍTICA!)
// Asegúrate de que esta línea esté correcta.
$basePath = '/ProjectGenesis';


// ======================================
// === FUNCIONES CSRF GENERALES ===
// ======================================

/**
 * Genera un nuevo token CSRF, lo almacena en la sesión y lo devuelve.
 * @return string
 */
function generateCsrfToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * Obtiene el token CSRF actual de la sesión. Si no existe, genera uno nuevo.
 * @return string
 */
function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        return generateCsrfToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token enviado contra el almacenado en la sesión.
 * @param string $submittedToken El token enviado (ej. desde $_POST o $_GET)
 * @return bool
 */
function validateCsrfToken($submittedToken) {
    if (empty($submittedToken) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submittedToken);
}

/**
 * Imprime un <input> oculto con el token CSRF actual.
 * Se usa para insertarlo fácilmente en los formularios.
 */
function outputCsrfInput() {
    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(getCsrfToken()) . '">';
}


// ======================================
// === ▼▼▼ NUEVAS FUNCIONES DE SEGURIDAD (RATE LIMIT) ▼▼▼ ===
// ======================================

// 1. Definir constantes de seguridad
define('MAX_LOGIN_ATTEMPTS', 5); // Intentos máximos
define('LOCKOUT_TIME_MINUTES', 5); // Minutos de bloqueo

/**
 * Obtiene la dirección IP real del usuario.
 * @return string
 */
function getIpAddress() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }
}

/**
 * Verifica si un email o IP están bloqueados por intentos excesivos.
 * @param PDO $pdo
 * @param string $identifier El email del usuario.
 * @param string $ip La IP del usuario.
 * @return bool
 */
function checkLockStatus($pdo, $identifier, $ip) {
    try {
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM security_logs 
             WHERE (user_identifier = ? OR ip_address = ?) 
             AND created_at > (NOW() - INTERVAL ? MINUTE)"
        );
        $stmt->execute([$identifier, $ip, LOCKOUT_TIME_MINUTES]);
        $attempts = $stmt->fetchColumn();
        
        return $attempts >= MAX_LOGIN_ATTEMPTS;

    } catch (PDOException $e) {
        // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
        logDatabaseError($e, 'checkLockStatus');
        // Si falla la BD, por seguridad, no bloqueamos.
        return false;
        // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
    }
}

/**
 * Registra un intento fallido en la base de datos.
 * @param PDO $pdo
 * @param string $identifier El email del usuario.
 * @param string $ip La IP del usuario.
 * @param string $actionType 'login_fail' or 'reset_fail'
 */
function logFailedAttempt($pdo, $identifier, $ip, $actionType) {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO security_logs (user_identifier, action_type, ip_address) 
             VALUES (?, ?, ?)"
        );
        $stmt->execute([$identifier, $actionType, $ip]);
    } catch (PDOException $e) {
        // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
        logDatabaseError($e, 'logFailedAttempt');
        // No hacer nada si falla el log
        // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
    }
}

/**
 * Limpia los intentos fallidos de un usuario (usado en login/reseteo exitoso).
 * @param PDO $pdo
 * @param string $identifier El email del usuario.
 */
function clearFailedAttempts($pdo, $identifier) {
    try {
        $stmt = $pdo->prepare(
            "DELETE FROM security_logs 
             WHERE user_identifier = ?"
        );
        $stmt->execute([$identifier]);
    } catch (PDOException $e) {
        // --- ▼▼▼ MODIFICACIÓN DE SEGURIDAD (LOG) ▼▼▼ ---
        logDatabaseError($e, 'clearFailedAttempts');
        // No hacer nada si falla la limpieza
        // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
    }
}
// --- ▲▲▲ FIN DE NUEVAS FUNCIONES DE SEGURIDAD ▲▲▲ ---


// ======================================
// === ▼▼▼ NUEVA FUNCIÓN DE LOGGING DE ERRORES ▼▼▼ ===
// ======================================

/**
 * Escribe un error de base de datos en un archivo de log.
 * @param PDOException $e La excepción capturada.
 * @param string $context Un identificador de dónde ocurrió el error (ej. 'auth_handler', 'PDO Connection').
 */
function logDatabaseError(PDOException $e, $context = 'Default') {
    // Definir la ruta del log (un nivel arriba de /config, en /ProjectGenesis/logs/database_errors.log)
    $logDir = dirname(__DIR__) . '/logs';
    $logFile = $logDir . '/database_errors.log';

    // Crear el directorio de logs si no existe
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Formatear el mensaje de error
    $timestamp = date('Y-m-d H:i:s T'); // UTC gracias al date_default_timezone_set
    $errorMessage = sprintf(
        "[%s] [%s] %s (Archivo: %s, Línea: %d)\n",
        $timestamp,
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );

    // Escribir en el archivo de log (añadiendo, sin sobrescribir)
    // Usamos @ para suprimir errores si el archivo no se puede escribir,
    // aunque en un entorno de producción esto debería tener permisos correctos.
    @file_put_contents($logFile, $errorMessage, FILE_APPEND);
}
// --- ▲▲▲ FIN DE LA NUEVA FUNCIÓN DE LOGGING ▲▲▲ ---
?>