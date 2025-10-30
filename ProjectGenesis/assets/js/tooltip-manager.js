// assets/js/tooltip-manager.js

// Importar Popper.js desde un CDN
import { createPopper } from 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/esm/popper.min.js';

// Importar nuestro gestor de traducciones
import { getTranslation } from './i18n-manager.js';

let tooltipEl;
let popperInstance;

/**
 * Crea una NUEVA instancia del elemento tooltip.
 * No lo añade al body.
 */
function createTooltipElementInstance() {
    const newTooltipEl = document.createElement('div');
    newTooltipEl.id = 'main-tooltip'; // El ID puede causar conflictos si se muestran varios a la vez, pero para este sistema funciona.
    newTooltipEl.className = 'tooltip';
    newTooltipEl.setAttribute('role', 'tooltip');
    
    const textEl = document.createElement('div');
    textEl.className = 'tooltip-text';
    newTooltipEl.appendChild(textEl);
    
    return newTooltipEl;
}

/**
 * Muestra el tooltip para un elemento específico.
 * @param {HTMLElement} target - El elemento que activa el tooltip.
 */
function showTooltip(target) {
    const tooltipKey = target.getAttribute('data-tooltip');
    if (!tooltipKey) return;

    // --- ▼▼▼ INICIO DE MODIFICACIÓN ▼▼▼ ---
    // 1. Crear el elemento
    tooltipEl = createTooltipElementInstance();
    
    // 2. Añadirlo al body
    document.body.appendChild(tooltipEl);
    // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---

    const text = getTranslation(tooltipKey);
    tooltipEl.querySelector('.tooltip-text').textContent = text;
    tooltipEl.style.display = 'block';

    popperInstance = createPopper(target, tooltipEl, {
       placement: 'bottom',
       placement: 'auto',
        modifiers: [
            {
                name: 'offset',
                options: {
                    offset: [0, 8], // 8px de espacio
                },
            },
        ],
    });
}

/**
 * Oculta el tooltip y destruye la instancia de Popper.
 */
function hideTooltip() {
    
    // --- ▼▼▼ INICIO DE MODIFICACIÓN ▼▼▼ ---
    // 1. Destruir la instancia de Popper
    if (popperInstance) {
        popperInstance.destroy();
        popperInstance = null;
    }
    
    // 2. Eliminar el elemento del DOM
    if (tooltipEl && tooltipEl.parentNode) {
        tooltipEl.parentNode.removeChild(tooltipEl);
        tooltipEl = null; // Limpiar la referencia
    }
    // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---
}

/**
 * Inicializa los listeners para los tooltips.
 */
export function initTooltipManager() {
    
    // --- ▼▼▼ INICIO DE MODIFICACIÓN ▼▼▼ ---
    // Ya NO creamos el elemento al iniciar
    // createTooltipElement();
    // --- ▲▲▲ FIN DE MODIFICACIÓN ▲▲▲ ---

    document.body.addEventListener('mouseover', (e) => {
        const target = e.target.closest('[data-tooltip]');
        if (!target) return;

        // Mostrar instantáneamente
        showTooltip(target);
    });

    document.body.addEventListener('mouseout', (e) => {
        const target = e.target.closest('[data-tooltip]');
        if (!target) return;
        
        hideTooltip();
    });
    
    // Ocultar si se hace clic en cualquier lugar (para botones de menú)
    document.body.addEventListener('click', () => {
         hideTooltip();
    });
}