<?php
// FILE: jorgeortega-ux/projectgenesis/ProjectGenesis-98418948306e47bc505f1797114031c3351b5e33/ProjectGenesis/includes/sections/settings/login-security.php
?>
<div class="section-content <?php echo ($CURRENT_SECTION === 'settings-login') ? 'active' : 'disabled'; ?>" data-section="settings-login">
    <div class="settings-wrapper">

        <div class="settings-header-card">
            <h1 class="settings-title"><?php echo __('settings.login.title'); ?></h1>
            <p class="settings-description">
                <?php echo __('settings.login.description'); ?>
            </p>
        </div>

        <div class="settings-card">
            <div class="settings-card-left">
                <div class="settings-card-icon">
                    <span class="material-symbols-rounded">lock</span>
                </div>
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.login.password.label'); ?></h2>

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
                    <button type="button" class="settings-button" id="password-edit-trigger"><?php echo __('settings.login.password.button'); ?></button>
                </div>
            </div>
        </div>

        <div class="settings-card">
            <div class="settings-card-left">
                <div class="settings-card-icon">
                    <span class="material-symbols-rounded">shield_lock</span>
                </div>
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.login.2fa.label'); ?></h2>
                    <p class="settings-text-description" id="tfa-status-text">
                        <?php
                        // $is2faEnabled viene de config/router.php
                        echo $is2faEnabled ? __('settings.login.2fa.descriptionEnabled') : __('settings.login.2fa.descriptionDisabled');
                        ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                <div class="settings-card-right-actions">
                    <button type="button" 
                            class="settings-button <?php echo $is2faEnabled ? 'danger' : ''; ?>" 
                            id="tfa-toggle-button"
                            data-is-enabled="<?php echo $is2faEnabled ? '1' : '0'; ?>">
                        <?php echo $is2faEnabled ? __('settings.login.2fa.buttonDisable') : __('settings.login.2fa.buttonEnable'); ?>
                    </button>
                    </div>
            </div>
        </div>

        <div class="settings-card settings-card-column">
            <div class="settings-card-left">
                <div class="settings-card-icon">
                    <span class="material-symbols-rounded">devices</span>
                </div>
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.login.devices.label'); ?></h2>
                    <p class="settings-text-description">
                        <?php echo __('settings.login.devices.description'); ?>
                    </p>
                </div>
            </div>
            
            <div class="settings-card-bottom">
                <div class="settings-card-right-actions">
                    <button type="button" 
                            class="settings-button" 
                            data-action="toggleSectionSettingsDevices"> 
                            <?php echo __('settings.login.devices.button'); ?>
                    </button>
                </div>
            </div>
        </div>
        <div class="settings-card settings-card-column settings-card-danger">
            
            <div class="settings-text-content">
                <h2 class="settings-text-title"><?php echo __('settings.login.deleteAccount.label'); ?></h2>
                <p class="settings-text-description">
                    <?php echo __('settings.login.deleteAccount.description'); ?>
                </p>
            </div>
            
            <div class="settings-card-bottom">
                <div class="settings-card-right-actions">
                    <button type="button" class="settings-button danger"><?php echo __('settings.login.deleteAccount.button'); ?></button>
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
                    <h2 class="auth-title"><?php echo __('settings.password.modal.step1.title'); ?></h2>
                    <p class="auth-verification-text">
                        <?php echo __('settings.password.modal.step1.message'); ?>
                    </p>

                    <div class="auth-error-message" id="password-verify-error" style="display: none;"></div>

                    <div class="auth-input-group">
                        <input type="password" id="password-verify-current" name="current_password" required placeholder=" ">
                        <label for="password-verify-current"><?php echo __('settings.password.modal.step1.label'); ?>*</label>
                    </div>

                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button" id="password-verify-continue"><?php echo __('auth.form.button.continue'); ?></button>
                    </div>
                </fieldset>

                <fieldset class="auth-step" data-step="2" style="display: none;">
                    <h2 class="auth-title"><?php echo __('settings.password.modal.step2.title'); ?></h2>
                    <p class="auth-verification-text">
                        <?php echo __('settings.password.modal.step2.message'); ?>
                    </p>

                    <div class="auth-error-message" id="password-update-error"></div>

                    <div class="auth-input-group">
                        <input type="password" id="password-update-new" name="new_password" required placeholder=" " minlength="8" maxlength="72">
                        <label for="password-update-new"><?php echo __('settings.password.modal.step2.labelNew'); ?>*</label>
                    </div>

                    <div class="auth-input-group">
                        <input type="password" id="password-update-confirm" name="confirm_password" required placeholder=" " minlength="8" maxlength="72">
                        <label for="password-update-confirm"><?php echo __('settings.password.modal.step2.labelConfirm'); ?>*</label>
                    </div>

                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button-back" id="password-update-back"><?php echo __('auth.form.button.back'); ?></button>
                        <button type="button" class="auth-button" id="password-update-save"><?php echo __('settings.button.save'); ?></button>
                    </div>
                </fieldset>

            </form>
        </div>
    </div>

    <div class="settings-modal-overlay" id="tfa-verify-modal" style="display: none;">
        <button type="button" class="settings-modal-close-btn" id="tfa-verify-close">
            <span class="material-symbols-rounded">close</span>
        </button>
        <div class="settings-modal-content">
            <form class="auth-form" onsubmit="event.preventDefault();" novalidate>
                <fieldset class="auth-step active">
                    <h2 class="auth-title" id="tfa-modal-title"><?php echo __('settings.2fa.modal.title'); ?></h2>
                    <p class_id="tfa-modal-text">
                        <?php echo __('settings.2fa.modal.message'); ?>
                    </p>
                    <div class="auth-error-message" id="tfa-verify-error" style="display: none;"></div>
                    <div class="auth-input-group">
                        <input type="password" id="tfa-verify-password" name="current_password" required placeholder=" ">
                        <label for="tfa-verify-password"><?php echo __('settings.password.modal.step1.label'); ?>*</label>
                    </div>
                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button" id="tfa-verify-continue"><?php echo __('settings.2fa.modal.button'); ?></button>
                    </div>
                </fieldset>
            </form>
        </div>
    </div>

    </div>
</div>