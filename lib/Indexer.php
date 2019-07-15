<?php

namespace SearchEngine;

/**
 * SearchEngine Indexer
 *
 * @version 0.2.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Indexer extends Base {

    /**
     * Index multiple pages
     *
     * By default this method will index all pages with the search index field. If an optional
     * selector is provided, pages matching that will be indexed instead.
     *
     * @param string $selector Selector string to match pages against.
     * @return int The number of indexed pages.
     */
    public function indexPages(string $selector = null) {
        $indexed_pages = 0;
        if (empty($selector)) {
            $index_field = $this->wire('fields')->get($this->options['index_field']);
            $indexed_templates = $index_field->getTemplates()->implode('|', 'name');
            $selector = "template=" . $indexed_templates;
        }
        foreach ($this->wire('pages')->findMany($selector) as $page) {
            if ($this->indexPage($page)) {
                ++$indexed_pages;
            }
        }
        return $indexed_pages;
    }

    /**
     * Index a single page
     *
     * @param \ProcessWire\Page $page Page to be indexed.
     * @param bool $save Boolean that defines whether the index field value should be saved or just set.
     * @return bool True on success, false on failure.
     */
    public function indexPage(\ProcessWire\Page $page, bool $save = true) {
        $index_field = $this->options['index_field'];
        if ($page->id && $page->hasField($index_field)) {
            $index = $this->getPageIndex($page, $this->options['indexed_fields']);
            $index = $this->processIndex($index);
            if ($save) {
                $this->wire('pages')->save($page, $index_field, $index, [
                    'quiet' => true,
                ]);
            } else {
                $page->set($index_field, $index);
            }
            return true;
        }
        return false;
    }

    /**
     * Store page data in search index and return it as an array
     *
     * @param \ProcessWire\Page $page
     * @param array $indexed_fields
     * @param string $prefix
     * @return array
     */
    protected function ___getPageIndex(\ProcessWire\Page $page, array $indexed_fields = [], string $prefix = ''): array {
        $index = [];
        if ($page->id && !empty($indexed_fields)) {
            $repeatable_fieldtypes = [
                'FieldtypePageTable',
                'FieldtypeRepeater',
                'FieldtypeRepeaterMatrix',
            ];
            foreach ($page->fields as $field) {
                if (in_array($field->name, $indexed_fields)) {
                    if (in_array($field->type, $repeatable_fieldtypes)) {
                        // Note: union operator is slightly faster than array_merge() and makes sense
                        // here since we're working with associative arrays only.
                        $index += $this->getRepeatableIndexValue($page, $field, $indexed_fields, $prefix);
                    } else {
                        $index[$prefix . $field->name] = $this->getIndexValue($page, $field);
                    }
                }
            }
        }
        return $index;
    }

    /**
     * Get the index value for a single page field
     *
     * @param \ProcessWire\Page $page
     * @param \ProcessWire\Field $field
     * @return mixed
     */
    protected function ___getIndexValue(\ProcessWire\Page $page, \ProcessWire\Field $field) {
        $value = '';
        if ($field->type instanceof \ProcessWire\FieldtypeFile) {
            $value = $page->getUnformatted($field->name)->implode(' ', function($item) {
                return implode(' ', array_filter([
                    $item->name,
                    $item->description,
                ]));
            });
        } else {
            $value = $page->getFormatted($field->name);
        }
        return $value;
    }

    /**
     * Get index value for a repeatable page field
     *
     * @param \ProcessWire\Page $page
     * @param \ProcessWire\Field $field
     * @param array $indexed_fields
     * @param string $prefix
     * @return array
     */
    protected function ___getRepeatableIndexValue(\Processwire\Page $page, \ProcessWire\Field $field, array $indexed_fields = [], string $prefix = ''): array {
        $index = [];
        foreach ($page->get($field->name) as $child) {
            // Note: union operator is slightly faster than array_merge() and makes sense
            // here since we're working with associative arrays only.
            $index += $this->getPageIndex($child, $indexed_fields, $prefix . $child->name . '.');
        }
        return $index;
    }

    /**
     * Process index for storage
     *
     * This method converts the index array to a string, sanitizes it removing content we don't
     * want in the index (tags etc.) and appends an indx of links to the index string.
     *
     * @param array $index Index as an array.
     * @return string Processed index string.
     */
    protected function processIndex(array $index): string {
        $processed_index = '';
        if (!empty($index)) {
            $processed_index = implode(' ', $index);
            $processed_index .= ' ' . $this->getURLIndex($processed_index);
            $processed_index = strip_tags($processed_index);
            $processed_index = preg_replace('/\s+/', ' ', $processed_index);
        }
        return $processed_index;
    }

    /**
     * Create an index of URLs
     *
     * Grab URLs from field data, mash them together into a space separated string,
     * and return the resulting string. This allows us to search specificallly for
     * links by using "link:https://URL" syntax ("link:" prefix is configurable).
     *
     * @param string $data
     * @return string
     */
    protected function getURLIndex(string $data): string {
        $index = '';
        if (!empty($data) && preg_match_all('/href=([\"\'])(.*?)\1/i', $data, $matches)) {
            $link_prefix = $this->options['prefixes']['link'];
            $index = $link_prefix . implode(' ' . $link_prefix, $matches[2]);
        }
        return $index;
    }

}
