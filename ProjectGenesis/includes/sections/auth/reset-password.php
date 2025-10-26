<div class="section-content <?php echo (strpos($CURRENT_SECTION, 'reset-') === 0) ? 'active' : 'disabled'; ?>" data-section="<?php echo htmlspecialchars($CURRENT_SECTION); ?>">
    <div class="auth-container">
        <h1 class="auth-title"><?php echo __('auth.reset.title'); ?></h1>
        
        <form class="auth-form" id="reset-form" onsubmit="event.preventDefault();" novalidate>
            
            <?php outputCsrfInput(); ?>

            <fieldset class="auth-step <?php echo ($CURRENT_RESET_STEP == 1) ? 'active' : ''; ?>" data-step="1" <?php echo ($CURRENT_RESET_STEP != 1) ? 'style="display: none;"' : ''; ?>>
                 <p class="auth-verification-text" style="margin-bottom: 16px;">
                    <?php echo __('auth.reset.step1.message'); ?>
                </p>
                <div class="auth-input-group">
                    <input type="email" id="reset-email" name="email" required placeholder=" " maxlength="255">
                    <label for="reset-email"><?php echo __('auth.form.email.label'); ?>*</label>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step"><?php echo __('auth.reset.step1.button'); ?></button>
                </div>
                
                <div class="auth-error-message" style="display: none;"></div>
                
                <p class="auth-link" style="text-align: center;">
                    <?php echo __('auth.reset.rememberedPassword'); ?> <a href="/ProjectGenesis/login"><?php echo __('auth.register.loginLink'); ?></a>
                </p>
            </fieldset>

            <fieldset class="auth-step <?php echo ($CURRENT_RESET_STEP == 2) ? 'active' : ''; ?>" data-step="2" <?php echo ($CURRENT_RESET_STEP != 2) ? 'style="display: none;"' : ''; ?>>
                <p class="auth-verification-text" style="margin-bottom: 16px;">
                    <?php echo __('auth.reset.step2.message'); ?>
                </p>
                <div class="auth-input-group">
                    <input type="text" id="reset-code" name="verification_code" required placeholder=" " maxlength="14">
                    <label for="reset-code"><?php echo __('auth.form.verificationCode.label'); ?>*</label>
                </div>

                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step"><?php echo __('auth.reset.step2.button'); ?></button>
                </div>
                
                <div class="auth-error-message" style="display: none;"></div>
                
                <p class="auth-link" style="text-align: center;">
                    <a href="#" 
                       id="reset-resend-code-link" 
                       data-auth-action="resend-code"
                    >
                       <?php echo __('auth.form.resendCodeLink'); ?>
                    </a>
                </p>
            </fieldset>

            <fieldset class="auth-step <?php echo ($CURRENT_RESET_STEP == 3) ? 'active' : ''; ?>" data-step="3" <?php echo ($CURRENT_RESET_STEP != 3) ? 'style="display: none;"' : ''; ?>>
                <p class="auth-verification-text" style="margin-bottom: 16px;">
                    <?php echo __('auth.reset.step3.message'); ?>
                </p>

                <div class="auth-input-group">
                    <input type="password" id="reset-password" name="password" required placeholder=" " minlength="8" maxlength="72">
                    <label for="reset-password"><?php echo __('auth.reset.step3.newPassword.label'); ?>*</label>
                    <button type="button" class="auth-toggle-password" data-toggle="reset-password">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

                 <div class="auth-input-group">
                    <input type="password" id="reset-password-confirm" name="password_confirm" required placeholder=" " minlength="8" maxlength="72">
                    <label for="reset-password-confirm"><?php echo __('auth.reset.step3.confirmPassword.label'); ?>*</label>
                    <button type="button" class="auth-toggle-password" data-toggle="reset-password-confirm">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>
                
                <div class="auth-step-buttons">
                     <a href="/ProjectGenesis/reset-password/verify-code" class="auth-button-back"><?php echo __('auth.form.button.back'); ?></a>
                    <button type="submit" class="auth-button"><?php echo __('auth.reset.step3.button'); ?></button>
                </div>
                
                <div class="auth-error-message" style="display: none;"></div>
                
            </fieldset>
            
            </form>
    </div>
</div>