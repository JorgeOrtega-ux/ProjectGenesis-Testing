// ARCHIVO: assets/js/modules/chat-manager.js
// (Versión modificada para CUADRÍCULA 2x2 CON CONTADOR)

import { callChatApi } from '../services/api-service.js';
import { showAlert } from '../services/alert-manager.js';
import { getTranslation } from '../services/i18n-manager.js';
import { hideTooltip } from '../services/tooltip-manager.js';

const attachedFiles = new Map();
let isSending = false;
const MAX_GROUPING_TIME_MS = 60 * 1000; // 60 segundos

/**
 * Limpia el input de chat, borra los archivos adjuntos y elimina las vistas previas.
 */
function clearChatInput() {
    const textInput = document.getElementById('chat-input-text-area');
    if (textInput) {
        textInput.innerHTML = '';
    }

    const previewContainer = document.getElementById('chat-preview-container');
    if (previewContainer) {
        previewContainer.innerHTML = ''; // Borra todos los items
        removePreviewContainer(previewContainer); // Elimina el contenedor
    }
    
    attachedFiles.clear();
}

/**
 * Elimina el contenedor de preview y quita la clase del wrapper.
 */
function removePreviewContainer(previewContainer) {
    const inputWrapper = document.getElementById('chat-input-wrapper');
    if (inputWrapper) {
        inputWrapper.classList.remove('has-previews');
    }
    if (previewContainer && previewContainer.parentNode) {
        previewContainer.parentNode.removeChild(previewContainer);
    }
}

/**
 * Elimina una vista previa específica y su archivo.
 */
function removePreview(fileId) {
    attachedFiles.delete(fileId);

    const previewContainer = document.getElementById('chat-preview-container');
    if (!previewContainer) return;

    const previewItem = previewContainer.querySelector(`.chat-preview-item[data-file-id="${fileId}"]`);
    if (previewItem) {
        previewItem.remove();
    }

    if (previewContainer.children.length === 0) {
        removePreviewContainer(previewContainer);
    }
}

/**
 * Crea la vista previa de la imagen y la añade al DOM.
 */
function createPreview(file, fileId, previewContainer, inputWrapper) {
    const reader = new FileReader();
    
    reader.onload = (e) => {
        const dataUrl = e.target.result;

        const previewItem = document.createElement('div');
        previewItem.className = 'chat-preview-item';
        previewItem.dataset.fileId = fileId;

        previewItem.innerHTML = `
            <img src="${dataUrl}" alt="${file.name}" class="chat-preview-image">
            <button type"button" class="chat-preview-remove">
                <span class="material-symbols-rounded">close</span>
            </button>
        `;

        previewItem.querySelector('.chat-preview-remove').addEventListener('click', () => {
            removePreview(fileId);
        });

        previewContainer.appendChild(previewItem);
        inputWrapper.classList.add('has-previews');
    };

    reader.readAsDataURL(file);
}

/**
 * Formatea un timestamp (ej. "2025-11-06 20:43:00") a "HH:MM".
 */
function formatMessageTime(timestamp) {
    try {
        const date = new Date(timestamp.replace(' ', 'T') + 'Z');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}`;
    } catch (e) {
        console.error("Error formateando fecha de chat:", e);
        return "--:--";
    }
}


// --- ▼▼▼ ¡FUNCIÓN RENDERINCOMINGMESSAGE MODIFICADA! ▼▼▼ ---
/**
 * Renderiza un nuevo mensaje en la UI del chat.
 * @param {object} msgData El objeto del mensaje (de la API/WS).
 */
export function renderIncomingMessage(msgData) {
    const chatHistory = document.getElementById('chat-history-container');
    if (!chatHistory) return;

    const isOwnMessage = msgData.user_id === window.userId;
    const avatarUrl = msgData.profile_image_url || `https://ui-avatars.com/api/?name=${encodeURIComponent(msgData.username)}&size=100&background=e0e0e0&color=ffffff`;
    const time = formatMessageTime(msgData.created_at);
    const msgTimestamp = new Date(msgData.created_at.replace(' ', 'T') + 'Z').getTime();

    // 1. Comprobar si este mensaje debe agruparse con el anterior
    const lastBubble = chatHistory.lastElementChild;
    let isContinuation = false;
    
    if (lastBubble && lastBubble.classList.contains('chat-bubble')) {
        const lastUserId = lastBubble.dataset.userId;
        const lastTimestamp = parseInt(lastBubble.dataset.timestamp, 10);
        
        if (lastUserId == msgData.user_id && (msgTimestamp - lastTimestamp) <= MAX_GROUPING_TIME_MS) {
            isContinuation = true;
        }
    }

    if (isContinuation) {
        // --- 2. AGRUPAR MENSAJE ---
        const bubbleContent = lastBubble.querySelector('.chat-bubble-content');
        const bubbleBody = lastBubble.querySelector('.chat-bubble-body');
        const bubbleFooter = lastBubble.querySelector('.chat-bubble-footer .chat-bubble-time');

        if (!bubbleContent || !bubbleBody) return; 

        if (msgData.message_type === 'text') {
            // Si el último elemento era un grupo de imágenes, lo cerramos lógicamente.
            // El texto siempre va en su propio div.
            const textEl = document.createElement('div');
            textEl.className = 'chat-bubble-text';
            textEl.textContent = msgData.content;
            bubbleBody.appendChild(textEl);
        
        } else if (msgData.message_type === 'image') {
            
            // Buscar si ya existe un contenedor de adjuntos
            let attachments = bubbleBody.querySelector('.chat-bubble-attachments');
            
            // Si no existe O si el último elemento fue un texto, creamos un *nuevo* contenedor
            if (!attachments || bubbleBody.lastElementChild.classList.contains('chat-bubble-text')) {
                attachments = document.createElement('div');
                attachments.className = 'chat-bubble-attachments';
                attachments.dataset.count = '0'; // Empezar contador
                bubbleBody.appendChild(attachments);
            }
            
            const currentImageCount = attachments.children.length;
            const newTotalCount = currentImageCount + 1;
            attachments.dataset.count = newTotalCount; // Actualizar el contador total

            // Lógica de la cuadrícula 2x2 + Overlay
            
            if (newTotalCount < 4) {
                // 1ra, 2da o 3ra imagen: Simplemente añadirla
                const imageEl = document.createElement('div');
                imageEl.className = 'chat-bubble-image';
                imageEl.innerHTML = `<img src="${msgData.content}" alt="Imagen adjunta" loading="lazy">`;
                attachments.appendChild(imageEl);
                
            } else if (newTotalCount === 4) {
                // 4ta imagen: Añadirla. Esta será la última visible.
                const imageEl = document.createElement('div');
                imageEl.className = 'chat-bubble-image';
                imageEl.innerHTML = `<img src="${msgData.content}" alt="Imagen adjunta" loading="lazy">`;
                attachments.appendChild(imageEl);
                
            } else if (newTotalCount === 5) {
                // 5ta imagen: NO AÑADIRLA. Buscar la 4ta y ponerle el overlay "+1".
                const fourthImage = attachments.children[3]; // Índice 3 es el 4to elemento
                if (fourthImage) {
                    const overlay = document.createElement('div');
                    overlay.className = 'chat-image-overlay';
                    overlay.textContent = '+1';
                    fourthImage.appendChild(overlay);
                }
            } else {
                // 6ta, 7ma... imagen: NO AÑADIRLA. Buscar la 4ta y ACTUALIZAR el overlay.
                const fourthImage = attachments.children[3];
                if (fourthImage) {
                    let overlay = fourthImage.querySelector('.chat-image-overlay');
                    if (!overlay) { // Por si acaso, crearlo
                        overlay = document.createElement('div');
                        overlay.className = 'chat-image-overlay';
                        fourthImage.appendChild(overlay);
                    }
                    const remainingCount = newTotalCount - 4;
                    overlay.textContent = `+${remainingCount}`;
                }
            }
        }

        // Actualizar la hora de todo el grupo
        if (bubbleFooter) {
            bubbleFooter.textContent = time;
        }
        lastBubble.dataset.timestamp = msgTimestamp; // Actualizar el timestamp del grupo

    } else {
        // --- 3. CREAR NUEVA BURBUJA ---
        const bubble = document.createElement('div');
        bubble.className = 'chat-bubble';
        if (isOwnMessage) {
            bubble.classList.add('is-own');
        }
        bubble.dataset.userId = msgData.user_id;
        bubble.dataset.timestamp = msgTimestamp;

        let bodyContent = '';
        if (msgData.message_type === 'text') {
            bodyContent = `<div class="chat-bubble-text">${msgData.content}</div>`;
        } else if (msgData.message_type === 'image') {
            // Si es una imagen, ya debe tener el data-count="1"
            bodyContent = `
                <div class="chat-bubble-attachments" data-count="1">
                    <div class="chat-bubble-image">
                        <img src="${msgData.content}" alt="Imagen adjunta" loading="lazy">
                    </div>
                </div>`;
        }

        bubble.innerHTML = `
            <div class="chat-bubble-avatar" data-role="${msgData.user_role || 'user'}">
                <img src="${avatarUrl}" alt="${msgData.username}">
            </div>
            <div class="chat-bubble-content">
                <div class="chat-bubble-header">
                    <span class="chat-bubble-username">${msgData.username}</span>
                </div>
                <div class="chat-bubble-body">
                    ${bodyContent}
                </div>
                <div class="chat-bubble-footer">
                    <span class="chat-bubble-time">${time}</span>
                </div>
            </div>
        `;
        
        chatHistory.appendChild(bubble);
    }

    // 4. Hacer scroll al final
    chatHistory.scrollTop = chatHistory.scrollHeight;
}
// --- ▲▲▲ FIN DE FUNCIÓN MODIFICADA ▲▲▲ ---


/**
 * Maneja el envío del formulario de chat.
 */
async function handleSendMessage() {
    if (isSending) return;

    const textInput = document.getElementById('chat-input-text-area');
    const sendButton = document.getElementById('chat-send-button');
    const currentGroupUuid = document.body.dataset.currentGroupUuid || '';
    const csrfToken = window.csrfToken || '';

    if (!currentGroupUuid) {
        showAlert(getTranslation('home.chat.error.noGroup'), 'error');
        return;
    }

    const messageText = textInput ? textInput.innerText.trim() : '';
    const hasFiles = attachedFiles.size > 0;

    if (!messageText && !hasFiles) {
        return; // No enviar nada si está vacío
    }

    isSending = true;
    if(sendButton) sendButton.disabled = true;
    hideTooltip();

    // 1. Preparar FormData
    const formData = new FormData();
    formData.append('action', 'send-message');
    formData.append('csrf_token', csrfToken);
    formData.append('group_uuid', currentGroupUuid);
    formData.append('message_text', messageText);

    // 2. Adjuntar archivos
    attachedFiles.forEach((file) => {
        formData.append('images[]', file, file.name);
    });

    try {
        // 3. Llamar a la API de Chat
        const result = await callChatApi(formData);

        if (result.success) {
            // 4. Éxito: Limpiar el input.
            // El mensaje se renderizará cuando vuelva por el WebSocket.
            clearChatInput();
        } else {
            // 5. Error: Mostrar alerta
            showAlert(getTranslation(result.message || 'js.api.errorServer'), 'error');
        }

    } catch (error) {
        console.error("Error al enviar mensaje:", error);
        showAlert(getTranslation('js.api.errorConnection'), 'error');
    } finally {
        isSending = false;
        if(sendButton) sendButton.disabled = false;
    }
}

/**
 * Inicializa los listeners para el input de chat.
 */
export function initChatManager() {
    
    document.body.addEventListener('click', (event) => {
        const target = event.target;
        
        const attachButton = target.closest('#chat-attach-button');
        if (attachButton) {
            const fileInput = document.getElementById('chat-file-input');
            if (fileInput) {
                fileInput.click(); 
            }
            return;
        }
        
        const sendButton = target.closest('#chat-send-button');
        if (sendButton) {
            handleSendMessage();
            return;
        }
    });

    document.body.addEventListener('change', (event) => {
        const fileInput = event.target.closest('#chat-file-input');
        
        if (fileInput) {
            const files = fileInput.files;
            if (!files) return;

            // --- ▼▼▼ INICIO DE MODIFICACIÓN (Límite de 5 archivos) ▼▼▼ ---
            if (attachedFiles.size + files.length > 5) {
                showAlert(getTranslation('home.chat.error.onlyImages'), 'error'); // Reutilizo esta clave, deberías cambiarla
                fileInput.value = ''; // Limpiar
                return;
            }
            // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---


            const inputWrapper = document.getElementById('chat-input-wrapper');
            if (!inputWrapper) {
                console.error("No se encontró #chat-input-wrapper.");
                return;
            }

            let previewContainer = document.getElementById('chat-preview-container');
            if (!previewContainer) {
                previewContainer = document.createElement('div');
                previewContainer.className = 'chat-input__previews';
                previewContainer.id = 'chat-preview-container';
                
                const textArea = inputWrapper.querySelector('.chat-input__text-area');
                if (textArea) {
                    inputWrapper.insertBefore(previewContainer, textArea);
                } else {
                    inputWrapper.prepend(previewContainer); 
                }
            }

            for (const file of files) {
                if (file.type.startsWith('image/')) {
                    const fileId = `file-${Date.now()}-${Math.random()}`;
                    attachedFiles.set(fileId, file);
                    createPreview(file, fileId, previewContainer, inputWrapper);
                } else {
                    showAlert(getTranslation('home.chat.error.onlyImages'), 'error');
                }
            }

            fileInput.value = '';
        }
    });

    document.body.addEventListener('keydown', (event) => {
        const textInput = event.target.closest('#chat-input-text-area');
        if (textInput && event.key === 'Enter') {
            if (!event.shiftKey) {
                event.preventDefault(); 
                handleSendMessage();
            }
        }
    });
}