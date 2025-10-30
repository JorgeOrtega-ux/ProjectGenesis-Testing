import { callAuthApi } from './api-service.js';
import { handleNavigation } from './url-manager.js';
import { getTranslation } from './i18n-manager.js';

// --- ▼▼▼ INICIO DE NUEVAS FUNCIONES HELPER ▼▼▼ ---
/**
 * Reemplaza el contenido de un botón de auth con un spinner.
 * @param {HTMLElement} button - El botón a modificar.
 */
function setButtonSpinner(button) {
    if (!button) return;
    // Guardar el texto original (o HTML si tuviera iconos)
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<div class="auth-button-spinner"></div>';
}

/**
 * Restaura el contenido original de un botón y lo reactiva.
 * @param {HTMLElement} button - El botón a restaurar.
 */
function removeButtonSpinner(button) {
    if (!button) return;
    button.disabled = false;
    // Restaurar desde el HTML guardado.
    if (button.dataset.originalHtml) {
        button.innerHTML = button.dataset.originalHtml;
    }
}
// --- ▲▲▲ FIN DE NUEVAS FUNCIONES HELPER ▲▲▲ ---


export function startResendTimer(linkElement, seconds) {
    if (!linkElement) {
        return;
    }

    let secondsRemaining = seconds;
    
    const originalBaseText = linkElement.textContent.trim().replace(/\s*\(\d+s?\)$/, '');

    linkElement.classList.add('disabled-interactive');
    // linkElement.style.opacity = '0.7'; // ELIMINADO
    // linkElement.style.textDecoration = 'none'; // ELIMINADO
    linkElement.textContent = `${originalBaseText} (${secondsRemaining}s)`;

    const intervalId = setInterval(() => {
        secondsRemaining--;
        if (secondsRemaining > 0) {
            linkElement.textContent = `${originalBaseText} (${secondsRemaining}s)`;
        } else {
            clearInterval(intervalId);
            linkElement.textContent = originalBaseText;
            linkElement.classList.remove('disabled-interactive');
            // linkElement.style.opacity = '1'; // ELIMINADO
            // linkElement.style.textDecoration = ''; // ELIMINADO
        }
    }, 1000);

    linkElement.dataset.timerId = intervalId;
}

async function handleRegistrationSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    const activeStep = form.querySelector('.auth-step.active');
    const errorDiv = activeStep.querySelector('.auth-error-message');

    // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
    setButtonSpinner(button);
    // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---

    const formData = new FormData(form);
    formData.append('action', 'register-verify');
    formData.append('email', sessionStorage.getItem('regEmail') || '');

    const result = await callAuthApi(formData);

    if (result.success) {
        sessionStorage.removeItem('regEmail');
        sessionStorage.removeItem('regPass');
        window.location.href = window.projectBasePath + '/';
    } else {
        // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
        const codeInput = form.querySelector('#register-code');
        showAuthError(errorDiv, getTranslation(result.message || 'js.auth.genericError'), result.data, codeInput);
        // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
        
        // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
        removeButtonSpinner(button);
        // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
    }
}

async function handleResetSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    const activeStep = form.querySelector('.auth-step.active');
    const errorDiv = activeStep.querySelector('.auth-error-message');

    // --- ▼▼▼ MODIFICACIÓN: Obtener elementos, no solo valores ▼▼▼ ---
    const password = form.querySelector('#reset-password');
    const passwordConfirm = form.querySelector('#reset-password-confirm');

    if (password.value.length < 8 || password.value.length > 72) {
        showAuthError(errorDiv, getTranslation('js.auth.errorPasswordLength', {min: 8, max: 72}), null, password);
        return;
    }
    if (password.value !== passwordConfirm.value) {
        showAuthError(errorDiv, getTranslation('js.auth.errorPasswordMismatch'), null, [password, passwordConfirm]);
        return;
    }
    // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---

    // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
    setButtonSpinner(button);
    // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---

    const formData = new FormData(form);
    formData.append('action', 'reset-update-password');

    const result = await callAuthApi(formData);

    if (result.success) {
        sessionStorage.removeItem('resetEmail');
        sessionStorage.removeItem('resetCode');
        // --- ▼▼▼ LÍNEA CORREGIDA ▼▼▼ ---
        window.showAlert(getTranslation(result.message || 'js.auth.successPasswordUpdate'), 'success');
        // --- ▲▲▲ LÍNEA CORREGIDA ▲▲▲ ---
        setTimeout(() => {
            window.location.href = window.projectBasePath + '/login';
        }, 2000);
    } else {
        // --- ▼▼▼ LÍNEA CORREGIDA (Sin input específico para error de servidor genérico) ▼▼▼ ---
        showAuthError(errorDiv, getTranslation(result.message || 'js.auth.genericError'), result.data);
        // --- ▲▲▲ LÍNEA CORREGIDA ▲▲▲ ---
        
        // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
        removeButtonSpinner(button);
        // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
    }
}

// --- ▼▼▼ INICIO DE LA MODIFICACIÓN (FUNCIÓN handleLoginFinalSubmit) ▼▼▼ ---
async function handleLoginFinalSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const button = form.querySelector('button[type="submit"]');
    
    // CAMBIO CLAVE: Buscar el error div relativo al botón, no a la clase '.active'
    const activeStep = button.closest('.auth-step'); 
    const errorDiv = activeStep.querySelector('.auth-error-message');

    // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
    setButtonSpinner(button);
    // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---

    const formData = new FormData(form);
    formData.append('action', 'login-verify-2fa');

    const result = await callAuthApi(formData);

    if (result.success) {
        window.location.href = window.projectBasePath + '/';
    } else {
        // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
        const codeInput = form.querySelector('#login-code');
        showAuthError(errorDiv, getTranslation(result.message || 'js.auth.genericError'), result.data, codeInput);
        // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
        
        // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
        removeButtonSpinner(button);
        // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
    }
}
// --- ▲▲▲ FIN DE LA MODIFICACIÓN (FUNCIÓN handleLoginFinalSubmit) ▲▲▲ ---


// --- ▼▼▼ INICIO DE FUNCIÓN MODIFICADA ▼▼▼ ---
/**
 * Muestra un mensaje de error en un contenedor, reemplazando placeholders.
 * @param {HTMLElement} errorDiv - El div donde se mostrará el error.
 * @param {string} message - El mensaje (o clave i18n) a mostrar.
 * @param {object|null} data - Datos opcionales para reemplazar (ej. {seconds: 5}).
 * @param {HTMLElement|HTMLElement[]|null} inputElement - El input (o inputs) a marcar en rojo.
 */
function showAuthError(errorDiv, message, data = null, inputElement = null) {
    if (errorDiv) {
        let finalMessage = message;
        
        // Reemplazar placeholders si hay datos
        if (data) {
            Object.keys(data).forEach(key => {
                // Usar un RegExp global (g) para reemplazar todas las instancias
                const regex = new RegExp(`%${key}%`, 'g');
                finalMessage = finalMessage.replace(regex, data[key]);
            });
        }
        
        errorDiv.textContent = finalMessage;
        errorDiv.style.display = 'block';
    }
    
    // --- ▼▼▼ NUEVO BLOQUE PARA MARCAR INPUTS ▼▼▼ ---
    if (inputElement) {
        if (Array.isArray(inputElement)) {
            inputElement.forEach(input => {
                if(input) input.classList.add('auth-input-error');
            });
        } else {
            inputElement.classList.add('auth-input-error');
        }
    }
    // --- ▲▲▲ FIN DE NUEVO BLOQUE ▲▲▲ ---
}
// --- ▲▲▲ FIN DE FUNCIÓN MODIFICADA ▲▲▲ ---


function initPasswordToggles() {
    document.body.addEventListener('click', e => {
        const toggleBtn = e.target.closest('.auth-toggle-password');
        if (toggleBtn) {
            const inputId = toggleBtn.getAttribute('data-toggle');
            const input = document.getElementById(inputId);
            const icon = toggleBtn.querySelector('.material-symbols-rounded');

            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.textContent = 'visibility_off';
                } else {
                    input.type = 'password';
                    icon.textContent = 'visibility';
                }
            }
        }
    });
}

function initRegisterWizard() {

    document.body.addEventListener('click', async e => {
        const button = e.target.closest('[data-auth-action]');
        if (!button) return;

        const action = button.getAttribute('data-auth-action');

        if (action === 'resend-code') {
            e.preventDefault();
            
            const registerForm = button.closest('#register-form');
            if (!registerForm) return; 

            const currentStepEl = button.closest('.auth-step');
            const errorDiv = currentStepEl.querySelector('.auth-error-message');
            
            const linkElement = button;

            if (linkElement.classList.contains('disabled-interactive')) {
                return;
            }

            const email = sessionStorage.getItem('regEmail');
            if (!email) {
                showAuthError(errorDiv, getTranslation('js.auth.errorNoEmail'));
                return;
            }
            
            
            const originalText = linkElement.textContent.replace(/\s*\(\d+s?\)$/, '').trim();
            
            startResendTimer(linkElement, 60); 

            const formData = new FormData();
            formData.append('action', 'register-resend-code');
            formData.append('email', email);
            
            const csrfToken = registerForm.querySelector('[name="csrf_token"]');
            if(csrfToken) {
                 formData.append('csrf_token', csrfToken.value);
            }

            const result = await callAuthApi(formData);

            if (result.success) {
                // --- ▼▼▼ LÍNEA CORREGIDA ▼▼▼ ---
                window.showAlert(getTranslation(result.message || 'js.auth.successCodeResent'), 'success');
                // --- ▲▲▲ LÍNEA CORREGIDA ▲▲▲ ---
            } else {
                // --- ▼▼▼ LÍNEA CORREGIDA ▼▼▼ ---
                // Ahora pasa result.data para reemplazar %seconds%
                showAuthError(errorDiv, getTranslation(result.message || 'js.auth.errorCodeResent'), result.data);
                // --- ▲▲▲ LÍNEA CORREGIDA ▲▲▲ ---
                
                const timerId = linkElement.dataset.timerId;
                if (timerId) {
                    clearInterval(timerId);
                }
                
                linkElement.textContent = originalText; 
                linkElement.classList.remove('disabled-interactive');
                // linkElement.style.opacity = '1'; // ELIMINADO
                // linkElement.style.textDecoration = ''; // ELIMINADO
            }
            return;
        }


        const registerForm = button.closest('#register-form');
        if (!registerForm) return;

        const currentStepEl = button.closest('.auth-step');
        if (!currentStepEl) return;
        const errorDiv = currentStepEl.querySelector('.auth-error-message'); 
        if (!errorDiv) return; 

        const currentStep = parseInt(currentStepEl.getAttribute('data-step'), 10);

        if (action === 'prev-step') {
            return;
        }

        if (action === 'next-step') {
            let isValid = true;
            let clientErrorMessage = getTranslation('js.auth.errorCompleteFields');
            let errorData = null;

            // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (register-step1) ▼▼▼ ---
            if (currentStep === 1) {
                const emailInput = currentStepEl.querySelector('#register-email');
                const passwordInput = currentStepEl.querySelector('#register-password');
                const allowedDomains = /@(gmail\.com|outlook\.com|hotmail\.com|yahoo\.com|icloud\.com)$/i;

                if (!emailInput.value || !passwordInput.value) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorCompleteEmailPass');
                    // Marcar solo los campos vacíos
                    showAuthError(errorDiv, clientErrorMessage, null, [emailInput, passwordInput].filter(el => !el.value));
                } else if (!emailInput.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) { 
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorInvalidEmail');
                    showAuthError(errorDiv, clientErrorMessage, null, emailInput);
                } else if (emailInput.value.length > 255) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorEmailLength');
                    showAuthError(errorDiv, clientErrorMessage, null, emailInput);
                } else if (!allowedDomains.test(emailInput.value)) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorEmailDomain');
                    showAuthError(errorDiv, clientErrorMessage, null, emailInput);
                } else if (passwordInput.value.length < 8 || passwordInput.value.length > 72) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorPasswordLength');
                    errorData = {min: 8, max: 72};
                    showAuthError(errorDiv, clientErrorMessage, errorData, passwordInput);
                }
            }
            // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (register-step1) ▲▲▲ ---

            // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (register-step2) ▼▼▼ ---
            else if (currentStep === 2) {
                const usernameInput = currentStepEl.querySelector('#register-username');

                if (!usernameInput.value) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorUsernameMissing');
                    showAuthError(errorDiv, clientErrorMessage, null, usernameInput);
                } else if (usernameInput.value.length < 6 || usernameInput.value.length > 32) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorUsernameLength');
                    errorData = {min: 6, max: 32};
                    showAuthError(errorDiv, clientErrorMessage, errorData, usernameInput);
                }
            }
            // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (register-step2) ▲▲▲ ---

            if (!isValid) {
                // showAuthError(errorDiv, clientErrorMessage, errorData); // <-- Llamada antigua eliminada
                return;
            }

            if (errorDiv) errorDiv.style.display = 'none';

            // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
            setButtonSpinner(button);
            // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---

            const formData = new FormData(registerForm);
            let fetchAction = '';

            if (currentStep === 1) {
                fetchAction = 'register-check-email';
            }
            else if (currentStep === 2) {
                fetchAction = 'register-check-username-and-generate-code';
                formData.append('email', sessionStorage.getItem('regEmail') || '');
                formData.append('password', sessionStorage.getItem('regPass') || '');
            }
            formData.append('action', fetchAction);

            const result = await callAuthApi(formData);

            if (result.success) {
                let nextPath = '';
                if (currentStep === 1) {
                    sessionStorage.setItem('regEmail', registerForm.querySelector('#register-email').value);
                    sessionStorage.setItem('regPass', registerForm.querySelector('#register-password').value);
                    nextPath = '/register/additional-data';
                } else if (currentStep === 2) {
                    nextPath = '/register/verification-code';
                }

                if (nextPath) {
                    const fullUrlPath = window.projectBasePath + nextPath;
                    history.pushState(null, '', fullUrlPath);
                    handleNavigation(); 
                }
            } else {
                // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (Error de Servidor) ▼▼▼ ---
                let errorInput = null;
                if (result.message === 'js.auth.errorEmailInUse') {
                    errorInput = registerForm.querySelector('#register-email');
                } else if (result.message === 'js.auth.errorUsernameInUse') {
                    errorInput = registerForm.querySelector('#register-username');
                }
                showAuthError(errorDiv, getTranslation(result.message || 'js.auth.errorUnknown'), result.data, errorInput); 
                // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (Error de Servidor) ▼▼▼ ---
            }

            if (!result.success) {
                 // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
                 removeButtonSpinner(button);
                 // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
            }
        }
    });

    // --- ELIMINADO: El listener de 'input' se moverá a initAuthManager ---
}

function initResetWizard() {
    document.body.addEventListener('click', async e => {
        const button = e.target.closest('[data-auth-action]');
        if (!button) return;

        const resetForm = button.closest('#reset-form');
        if (!resetForm) return;

        const currentStepEl = button.closest('.auth-step');
        if (!currentStepEl) return;
        const errorDiv = currentStepEl.querySelector('.auth-error-message'); 
        
        const action = button.getAttribute('data-auth-action');

        if (action === 'resend-code') {
            e.preventDefault();
            const linkElement = button;

            if (linkElement.classList.contains('disabled-interactive')) {
                return;
            }
            
            const email = sessionStorage.getItem('resetEmail');
            if (!email) {
                showAuthError(errorDiv, getTranslation('js.auth.errorNoEmail'));
                return;
            }


            const originalText = linkElement.textContent.replace(/\s*\(\d+s?\)$/, '').trim();
            
            startResendTimer(linkElement, 60);

            const formData = new FormData();
            formData.append('action', 'reset-resend-code');
            formData.append('email', email);
            
            const csrfToken = resetForm.querySelector('[name="csrf_token"]');
            if(csrfToken) {
                 formData.append('csrf_token', csrfToken.value);
            }

            const result = await callAuthApi(formData);

            if (result.success) {
                // --- ▼▼▼ LÍNEA CORREGIDA ▼▼▼ ---
                window.showAlert(getTranslation(result.message || 'js.auth.successCodeResent'), 'success');
                // --- ▲▲▲ LÍNEA CORREGIDA ▲▲▲ ---
            } else {
                // --- ▼▼▼ LÍNEA CORREGIDA ▼▼▼ ---
                // Ahora pasa result.data para reemplazar %seconds%
                showAuthError(errorDiv, getTranslation(result.message || 'js.auth.errorCodeResent'), result.data);
                // --- ▲▲▲ LÍNEA CORREGIDA ▲▲▲ ---
                
                const timerId = linkElement.dataset.timerId;
                if (timerId) {
                    clearInterval(timerId);
                }
                
                linkElement.textContent = originalText; 
                linkElement.classList.remove('disabled-interactive');
                // linkElement.style.opacity = '1'; // ELIMINADO
                // linkElement.style.textDecoration = ''; // ELIMINADO
            }
            return;
        }


        const currentStep = parseInt(currentStepEl.getAttribute('data-step'), 10);

        if (action === 'prev-step') {
            return;
        }

        if (action === 'next-step') {
            let isValid = true;
            let clientErrorMessage = getTranslation('js.auth.errorCompleteFields');

            // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (reset-step1) ▼▼▼ ---
            if (currentStep === 1) {
                const emailInput = currentStepEl.querySelector('#reset-email');
                if (!emailInput.value || !emailInput.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) { 
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorInvalidEmail');
                    showAuthError(errorDiv, clientErrorMessage, null, emailInput);
                } else if (emailInput.value.length > 255) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorEmailLength');
                    showAuthError(errorDiv, clientErrorMessage, null, emailInput);
                }
            }
            // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (reset-step1) ▲▲▲ ---

            // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (reset-step2) ▼▼▼ ---
            else if (currentStep === 2) {
                const codeInput = currentStepEl.querySelector('#reset-code');
                if (!codeInput.value || codeInput.value.length < 14) {
                    isValid = false;
                    clientErrorMessage = getTranslation('js.auth.errorInvalidCode');
                    showAuthError(errorDiv, clientErrorMessage, null, codeInput);
                }
            }
            // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (reset-step2) ▲▲▲ ---

            if (!isValid) {
                // showAuthError(errorDiv, clientErrorMessage); // <-- Llamada antigua eliminada
                return;
            }

            if(errorDiv) errorDiv.style.display = 'none'; 
            
            // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
            setButtonSpinner(button);
            // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---

            const formData = new FormData(resetForm);
            let fetchAction = '';

            if (currentStep === 1) {
                fetchAction = 'reset-check-email';
            }
            else if (currentStep === 2) {
                fetchAction = 'reset-check-code';
                formData.append('email', sessionStorage.getItem('resetEmail') || '');
            }
            formData.append('action', fetchAction);

            const result = await callAuthApi(formData);

            if (result.success) {
                 let nextPath = '';
                 if (currentStep === 1) {
                    sessionStorage.setItem('resetEmail', resetForm.querySelector('#reset-email').value);
                    nextPath = '/reset-password/verify-code';
                 } else if (currentStep === 2) {
                    sessionStorage.setItem('resetCode', resetForm.querySelector('#reset-code').value);
                    nextPath = '/reset-password/new-password';
                 }
                 
                 if (nextPath) {
                    const fullUrlPath = window.projectBasePath + nextPath;
                    history.pushState(null, '', fullUrlPath);
                    handleNavigation(); 
                 }

            } else {
                // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (Error de Servidor) ▼▼▼ ---
                let errorInput = null;
                if (result.message === 'js.auth.errorUserNotFound') {
                    errorInput = resetForm.querySelector('#reset-email');
                } else if (result.message === 'js.auth.errorCodeExpired') {
                    errorInput = resetForm.querySelector('#reset-code');
                }
                showAuthError(errorDiv, getTranslation(result.message || 'js.auth.errorUnknown'), result.data, errorInput); 
                // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (Error de Servidor) ▼▼▼ ---
            }

            if (!result.success) {
                // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
                removeButtonSpinner(button);
                // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
            }
        }
    });
}

function initLoginWizard() {
    document.body.addEventListener('click', async e => {
        const button = e.target.closest('[data-auth-action]');
        if (!button) return;

        const loginForm = button.closest('#login-form');
        if (!loginForm) return;

        const action = button.getAttribute('data-auth-action');

        if (action === 'resend-code') {
            e.preventDefault();
            
            const currentStepEl = button.closest('.auth-step');
            const errorDiv = currentStepEl.querySelector('.auth-error-message');
            const linkElement = button;

            if (linkElement.classList.contains('disabled-interactive')) {
                return;
            }

            // Obtener el email del input del paso 1
            const emailInput = loginForm.querySelector('#login-email');
            if (!emailInput || !emailInput.value) {
                showAuthError(errorDiv, getTranslation('js.auth.errorNoEmail')); // Re-usar esta clave
                return;
            }
            const email = emailInput.value;
            
            const originalText = linkElement.textContent.replace(/\s*\(\d+s?\)$/, '').trim();
            
            startResendTimer(linkElement, 60); 

            const formData = new FormData();
            formData.append('action', 'login-resend-2fa-code'); // Nueva acción de API
            formData.append('email', email);
            
            const csrfToken = loginForm.querySelector('[name="csrf_token"]');
            if(csrfToken) {
                 formData.append('csrf_token', csrfToken.value);
            }

            const result = await callAuthApi(formData);

            if (result.success) {
                window.showAlert(getTranslation(result.message || 'js.auth.successCodeResent'), 'success');
            } else {
                showAuthError(errorDiv, getTranslation(result.message || 'js.auth.errorCodeResent'), result.data);
                
                const timerId = linkElement.dataset.timerId;
                if (timerId) {
                    clearInterval(timerId);
                }
                
                linkElement.textContent = originalText; 
                linkElement.classList.remove('disabled-interactive');
                // linkElement.style.opacity = '1'; // ELIMINADO
                // linkElement.style.textDecoration = ''; // ELIMINADO
            }
            return;
        }


        const currentStepEl = button.closest('.auth-step');
        if (!currentStepEl) return;
        const errorDiv = currentStepEl.querySelector('.auth-error-message'); 

        const currentStep = parseInt(currentStepEl.getAttribute('data-step'), 10);

        // --- ▼▼▼ INICIO DE LA MODIFICACIÓN (initLoginWizard -> prev-step) ▼▼▼ ---
        if (action === 'prev-step') {
            // Esta lógica ya no se usa porque eliminamos el botón "Atrás" de 2FA
            // Pero la dejamos por si se reutiliza.
            const prevStepEl = loginForm.querySelector(`[data-step="${currentStep - 1}"]`);
            if (prevStepEl) {
                currentStepEl.style.display = 'none';
                currentStepEl.classList.remove('active'); // <-- LÍNEA AÑADIDA
                
                prevStepEl.style.display = 'block';
                prevStepEl.classList.add('active'); // <-- LÍNEA AÑADIDA
                
                if(errorDiv) errorDiv.style.display = 'none'; 
            }
            return;
        }
        // --- ▲▲▲ FIN DE LA MODIFICACIÓN (initLoginWizard -> prev-step) ▲▲▲ ---

        if (action === 'next-step') { 
            // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (login-step1) ▼▼▼ ---
            const emailInput = currentStepEl.querySelector('#login-email');
            const passwordInput = currentStepEl.querySelector('#login-password');
            if (!emailInput.value || !passwordInput.value) {
                // Marcar solo los campos vacíos
                showAuthError(errorDiv, getTranslation('js.auth.errorCompleteEmailPass'), null, [emailInput, passwordInput].filter(el => !el.value)); 
                return;
            }
            // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (login-step1) ▲▲▲ ---

            if(errorDiv) errorDiv.style.display = 'none'; 
            
            // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
            setButtonSpinner(button);
            // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---

            const formData = new FormData(loginForm);
            formData.append('action', 'login-check-credentials');

            const result = await callAuthApi(formData);

            if (result.success) {
                // --- ▼▼▼ INICIO DE LA MODIFICACIÓN (initLoginWizard -> next-step) ▼▼▼ ---
                if (result.is_2fa_required) {
                    const nextStepEl = loginForm.querySelector(`[data-step="${currentStep + 1}"]`);
                    if (nextStepEl) {
                        currentStepEl.style.display = 'none';
                        currentStepEl.classList.remove('active'); // <-- LÍNEA AÑADIDA
                        
                        nextStepEl.style.display = 'block';
                        nextStepEl.classList.add('active'); // <-- LÍNEA AÑADIDA
                        
                         const nextInput = nextStepEl.querySelector('input#login-code');
                         if (nextInput) nextInput.focus();
                         
                         const resendLink = nextStepEl.querySelector('#login-resend-code-link');
                         const cooldown = result.cooldown || 0; 
                         if (resendLink && cooldown > 0) {
                            startResendTimer(resendLink, cooldown);
                         }
                    }
                     if (result.message) {
                         window.showAlert(getTranslation(result.message), 'info');
                     }
                // --- ▲▲▲ FIN DE LA MODIFICACIÓN (initLoginWizard -> next-step) ▲▲▲ ---
                } else {
                     if (result.message) {
                         window.showAlert(getTranslation(result.message), 'success');
                         await new Promise(resolve => setTimeout(resolve, 500)); 
                     }
                    window.location.href = window.projectBasePath + '/';
                }
            } else if (result.redirect_to_status) {
                 window.location.href = window.projectBasePath + '/account-status/' + result.redirect_to_status;
            } else {
                // --- ▼▼▼ INICIO DE BLOQUE MODIFICADO (Error de Servidor) ▼▼▼ ---
                let errorInput = [emailInput, passwordInput]; // Marcar ambos por defecto
                if (result.message === 'js.auth.errorTooManyAttempts') {
                    errorInput = null; // No marcar campos si es por rate limiting
                }
                showAuthError(errorDiv, getTranslation(result.message || 'js.auth.errorUnknown'), result.data, errorInput); 
                // --- ▲▲▲ FIN DE BLOQUE MODIFICADO (Error de Servidor) ▼▼▼ ---
                
                // --- ▼▼▼ LÍNEA MODIFICADA ▼▼▼ ---
                removeButtonSpinner(button);
                // --- ▲▲▲ LÍNEA MODIFICADA ▲▲▲ ---
            }

             if(result.success && result.is_2fa_required) {
             } else if (!result.success && !result.redirect_to_status) { // Modificado para no reactivar botón si hay redirección
                // No reactivar el botón si estamos redirigiendo
             }
        }
    });
}


export function initAuthManager() {
    initPasswordToggles();

    // --- ▼▼▼ INICIO DE NUEVO LISTENER (Unificado) ▼▼▼ ---
    // Centraliza la limpieza de errores y el formato de códigos
    document.body.addEventListener('input', e => {
        
        // Parte 1: Limpiar errores de validación
        const authInput = e.target.closest('.auth-input-group input');
        if (authInput && e.target.closest('.auth-form')) {
            
            // Quitar el borde rojo del input
            authInput.classList.remove('auth-input-error');
            
            // Ocultar el mensaje de error del paso actual
            const currentStep = authInput.closest('.auth-step');
            if (currentStep) {
                const errorDiv = currentStep.querySelector('.auth-error-message');
                if (errorDiv && errorDiv.style.display !== 'none') {
                    errorDiv.style.display = 'none';
                }
            }
        }

        // Parte 2: Formatear códigos de verificación
        const isRegisterCode = (e.target.id === 'register-code' && e.target.closest('#register-form'));
        const isResetCode = (e.target.id === 'reset-code' && e.target.closest('#reset-form'));
        const isLoginCode = (e.target.id === 'login-code' && e.target.closest('#login-form'));
        const isSettingsEmailCode = (e.target.id === 'email-verify-code' && e.target.closest('#email-verify-modal'));

        if (isRegisterCode || isResetCode || isLoginCode || isSettingsEmailCode) {
            let input = e.target.value.replace(/[^0-9a-zA-Z]/g, '');
            input = input.toUpperCase();
            input = input.substring(0, 12);

            let formatted = '';
            for (let i = 0; i < input.length; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += '-';
                }
                formatted += input[i];
            }
            e.target.value = formatted;
        }
    });
    // --- ▲▲▲ FIN DE NUEVO LISTENER (Unificado) ▲▲▲ ---

    initRegisterWizard();
    initResetWizard();
    initLoginWizard();

    document.body.addEventListener('submit', e => {
        if (e.target.id === 'login-form') {
            handleLoginFinalSubmit(e);
        } else if (e.target.id === 'register-form') {
            handleRegistrationSubmit(e);
        } else if (e.target.id === 'reset-form') {
            handleResetSubmit(e);
        }
    });
}