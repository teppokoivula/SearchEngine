<?php

namespace SearchEngine;

/**
 * SearchEngine Indexer
 *
 * @version 0.6.0
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
            if ($this->wire('modules')->isInstalled('LanguageSupport') && $this->wire('fields')->get($index_field)->type == 'FieldtypeTextareaLanguage') {
                foreach ($this->wire('languages') as $language) {
                    $index = $this->getPageIndex($page, $options['indexed_fields'], '', [
                        'language' => $language,
                    ]);
                    $index = $this->processIndex($index);
                    $page->get($index_field)->setLanguageValue($language, $index);
                }
                if ($save) {
                    $of = $page->of();
                    $page->of(false);
                    $page->save($index_field, [
                        'quiet' => true,
                        'noHooks' => true,
                    ]);
                    $page->of($of);
                }
            } else {
                $index = $this->getPageIndex($page, $options['indexed_fields'], '');
                $index = $this->processIndex($index);
                if ($save) {
                    $page->setAndSave($index_field, $index, [
                        'quiet' => true,
                        'noHooks' => true,
                    ]);
                } else {
                    $page->set($index_field, $index);
                }
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
        $user_language = null;
        if (!empty($args['language'])) {
            // Change current user's language to the one we're currently processing
            $user_language = $this->wire('user')->language;
            $this->wire('user')->language = $args['language'];
        }
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
                            'id',
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
            $properties = [
                'ids' => 'id',
                'names' => 'name',
            ];
            foreach ($properties as $property_key => $property) {
                if (in_array($property, $indexed_fields)) {
                    $property_prefix = $args[$property . '_prefix'] ?? '';
                    $index[self::META_PREFIX . $property_key . '.' . rtrim($prefix, '.')] = $property_prefix . $this->getIndexValue($page, $property);
                }
            }
        }
        if (!empty($user_language)) {
            // Restore current user's original language
            $this->wire('user')->language = $user_language;
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
                return $page->getUnformatted($field->name)->implode(' ', function($item) {
                    return implode(' ', array_filter([
                        $item->name,
                        $item->description,
                    ]));
                });
            } else if ($field->type instanceof \ProcessWire\FieldtypeTable) {
                return $this->sanitizer->unentities(strip_tags(str_replace('</td><td>', ' ', $page->getFormatted($field->name)->render([
                    'tableClass' => null,
                    'useWidth' => false,
                    'thead' => ' ',
                ]))));
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
        $index_num = 0;
        foreach ($page->get($field->name) as $child) {
            // Note: union operator is slightly faster than array_merge() and makes sense
            // here since we're working with associative arrays only.
            $index += $this->getPageIndex($child, $indexed_fields, $prefix . $field->name . '.' . $index_num . '.');
            ++$index_num;
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
            $index_num = 0;
            $prefixes = $this->getOptions()['prefixes'];
            $args = [
                'id_prefix' => str_replace('{field.name}', $field->name, $prefixes['id']),
                'name_prefix' => str_replace('{field.name}', $field->name, $prefixes['name']),
            ];
            foreach ($page_ref as $page_ref_page) {
                $index += $this->getPageIndex($page_ref_page, $indexed_fields, $prefix . $field->name . '.' . $index_num . '.', $args);
                ++$index_num;
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
                    if (substr_count($meta_key, '.') > 1) {
                        // Note: index is always a flat assoc array, but a key with multiple dots
                        // means that the value needs to be stored as a multi-dimensional array.
                        list($meta_parent, $meta_child, $meta_name) = explode('.', $meta_key);
                        if (empty($meta_index[$meta_parent])) {
                            $meta_index[$meta_parent] = [];
                        }
                        if (empty($meta_index[$meta_parent][$meta_child])) {
                            $meta_index[$meta_parent][$meta_child] = [];
                        }
                        $meta_index[$meta_parent][$meta_child] += [$meta_name => $index_value];
                    } else {
                        $meta_index[$meta_key] = $index_value;
                    }
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
            $processed_index .= "\n" . (empty($meta_index) ? '{}' : json_encode($meta_index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        return $processed_index;
    }

    /**
     * Create an index of URLs
     *
     * Find URLs from field data and return them as an array. This allows us to search for links
     * with "link:https://URL" syntax (link prefix is configurable but defaults to "link:").
     *
     * @param string $data
     * @return array
     */
    protected function getURLIndex(string $data): array {
        $index = [];
        if (!empty($data) && preg_match_all('/href=([\"\'])(.*?)\1/i', $data, $matches)) {
            $link_prefix = $this->getOptions()['prefixes']['link'];
            $index[] = $link_prefix . implode(' ' . $link_prefix, $matches[2]);
        }
        return $index;
    }

}
