// FILE: assets/js/modules/admin-backups-manager.js

import { showAlert } from '../services/alert-manager.js';
import { getTranslation } from '../services/i18n-manager.js';
import { hideTooltip } from '../services/tooltip-manager.js';

// --- INICIO: Lógica de API (copiada de api-service.js para ser autocontenido) ---
// Definimos el nuevo endpoint de la API de backups
const API_ENDPOINT_BACKUP = `${window.projectBasePath}/api/backup_handler.php`;

/**
 * Función _post interna para llamar a la API de backups.
 */
async function callBackupApi(formData) {
    const csrfToken = window.csrfToken || '';
    formData.append('csrf_token', csrfToken);

    try {
        const response = await fetch(API_ENDPOINT_BACKUP, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            console.error('Error de red o servidor:', response.statusText);
            return { success: false, message: getTranslation('js.api.errorServer') };
        }

        const responseClone = response.clone();
        try {
            const result = await response.json();
            if (result.success === false && result.message && result.message.includes('Error de seguridad')) {
                window.showAlert(getTranslation('js.api.errorSecurity'), 'error');
                setTimeout(() => location.reload(), 2000);
            }
            return result;
        } catch (jsonError) {
            console.error('Error al parsear JSON:', jsonError);
            const errorText = await responseClone.text();
            console.error('Respuesta del servidor (no-JSON):', errorText);
            return { success: false, message: getTranslation('js.api.errorServer') + ' (Respuesta inválida)' };
        }
    } catch (error) {
        console.error('Error en la llamada fetch (Red):', error);
        return { success: false, message: getTranslation('js.api.errorConnection') };
    }
}
// --- FIN: Lógica de API ---


export function initAdminBackupsManager() {

    let selectedBackupFile = null;
    const toolbarContainer = document.getElementById('backup-toolbar-container');

    function enableSelectionActions() {
        if (!toolbarContainer) return;
        toolbarContainer.classList.add('selection-active');
        const selectionButtons = toolbarContainer.querySelectorAll('.toolbar-action-selection button');
        selectionButtons.forEach(btn => {
            btn.disabled = false;
        });
    }

    function disableSelectionActions() {
        if (!toolbarContainer) return;
        toolbarContainer.classList.remove('selection-active');
        const selectionButtons = toolbarContainer.querySelectorAll('.toolbar-action-selection button');
        selectionButtons.forEach(btn => {
            btn.disabled = true;
        });
    }

    function clearBackupSelection() {
        const selectedCard = document.querySelector('.user-card-item.selected[data-backup-filename]');
        if (selectedCard) {
            selectedCard.classList.remove('selected');
        }
        disableSelectionActions();
        selectedBackupFile = null;
    }

    /**
     * Muestra/Oculta un spinner en un botón de la barra de herramientas.
     */
    function toggleToolbarSpinner(button, isLoading) {
        if (!button) return;
        const iconSpan = button.querySelector('.material-symbols-rounded');
        if (!iconSpan) return;

        if (isLoading) {
            button.disabled = true;
            button.dataset.originalIcon = iconSpan.textContent;
            iconSpan.innerHTML = `<span class="logout-spinner" style="width: 20px; height: 20px; border-width: 2px; margin: 0 auto; border-top-color: inherit;"></span>`;
        } else {
            button.disabled = false;
            iconSpan.innerHTML = button.dataset.originalIcon || 'error';
        }
    }

    /**
     * Maneja las acciones de la API de backup.
     */
    async function handleBackupAction(action, filename = null, buttonEl = null) {
        
        // Deshabilitar todos los botones mientras se procesa
        toolbarContainer.querySelectorAll('button').forEach(btn => btn.disabled = true);
        if(buttonEl) toggleToolbarSpinner(buttonEl, true);
        
        const formData = new FormData();
        formData.append('action', action);
        if (filename) {
            formData.append('filename', filename);
        }

        try {
            const result = await callBackupApi(formData);

            if (result.success) {
                showAlert(getTranslation(result.message || 'js.admin.backups.successGeneric'), 'success');
                // Recargar la página para ver los cambios (nuevo archivo, o lista actualizada)
                setTimeout(() => {
                    // Usamos el sistema de navegación de la app para recargar la sección
                    const link = document.createElement('a');
                    link.href = window.projectBasePath + '/admin/manage-backups';
                    link.setAttribute('data-nav-js', 'true');
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                }, 1500);

            } else {
                showAlert(getTranslation(result.message || 'js.admin.backups.errorGeneric'), 'error');
                // Volver a habilitar botones en caso de error
                toolbarContainer.querySelectorAll('button').forEach(btn => btn.disabled = false);
                if(buttonEl) toggleToolbarSpinner(buttonEl, false);
                clearBackupSelection(); // Restablecer estado
            }
        } catch (error) {
            showAlert(getTranslation('js.api.errorServer'), 'error');
            toolbarContainer.querySelectorAll('button').forEach(btn => btn.disabled = false);
            if(buttonEl) toggleToolbarSpinner(buttonEl, false);
            clearBackupSelection(); // Restablecer estado
        }
    }


    // --- Listener de Clics Principal ---
    document.body.addEventListener('click', async function (event) {
        
        // 1. Verificar si estamos en la sección de backups
        const section = event.target.closest('[data-section="admin-backups"]');
        if (!section) {
            // Si no estamos en la sección, limpiar selección por si acaso
            if (selectedBackupFile) clearBackupSelection();
            return;
        }

        // 2. Manejar selección de item
        const backupCard = event.target.closest('.user-card-item[data-backup-filename]');
        if (backupCard) {
            event.preventDefault();
            const filename = backupCard.dataset.backupFilename;
            
            if (selectedBackupFile === filename) {
                clearBackupSelection();
            } else {
                const oldSelected = document.querySelector('.user-card-item.selected[data-backup-filename]');
                if (oldSelected) {
                    oldSelected.classList.remove('selected');
                }
                backupCard.classList.add('selected');
                selectedBackupFile = filename;
                enableSelectionActions();
            }
            return;
        }

        // 3. Manejar botones de acción
        const button = event.target.closest('button[data-action]');
        if (!button) {
             // Si se hace clic fuera de un botón o tarjeta, deseleccionar
            if (!event.target.closest('[data-module].active')) {
                clearBackupSelection();
            }
            return;
        }
        
        const action = button.getAttribute('data-action');
        hideTooltip();

        switch (action) {
            case 'admin-backup-create':
                if (!confirm(getTranslation('admin.backups.confirmCreate'))) {
                    return;
                }
                await handleBackupAction('create-backup', null, button);
                break;

            case 'admin-backup-restore':
                if (!selectedBackupFile) {
                    showAlert(getTranslation('admin.backups.errorNoSelection'), 'error');
                    return;
                }
                if (!confirm(getTranslation('admin.backups.confirmRestore', { filename: selectedBackupFile }))) {
                    return;
                }
                await handleBackupAction('restore-backup', selectedBackupFile, button);
                break;
            
            case 'admin-backup-delete':
                if (!selectedBackupFile) {
                    showAlert(getTranslation('admin.backups.errorNoSelection'), 'error');
                    return;
                }
                 if (!confirm(getTranslation('admin.backups.confirmDelete', { filename: selectedBackupFile }))) {
                    return;
                }
                await handleBackupAction('delete-backup', selectedBackupFile, button);
                break;

            case 'admin-backup-clear-selection':
                clearBackupSelection();
                break;
        }
    });

    // --- Listeners Globales para deseleccionar ---
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            clearBackupSelection();
        }
    });
}