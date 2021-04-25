<?php

namespace SearchEngine;

/**
 * SearchEngine Base class
 *
 * @version 0.3.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class Base extends \ProcessWire\Wire {

    /**
     * Get options from the SearchEngine module
     *
     * @return array Options array.
     */
    public function getOptions(): array {
        return $this->wire('modules')->get('SearchEngine')->options;
    }

    /**
     * Merge default string values with provided custom strings
     *
     * @param array|null $strings Optional custom/override strings.
     * @return array Array of strings.
     */
    protected function getStrings(array $strings = null) {
        $options = $this->getOptions();
        $strings = empty($strings) ? $options['render_args']['strings'] : array_replace_recursive(
            $options['render_args']['strings'],
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
