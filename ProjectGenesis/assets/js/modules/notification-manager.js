// FILE: assets/js/modules/notification-manager.js
// (CORREGIDO: Ahora este módulo maneja el toggle y la carga)

import { callNotificationApi } from '../services/api-service.js';
import { getTranslation, applyTranslations } from '../services/i18n-manager.js';
// --- ▼▼▼ INICIO DE MODIFICACIÓN ▼▼▼ ---
import { deactivateAllModules } from '../app/main-controller.js';
// --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---

let hasLoadedNotifications = false;
let currentNotificationCount = 0;

// ... (Las funciones formatTimeAgo, getRelativeDateGroup, setNotificationCount, addNotificationToUI no cambian) ...
function formatTimeAgo(dateString) {
    if (!dateString) return '';
    try {
        const date = new Date(dateString.includes('Z') ? dateString : dateString + 'Z'); // Asegurar que se parsee como UTC
        const now = new Date();
        const seconds = Math.round((now - date) / 1000);
        
        const minutes = Math.round(seconds / 60);
        const hours = Math.round(minutes / 60);
        const days = Math.round(hours / 24);

        if (seconds < 60) {
            return 'Ahora';
        } else if (minutes < 60) {
            return `hace ${minutes}m`;
        } else if (hours < 24) {
            return `hace ${hours}h`;
        } else if (days === 1) {
            return 'Ayer';
        } else {
            // Formato para fechas más antiguas (ej. "7 Nov")
            return date.toLocaleDateString(window.userLanguage.split('-')[0] || 'es', {
                month: 'short',
                day: 'numeric'
            });
        }
    } catch (e) {
        console.error("Error al formatear fecha:", e);
        return dateString;
    }
}
function getRelativeDateGroup(date) {
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    const lang = window.userLanguage.split('-')[0] || 'es';

    if (date.toDateString() === today.toDateString()) {
        return "Hoy";
    }
    if (date.toDateString() === yesterday.toDateString()) {
        return "Ayer";
    }
    // Formato para grupos más antiguos (ej. "viernes, 7 de noviembre de 2025")
    return date.toLocaleDateString(lang, {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}
export function setNotificationCount(count) {
    currentNotificationCount = count;
    const badge = document.getElementById('notification-badge-count');
    if (!badge) return;

    badge.textContent = count;
    if (count > 0) {
        badge.classList.remove('disabled');
    } else {
        badge.classList.add('disabled');
    }
}
function addNotificationToUI(notification) {
    const avatar = notification.actor_avatar || "https://ui-avatars.com/api/?name=?&size=100&background=e0e0e0&color=ffffff";
    let notificationHtml = '';
    let textKey = '';
    let href = '#'; // Enlace por defecto

    // 1. Generar el string de tiempo relativo
    const timeAgo = formatTimeAgo(notification.created_at);
    // 2. Determinar estado (leído/no leído)
    const isUnread = notification.is_read == 0;
    const readClass = isUnread ? 'is-unread' : 'is-read';
    // 3. Crear el punto azul si no está leído
    const unreadDot = isUnread ? '<span class="notification-unread-dot"></span>' : '';

    switch (notification.type) {
        case 'friend_request':
            textKey = 'notifications.friendRequestText';
            href = `${window.projectBasePath}/profile/${notification.actor_username}`;
            notificationHtml = `
                <div class="notification-item ${readClass}" data-id="${notification.id}" data-user-id="${notification.actor_user_id}">
                    <a href="${href}" data-nav-js="true" class="notification-avatar">
                        <img src="${avatar}" alt="${notification.actor_username}">
                    </a>
                    <div class="notification-content">
                        <div class="notification-text">
                            <a href="${href}" data-nav-js="true" style="text-decoration: none; color: inherit;">
                                <strong>${notification.actor_username}</strong>
                            </a>
                            <span data-i18n="${textKey}">quiere ser tu amigo.</span>
                        </div>
                        <div class="notification-timestamp">${timeAgo} ${unreadDot}</div>
                        <div class="notification-actions">
                            <button type="button" class="notification-action-button notification-action-button--secondary" 
                                    data-action="friend-decline-request" data-user-id="${notification.actor_user_id}">
                                <span data-i18n="friends.declineRequest">Rechazar</span>
                            </button>
                            <button type="button" class="notification-action-button notification-action-button--primary" 
                                    data-action="friend-accept-request" data-user-id="${notification.actor_user_id}">
                                <span data-i18n="friends.acceptRequest">Aceptar</span>
                            </button>
                        </div>
                    </div>
                </div>`;
            break;
        
        case 'friend_accept':
            textKey = 'js.notifications.friendAccepted';
            href = `${window.projectBasePath}/profile/${notification.actor_username}`;
            notificationHtml = `
                <a href="${href}" data-nav-js="true" class="notification-item ${readClass}" data-id="${notification.id}" data-user-id="${notification.actor_user_id}">
                    <div class="notification-avatar">
                        <img src="${avatar}" alt="${notification.actor_username}">
                    </div>
                    <div class="notification-content">
                        <div class="notification-text">
                            <span data-i18n="${textKey}">${getTranslation(textKey).replace('{username}', notification.actor_username)}</span>
                        </div>
                        <div class="notification-timestamp">${timeAgo} ${unreadDot}</div>
                        </div>
                </a>`;
            break;

        case 'like':
            textKey = 'js.notifications.newLike';
            href = `${window.projectBasePath}/post/${notification.reference_id}`;
            notificationHtml = `
                <a href="${href}" data-nav-js="true" class="notification-item ${readClass}" data-id="${notification.id}" data-user-id="${notification.actor_user_id}">
                    <div class="notification-avatar">
                        <img src="${avatar}" alt="${notification.actor_username}">
                    </div>
                    <div class="notification-content">
                        <div class="notification-text">
                            <span data-i18n="${textKey}">${getTranslation(textKey).replace('{username}', notification.actor_username)}</span>
                        </div>
                        <div class="notification-timestamp">${timeAgo} ${unreadDot}</div>
                        </div>
                </a>`;
            break;
            
        case 'comment':
            textKey = 'js.notifications.newComment';
            href = `${window.projectBasePath}/post/${notification.reference_id}`;
             notificationHtml = `
                <a href="${href}" data-nav-js="true" class="notification-item ${readClass}" data-id="${notification.id}" data-user-id="${notification.actor_user_id}">
                    <div class="notification-avatar">
                        <img src="${avatar}" alt="${notification.actor_username}">
                    </div>
                    <div class="notification-content">
                        <div class="notification-text">
                            <span data-i18n="${textKey}">${getTranslation(textKey).replace('{username}', notification.actor_username)}</span>
                        </div>
                        <div class="notification-timestamp">${timeAgo} ${unreadDot}</div>
                        </div>
                </a>`;
            break;

        case 'reply':
            textKey = 'js.notifications.newReply';
            href = `${window.projectBasePath}/post/${notification.reference_id}`;
             notificationHtml = `
                <a href="${href}" data-nav-js="true" class="notification-item ${readClass}" data-id="${notification.id}" data-user-id="${notification.actor_user_id}">
                    <div class="notification-avatar">
                        <img src="${avatar}" alt="${notification.actor_username}">
                    </div>
                    <div class="notification-content">
                        <div class="notification-text">
                            <span data-i18n="${textKey}">${getTranslation(textKey).replace('{username}', notification.actor_username)}</span>
                        </div>
                        <div class="notification-timestamp">${timeAgo} ${unreadDot}</div>
                        </div>
                </a>`;
            break;
    }
    
    // Devolver el HTML
    return notificationHtml;
}


/**
 * Carga TODAS las notificaciones (amistad, likes, etc.) desde la BD.
 */
export async function loadAllNotifications() {
    
    // --- ▼▼▼ INICIO DE MODIFICACIÓN ▼▼▼ ---
    // ¡La bandera SÍ se usa aquí!
    // Si la lista ya está cargada Y no ha sido invalidada por un PING,
    // no hacemos nada.
    if (hasLoadedNotifications) {
        console.log("[Notis] Lista ya cargada y válida. No se recarga.");
        return; 
    }
    // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---
    
    const listContainer = document.getElementById('notification-list-items');
    const placeholder = document.getElementById('notification-placeholder');
    const markAllButton = document.getElementById('notification-mark-all-btn');
    
    if (!listContainer || !placeholder) return;

    placeholder.style.display = 'flex';
    placeholder.querySelector('.material-symbols-rounded').innerHTML = '<span class="logout-spinner" style="width: 32px; height: 32px; border-width: 3px;"></span>';
    placeholder.querySelector('span[data-i18n]').setAttribute('data-i18n', 'notifications.loading');
    placeholder.querySelector('span[data-i18n]').textContent = getTranslation('notifications.loading');
    listContainer.innerHTML = ''; // Limpiar lista
    if (markAllButton) markAllButton.style.display = 'none'; // Ocultar botón mientras carga

    const formData = new FormData();
    formData.append('action', 'get-notifications'); 

    try {
        const result = await callNotificationApi(formData);
        
        if (result.success && result.notifications) {
            
            // Ponemos la bandera en 'true' SÓLO si la carga fue exitosa.
            // El PING la pondrá en 'false' para forzar la recarga.
            hasLoadedNotifications = true; 
            
            setNotificationCount(result.unread_count || 0);
            
            if (result.notifications.length === 0) {
                placeholder.style.display = 'flex';
                placeholder.querySelector('.material-symbols-rounded').innerHTML = 'notifications_off';
                placeholder.querySelector('span[data-i18n]').setAttribute('data-i18n', 'notifications.empty');
                placeholder.querySelector('span[data-i18n]').textContent = getTranslation('notifications.empty');
                if (markAllButton) markAllButton.style.display = 'none';
            } else {
                placeholder.style.display = 'none';
                
                // 1. Lógica de agrupación
                let lastDateGroup = null;
                
                result.notifications.forEach(notification => {
                    // 2. Insertar cabecera de grupo si es nueva
                    const notificationDate = new Date(notification.created_at + 'Z');
                    const currentGroup = getRelativeDateGroup(notificationDate);
                    
                    if (currentGroup !== lastDateGroup) {
                        const dividerHtml = `<div class="notification-date-divider">${currentGroup}</div>`;
                        listContainer.insertAdjacentHTML('beforeend', dividerHtml);
                        lastDateGroup = currentGroup;
                    }

                    // 3. Generar e insertar el item
                    const notificationHtml = addNotificationToUI(notification);
                    if (notificationHtml) {
                        listContainer.insertAdjacentHTML('beforeend', notificationHtml);
                    }
                });
                
                // Aplicar traducciones a los items recién insertados
                applyTranslations(listContainer);
                
                // Mostrar el botón "Marcar todas" si hay notificaciones no leídas
                if (markAllButton && result.unread_count > 0) {
                    markAllButton.style.display = 'block';
                }
            }
        } else {
             // Si la API falla, nos aseguramos que la bandera quede en 'false'
             hasLoadedNotifications = false; 
             placeholder.style.display = 'flex';
             placeholder.querySelector('.material-symbols-rounded').innerHTML = 'error';
             placeholder.querySelector('span[data-i18n]').setAttribute('data-i18n', 'js.api.errorServer');
             placeholder.querySelector('span[data-i18n]').textContent = getTranslation('js.api.errorServer');
        }
    } catch (e) {
        // Si la RED falla, nos aseguramos que la bandera quede en 'false'
        hasLoadedNotifications = false;
        console.error("Error al cargar notificaciones:", e);
         placeholder.style.display = 'flex';
         placeholder.querySelector('.material-symbols-rounded').innerHTML = 'error';
         placeholder.querySelector('span[data-i18n]').setAttribute('data-i18n', 'js.api.errorConnection');
         placeholder.querySelector('span[data-i18n]').textContent = getTranslation('js.api.errorConnection');
    }
}

/**
 * Obtiene el conteo inicial de notificaciones.
 */
export async function fetchInitialCount() {
    const formData = new FormData();
    formData.append('action', 'get-notifications'); 
    const result = await callNotificationApi(formData);
    if (result.success && result.unread_count !== undefined) {
        setNotificationCount(result.unread_count);
    }
}

/**
 * Maneja un ping de notificación del WebSocket.
 */
export function handleNotificationPing() {
    // 1. Actualizar el contador del badge
    setNotificationCount(currentNotificationCount + 1);
    
    // 2. Invalidar la lista actual (para que se recargue si se cierra y se vuelve a abrir)
    hasLoadedNotifications = false;

    // 3. Comprobar si el panel está abierto
    const notificationPanel = document.querySelector('[data-module="moduleNotifications"]');
    if (notificationPanel && notificationPanel.classList.contains('active')) {
        console.log("[WS] El panel de notificaciones está abierto. Recargando lista en vivo...");
        // 4. Si está abierto, forzar la recarga de la lista AHORA
        loadAllNotifications(); 
    }
}

/**
 * Inicializa los listeners para el panel de notificaciones.
 */
export function initNotificationManager() {
    
    // 1. LISTENER PARA ABRIR EL PANEL (CLIC EN LA CAMPANA)
    const notificationButton = document.querySelector('[data-action="toggleModuleNotifications"]');
    if (notificationButton) {
        // --- ▼▼▼ INICIO DE MODIFICACIÓN ▼▼▼ ---
        notificationButton.addEventListener('click', (e) => {
            // Detenemos la propagación para que el main-controller NO lo maneje
            e.stopPropagation(); 
            
            const module = document.querySelector('[data-module="moduleNotifications"]');
            if (!module) return;

            // 1. Cerrar todos los demás popovers
            deactivateAllModules(module);
            
            // 2. Alternar este popover
            module.classList.toggle('disabled');
            module.classList.toggle('active');

            // 3. Cargar el contenido (la función interna 'loadAllNotifications'
            //    ahora tiene la lógica de 'hasLoadedNotifications' para
            //    evitar recargas innecesarias)
            if (module.classList.contains('active')) {
                loadAllNotifications();
            }
        });
        // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---
    }
    
    // 2. LISTENER PARA CLIC EN "MARCAR TODAS COMO LEÍDAS"
    const markAllButton = document.getElementById('notification-mark-all-btn');
    if (markAllButton) {
        markAllButton.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            
            markAllButton.style.display = 'none'; // Ocultarlo inmediatamente
            setNotificationCount(0); // Poner el badge a 0

            // Actualizar visualmente todos los items
            document.querySelectorAll('#notification-list-items .notification-item.is-unread').forEach(item => {
                item.classList.remove('is-unread');
                item.classList.add('is-read');
                item.querySelector('.notification-unread-dot')?.remove();
            });

            // Llamar a la API en segundo plano
            const formData = new FormData();
            formData.append('action', 'mark-all-read');
            await callNotificationApi(formData); // No necesitamos esperar la respuesta
        });
    }

    // 3. LISTENER PARA CLIC EN UN ITEM INDIVIDUAL
    const listContainer = document.getElementById('notification-list-items');
    if (listContainer) {
        listContainer.addEventListener('click', (e) => {
            
            // Ignorar clics en botones de acción (Aceptar/Rechazar amigo)
            if (e.target.closest('.notification-action-button')) {
                return;
            }

            // Encontrar el item de notificación que no esté leído
            const item = e.target.closest('.notification-item.is-unread');
            
            if (item) {
                // SI NO ESTÁ LEÍDO:
                // No prevenimos el default, dejamos que el router actúe.
                
                const notificationId = item.dataset.id;
                const markAllButton = document.getElementById('notification-mark-all-btn');
                if (!notificationId) return; 

                // Marcar visualmente como leído INMEDIATAMENTE
                item.classList.remove('is-unread');
                item.classList.add('is-read');
                item.querySelector('.notification-unread-dot')?.remove();

                // Actualizar el contador visual
                const newCount = Math.max(0, currentNotificationCount - 1);
                setNotificationCount(newCount);
                if (newCount === 0 && markAllButton) {
                    markAllButton.style.display = 'none';
                }

                // Llamar a la API en segundo plano (SIN 'await')
                const formData = new FormData();
                formData.append('action', 'mark-one-read');
                formData.append('notification_id', notificationId);
                
                callNotificationApi(formData).then(result => {
                    if (result.success) {
                        // Re-sincronizar el conteo
                        setNotificationCount(result.new_unread_count);
                        if (result.new_unread_count === 0 && markAllButton) {
                            markAllButton.style.display = 'none';
                        }
                    } else {
                        console.error("Error al marcar la notificación como leída en el backend.");
                    }
                });
            }
            // Si el item ya estaba leído, no hacemos nada y dejamos que el clic
            // sea manejado por el url-manager.js para la navegación.
        });
    }
    
    // 4. LISTENER PARA BOTONES DE AMISTAD (para eliminar el item de la UI)
    document.body.addEventListener('click', (e) => {
        const targetButton = e.target.closest('[data-action="friend-accept-request"], [data-action="friend-decline-request"]');
        
        // Asegurarse que el clic SÍ fue dentro del popover de notificaciones
        if (targetButton && targetButton.closest('.notification-item')) {
            const item = targetButton.closest('.notification-item');
            if (item) {
                // Marcar como leído si no lo estaba (la API de amigo se encarga de borrarlo)
                if (item.classList.contains('is-unread')) {
                    item.classList.remove('is-unread');
                    item.classList.add('is-read');
                    item.querySelector('.notification-unread-dot')?.remove();
                    
                    // Llamar a la API para marcarlo
                    const formData = new FormData();
                    formData.append('action', 'mark-one-read');
                    formData.append('notification_id', item.dataset.id);
                    callNotificationApi(formData).then(result => {
                         if (result.success) setNotificationCount(result.new_unread_count);
                    });
                }
                
                // Esconder la notificación (la lógica de amigo la elimina de la BD)
                item.style.opacity = '0.5'; 
                setTimeout(() => {
                    item.remove();
                    const listContainer = document.getElementById('notification-list-items');
                    const placeholder = document.getElementById('notification-placeholder');
                    if (listContainer && placeholder && listContainer.children.length === 0) {
                        placeholder.style.display = 'flex';
                    }
                }, 1000);
            }
        }
    });
}