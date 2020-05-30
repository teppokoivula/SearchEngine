/**
 * SearchEngine JS Debugger
 *
 * @version 0.2.0
 */
class PWSE_Debugger {

    /**
     * Constructor method
     */
    constructor() {

        // find debug containers
        const debugContainers = document.querySelectorAll('.search-engine-debug');

        if (debugContainers.length) {

            // define base url for debug requests
            this.debugURL = SearchEngine.configURL + '&se-debug=1&';

            debugContainers.forEach((debugContainer) => {

                // add debug button
                const button = document.createElement('button');
                button.setAttribute('class', 'ui-button ui-state-disabled');
                button.setAttribute('disabled', 'disabled');
                debugContainer.parentNode.insertBefore(button, debugContainer);
                const buttonText = document.createElement('span');
                buttonText.innerText = debugContainer.getAttribute('data-debug-button-label');
                button.appendChild(buttonText);
                const buttonIcon = document.createElement('i');
                buttonIcon.setAttribute('class', 'fa fa-bug');
                buttonIcon.setAttribute('style', 'margin-left: .5rem');
                button.appendChild(buttonIcon);

                switch (debugContainer.getAttribute('data-type')) {
                    case 'index':
                        this.initIndex(debugContainer, button, buttonText, buttonIcon)
                        break;
                    case 'page':
                        this.initPage(debugContainer, button, buttonText, buttonIcon);
                        break;
                    case 'query':
                        this.initQuery(debugContainer, button, buttonText, buttonIcon);
                        break;
                    default:
                        console.error('Unidentified debug container type (' + debugContainer.getAttribute('data-type') + ')');
                }
            });
        }
    }

    /**
     * Init index debug container
     *
     * @param {object} debugContainer
     * @param {object} button
     * @param {object} buttonText
     * @param {object} buttonIcon
     */
    initIndex(debugContainer, button, buttonText, buttonIcon) {

        // enable debug button
        button.removeAttribute('disabled');
        button.setAttribute('class', 'ui-button ui-state-default');

        // listen to button click event
        button.addEventListener("click", e => {
            e.preventDefault();
            button.setAttribute('disabled', 'disabled');
            button.setAttribute('class', 'ui-button ui-state-disabled');
            buttonIcon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-debug-index=1')
                .then(response => response.text())
                .then(data => {
                    buttonText.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    buttonIcon.setAttribute('class', 'fa fa-refresh');
                    button.removeAttribute('disabled');
                    button.setAttribute('class', 'ui-button ui-state-default');
                    debugContainer.innerHTML = data;
                    debugContainer.setAttribute('style', 'margin-top: 2rem');
                    this.highlight(debugContainer);
                });
        });
    }

    /**
     * Init page debug container
     *
     * @param {object} debugContainer
     * @param {object} button
     * @param {object} buttonText
     * @param {object} buttonIcon
     */
    initPage(debugContainer, button, buttonText, buttonIcon) {

        // get debug page ID
        let debugPageID = parseInt(debugContainer.getAttribute('data-page-id'));
        if (debugPageID) {
            button.removeAttribute('disabled');
            button.setAttribute('class', 'ui-button ui-state-default');
        }

        // listen to page select event
        const debugPageInput = document.getElementById('Inputfield_debugger_page');
        debugPageInput.previousSibling.addEventListener("click", e => {
            if (!e.target.parentNode.classList.contains('PageListActionSelect')) return;
            let debugPageItem = e.target.closest('.PageListItem');
            debugPageID = debugPageItem.getAttribute('class').match(/(?!PageList)([0-9])+/)[0];
            debugContainer.innerHTML = '';
            debugContainer.setAttribute('style', 'margin-top: 0');
            buttonText.innerText = debugContainer.getAttribute('data-debug-button-label');
            buttonIcon.setAttribute('class', 'fa fa-bug');
            if (debugPageID && (debugPageInput.value != debugPageID)) {
                button.removeAttribute('disabled');
                button.setAttribute('class', 'ui-button ui-state-default');
            } else {
                button.setAttribute('disabled', 'disabled')
                button.setAttribute('class', 'ui-button ui-state-disabled');
            }
        }, true);

        // listen to button click event
        button.addEventListener("click", e => {
            e.preventDefault();
            button.setAttribute('disabled', 'disabled');
            button.setAttribute('class', 'ui-button ui-state-disabled');
            buttonIcon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-debug-page-id=' + debugPageID)
                .then(response => response.text())
                .then(data => {
                    buttonText.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    buttonIcon.setAttribute('class', 'fa fa-refresh');
                    button.removeAttribute('disabled');
                    button.setAttribute('class', 'ui-button ui-state-default');
                    debugContainer.innerHTML = data;
                    debugContainer.setAttribute('style', 'margin-top: 2rem');
                    this.highlight(debugContainer);
                });
        });
    }

    /**
     * Init query debug container
     *
     * @param {object} debugContainer
     * @param {object} button
     * @param {object} buttonText
     * @param {object} buttonIcon
     */
    initQuery(debugContainer, button, buttonText, buttonIcon) {

        // get debug query
        let debugQuery = debugContainer.getAttribute('data-query');
        if (debugQuery) {
            button.removeAttribute('disabled');
            button.setAttribute('class', 'ui-button ui-state-default');
        }

        // listen to keyup event
        document.getElementById('Inputfield_debugger_query').addEventListener("keyup", function(e) {
            if (e.key == 'Enter') {
                e.preventDefault();
            }
            debugQuery = e.target.value;
            if (debugQuery) {
                button.removeAttribute('disabled');
                button.setAttribute('class', 'ui-button ui-state-default');
                buttonText
                    .innerText = debugContainer.getAttribute('data-' + (debugQuery == prevQuery ? 'refresh' : 'debug') + '-button-label');
                if (e.key == 'Enter') {
                    button.click();
                }
            } else {
                button.setAttribute('disabled', 'disabled');
                button.setAttribute('class', 'ui-button ui-state-disabled');
                buttonText
                    .innerText = debugContainer.getAttribute('data-debug-button-label');
            }
        });

        // listen to button click event
        let prevQuery = debugQuery;
        button.addEventListener("click", e => {
            e.preventDefault();
            button.setAttribute('disabled', 'disabled');
            button.setAttribute('class', 'ui-button ui-state-disabled');
            buttonIcon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-debug-query=' + encodeURIComponent(debugQuery))
                .then(response => response.text())
                .then(data => {
                    prevQuery = debugQuery;
                    buttonText.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    buttonIcon.setAttribute('class', 'fa fa-refresh');
                    button.removeAttribute('disabled');
                    button.setAttribute('class', 'ui-button ui-state-default');
                    debugContainer.innerHTML = data;
                    debugContainer.setAttribute('style', 'margin-top: 2rem');
                    this.highlight(debugContainer);
                });
        });
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
    window.SearchEngine.Debugger = new PWSE_Debugger();
});
