<?php
// --- ▼▼▼ INICIO DE NUEVO BLOQUE PHP ▼▼▼ ---

// Estas variables ($userLanguage, $userUsageType, $openLinksInNewTab) 
// son cargadas por config/router.php

// 1. Definir los mapas de valores de BD a texto legible
$usageMap = [
    'personal' => __('settings.profile.usage.personal'),
    'student' => __('settings.profile.usage.student'),
    'teacher' => __('settings.profile.usage.teacher'),
    'small_business' => __('settings.profile.usage.small_business'),
    'large_company' => __('settings.profile.usage.large_company')
];

// --- ¡NUEVO MAPA DE ICONOS AÑADIDO! ---
$usageIconMap = [
    'personal' => 'person',
    'student' => 'school',
    'teacher' => 'history_edu',
    'small_business' => 'storefront',
    'large_company' => 'business'
];

$languageMap = [
    'es-419' => __('settings.profile.language.es-419'),
    'es-latam' => __('settings.profile.language.es-latam'),
    'es-mx' => __('settings.profile.language.es-mx'),
    'en-us' => __('settings.profile.language.en-us'),
    'fr-fr' => __('settings.profile.language.fr-fr')
];

// 2. Obtener el texto actual para mostrar en el botón
$currentUsageText = $usageMap[$userUsageType] ?? $usageMap['personal'];
$currentLanguageText = $languageMap[$userLanguage] ?? $languageMap['es-419'];

// --- ▲▲▲ FIN DE NUEVO BLOQUE PHP ▲▲▲ ---
?>
<div class="section-content <?php echo ($CURRENT_SECTION === 'settings-profile') ? 'active' : 'disabled'; ?>" data-section="settings-profile">
    <div class="settings-wrapper">
        
        <div class="settings-header-card">
            <h1 class="settings-title"><?php echo __('settings.profile.title'); ?></h1>
            <p class="settings-description">
                <?php echo __('settings.profile.description'); ?>
            </p>
        </div>

        <?php
        // ¡Este bloque de lógica se ha ido!
        ?>
        
        <form id="avatar-form" onsubmit="event.preventDefault();" novovite>
            
            <?php outputCsrfInput(); ?>
            
            <input type="file" id="avatar-upload-input" name="avatar" class="visually-hidden" accept="image/png, image/jpeg, image/gif, image/webp">
            <div class="settings-card-avatar-error" id="avatar-error" style="display: none;"></div>

            <div class="settings-card">
                <div class="settings-card-left">
                    <div class="settings-avatar" data-role="<?php echo htmlspecialchars($userRole); ?>" id="avatar-preview-container">
                        <img src="<?php echo htmlspecialchars($profileImageUrl); ?>" 
                             alt="<?php echo htmlspecialchars(__('header.avatarAlt', ['username' => $usernameForAlt])); ?>"
                             class="settings-avatar-image"
                             id="avatar-preview-image">
                        
                        <div class="settings-avatar-overlay">
                            <span class="material-symbols-rounded">photo_camera</span>
                        </div>
                        </div>
                    <div class="settings-text-content">
                        <h2 class="settings-text-title"><?php echo __('settings.profile.avatar.label'); ?></h2>
                        <p class="settings-text-description"><?php echo __('settings.profile.avatar.description'); ?></p>
                    </div>
                </div>
                
                <div class="settings-card-right">
                    
                    <div class="settings-card-right-actions" id="avatar-actions-default" <?php echo $isDefaultAvatar ? '' : 'style="display: none;"'; ?>>
                        <button type="button" class="settings-button" id="avatar-upload-trigger"><?php echo __('settings.profile.avatar.buttonUpload'); ?></button>
                    </div>

                    <div class="settings-card-right-actions" id="avatar-actions-custom" <?php echo !$isDefaultAvatar ? '' : 'style="display: none;"'; ?>>
                        <button type="button" class="settings-button danger" id="avatar-remove-trigger"><?php echo __('settings.profile.avatar.buttonRemove'); ?></button>
                        <button type="button" class="settings-button" id="avatar-change-trigger"><?php echo __('settings.profile.avatar.buttonChange'); ?></button>
                    </div>

                    <div class="settings-card-right-actions" id="avatar-actions-preview" style="display: none;">
                        <button type="button" class="settings-button" id="avatar-cancel-trigger"><?php echo __('settings.button.cancel'); ?></button>
                        <button type="submit" class="settings-button" id="avatar-save-trigger"><?php echo __('settings.button.save'); ?></button>
                    </div>

                </div>
            </div>
        </form>

        <form id="username-form" onsubmit="event.preventDefault();" novalidate>
            <?php outputCsrfInput(); ?>
            <input type="hidden" name="action" value="update-username">
            
            <div class="settings-card">
                
                <div class="settings-card-left" id="username-view-state" style="display: flex;">
                    <div class="settings-text-content">
                        <h2 class="settings-text-title"><?php echo __('settings.profile.username.label'); ?></h2>
                        <p class="settings-text-description" 
                           id="username-display-text" 
                           data-original-username="<?php echo htmlspecialchars($usernameForAlt); ?>">
                           <?php echo htmlspecialchars($usernameForAlt); ?>
                        </p>
                    </div>
                </div>
                <div class="settings-card-right" id="username-actions-view" style="display: flex;">
                    <button type="button" class="settings-button" id="username-edit-trigger"><?php echo __('settings.button.edit'); ?></button>
                </div>

                <div class="settings-card-left" id="username-edit-state" style="display: none;">
                    <div class="settings-text-content" style="width: 100%;">
                        <h2 class="settings-text-title"><?php echo __('settings.profile.username.label'); ?></h2>
                        <input type="text" 
                               class="settings-username-input" 
                               id="username-input" 
                               name="username" 
                               value="<?php echo htmlspecialchars($usernameForAlt); ?>"
                               required
                               minlength="6"
                               maxlength="32">
                        </div>
                </div>
                <div class="settings-card-right-actions" id="username-actions-edit" style="display: none;">
                    <button type="button" class="settings-button" id="username-cancel-trigger"><?php echo __('settings.button.cancel'); ?></button>
                    <button type="submit" class="settings-button" id="username-save-trigger"><?php echo __('settings.button.save'); ?></button>
                </div>

            </div>
        </form>

        <form id="email-form" onsubmit="event.preventDefault();" novalidate>
            <?php outputCsrfInput(); ?>
            <input type="hidden" name="action" value="update-email">
            
            <div class="settings-card">
                
                <div class="settings-card-left" id="email-view-state" style="display: flex;">
                    <div class="settings-text-content">
                        <h2 class="settings-text-title"><?php echo __('settings.profile.email.label'); ?></h2>
                        <p class="settings-text-description" 
                           id="email-display-text" 
                           data-original-email="<?php echo htmlspecialchars($userEmail); ?>">
                           <?php echo htmlspecialchars($userEmail); ?>
                        </p>
                    </div>
                </div>
                <div class="settings-card-right" id="email-actions-view" style="display: flex;">
                    <button type="button" class="settings-button" id="email-edit-trigger"><?php echo __('settings.button.edit'); ?></button>
                </div>

                <div class="settings-card-left" id="email-edit-state" style="display: none;">
                    <div class="settings-text-content" style="width: 100%;">
                        <h2 class="settings-text-title"><?php echo __('settings.profile.email.label'); ?></h2>
                        <input type="email" 
                               class="settings-username-input" 
                               id="email-input" 
                               name="email" 
                               value="<?php echo htmlspecialchars($userEmail); ?>"
                               required
                               maxlength="255">
                        </div>
                </div>
                <div class="settings-card-right-actions" id="email-actions-edit" style="display: none;">
                    <button type="button" class="settings-button" id="email-cancel-trigger"><?php echo __('settings.button.cancel'); ?></button>
                    <button type="submit" class="settings-button" id="email-save-trigger"><?php echo __('settings.button.save'); ?></button>
                </div>

            </div>
        </form>
        
        <div class="settings-modal-overlay" id="email-verify-modal" style="display: none;">
            
            <button type="button" class="settings-modal-close-btn" id="email-verify-close">
                <span class="material-symbols-rounded">close</span>
            </button>

            <div class="settings-modal-content">
                <h2 class="auth-title" style="margin-bottom: 16px;"><?php echo __('settings.email.modal.title'); ?></h2>
                
                <p class="auth-verification-text" style="margin-bottom: 24px;">
                    <?php echo __('settings.email.modal.message'); ?>
                    <strong id="email-verify-modal-email"><?php echo htmlspecialchars($userEmail); ?></strong>.
                </p>

                <div class="auth-error-message" id="email-verify-error" style="display: none; margin-bottom: 16px;"></div>

                <form onsubmit="event.preventDefault();" novalidate>
                    
                    <div class="auth-input-group">
                        <input type="text" id="email-verify-code" name="verification_code" required placeholder=" " maxlength="14">
                        <label for="email-verify-code"><?php echo __('auth.form.verificationCode.label'); ?>*</label>
                    </div>

                    <div class="auth-step-buttons">
                        <button type="button" class="auth-button" id="email-verify-continue"><?php echo __('auth.form.button.continue'); ?></button>
                    </div>
                    </form>

                <div class="settings-modal-footer">
                    <p>
                        <?php echo __('settings.email.modal.noCode'); ?>
                        <a id="email-verify-resend"><?php echo __('settings.email.modal.resendLink'); ?></a>
                    </p>
                </div>

            </div>
        </div>

        <div class="settings-card settings-card-trigger-column">
            <div class="settings-card-left">
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.profile.usage.label'); ?></h2>
                    <p class="settings-text-description">
                        <?php echo __('settings.profile.usage.description'); ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                
                <div class="trigger-select-wrapper">
                    
                    <div class="trigger-selector" 
                         data-action="toggleModuleUsageSelect">
                        
                        <div class="trigger-select-icon">
                            <span class="material-symbols-rounded">person</span>
                        </div>
                        <div class="trigger-select-text">
                            <span><?php echo htmlspecialchars($currentUsageText); ?></span> 
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="module-content module-trigger-select body-title disabled" 
                         data-module="moduleUsageSelect"
                         data-preference-type="usage">
                        
                        <div class="menu-content">
                            <div class="menu-list">

                                <?php 
                                // --- ▼▼▼ INICIO DE MODIFICACIÓN DEL BUCLE ▼▼▼ ---
                                foreach ($usageMap as $key => $text): 
                                    $isActive = ($key === $userUsageType); 
                                    $iconName = $usageIconMap[$key] ?? 'person'; // Icono por defecto
                                ?>
                                    <div class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
                                         data-value="<?php echo htmlspecialchars($key); ?>">
                                        
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded"><?php echo $iconName; ?></span>
                                        </div>
                                        <div class="menu-link-text">
                                            <span><?php echo htmlspecialchars($text); ?></span>
                                        </div>
                                        <div class="menu-link-check-icon">
                                            <?php if ($isActive): ?>
                                                <span class="material-symbols-rounded">check</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; 
                                // --- ▲▲▲ FIN DE MODIFICACIÓN DEL BUCLE ▲▲▲ ---
                                ?>
                                
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div class="settings-card settings-card-trigger-column">
            <div class="settings-card-left">
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.profile.language.label'); ?></h2>
                    <p class="settings-text-description">
                        <?php echo __('settings.profile.language.description'); ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                
                <div class="trigger-select-wrapper">
                    
                    <div class="trigger-selector" 
                         data-action="toggleModuleLanguageSelect">
                        
                        <div class="trigger-select-icon">
                            <span class="material-symbols-rounded">language</span>
                        </div>
                        <div class="trigger-select-text">
                            <span><?php echo htmlspecialchars($currentLanguageText); ?></span>
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="module-content module-trigger-select body-title disabled" 
                         data-module="moduleLanguageSelect"
                         data-preference-type="language">
                        
                        <div class="menu-content">
                            <div class="menu-list">

                                <?php 
                                // --- ▼▼▼ INICIO DE MODIFICACIÓN DEL BUCLE ▼▼▼ ---
                                foreach ($languageMap as $key => $text): 
                                    $isActive = ($key === $userLanguage); 
                                ?>
                                    <div class="menu-link <?php echo $isActive ? 'active' : ''; ?>" 
                                         data-value="<?php echo htmlspecialchars($key); ?>">
                                        
                                        <div class="menu-link-icon">
                                            <span class="material-symbols-rounded">language</span>
                                        </div>
                                        <div class="menu-link-text">
                                            <span><?php echo htmlspecialchars($text); ?></span>
                                        </div>
                                        <div class="menu-link-check-icon">
                                            <?php if ($isActive): ?>
                                                <span class="material-symbols-rounded">check</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; 
                                // --- ▲▲▲ FIN DE MODIFICACIÓN DEL BUCLE ▲▲▲ ---
                                ?>
                                
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        
        <div class="settings-card settings-card-align-bottom">
            <div class="settings-card-left">
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.profile.openLinks.label'); ?></h2>
                    <p class="settings-text-description">
                        <?php echo __('settings.profile.openLinks.description'); ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                
                <label class="settings-toggle-switch">
                    <input type="checkbox" 
                           id="toggle-new-tab"
                           data-preference-type="boolean"
                           data-field-name="open_links_in_new_tab"
                           <?php echo ($openLinksInNewTab == 1) ? 'checked' : ''; ?>> 
                    <span class="settings-toggle-slider"></span>
                </label>

            </div>
        </div>
        </div>
</div>