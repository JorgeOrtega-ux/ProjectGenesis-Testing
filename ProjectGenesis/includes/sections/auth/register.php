<div class="section-content <?php echo (strpos($CURRENT_SECTION, 'register-') === 0) ? 'active' : 'disabled'; ?>" data-section="<?php echo htmlspecialchars($CURRENT_SECTION); ?>">
    <div class="auth-container">
        <h1 class="auth-title"><?php echo __('auth.register.title'); ?></h1>
        
        <form class="auth-form" id="register-form" onsubmit="event.preventDefault();" novalidate>
            
            <?php outputCsrfInput(); ?>

            <fieldset class="auth-step <?php echo ($CURRENT_REGISTER_STEP == 1) ? 'active' : ''; ?>" data-step="1" <?php echo ($CURRENT_REGISTER_STEP != 1) ? 'style="display: none;"' : ''; ?>>
                <div class="auth-input-group">
                    <input type="email" id="register-email" name="email" required placeholder=" " maxlength="255">
                    <label for="register-email"><?php echo __('auth.form.email.label'); ?>*</label>
                </div>

                <div class="auth-input-group">
                    <input type="password" id="register-password" name="password" required placeholder=" " minlength="8" maxlength="72">
                    <label for="register-password"><?php echo __('auth.form.password.label'); ?>*</label>
                    <button type="button" class="auth-toggle-password" data-toggle="register-password">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step"><?php echo __('auth.form.button.continue'); ?></button>
                </div>
                <div class="auth-error-message" style="display: none;"></div>
            </fieldset>

            <fieldset class="auth-step <?php echo ($CURRENT_REGISTER_STEP == 2) ? 'active' : ''; ?>" data-step="2" <?php echo ($CURRENT_REGISTER_STEP != 2) ? 'style="display: none;"' : ''; ?>>
                <div class="auth-input-group">
                    <input type="text" id="register-username" name="username" required placeholder=" " minlength="6" maxlength="32">
                    <label for="register-username"><?php echo __('auth.form.username.label'); ?>*</label>
                </div>

                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step"><?php echo __('auth.form.button.continue'); ?></button>
                </div>
                <div class="auth-error-message" style="display: none;"></div>
            </fieldset>

            <fieldset class="auth-step <?php echo ($CURRENT_REGISTER_STEP == 3) ? 'active' : ''; ?>" data-step="3" <?php echo ($CURRENT_REGISTER_STEP != 3) ? 'style="display: none;"' : ''; ?>>
                
                <p class="auth-verification-text">
                    <?php echo __('auth.register.verification.message', ['email' => htmlspecialchars($_SESSION['registration_email'] ?? __('auth.register.verification.fallbackEmail'))]); ?>
                </p>

                <div class="auth-input-group">
                    <input type="text" id="register-code" name="verification_code" required placeholder=" " maxlength="14">
                    <label for="register-code"><?php echo __('auth.form.verificationCode.label'); ?>*</label>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="submit" class="auth-button"><?php echo __('auth.register.verification.button'); ?></button>
                </div>
                
                <div class="auth-error-message" style="display: none;"></div>
                
                <p class="auth-link" style="text-align: center;">
                    <a href="#" 
                       id="register-resend-code-link" 
                       data-auth-action="resend-code"
                       data-cooldown="<?php echo isset($initialCooldown) ? $initialCooldown : 0; ?>"
                       class="<?php echo (isset($initialCooldown) && $initialCooldown > 0) ? 'disabled-interactive' : ''; ?>"
                       style="<?php echo (isset($initialCooldown) && $initialCooldown > 0) ? 'opacity: 0.7; text-decoration: none;' : ''; ?>"
                    >
                       <?php // --- ▼▼▼ INICIO DE LA MODIFICACIÓN ▼▼▼ --- ?>
                       <?php 
                       $linkText = __('auth.form.resendCodeLink');
                       if (isset($initialCooldown) && $initialCooldown > 0) {
                           // Ya está en cooldown al cargar, muestra el tiempo
                           // (JS lo actualizará dinámicamente)
                           echo htmlspecialchars($linkText . " (" . $initialCooldown . "s)");
                       } else {
                           // No hay cooldown, muestra el texto normal
                           echo htmlspecialchars($linkText);
                       }
                       ?>
                       <?php // --- ▲▲▲ FIN DE LA MODIFICACIÓN ▲▲▲ --- ?>
                    </a>
                </p>
                </fieldset>
            
            </form>
        
        <?php if ($CURRENT_REGISTER_STEP == 1): ?>
        <p class="auth-link">
            <?php echo __('auth.register.haveAccount'); ?> <a href="/ProjectGenesis/login"><?php echo __('auth.register.loginLink'); ?></a>
        </p>
        <?php endif; ?>
    </div>
</div>