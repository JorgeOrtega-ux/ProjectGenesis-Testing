<div class="section-content <?php echo ($CURRENT_SECTION === 'settings-login') ? 'active' : 'disabled'; ?>" data-section="settings-login">
    <div class="settings-wrapper">

        <div class="settings-header-card">
            <h1 class="settings-title">Inicio de Sesión y Seguridad</h1>
            <p class="settings-description">
                Gestiona tu contraseña, activa la verificación de dos pasos (2FA) y revisa tu historial de inicio de sesión.
            </p>
        </div>

        <div class="settings-card">
            <div class="settings-card-left">
                <div class="settings-card-icon">
                    <span class="material-symbols-rounded">lock</span>
                </div>
                <div class="settings-text-content">
                    <h2 class="settings-text-title">Contraseña</h2>

                    <p class="settings-text-description">
                        <?php
                        // Esta variable $lastPasswordUpdateText se define en config/router.php
                        echo htmlspecialchars($lastPasswordUpdateText);
                        ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                <div class="settings-card-right-actions">
                    <button type="button" class="settings-button" id="password-edit-trigger">Actualizar</button>
                </div>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-left">
                <div class="settings-card-icon">
                    <span class="material-symbols-rounded">shield_lock</span>
                </div>
                <div class="settings-text-content">
                    <h2 class="settings-text-title">Verificación de dos pasos (2FA)</h2>
                    <p class="settings-text-description" id="2fa-status-text">
                        <?php
                        // $is2faEnabled viene de config/router.php
                        echo $is2faEnabled ? 'La autenticación de dos pasos está activa.' : 'Añade una capa extra de seguridad a tu cuenta.';
                        ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                <div class="settings-card-right-actions">
                    <label class="settings-toggle-switch" for="2fa-toggle-input">
                        <input type="checkbox" 
                               id="2fa-toggle-input" 
                               class="visually-hidden"
                               <?php echo $is2faEnabled ? 'checked' : ''; ?>>
                        <span class="settings-toggle-slider"></span>
                    </label>
                </div>
            </div>
        </div>
        </div>

    <div class="settings-modal-overlay" id="password-change-modal" style="display: none;">

        <button type="button" class="settings-modal-close-btn" id="password-verify-close">
            <span class="material-symbols-rounded">close</span>
        </button>

        <div class="settings-modal-content">

            <form class="auth-form" onsubmit="event.preventDefault();" novalidate>

                <fieldset class="auth-step active" data-step="1">
                    <h2 class="auth-title">Verifica tu identidad</h2>
                    <p class="auth-verification-text">
                        Para continuar, por favor ingresa tu contraseña actual.
                    </p>

                    <div class="auth-error-message" id="password-verify-error" style="display: none;"></div>

                    <div class="auth-input-group">
                        <input type="password" id="password-verify-current" name="current_password" required placeholder=" ">
                        <label for="password-verify-current">Contraseña actual*</label>
                    </div>

                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button" id="password-verify-continue">Continuar</button>
                    </div>
                </fieldset>

                <fieldset class="auth-step" data-step="2" style="display: none;">
                    <h2 class="auth-title">Crea una nueva contraseña</h2>
                    <p class="auth-verification-text">
                        Tu nueva contraseña debe tener al menos 8 caracteres.
                    </p>

                    <div class="auth-error-message" id="password-update-error"></div>

                    <div class="auth-input-group">
                        <input type="password" id="password-update-new" name="new_password" required placeholder=" ">
                        <label for="password-update-new">Nueva contraseña*</label>
                    </div>

                    <div class="auth-input-group">
                        <input type="password" id="password-update-confirm" name="confirm_password" required placeholder=" ">
                        <label for="password-update-confirm">Confirmar nueva contraseña*</label>
                    </div>

                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button-back" id="password-update-back">Atrás</button>
                        <button type="button" class="auth-button" id="password-update-save">Guardar Contraseña</button>
                    </div>
                </fieldset>

            </form>
        </div>
    </div>

    <div class="settings-modal-overlay" id="2fa-verify-modal" style="display: none;">
        <button type="button" class="settings-modal-close-btn" id="2fa-verify-close">
            <span class="material-symbols-rounded">close</span>
        </button>
        <div class="settings-modal-content">
            <form class="auth-form" onsubmit="event.preventDefault();" novalidate>
                <fieldset class="auth-step active">
                    <h2 class="auth-title" id="2fa-modal-title">Verifica tu identidad</h2>
                    <p class="auth-verification-text" id="2fa-modal-text">
                        Para continuar, por favor ingresa tu contraseña actual.
                    </p>
                    <div class="auth-error-message" id="2fa-verify-error" style="display: none;"></div>
                    <div class="auth-input-group">
                        <input type="password" id="2fa-verify-password" name="current_password" required placeholder=" ">
                        <label for="2fa-verify-password">Contraseña actual*</label>
                    </div>
                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button" id="2fa-verify-continue">Confirmar</button>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>
    </div>