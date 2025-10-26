<?php
// --- ▼▼▼ INICIO DE NUEVO BLOQUE PHP ▼▼▼ ---

// $userTheme y $increaseMessageDuration son cargados por config/router.php

$themeMap = [
    'system' => __('settings.accessibility.theme.system'),
    'light' => __('settings.accessibility.theme.light'),
    'dark' => __('settings.accessibility.theme.dark')
];

// --- ¡NUEVO MAPA DE ICONOS AÑADIDO! ---
$themeIconMap = [
    'system' => 'desktop_windows',
    'light' => 'light_mode',
    'dark' => 'dark_mode'
];

$currentThemeText = $themeMap[$userTheme] ?? $themeMap['system'];

// --- ▲▲▲ FIN DE NUEVO BLOQUE PHP ▲▲▲ ---
?>
<div class="section-content <?php echo ($CURRENT_SECTION === 'settings-accessibility') ? 'active' : 'disabled'; ?>" data-section="settings-accessibility">
    <div class="settings-wrapper">
        
        <div class="settings-header-card">
            <h1 class="settings-title"><?php echo __('settings.accessibility.title'); ?></h1>
            <p class="settings-description">
                <?php echo __('settings.accessibility.description'); ?>
            </p>
        </div>

        <div class="settings-card settings-card-trigger-column">
            <div class="settings-card-left">
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.accessibility.theme.label'); ?></h2>
                    <p class="settings-text-description">
                        <?php echo __('settings.accessibility.theme.description'); ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                
                <div class="trigger-select-wrapper">
                    
                    <div class="trigger-selector" 
                         data-action="toggleModuleThemeSelect">
                        
                        <div class="trigger-select-icon">
                            <span class="material-symbols-rounded">brightness_medium</span>
                        </div>
                        <div class="trigger-select-text">
                            <span><?php echo htmlspecialchars($currentThemeText); ?></span>
                        </div>
                        <div class="trigger-select-arrow">
                            <span class="material-symbols-rounded">arrow_drop_down</span>
                        </div>
                    </div>

                    <div class="module-content module-trigger-select body-title disabled" 
                         data-module="moduleThemeSelect"
                         data-preference-type="theme">
                        
                        <div class="menu-content">
                            <div class="menu-list">

                                <?php 
                                // --- ▼▼▼ INICIO DE MODIFICACIÓN DEL BUCLE ▼▼▼ ---
                                foreach ($themeMap as $key => $text): 
                                    $isActive = ($key === $userTheme); 
                                    $iconName = $themeIconMap[$key] ?? 'desktop_windows'; // Icono por defecto
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
        
        <div class="settings-card settings-card-align-bottom">
            <div class="settings-card-left">
                <div class="settings-text-content">
                    <h2 class="settings-text-title"><?php echo __('settings.accessibility.messageDuration.label'); ?></h2>
                    <p class="settings-text-description">
                        <?php echo __('settings.accessibility.messageDuration.description'); ?>
                    </p>
                </div>
            </div>

            <div class="settings-card-right">
                
                <label class="settings-toggle-switch">
                    <input type="checkbox" 
                           id="toggle-message-duration"
                           data-preference-type="boolean"
                           data-field-name="increase_message_duration"
                           <?php echo ($increaseMessageDuration == 1) ? 'checked' : ''; ?>> 
                    <span class="settings-toggle-slider"></span>
                </label>

            </div>
        </div>
        </div>
</div>