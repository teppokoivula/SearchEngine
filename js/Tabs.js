/**
 * SearchEngine JS Tabs
 *
 * Based on https://inclusive-components.design/tabbed-interfaces/.
 *
 * @version 0.1.0
 */
class PWSE_Tabs {

    /**
     * Init method
     *
     * @param {?object} debugContainer Optional debug container.
     */
    init(debugContainer = null) {

        // find tab containers
        let tabContainers = [];
        if (debugContainer) {
            tabContainers = debugContainer.querySelectorAll('.pwse-debug-tabs');
        } else {
            tabContainers = document.querySelectorAll('.pwse-debug-tabs');
        }
        if (!tabContainers.length) return;

        tabContainers.forEach((tabContainer) => {

            // get relevant elements and collections
            const tablist = tabContainer.querySelector('ul');
            const tabs = tablist.querySelectorAll('a');
            const panels = tabContainer.querySelectorAll('[id^="pwse-debug-tab-"]');

            // the tab switching function
            const switchTab = (oldTab, newTab) => {
                newTab.focus();
                // Make the active tab focusable by the user (Tab key)
                newTab.removeAttribute('tabindex');
                // Set the selected state
                newTab.setAttribute('aria-selected', 'true');
                oldTab.removeAttribute('aria-selected');
                oldTab.setAttribute('tabindex', '-1');
                // Get the indices of the new and old tabs to find the correct
                // tab panels to show and hide
                console.log(tabs, panels, newTab, oldTab);
                let index = Array.prototype.indexOf.call(tabs, newTab);
                let oldIndex = Array.prototype.indexOf.call(tabs, oldTab);
                panels[oldIndex].hidden = true;
                panels[index].hidden = false;
            }

            // add the tablist role to the first <ul> in the tab container
            tablist.setAttribute('role', 'tablist');

            // add semantics are remove user focusability for each tab
            Array.prototype.forEach.call(tabs, (tab, i) => {
                console.log(tabContainer);
                tab.setAttribute('role', 'tab');
                tab.setAttribute('id', tabContainer.getAttribute('id') + '-' + (i + 1));
                tab.setAttribute('tabindex', '-1');
                tab.parentNode.setAttribute('role', 'presentation');

                // handle clicking of tabs for mouse users
                tab.addEventListener('click', e => {
                    e.preventDefault();
                    let currentTab = tablist.querySelector('[aria-selected]');
                    if (e.currentTarget !== currentTab) {
                        switchTab(currentTab, e.currentTarget);
                    }
                });

                // handle keydown events for keyboard users
                tab.addEventListener('keydown', e => {
                    // get the index of the current tab in the tabs node list
                    let index = Array.prototype.indexOf.call(tabs, e.currentTarget);
                    // work out which key the user is pressing and
                    // calculate the new tab's index where appropriate
                    let dir = e.which === 37 ? index - 1 : e.which === 39 ? index + 1 : e.which === 40 ? 'down' : null;
                    if (dir !== null) {
                        e.preventDefault();
                        // if the down key is pressed, move focus to the open panel,
                        // otherwise switch to the adjacent tab
                        dir === 'down' ? panels[i].focus() : tabs[dir] ? switchTab(e.currentTarget, tabs[dir]) : void 0;
                    }
                });
            });

            // add tab panel semantics and hide them all
            Array.prototype.forEach.call(panels, (panel, i) => {
                panel.setAttribute('role', 'tabpanel');
                panel.setAttribute('tabindex', '-1');
                let id = panel.getAttribute('id');
                panel.setAttribute('aria-labelledby', tabs[i].id);
                panel.hidden = true;
            });

            // initially activate the first tab and reveal the first tab panel
            tabs[0].removeAttribute('tabindex');
            tabs[0].setAttribute('aria-selected', 'true');
            panels[0].hidden = false;
        })
    }

}

document.addEventListener("SearchEngineConstructed", function() {
    window.SearchEngine.Tabs = new PWSE_Tabs();
});
