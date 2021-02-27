/**
 * SearchEngine JS Core
 *
 * @version 0.1.1
 */
class PWSE_Core {

    /**
     * Constructor
     */
    constructor() {}

}

document.addEventListener("DOMContentLoaded", function() {
    window.SearchEngine = new PWSE_Core();
    document.dispatchEvent(new CustomEvent('SearchEngineConstructed'));
});
