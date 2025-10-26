// --- ▼▼▼ ¡MODIFICADO! Importar la función de traducción ▼▼▼ ---
import { __ } from './auth-manager.js';
// --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---

const API_ENDPOINTS = {
    AUTH: `${window.projectBasePath}/api/auth_handler.php`,
    SETTINGS: `${window.projectBasePath}/api/settings_handler.php`
};

async function _post(url, formData) {
    const csrfToken = window.csrfToken || '';
    formData.append('csrf_token', csrfToken);

    try {
        const response = await fetch(url, {
            method: 'POST',
            body: formData,
        });

        if (!response.ok) {
            console.error('Error de red o servidor:', response.statusText);
            // --- ▼▼▼ ¡MODIFICADO! Usar traducción ▼▼▼ ---
            return { success: false, message: __('js.api.error.server') };
            // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
        }

        const result = await response.json();

        if (result.success === false && result.message && result.message.includes('Error de seguridad')) {
             // --- ▼▼▼ ¡MODIFICADO! Usar traducción ▼▼▼ ---
            window.showAlert(__('js.api.error.security'), 'error');
            // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
            setTimeout(() => location.reload(), 2000);
        }

        return result;

    } catch (error) {
        console.error('Error en la llamada fetch:', error);
         // --- ▼▼▼ ¡MODIFICADO! Usar traducción ▼▼▼ ---
        return { success: false, message: __('js.api.error.connect') };
        // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ ---
    }
}

export async function callAuthApi(formData) {
    return _post(API_ENDPOINTS.AUTH, formData);
}

export async function callSettingsApi(formData) {
    return _post(API_ENDPOINTS.SETTINGS, formData);
}