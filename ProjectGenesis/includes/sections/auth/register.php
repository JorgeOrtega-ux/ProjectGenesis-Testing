<div class="section-content <?php echo ($CURRENT_SECTION === 'register') ? 'active' : 'disabled'; ?>" data-section="register">
    <div class="auth-container">
        <h1 class="auth-title">Crea una cuenta</h1>
        
        <form class="auth-form" id="register-form" onsubmit="event.preventDefault();" novalidate>
            
            <?php outputCsrfInput(); ?>
            <div class="auth-error-message" id="register-error" style="display: none;"></div>

            <fieldset class="auth-step active" data-step="1">
                <p class="auth-step-indicator">Paso 1 de 3: Tu cuenta</p>
                <div class="auth-input-group">
                    <input type="email" id="register-email" name="email" required placeholder=" ">
                    <label for="register-email">Dirección de correo electrónico*</label>
                </div>

                <div class="auth-input-group">
                    <input type="password" id="register-password" name="password" required placeholder=" ">
                    <label for="register-password">Contraseña*</label>
                    <button type="button" class="auth-toggle-password" data-toggle="register-password">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step">Continuar</button>
                </div>
            </fieldset>

            <fieldset class="auth-step" data-step="2" style="display: none;">
                <p class="auth-step-indicator">Paso 2 de 3: Tu perfil</p>
                <div class="auth-input-group">
                    <input type="text" id="register-username" name="username" required placeholder=" ">
                    <label for="register-username">Nombre de usuario*</label>
                </div>

                <div class="auth-step-buttons">
                    <button type="button" class="auth-button-back" data-auth-action="prev-step">Atrás</button>
                    <button type="button" class="auth-button" data-auth-action="next-step">Continuar</button>
                </div>
            </fieldset>

            <fieldset class="auth-step" data-step="3" style="display: none;">
                <p class="auth-step-indicator">Paso 3 de 3: Verificación</p>
                
                <p class="auth-verification-text">
                    Te hemos enviado (simulado) un código de verificación. 
                    Por favor, ingrésalo para finalizar tu registro.
                </p>

                <div class="auth-input-group">
                    <input type="text" id="register-code" name="verification_code" required placeholder=" " maxlength="14">
                    <label for="register-code">Código de Verificación*</label>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button-back" data-auth-action="prev-step">Atrás</button>
                    <button type="submit" class="auth-button">Verificar y Crear Cuenta</button>
                </div>
            </fieldset>
            
        </form>
        
        <p class="auth-link">
            ¿Ya tienes una cuenta? <a href="/ProjectGenesis/login">Inicia sesión</a>
        </p>
    </div>
</div>