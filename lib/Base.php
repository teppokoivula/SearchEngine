<?php

namespace SearchEngine;

/**
 * SearchEngien Base class
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Base extends \ProcessWire\Wire {

    /**
     * Options array from the SearchEngine module
     *
     * @var array
     */
    protected $options = [];

    /**
     * Constructor method
     *
     * This method gets the options array from parent class and stores it in local property.
     */
    public function __construct() {
        parent::__construct();
        $this->options = $this->wire('modules')->get('SearchEngine')->options;
    }

    /**
     * Merge default string values with provided custom strings
     *
     * @param array|null $strings Optional custom/override strings.
     * @return array Array of strings.
     */
    protected function getStrings(array $strings = null) {
        $strings = empty($strings) ? $this->options['render_args']['strings'] : array_replace_recursive(
            $this->options['render_args']['strings'],
            $strings
        );
        foreach ($this->wire('modules')->get('SearchEngine')->getDefaultStrings() as $string => $value) {
            if (is_null($strings[$string])) {
                $strings[$string] = $value;
            }
        }
        return $strings;
    }

}
