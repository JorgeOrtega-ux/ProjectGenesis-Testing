<div class="section-content <?php echo ($CURRENT_SECTION === 'login') ? 'active' : 'disabled'; ?>" data-section="login">
    <div class="auth-container">
        <h1 class="auth-title"><?php echo __('auth.login.title'); ?></h1>
        
        <form class="auth-form" id="login-form" onsubmit="event.preventDefault();" novalidate>
            
            <?php outputCsrfInput(); ?>

            <fieldset class="auth-step active" data-step="1">
                <div class="auth-input-group">
                    <input type="email" id="login-email" name="email" required placeholder=" ">
                    <label for="login-email"><?php echo __('auth.form.email.label'); ?>*</label>
                </div>

                <div class="auth-input-group">
                    <input type="password" id="login-password" name="password" required placeholder=" ">
                    <label for="login-password"><?php echo __('auth.form.password.label'); ?>*</label>
                    <button type="button" class="auth-toggle-password" data-toggle="login-password">
                        <span class="material-symbols-rounded">visibility</span>
                    </button>
                </div>

                <p class="auth-link" style="text-align: right;">
                    <a href="/ProjectGenesis/reset-password"><?php echo __('auth.login.forgotPasswordLink'); ?></a>
                </p>
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button" data-auth-action="next-step"><?php echo __('auth.form.button.continue'); ?></button>
                </div>
                <div class="auth-error-message" style="display: none;"></div>
            </fieldset>
            <fieldset class="auth-step" data-step="2" style="display: none;">
                <p class="auth-verification-text" style="margin-bottom: 16px;">
                    <?php echo __('auth.login.2fa.message'); ?>
                </p>

                <div class="auth-input-group">
                    <input type="text" id="login-code" name="verification_code" required placeholder=" " maxlength="14">
                    <label for="login-code"><?php echo __('auth.form.verificationCode.label'); ?>*</label>
                </div>
                
                <div class="auth-step-buttons">
                    <button type="button" class="auth-button-back" data-auth-action="prev-step"><?php echo __('auth.form.button.back'); ?></button>
                    <button type="submit" class="auth-button"><?php echo __('auth.login.2fa.button'); ?></button>
                </div>
                <div class="auth-error-message" style="display: none;"></div>
            </fieldset>
            
            </form>

        <p class="auth-link">
            <?php echo __('auth.login.noAccount'); ?> <a href="/ProjectGenesis/register"><?php echo __('auth.login.createAccountLink'); ?></a>
        </p>
    </div>
</div>