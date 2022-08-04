<?php

namespace SearchEngine;

/**
 * Index value object
 *
 * @version 0.1.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class IndexValue {

    /**
     * Index value
     *
     * @var string
     */
    protected $value = '';

    /**
     * Metadata
     *
     * @var array
     */
    protected $meta = [];

    /**
     * Constructor
     *
     * @param string|null $value
     */
    public function __construct(?string $value = '') {
        $this->value = $value ?? '';
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue(): string {
        return $this->value;
    }

    /**
     * Set value
     *
     * @param string $value
     * @return IndexValue Self-reference
     */
    public function setValue(string $value): IndexValue {
        $this->value = $value;
        return $this;
    }

    /**
     * Add metadata
     *
     * @param string $key
     * @param mixed $value
     * @return IndexValue Self-reference
     */
    public function addMeta(string $key, $value): IndexValue {
        $this->meta[$key] = $value;
        return $this;
    }

    /**
     * Set metadata
     *
     * @param array $value
     * @return IndexValue Self-reference
     */
    public function setMeta(array $value) {
        $this->meta = $value;
        return $this;
    }

    /**
     * Get metadata
     *
     * @param bool $flatten
     * @return array
     */
    public function getMeta(bool $flatten = false): array {
        return $flatten ? $this->flattenArray($this->meta): $this->meta;
    }

    /**
     * Flatten array
     *
     * This method converts multidimensional array to one-dimensional array for storage; ['group' => ['a', 'b', 'c']]`
     * becomes `['group.0' => 'a', 'group.1' => 'b', 'group.2' => 'c']` etc.
     *
     * @param array $array
     * @param string $prefix
     * @return array
     */
    protected function flattenArray(array $array, string $prefix = ''): array {
        $out = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $out += $this->flattenArray($value, $prefix . $key . '.');
                continue;
            }
            $out[$prefix . $key] = $value;
        }
        return $out;
    }

    /**
     * Get string value for this value object
     *
     * @return string
     */
    public function __toString(): string {
        return $this->value;
    }

}
