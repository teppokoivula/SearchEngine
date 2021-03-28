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

        // toggle operator details
        const operatorDetails = document.getElementById('pwse-operator-details');
        if (operatorDetails) {
            operatorDetails.setAttribute('hidden', '');
            const operatorDetailsToggle = document.createElement('button');
            operatorDetailsToggle.classList.add('ui-button', 'ui-state-default');
            operatorDetailsToggle.innerHTML = operatorDetails.getAttribute('data-toggle-label');
            operatorDetailsToggle.innerHTML += ' <i class="fa fa-question-circle"></i>';
            operatorDetailsToggle.addEventListener('click', e => {
                e.preventDefault();
                operatorDetails.toggleAttribute('hidden');
                if (typeof window.InputfieldColumnWidths === 'function') {
                    window.InputfieldColumnWidths();
                }
            });
            operatorDetails.parentNode.prepend(operatorDetailsToggle);
            if (typeof window.InputfieldColumnWidths === 'function') {
                window.InputfieldColumnWidths();
            }

            // operator select
            const operatorSelect = document.getElementById('Inputfield_find_args__operator');
            if (operatorSelect) {
                operatorSelect.addEventListener('change', e => {
                    this.setOperator(e.target.value);
                });
            }

            // operator buttons
            const operatorButtons = document.querySelectorAll('.pwse-operator-details__button');
            if (operatorButtons.length) {
                operatorButtons.forEach(button => {
                    button.addEventListener('click', e => {
                        e.preventDefault();
                        this.setOperator(e.target.getAttribute('data-operator'));
                    });
                });
            }
        }
    }

    /**
     * Set operator
     *
     * @param {String} operator
     */
    setOperator(operator) {
        const activeOperator = document.querySelector('.pwse-operator-details__list-item--active');
        if (activeOperator) {
            activeOperator.classList.remove('pwse-operator-details__list-item--active');
        }
        const operatorButton = document.querySelector('button[data-operator="' + operator + '"]');
        operatorButton.closest('.pwse-operator-details__list-item').classList.add('pwse-operator-details__list-item--active');
        document.getElementById('Inputfield_find_args__operator').value = operator;
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

document.addEventListener("pwse_init", function() {
    window.pwse.config = new PWSE_Config();
});
