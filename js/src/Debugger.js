/**
 * SearchEngine JS Debugger
 *
 * @version 0.5.1
 */
class PWSE_Debugger {

    /**
     * Constructor method
     */
    constructor() {

        // find debug containers
        const debugContainers = document.querySelectorAll('.pwse-debug');

        if (debugContainers.length) {

            // define base url for debug requests
            this.configURL = ProcessWire.config.urls.admin + 'module/edit?name=SearchEngine';
            this.debugURL = this.configURL + '&se-debug=1&';

            debugContainers.forEach(debugContainer => {

                // add debug button
                const debugButton = this.makeButton(debugContainer.getAttribute('data-debug-button-label'), debugContainer, true);
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

                    // update debug button
                    debugButton.text.innerText = debugContainer.getAttribute('data-refresh-button-label');
                    debugButton.icon.setAttribute('class', 'fa fa-refresh');
                    debugButton.button.removeAttribute('disabled');
                    debugButton.button.setAttribute('class', 'ui-button ui-state-default');

                    // update and highlight debug container
                    debugContainer.innerHTML = data;
                    debugContainer.setAttribute('style', 'margin-top: 2rem');
                    this.highlight(debugContainer);

                    // find collapsed sections
                    this.findCollapsed(debugContainer);

                    // init tabs
                    window.pwse.tabs.init(debugContainer);
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
        const reindexButton = this.makeButton(debugContainer.getAttribute('data-reindex-button-label'), debugContainer, true);

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
                    this.findCollapsed(debugContainer);
                    window.pwse.tabs.init(debugContainer);
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

        // get debug query args
        const debugQueryArgs = document.getElementById('Inputfield_debugger_query_args');

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
            fetch(
                this.debugURL
                + 'se-debug-query=' + encodeURIComponent(debugQuery)
                + '&se-debug-query-args=' + encodeURIComponent(JSON.stringify(JSON.parse(debugQueryArgs.value)))
            )
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
                    window.pwse.tabs.init(debugContainer);
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
     * @param {string} label Label for the button
     * @param {object} [parent] Parent DOM node
     * @param {boolean} [sticky] Sticky button?
     * @return {object} Button
     */
    makeButton(label, parent, sticky) {
        const button = {
            button: document.createElement('button'),
            text: document.createElement('span'),
            icon: document.createElement('i'),
        };
        button.button.setAttribute('class', 'ui-button ui-state-disabled');
        button.button.setAttribute('disabled', 'disabled');
        if (typeof sticky !== 'undefined' && sticky !== false) {
            button.button.setAttribute('style', 'position: sticky; top: 1rem; z-index: 1');
        }
        if (typeof parent !== 'undefined') {
            parent.parentNode.insertBefore(button.button, parent);
        }
        button.text.innerText = label;
        button.button.appendChild(button.text);
        button.icon.setAttribute('class', 'fa fa-refresh');
        button.icon.setAttribute('style', 'margin-left: .5rem');
        button.button.appendChild(button.icon);
        return button;
    }

    /**
     * Find collapsed elements and add show more/less buttons
     *
     * @param {object} parent Parent DOM node
     */
    findCollapsed(parent) {
        const maxHeight = 400;
        const collapsedSections = parent.querySelectorAll('.pwse-collapse');
        if (collapsedSections.length) {
            collapsedSections.forEach(collapsedSection => {
                collapsedSection.style.maxHeight = maxHeight + 'px';
                if (collapsedSection.tagName === 'TEXTAREA') {
                    collapsedSection.style.height = (collapsedSection.scrollHeight + 2) + 'px';
                }
                collapsedSection.style.overflowY = 'auto';
                if ((collapsedSection.scrollHeight + 2) > maxHeight) {
                    const collapseButton = this.makeButton(parent.getAttribute('data-show-more-button-label'));
                    collapseButton.button.setAttribute('class', 'ui-button ui-state-default');
                    collapseButton.icon.setAttribute('class', 'fa fa-chevron-down');
                    collapseButton.button.addEventListener('click', e => {
                        e.preventDefault();
                        if (collapsedSection.style.maxHeight) {
                            collapsedSection.style.maxHeight = null;
                            collapseButton.text.innerText = parent.getAttribute('data-show-less-button-label');
                            collapseButton.icon.setAttribute('class', 'fa fa-chevron-up');
                        } else {
                            collapsedSection.style.maxHeight = maxHeight + 'px';
                            collapseButton.text.innerText = parent.getAttribute('data-show-more-button-label');
                            collapseButton.icon.setAttribute('class', 'fa fa-chevron-down');
                        }
                        collapseButton.button.removeAttribute('disabled');
                        collapseButton.button.setAttribute('class', 'ui-button ui-state-default');
                        this.highlight(collapsedSection);
                    });
                    collapseButton.button.removeAttribute('disabled');
                    collapsedSection.parentNode.insertBefore(collapseButton.button, collapsedSection.nextSibling);
                }
            });
        }
    }

}

document.addEventListener("pwse_init", function() {
    window.pwse.debugger = new PWSE_Debugger();
});
