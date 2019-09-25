<?php

namespace SearchEngine;

/**
 * SearchEngine Indexer
 *
 * @version 0.4.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Indexer extends Base {

    /**
     * Meta prefix used for storing (and later identifying) non-field-values
     *
     * @var string
     */
    const META_PREFIX = '_meta.';

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
            $index_field = $this->wire('fields')->get($this->getOptions()['index_field']);
            $indexed_templates = $index_field->getTemplates()->implode('|', 'name');
            if (!empty($indexed_templates)) {
                $selector = implode(',', [
                    'template=' . $indexed_templates,
                    'include=all',
                    'parent!=' . $this->wire('config')->trashPageID,
                ]);
            }
        }
        if (!empty($selector)) {
            foreach ($this->wire('pages')->findMany($selector) as $page) {
                if ($this->indexPage($page)) {
                    ++$indexed_pages;
                }
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
        $options = $this->getOptions();
        $index_field = $options['index_field'];
        if ($page->id && $page->hasField($index_field)) {
            $index = $this->getPageIndex($page, $options['indexed_fields']);
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
     * @param array $args Additional arguments.
     * @return array
     */
    protected function ___getPageIndex(\ProcessWire\Page $page, array $indexed_fields = [], string $prefix = '', array $args = []): array {
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
                    } else if ($field->type instanceof \ProcessWire\FieldtypePage) {
                        $indexed_page_reference_fields = [
                            'name',
                            'title',
                        ];
                        $index += $this->getPageReferenceIndexValue($page, $field, $indexed_page_reference_fields, $prefix);
                    } else {
                        $index[$prefix . $field->name] = $this->getIndexValue($page, $field);
                    }
                }
            }
            // Check for Page properties, which are not included in the fields property of a Page.
            if (in_array('name', $indexed_fields)) {
                $name_prefix = $args['name_prefix'] ?? '';
                $index[self::META_PREFIX . $prefix . 'name'] = $name_prefix . $this->getIndexValue($page, 'name');
            }
        }
        return $index;
    }

    /**
     * Get the index value for a single page field
     *
     * @param \ProcessWire\Page $page
     * @param \ProcessWire\Field|string $field Field object or name of a field (string).
     * @return mixed
     */
    protected function ___getIndexValue(\ProcessWire\Page $page, $field) {
        $value = '';
        $field_name = $field;
        if ($field instanceof \ProcessWire\Field) {
            if ($field->type instanceof \ProcessWire\FieldtypeFile) {
                $value = $page->getUnformatted($field->name)->implode(' ', function($item) {
                    return implode(' ', array_filter([
                        $item->name,
                        $item->description,
                    ]));
                });
            } else {
                $field_name = $field->name;
            }
        }
        $value = $page->getFormatted($field_name);
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
            $index += $this->getPageIndex($child, $indexed_fields, $prefix . $field->name . '.' . $child->name . '.');
        }
        return $index;
    }

    /**
     * Get index value for a Page Reference field
     *
     * @param \ProcessWire\Page $page
     * @param \ProcessWire\Field $field
     * @param array $indexed_fields
     * @param string $prefix
     * @return array
     */
    protected function ___getPageReferenceIndexValue(\ProcessWire\Page $page, \ProcessWire\Field $field, array $indexed_fields = [], string $prefix = ''): array {
        $index = [];
        $page_ref = $page->getUnformatted($field->name);
        if ($page_ref instanceof \ProcessWire\PageArray && $page_ref->count()) {
            $name_prefix = $field->name . ':';
            foreach ($page_ref as $page_ref_page) {
                $index += $this->getPageIndex($page_ref_page, $indexed_fields, $prefix . $field->name . '.' . $page_ref_page->name . '.', [
                    'name_prefix' => $name_prefix,
                ]);
            }
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
            $meta_index = [];
            foreach ($index as $index_key => $index_value) {
                // Identify and capture values belonging to the meta index (non-field values).
                if (strpos($index_key, self::META_PREFIX) === 0) {
                    $meta_key = substr($index_key, strlen(self::META_PREFIX));
                    $meta_index[$meta_key] = $index_value;
                    unset($index[$index_key]);
                }
            }
            $processed_index = implode(' ', $index);
            $url_index = $this->getURLIndex($processed_index);
            if (!empty($url_index)) {
                $meta_index['urls'] = $url_index;
            }
            $processed_index = strip_tags($processed_index);
            $processed_index = preg_replace('/\s+/', ' ', $processed_index);
            if (!empty($meta_index)) {
                $processed_index .= json_encode($meta_index, JSON_UNESCAPED_UNICODE);
            }
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
            $link_prefix = $this->getOptions()['prefixes']['link'];
            $index = $link_prefix . implode(' ' . $link_prefix, $matches[2]);
        }
        return $index;
    }

}
