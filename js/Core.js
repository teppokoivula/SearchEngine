/**
 * SearchEngine JS Core
 *
 * @version 0.1.0
 */
class PWSE_Core {

    /**
     * Constructor
     */
    constructor() {
        this.configURL = ProcessWire.config.urls.admin + 'module/edit?name=SearchEngine';
    }

}

document.addEventListener("DOMContentLoaded", function() {
    window.SearchEngine = new PWSE_Core();
    document.dispatchEvent(new CustomEvent('SearchEngineConstructed'));
});
