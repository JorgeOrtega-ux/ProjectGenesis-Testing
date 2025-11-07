<?php
// FILE: includes/sections/main/home.php
// (Contenido MODIFICADO para agrupar mensajes visualmente)

// --- ▼▼▼ INICIO DE LÓGICA DE CARGA DE GRUPO ▼▼▼ ---
$user_groups = []; 
$current_group_members = [];
$group_messages_raw = []; // Mensajes sin procesar de la BD
$grouped_messages = []; // Mensajes agrupados para renderizar

if (isset($_SESSION['user_id'], $pdo)) {
    
    // 1. Obtener TODOS los grupos del usuario para el popover
    try {
        $stmt_all = $pdo->prepare(
            "SELECT 
                g.id,
                g.name,
                g.uuid 
             FROM groups g
             JOIN user_groups ug_main ON g.id = ug_main.group_id
             WHERE ug_main.user_id = ?
             GROUP BY g.id, g.name, g.uuid
             ORDER BY g.name"
        );
        $stmt_all->execute([$_SESSION['user_id']]);
        $user_groups = $stmt_all->fetchAll();
    } catch (PDOException $e) {
        logDatabaseError($e, 'home.php - load user groups for popover');
    }

    if (isset($current_group_info) && $current_group_info) {
        $currentGroupId = $current_group_info['id'];
        
        // 2. Obtener Miembros (existente)
        try {
            $stmt_members = $pdo->prepare(
                "SELECT u.id, u.username, u.profile_image_url, u.role as user_role
                 FROM users u
                 JOIN user_groups ug ON u.id = ug.user_id
                 WHERE ug.group_id = ?
                 ORDER BY FIELD(u.role, 'founder', 'administrator', 'moderator', 'user'), u.username ASC"
            );
            $stmt_members->execute([$currentGroupId]);
            $current_group_members = $stmt_members->fetchAll();
        } catch (PDOException $e) {
            logDatabaseError($e, 'home.php - load group members');
        }

        // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (Cargar historial de chat) ▼▼▼ ---
        try {
            // ¡MODIFICACIÓN! Añadimos 'u.role as user_role'
            $stmt_messages = $pdo->prepare(
                "SELECT 
                    m.id, m.user_id, m.message_type, m.content, m.created_at,
                    u.username, u.profile_image_url, u.role as user_role
                 FROM group_messages m
                 JOIN users u ON m.user_id = u.id
                 WHERE m.group_id = ?
                 ORDER BY m.created_at ASC
                 LIMIT 50"
            );
            $stmt_messages->execute([$currentGroupId]);
            $group_messages_raw = $stmt_messages->fetchAll();
        } catch (PDOException $e) {
            logDatabaseError($e, 'home.php - load chat history');
        }
        // --- ▲▲▲ FIN DE BLOQUE MODIFICADO ▲▲▲ ---
    }
}

// 2. Preparar el texto para el H1
$homeH1TextKey = 'home.chat.selectGroup';
$homeH1Text = "Selecciona un grupo para comenzar a chatear"; // (i18n: home.chat.selectGroup)
if (isset($current_group_info) && $current_group_info) {
    $homeH1TextKey = 'home.chat.chattingWith';
    $homeH1Text = "Chat de " . htmlspecialchars($current_group_info['name']); // Fallback
}

// 3. Agrupar miembros (existente)
$grouped_members = [
    'founder' => [], 'administrator' => [], 'moderator' => [], 'user' => []
];
$member_role_headings = [
    'founder' => 'admin.users.roleFounder',
    'administrator' => 'admin.users.roleAdministrator',
    'moderator' => 'admin.users.roleModerator',
    'user' => 'admin.users.roleUser'
];
if (!empty($current_group_members)) {
    foreach ($current_group_members as $member) {
        $role = $member['user_role'] ?? 'user';
        if (isset($grouped_members[$role])) {
            $grouped_members[$role][] = $member;
        } else {
            $grouped_members['user'][] = $member;
        }
    }
}

// --- ▼▼▼ INICIO DE NUEVA FUNCIÓN (Renderizar burbuja de chat en PHP) ▼▼▼ ---
function formatMessageTimePHP($dateTimeString) {
    try {
        $date = new DateTime($dateTimeString, new DateTimeZone('UTC')); 
        // --- ¡MODIFICACIÓN! Usar la zona horaria del usuario si está disponible, si no, 'UTC' ---
        $userTimezone = $_SESSION['timezone'] ?? 'UTC'; 
        $date->setTimezone(new DateTimeZone($userTimezone)); 
        return $date->format('H:i');
    } catch (Exception $e) {
        return "--:--";
    }
}
// --- ▲▲▲ FIN DE NUEVA FUNCIÓN ▲▲▲ ---


// --- ▼▼▼ INICIO DE NUEVA LÓGICA DE AGRUPACIÓN DE MENSAJES ▼▼▼ ---
$current_message_group = null;
$max_time_diff_seconds = 60; // Agrupar mensajes si son del mismo usuario en 60 segundos

foreach ($group_messages_raw as $msg) {
    $isContinuation = false;
    $msgTimestamp = strtotime($msg['created_at']);

    if ($current_message_group !== null) {
        $lastTimestamp = $current_message_group['timestamp_raw'];
        $timeDiff = $msgTimestamp - $lastTimestamp;
        
        // Comprobar si es continuación (mismo usuario, poco tiempo)
        if ($msg['user_id'] === $current_message_group['user_id'] && $timeDiff <= $max_time_diff_seconds) {
            $isContinuation = true;
        }
    }

    if ($isContinuation) {
        // Añadir mensaje al grupo existente
        $current_message_group['messages'][] = [
            'type' => $msg['message_type'],
            'content' => $msg['content']
        ];
        // Actualizar la hora del *grupo* a la hora del *último* mensaje
        $current_message_group['timestamp_raw'] = $msgTimestamp;
        $current_message_group['timestamp_formatted'] = formatMessageTimePHP($msg['created_at']);
    } else {
        // Guardar el grupo anterior si existe
        if ($current_message_group !== null) {
            $grouped_messages[] = $current_message_group;
        }
        
        // Empezar un nuevo grupo
        $current_message_group = [
            'user_id' => $msg['user_id'],
            'username' => $msg['username'],
            'profile_image_url' => $msg['profile_image_url'],
            'user_role' => $msg['user_role'], // <-- ¡Añadido para el borde!
            'timestamp_raw' => $msgTimestamp,
            'timestamp_formatted' => formatMessageTimePHP($msg['created_at']),
            'messages' => [
                [
                    'type' => $msg['message_type'],
                    'content' => $msg['content']
                ]
            ]
        ];
    }
}
// Guardar el último grupo
if ($current_message_group !== null) {
    $grouped_messages[] = $current_message_group;
}
// --- ▲▲▲ FIN DE NUEVA LÓGICA DE AGRUPACIÓN DE MENSAJES ▲▲▲ ---

?>

<div class="section-content overflow-y <?php echo ($CURRENT_SECTION === 'home') ? 'active' : 'disabled'; ?>" data-section="home">

    <div class="page-toolbar-container" id="home-toolbar-container">
        <div class="page-toolbar-floating">
            <div class="toolbar-action-default">
                
                <div class="page-toolbar-left">
                    <button type="button"
                        class="page-toolbar-button"
                        data-action="toggleModuleGroupSelect" 
                        data-tooltip="toolbar.buttons.selectGroup">
                        <span class="material-symbols-rounded">groups</span>
                    </button>
                    
                    <div class="toolbar-group-display <?php echo (isset($current_group_info) && $current_group_info) ? 'active' : ''; ?>" id="selected-group-display">
                        <span class="material-symbols-rounded">label</span>
                        
                        <?php if (isset($current_group_info) && $current_group_info): ?>
                            <span class="toolbar-group-text">
                                <?php echo htmlspecialchars($current_group_info['name']); ?>
                            </span>
                        <?php else: ?>
                            <span class="toolbar-group-text" data-i18n="toolbar.noGroupSelected">
                                Ningún grupo
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="page-toolbar-right">
                    <button type="button"
                        class="page-toolbar-button"
                        id="toggle-members-panel-btn"
                        data-action="toggleModuleGroupMembers"
                        data-tooltip="header.buttons.groupMembers"
                        <?php echo (!isset($current_group_info) || !$current_group_info) ? 'disabled' : ''; ?>
                        > 
                        <span class="material-symbols-rounded">group</span>
                    </button>
                    <button type="button"
                        class="page-toolbar-button"
                        data-action="toggleSectionMyGroups"
                        data-tooltip="header.buttons.myGroups"> 
                        <span class="material-symbols-rounded">view_list</span>
                    </button>

                    <button type="button"
                        class="page-toolbar-button"
                        data-action="toggleSectionJoinGroup"
                        data-tooltip="home.noGroups.joinButton"> 
                        <span class="material-symbols-rounded">add</span>
                    </button>
                </div>
                
            </div>
        </div>

        <div class="popover-module body-title disabled" 
             data-module="moduleGroupSelect"
             style="width: 300px; left: 8px; right: auto; top: calc(100% + 8px);">
            <div class="menu-content">
                <?php if (empty($user_groups)): ?>
                    <div class="menu-list">
                        <div class="menu-header" data-i18n="modals.selectGroup.noGroups">No perteneces a ningún grupo.</div>
                        <div class="menu-link" data-action="toggleSectionJoinGroup">
                            <div class="menu-link-icon"><span class="material-symbols-rounded">add</span></div>
                            <div class="menu-link-text"><span data-i18n="modals.selectGroup.joinButton">Unirme a un grupo</span></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="menu-list">
                        <div class="menu-header" data-i18n="modals.selectGroup.title">Seleccionar Grupo</div>
                        
                        <div class="menu-link group-select-item <?php echo (!isset($current_group_info) || !$current_group_info) ? 'active' : ''; ?>"
                             data-group-id="none"
                             data-i18n-key="toolbar.noGroupSelected"
                             data-group-uuid=""> <div class="menu-link-icon"><span class="material-symbols-rounded">label_off</span></div>
                            <div class="menu-link-text"><span data-i18n="toolbar.noGroupSelected">Ningún grupo</span></div>
                            <div class="menu-link-check-icon">
                                <?php if (!isset($current_group_info) || !$current_group_info): ?>
                                    <span class="material-symbols-rounded">check</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php foreach ($user_groups as $group): ?>
                            <?php $is_active_group = (isset($current_group_info) && $current_group_info && $group['id'] === $current_group_info['id']); ?>
                            <div class="menu-link group-select-item <?php echo $is_active_group ? 'active' : ''; ?>" 
                                 data-group-id="<?php echo $group['id']; ?>" 
                                 data-group-name="<?php echo htmlspecialchars($group['name']); ?>"
                                 data-group-uuid="<?php echo htmlspecialchars($group['uuid']); ?>"> 
                                <div class="menu-link-icon"><span class="material-symbols-rounded">label</span></div>
                                <div class="menu-link-text"><span><?php echo htmlspecialchars($group['name']); ?></span></div>
                                <div class="menu-link-check-icon">
                                    <?php if ($is_active_group): ?>
                                        <span class="material-symbols-rounded">check</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="component-wrapper" style="display: flex; flex-direction: column; height: 100%; padding-bottom: 16px; padding-left: 0; padding-right: 0;"> 

        <div class="chat-layout-container">
            
            <div class="chat-layout__top" id="chat-history-container">
                
                <?php if (!isset($current_group_info) || !$current_group_info): ?>
                    <div class="auth-container text-center" style="margin-top: 10vh;"> 
                        <h1 class="auth-title" 
                            id="home-chat-placeholder" 
                            data-i18n="home.chat.selectGroup"
                            style="font-size: 24px; color: #6b7280; font-weight: 500; line-height: 1.6;">
                            <?php echo $homeH1Text; // "Selecciona un grupo..." ?>
                        </h1>
                    </div>
                <?php else: ?>
                    <?php
                    // --- ▼▼▼ INICIO DE RENDERIZADO MODIFICADO ▼▼▼ ---
                    $defaultAvatar = "https://ui-avatars.com/api/?name=?&size=100&background=e0e0e0&color=ffffff";
                    foreach($grouped_messages as $group):
                        $isOwnMessage = ($group['user_id'] == $_SESSION['user_id']);
                        $avatarUrl = $group['profile_image_url'] ?? $defaultAvatar;
                        if(empty($avatarUrl)) $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($group['username']) . "&size=100&background=e0e0e0&color=ffffff";
                        
                        $messages_text = [];
                        $messages_images = [];
                        foreach ($group['messages'] as $msg) {
                            if ($msg['type'] === 'text') {
                                $messages_text[] = htmlspecialchars($msg['content']);
                            } else if ($msg['type'] === 'image') {
                                $messages_images[] = htmlspecialchars($msg['content']);
                            }
                        }
                    ?>
                        <div class="chat-bubble <?php echo $isOwnMessage ? 'is-own' : ''; ?>"
                             data-user-id="<?php echo $group['user_id']; ?>"
                             data-timestamp="<?php echo $group['timestamp_raw']; ?>">
                            
                            <!-- ¡MODIFICADO! Añadido data-role para el borde -->
                            <div class="chat-bubble-avatar" data-role="<?php echo htmlspecialchars($group['user_role']); ?>">
                                <img src="<?php echo $avatarUrl; ?>" alt="<?php echo htmlspecialchars($group['username']); ?>">
                            </div>
                            
                            <div class="chat-bubble-content">
                                <div class="chat-bubble-header">
                                    <span class="chat-bubble-username"><?php echo htmlspecialchars($group['username']); ?></span>
                                </div>
                                
                                <!-- ¡NUEVO! Contenedor de cuerpo -->
                                <div class="chat-bubble-body">
                                    <?php 
                                    // 1. Renderizar texto (si existe)
                                    if (!empty($messages_text)): ?>
                                        <div class="chat-bubble-text">
                                            <?php echo implode('<br>', $messages_text); // Unir múltiples textos si es necesario ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    // 2. Renderizar imágenes (si existen)
                                    if (!empty($messages_images)): ?>
                                        <div class="chat-bubble-attachments">
                                            <?php foreach($messages_images as $img_url): ?>
                                                <div class="chat-bubble-image">
                                                    <img src="<?php echo $img_url; ?>" alt="Imagen adjunta" loading="lazy">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="chat-bubble-footer">
                                    <span class="chat-bubble-time"><?php echo $group['timestamp_formatted']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <!-- --- ▲▲▲ FIN DE RENDERIZADO MODIFICADO ▲▲▲ --- -->
                    
                    <script>
                        (function() {
                            const chatHistory = document.getElementById('chat-history-container');
                            if (chatHistory) {
                                chatHistory.scrollTop = chatHistory.scrollHeight;
                            }
                        })();
                    </script>
                <?php endif; ?>
                
            </div>
            <div class="chat-layout__bottom <?php echo (!isset($current_group_info) || !$current_group_info) ? 'hidden' : ''; ?>">
                
                <input type="file" id="chat-file-input" class="visually-hidden" accept="image/png, image/jpeg, image/gif, image/webp" multiple>

                <div class="chat-input-container" id="chat-input-wrapper">
                    
                    <div class="chat-input__text-area" 
                         id="chat-input-text-area"
                         contenteditable="true" 
                         data-placeholder="Escribe un mensaje..."> </div>
                    
                    <div class="chat-input__buttons-row">
                        <button type="button" class="chat-input__button chat-input__button--attach" id="chat-attach-button" data-tooltip="Adjuntar">
                            <span class="material-symbols-rounded">add</span>
                        </button>
                        <button type="button" class="chat-input__button chat-input__button--send" id="chat-send-button" data-tooltip="Enviar">
                            <span class="material-symbols-rounded">send</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="module-content module-members-surface body-title disabled" data-module="moduleGroupMembers">
        <div class="menu-content">
            
            <div class="members-header">
                <div class="members-title-wrapper">
                    <h3 class="members-title" data-i18n="members.title">Lista de miembros</h3>
                </div>
            </div>
            
            <div class="members-list-container">
                <?php if (!isset($current_group_info) || !$current_group_info): ?>
                    <div class="members-empty-state">
                        <span class="material-symbols-rounded">group_off</span>
                        <p data-i18n="members.noGroup">Selecciona un grupo para ver sus miembros.</p>
                    </div>
                <?php elseif (empty($current_group_members)): ?>
                    <div class="members-empty-state">
                        <span class="material-symbols-rounded">person_off</span>
                        <p data-i18n="members.noMembers">Este grupo aún no tiene miembros.</p>
                    </div>
                <?php else: ?>
                    <?php 
                    $defaultAvatar = "https://ui-avatars.com/api/?name=?&size=100&background=e0e0e0&color=ffffff";
                    
                    foreach ($grouped_members as $role => $members_in_role):
                        if (!empty($members_in_role)):
                    ?>
                        <h4 class="member-list-heading" data-i18n="<?php echo $member_role_headings[$role]; ?>"></h4>
                        
                        <div class="member-list">
                            <?php 
                            foreach ($members_in_role as $member): 
                                $avatarUrl = $member['profile_image_url'] ?? $defaultAvatar;
                                if (empty($avatarUrl)) {
                                    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($member['username']) . "&size=100&background=e0e0e0&color=ffffff";
                                }
                            ?>
                                <div class="member-item" data-user-id="<?php echo htmlspecialchars($member['id']); ?>" data-user-role="<?php echo htmlspecialchars($member['user_role']); ?>">
                                    <div class="component-card__avatar member-avatar" 
                                         data-role="<?php echo htmlspecialchars($member['user_role']); ?>"
                                         data-user-id="<?php echo htmlspecialchars($member['id']); ?>"> <img src="<?php echo htmlspecialchars($avatarUrl); ?>"
                                             alt="<?php echo htmlspecialchars($member['username']); ?>"
                                             class="component-card__avatar-image">
                                    </div>
                                    <span class="member-name"><?php echo htmlspecialchars($member['username']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>