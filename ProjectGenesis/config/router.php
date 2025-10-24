<?php
// /ProjectGenesis/config/router.php

// --- MODIFICACIÓN 1: INCLUIR CONFIG ---
include '../config/config.php'; // Inicia la sesión

$page = $_GET['page'] ?? 'home';

$CURRENT_SECTION = $page; 

// --- ▼▼▼ MODIFICACIÓN: AÑADIR RUTAS DE SETTINGS ▼▼▼ ---
$allowedPages = [
    'home'     => '../includes/sections/main/home.php',
    'explorer' => '../includes/sections/main/explorer.php',
    'login'    => '../includes/sections/auth/login.php',
    'register' => '../includes/sections/auth/register.php',
    'reset-password' => '../includes/sections/auth/reset-password.php',
    '404'      => '../includes/sections/main/404.php', 

    // Nuevas secciones de Configuración
    'settings-profile'       => '../includes/sections/settings/your-profile.php',
    'settings-login'         => '../includes/sections/settings/login-security.php',
    'settings-accessibility' => '../includes/sections/settings/accessibility.php',
];
// --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---

// --- MODIFICACIÓN 2: PROTEGER EL ROUTER ---
$authPages = ['login', 'register', 'reset-password'];
$isAuthPage = in_array($page, $authPages);

// --- ▼▼▼ MODIFICACIÓN: PROTEGER TAMBIÉN LAS PÁGINAS DE SETTINGS ▼▼▼ ---
// Si pide una página protegida (que no es de auth ni 404) Y NO tiene sesión
$isSettingsPage = strpos($page, 'settings-') === 0;

if (!isset($_SESSION['user_id']) && !$isAuthPage && $page !== '404') {
    // No le damos la página.
    http_response_code(403); // 403 Forbidden
    $CURRENT_SECTION = '404'; 
    include $allowedPages['404'];
    exit; // Detener script
}
// --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---


if (array_key_exists($page, $allowedPages)) {

    // --- ▼▼▼ INICIO DE LA LÓGICA MOVIDA (Y MODIFICADA) ▼▼▼ ---
    // Pre-procesamos las variables solo para la página que las necesita.
    if ($page === 'settings-profile') {
        
        $defaultAvatar = "https://ui-avatars.com/api/?name=?&size=100&background=e0e0e0&color=ffffff";
        $profileImageUrl = $_SESSION['profile_image_url'] ?? $defaultAvatar;
        if (empty($profileImageUrl)) {
            $profileImageUrl = $defaultAvatar;
        }

        // Comprobar si la URL es un avatar por defecto (generado) o uno subido
        // (La protección de ruta anterior ya asegura que $_SESSION['user_id'] existe)
        $isDefaultAvatar = strpos($profileImageUrl, 'ui-avatars.com') !== false || 
                           strpos($profileImageUrl, 'user-' . $_SESSION['user_id'] . '.png') !== false;

        $usernameForAlt = $_SESSION['username'] ?? 'Usuario';
        $userRole = $_SESSION['role'] ?? 'user';
        
        // --- ▼▼▼ LÍNEA AÑADIDA ▼▼▼ ---
        $userEmail = $_SESSION['email'] ?? 'correo@ejemplo.com';
        // --- ▲▲▲ FIN LÍNEA AÑADIDA ▲▲▲ ---
    }
    // --- ▲▲▲ FIN DE LA LÓGICA MOVIDA ▲▲▲ ---

    include $allowedPages[$page];

} else {
    http_response_code(404);
    $CURRENT_SECTION = '404'; 
    include $allowedPages['404']; 
}
?>