/**
 * SearchEngine JS Debugger
 *
 * @version 0.3.3
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
                const debugButton = this.makeButton(debugContainer, debugContainer.getAttribute('data-debug-button-label'));
                debugButton.icon.setAttribute('class', 'fa fa-bug');

                switch (debugContainer.getAttribute('data-type')) {
                    case 'index':
                        this.initIndex(debugContainer, debugButton)
                        break;
                    case 'page':
                        this.initPage(debugContainer, debugButton);
                        break;
                    case 'query':
                        this.initQuery(debugContainer, debugButton);
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
     * @param {object} debugButton
     */
    initIndex(debugContainer, debugButton) {

        // enable debug button
        debugButton.button.removeAttribute('disabled');
        debugButton.button.setAttribute('class', 'ui-button ui-state-default');

        // listen to debug button click event
        debugButton.button.addEventListener("click", e => {
            e.preventDefault();
            debugButton.button.setAttribute('disabled', 'disabled');
            debugButton.button.setAttribute('class', 'ui-button ui-state-disabled');
            debugButton.icon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-debug-index=1')
                .then(response => response.text())
                .then(data => {
                    debugButton.text.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    debugButton.icon.setAttribute('class', 'fa fa-refresh');
                    debugButton.button.removeAttribute('disabled');
                    debugButton.button.setAttribute('class', 'ui-button ui-state-default');
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
     * @param {object} debugButton
     */
    initPage(debugContainer, debugButton) {

        // add "reindex" button
        const reindexButton = this.makeButton(debugContainer, debugContainer.getAttribute('data-reindex-button-label'));

        // get debug page ID
        let debugPageID = parseInt(debugContainer.getAttribute('data-page-id'));
        if (debugPageID) {
            debugButton.button.removeAttribute('disabled');
            debugButton.button.setAttribute('class', 'ui-button ui-state-default');
            reindexButton.button.removeAttribute('disabled');
            reindexButton.button.setAttribute('class', 'ui-button ui-state-default');
        }

        // listen to page select event
        const debugPageInput = document.getElementById('Inputfield_debugger_page');
        debugPageInput.previousSibling.addEventListener("click", e => {
            if (!e.target.parentNode.classList.contains('PageListActionSelect')) return;
            let debugPageItem = e.target.closest('.PageListItem');
            debugPageID = debugPageItem.getAttribute('class').match(/(?!PageList)([0-9])+/)[0];
            debugContainer.innerHTML = '';
            debugContainer.setAttribute('style', 'margin-top: 0');
            debugButton.text.innerText = debugContainer.getAttribute('data-debug-button-label');
            debugButton.icon.setAttribute('class', 'fa fa-bug');
            if (debugPageID && (debugPageInput.value != debugPageID)) {
                debugButton.button.removeAttribute('disabled');
                debugButton.button.setAttribute('class', 'ui-button ui-state-default');
                reindexButton.button.removeAttribute('disabled');
                reindexButton.button.setAttribute('class', 'ui-button ui-state-default');
            } else {
                debugButton.button.setAttribute('disabled', 'disabled')
                debugButton.button.setAttribute('class', 'ui-button ui-state-disabled');
                reindexButton.button.setAttribute('disabled', 'disabled')
                reindexButton.button.setAttribute('class', 'ui-button ui-state-disabled');
            }
        }, true);

        // data queue
        const dataQueue = [];

        // listen to debug button click event
        debugButton.button.addEventListener("click", e => {
            e.preventDefault();
            debugButton.button.setAttribute('disabled', 'disabled');
            debugButton.button.setAttribute('class', 'ui-button ui-state-disabled');
            debugButton.icon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-debug-page-id=' + debugPageID)
                .then(response => response.text())
                .then(data => {
                    debugButton.text.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    debugButton.icon.setAttribute('class', 'fa fa-refresh');
                    debugButton.button.removeAttribute('disabled');
                    debugButton.button.setAttribute('class', 'ui-button ui-state-default');
                    debugContainer.innerHTML = data;
                    if (dataQueue.length) {
                        debugContainer.innerHTML = dataQueue.pop() + debugContainer.innerHTML;
                    }
                    const queueData = debugContainer.queueData
                    debugContainer.setAttribute('style', 'margin-top: 2rem');
                    this.highlight(debugContainer);
                });
        });

        // listen to reindex button click event
        reindexButton.button.addEventListener("click", e => {
            e.preventDefault();
            reindexButton.button.setAttribute('disabled', 'disabled');
            reindexButton.button.setAttribute('class', 'ui-button ui-state-disabled');
            reindexButton.icon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-reindex-page-id=' + debugPageID)
                .then(response => response.text())
                .then(data => {
                    reindexButton.icon.classList.remove('fa-spin');
                    reindexButton.button.removeAttribute('disabled');
                    reindexButton.button.setAttribute('class', 'ui-button ui-state-default');
                    dataQueue.push(data);
                    debugButton.button.click();
                });
        });
    }

    /**
     * Init query debug container
     *
     * @param {object} debugContainer
     * @param {object} debugButton
     */
    initQuery(debugContainer, debugButton) {

        // get debug query
        let debugQuery = debugContainer.getAttribute('data-query');
        if (debugQuery) {
            debugButton.button.removeAttribute('disabled');
            debugButton.button.setAttribute('class', 'ui-button ui-state-default');
        }

        // listen to keyup event
        document.getElementById('Inputfield_debugger_query').addEventListener("keyup", function(e) {
            if (e.key == 'Enter') {
                e.preventDefault();
            }
            debugQuery = e.target.value;
            if (debugQuery) {
                debugButton.button.removeAttribute('disabled');
                debugButton.button.setAttribute('class', 'ui-button ui-state-default');
                debugButton.text
                    .innerText = debugContainer.getAttribute('data-' + (debugQuery == prevQuery ? 'refresh' : 'debug') + '-button-label');
                if (e.key == 'Enter') {
                    debugButton.button.click();
                }
            } else {
                debugButton.button.setAttribute('disabled', 'disabled');
                debugButton.button.setAttribute('class', 'ui-button ui-state-disabled');
                debugButton.text
                    .innerText = debugContainer.getAttribute('data-debug-button-label');
            }
        });

        // listen to debug button click event
        let prevQuery = debugQuery;
        debugButton.button.addEventListener("click", e => {
            e.preventDefault();
            debugButton.button.setAttribute('disabled', 'disabled');
            debugButton.button.setAttribute('class', 'ui-button ui-state-disabled');
            debugButton.icon.classList.add('fa-spin');
            fetch(this.debugURL + 'se-debug-query=' + encodeURIComponent(debugQuery))
                .then(response => response.text())
                .then(data => {
                    prevQuery = debugQuery;
                    debugButton.text.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    debugButton.icon.setAttribute('class', 'fa fa-refresh');
                    debugButton.button.removeAttribute('disabled');
                    debugButton.button.setAttribute('class', 'ui-button ui-state-default');
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

    /**
     * Create new button
     *
     * @param {object} parent Parent DOM node
     * @param {string} label Label for the button
     * @return {object} Button
     */
    makeButton(parent, label) {
        const button = {
            button: document.createElement('button'),
            text: document.createElement('span'),
            icon: document.createElement('i'),
        };
        button.button.setAttribute('class', 'ui-button ui-state-disabled');
        button.button.setAttribute('disabled', 'disabled');
        button.button.setAttribute('style', 'position: sticky; top: 1rem; z-index: 1');
        parent.parentNode.insertBefore(button.button, parent);
        button.text.innerText = label;
        button.button.appendChild(button.text);
        button.icon.setAttribute('class', 'fa fa-refresh');
        button.icon.setAttribute('style', 'margin-left: .5rem');
        button.button.appendChild(button.icon);
        return button;
    }

}

document.addEventListener("SearchEngineConstructed", function() {
    window.SearchEngine.Debugger = new PWSE_Debugger();
});
