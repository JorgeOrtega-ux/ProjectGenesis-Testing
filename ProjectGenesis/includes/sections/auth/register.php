<div class="section-content <?php echo (strpos($CURRENT_SECTION, 'register-') === 0) ? 'active' : 'disabled'; ?>" data-section="<?php echo htmlspecialchars($CURRENT_SECTION); ?>">
    <div class="auth-container">
        <h1 class="auth-title" data-i18n="page.register.title"></h1>
        
        <form class="auth-form" id="register-form" onsubmit="event.preventDefault();" novalidate>
            
            <?php outputCsrfInput(); ?>

            <fieldset class="auth-step <?php echo ($CURRENT_REGISTER_STEP == 1) ? 'active' : ''; ?>" data-step="1">
                <div class="auth-input-group">
                    <input type="email" id="register-email" name="email" required placeholder=" " maxlength="255">
                    <label for="register-email" data-i18n="page.register.emailLabel"></label>
                </div>

                <div class="auth-input-group">
                    <input type="password" id="register-password" name="password" required placeholder=" " minlength="8" maxlength="72">
                    <label for="register-password" data-i18n="page.register.passwordLabel"></label>
                    <button type="button" class="auth-toggle-password" data-toggle="register-password">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step" data-i18n="page.register.continueButton"></button>
                </div>
                <div class="auth-error-message" style="display: none;"></div>
            </fieldset>

            <fieldset class="auth-step <?php echo ($CURRENT_REGISTER_STEP == 2) ? 'active' : ''; ?>" data-step="2">
                <div class="auth-input-group">
                    <input type="text" id="register-username" name="username" required placeholder=" " minlength="6" maxlength="32">
                    <label for="register-username" data-i18n="page.register.usernameLabel"></label>
                </div>

                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step" data-i18n="page.register.continueButton"></button>
                </div>
                <div class="auth-error-message" style="display: none;"></div>
            </fieldset>

            <fieldset class="auth-step <?php echo ($CURRENT_REGISTER_STEP == 3) ? 'active' : ''; ?>" data-step="3">
                
                <p class="auth-verification-text" data-i18n="page.register.verificationDesc">
                    <strong><?php echo htmlspecialchars($_SESSION['registration_email'] ?? 'tu correo'); ?></strong>.
                    </p>

                <div class="auth-input-group">
                    <input type="text" id="register-code" name="verification_code" required placeholder=" " maxlength="14">
                    <label for="register-code" data-i18n="page.register.verificationCodeLabel"></label>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="submit" class="auth-button" data-i18n="page.register.verifyButton"></button>
                </div>
                
                <div class="auth-error-message" style="display: none;"></div>
                
                <p class="auth-link text-center">
                    <a href="#" 
                       id="register-resend-code-link" 
                       data-auth-action="resend-code"
                       data-cooldown="<?php echo isset($initialCooldown) ? $initialCooldown : 0; ?>"
                       class="<?php echo (isset($initialCooldown) && $initialCooldown > 0) ? 'disabled-interactive' : ''; ?>"
                       data-i18n="page.register.resendCode"
                    >
                       <?php 
                       // El JS se encargará de poner el texto de la clave "page.register.resendCode"
                       // Este PHP solo maneja el cooldown inicial si existe
                       if (isset($initialCooldown) && $initialCooldown > 0) {
                           echo " (" . $initialCooldown . "s)";
                       }
                       ?>
                    </a>
                </p>
                </fieldset>
            
            </form>
        
        <?php if ($CURRENT_REGISTER_STEP == 1): ?>
        <p class="auth-link">
            <span data-i18n="page.register.hasAccount"></span> <a href="/ProjectGenesis/login" data-i18n="page.register.login"></a>
        </p>
        <?php endif; ?>
    </div>
</div>