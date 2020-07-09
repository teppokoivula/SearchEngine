/**
 * SearchEngine JS Config
 *
 * @version 0.1.0
 */
class PWSE_Config {

    /**
     * Constructor method
     */
    constructor() {

        // toggle operator instructions
        const operatorInstructions = document.getElementById('search-engine-operator-instructions');
        if (operatorInstructions) {
            operatorInstructions.setAttribute('hidden', '');
            const operatorInstructionsToggle = document.createElement('button');
            operatorInstructionsToggle.classList.add('ui-button', 'ui-state-default');
            operatorInstructionsToggle.innerHTML = operatorInstructions.getAttribute('data-toggle-label');
            operatorInstructionsToggle.innerHTML += ' <i class="fa fa-question-circle"></i>';
            operatorInstructionsToggle.addEventListener('click', e => {
                e.preventDefault();
                operatorInstructions.toggleAttribute('hidden');
                if (!operatorInstructions.hasAttribute('hidden')) {
                    this.highlight(operatorInstructions);
                }
                if (typeof window.InputfieldColumnWidths === 'function') {
                    window.InputfieldColumnWidths();
                }
            });
            operatorInstructions.parentNode.prepend(operatorInstructionsToggle);
            if (typeof window.InputfieldColumnWidths === 'function') {
                window.InputfieldColumnWidths();
            }
        }
    }

    /**
     * Highlight (blink/flash) a DOM node
     *
     * @param {object} node DOM node
     */
    highlight(node) {
        node.style.transition = 'all .25s ease-in-out';
        node.style.backgroundColor = 'lightyellow';
        setTimeout(() => {
            node.style.backgroundColor = null;
            setTimeout(() => {
                node.style.transition = null;
            }, 250);
        }, 1000);
    }
}

document.addEventListener("SearchEngineConstructed", function() {
    window.SearchEngine.Tabs = new PWSE_Config();
});
