<?php

namespace SearchEngine;

/**
 * SearchEngine Indexer
 *
 * @version 0.16.1
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
     * Processor instance
     *
     * @var Processor
     */
    protected $processor;

    /**
     * IndexerActions instance
     *
     * @var Actions
     */
    protected $actions;

    /**
     * Constructor method
     */
    public function __construct() {
        parent::__construct();
        $this->processor = new Processor;
        $this->actions = new IndexerActions;
    }

    /**
     * Index multiple pages
     *
     * By default this method will index all pages with the search index field. If an optional
     * selector is provided, pages matching that will be indexed instead.
     *
     * @param string $selector Selector string to match pages against.
     * @param bool $save Boolean that defines whether the index field value should be saved or just set.
     * @param array $args Additional arguments.
     * @return int The number of indexed pages.
     */
    public function indexPages(string $selector = null, bool $save = true, array $args = []) {
        $indexed_pages = 0;
        $return = isset($args['return']) && $args['return'] == 'index' ? 'index' : 'status';
        $index = [];
        if (empty($selector)) {
            $indexed_templates = implode('|', $this->getOptions()['indexed_templates']);
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
                if ($return == 'index') {
                    $index[$page->id] = $this->indexPage($page, $save, $args);
                } else if ($this->indexPage($page, $save, $args)) {
                    ++$indexed_pages;
                }
            }
        }
        return $return == 'status' ? $indexed_pages : $index;
    }

    /**
     * Index a single page
     *
     * @param \ProcessWire\Page $page Page to be indexed.
     * @param bool $save Boolean that defines whether the index field value should be saved or just set.
     * @param array $args Additional arguments.
     * @return bool|array Index as an array if return arg is 'index', otherwise true on success or false on failure.
     */
    public function indexPage(\ProcessWire\Page $page, bool $save = true, array $args = []) {
        $options = $this->getOptions();
        $return = isset($args['return']) && $args['return'] == 'index' ? 'index' : 'status';
        $index = [];
        $index_field = $options['index_field'];
        $index_field_exists = $page->hasField($index_field);
        if (!$index_field_exists) {
            $save = false;
        }
        if ($page->id && ($return == 'index' || $index_field_exists)) {
            $this->actions->prepareFor('indexPage');
            if ($this->wire('modules')->isInstalled('LanguageSupport') && $this->wire('fields')->get($index_field)->type == 'FieldtypeTextareaLanguage') {
                foreach ($this->wire('languages') as $language) {
                    $index[$language->id] = $this->getPageIndex($page, $options['indexed_fields'], '', [
                        'language' => $language,
                    ]);
                    $index[$language->id] = $this->processor->processIndex($index[$language->id]);
                    if ($index_field_exists) {
                        $page->getUnformatted($index_field)->setLanguageValue($language, $index[$language->id]);
                    }
                }
            } else {
                $index[0] = $this->getPageIndex($page, $options['indexed_fields'], '');
                $index[0] = $this->processor->processIndex($index[0]);
                if ($index_field_exists) {
                    $page->set($index_field, $index[0]);
                }
            }
            if ($save) {
                $of = $page->of();
                $page->of(false);
                $this->wire('pages')->___saveField($page, $index_field, [
                    'quiet' => true,
                    'noHooks' => true,
                ]);
                $page->of($of);
                $this->wire('modules')->get('SearchEngine')->savedPageIndex($page);
            }
            return $return == 'status' ? true : $index;
        }
        return $return == 'status' ? false : $index;
    }

    /**
     * Get index for a single page
     *
     * @param \ProcessWire\Page|null $page
     * @param array $indexed_fields
     * @param string $prefix
     * @param array $args Additional arguments.
     * @return array
     */
    protected function ___getPageIndex(?\ProcessWire\Page $page, array $indexed_fields = [], string $prefix = '', array $args = []): array {

        // This is a precaution (enhancing fault tolerance) in case a null value somehow gets passed here; reportedly
        // this has occurred with some Repeater/RepeaterMatrix + FieldtypePage combinations.
        if (is_null($page)) return [];

        // Change current user's language to the one we're currently processing
        $user_language = null;
        if (!empty($args['language'])) {
            $user_language = $this->wire('user')->language;
            $this->wire('user')->language = $args['language'];
        }

        // Create index for provided page
        $index = [];
        if ($page->id && !empty($indexed_fields)) {

            // Index Page fields defined as indexable via module config
            foreach ($page->fields as $field) {
                if ($this->isIndexedField($field, [
                    'indexed_fields' => $indexed_fields,
                    'object' => $page,
                ])) {
                    $field_index = $this->getFieldIndex($field, $page, $indexed_fields, $prefix, $args);
                    if (empty($field_index)) continue;
                    $index += $field_index;
                }
            }

            // Index Page properties defined indexable via module config. Note that these properties are not included
            // in the "fields" property of the Page object, so we're checking for them separately.
            $properties = [
                'ids' => 'id',
                'names' => 'name',
            ];
            $prefixes = $this->getOptions()['prefixes'];
            foreach ($properties as $property_group => $property) {
                if ($this->isIndexedField($property, [
                    'indexed_fields' => $indexed_fields,
                    'object' => $page,
                ])) {
                    $field_prefix = empty($prefix) ? 'page' : rtrim($prefix, '.');
                    $property_prefix = $prefixes[$property] ?? '';
                    if (!isset($args[$property . '_prefix']) && !empty($prefixes[$property]) && $prefixes[$property] != ':') {
                        $property_prefix = str_replace('{field.name}', $field_prefix, $prefixes[$property]);
                    } else if ($field_prefix === 'page') {
                        $property_prefix = $property . ':';
                    }
                    $property_prefix = (ctype_alnum($property_prefix[0] ?? '') ? '.' : '') . $property_prefix;
                    $index[self::META_PREFIX . $property_group . '.' . $field_prefix] = $property_prefix . $this->getIndexValue($page, $property)->getValue();
                }
            }
        }

        // Restore current user's original language
        if (!empty($user_language)) {
            $this->wire('user')->language = $user_language;
        }

        return $index;
    }

    /**
     * Get index for a single field
     *
     * @param \ProcessWire\Field $field
     * @param \ProcessWire\WireData $object
     * @param array $indexed_fields
     * @param string $prefix
     * @param array $args
     * @return array
     */
    protected function ___getFieldIndex(\ProcessWire\Field $field, \ProcessWire\WireData $object, array $indexed_fields = [], string $prefix = '', array $args = []): array {
        $index = [];
        if ($this->isRepeatableField($field)) {
            $index = $this->getRepeatableIndexValue($object, $field, $indexed_fields, $prefix);
        } else if ($field->type->className() == 'FieldtypeFieldsetPage') {
            $index = $this->getPageIndex(
                $this->getUnformattedFieldValue($object, $field->name),
                $indexed_fields,
                $prefix . $field->name . '.',
                $args
            );
        } else if ($field->type instanceof \ProcessWire\FieldtypePage) {
            // Note: unlike with FieldtypeFieldsetPage above, here we want to check for both FieldtypePage
            // AND any class that might potentially extend it, which is why we're using instanceof.
            $index = $this->getPageReferenceIndexValue($object, $field, [
                'id',
                'name',
                'title',
            ], $prefix);
        } else {
            $index_value = $this->getIndexValue($object, $field, $indexed_fields);
            $index[$prefix . $field->name] = $index_value->getValue();
            foreach ($index_value->getMeta(true) as $meta_key => $meta_value) {
                $meta_value = explode(':', $meta_value);
                $index[self::META_PREFIX . $meta_key . '.' . $field->name . '.' . array_shift($meta_value) . ':'] = implode(':', $meta_value);
            }
        }
        return array_filter($index);
    }

    /**
     * Get the index value for a single field
     *
     * @param \ProcessWire\WireData $object
     * @param \ProcessWire\Field|string $field Field object or name of a field (string).
     * @param array $indexed_fields
     * @return IndexValue
     */
    protected function ___getIndexValue(\ProcessWire\WireData $object, $field, array $indexed_fields = []): IndexValue {
        $field_name = $field;
        if ($field instanceof \ProcessWire\Field) {
            $field_name = $field->name;
            if ($field->type instanceof \ProcessWire\FieldtypeFile) {
                $value = [];
                $files = [];
                $field_value = $this->getUnformattedFieldValue($object, $field->name);
                if ($field_value instanceof \ProcessWire\Pagefile) {
                    $field_value = $this->wire(new \ProcessWire\Pagefiles($object))->add($field_value);
                }
                if ($field_value instanceof \ProcessWire\WireArray) {
                    foreach ($field_value as $item) {
                        $value[] = $this->getPagefileIndexValue($item, $indexed_fields);
                        $files[] = [
                            'file.name:' . $item->name,
                            'file.meta:' . $item->hash . '|' . $item->modified,
                        ];
                    }
                }
                return (new IndexValue(implode(' ', $value)))->setMeta([
                    'files' => $files,
                ]);
            } else if ($field->type instanceof \ProcessWire\FieldtypeTable) {
                $field_value = $this->getFormattedFieldValue($object, $field->name);
                return new IndexValue(is_object($field_value) ? $this->sanitizer->unentities(strip_tags(str_replace('</td><td>', ' ', $field_value->render([
                    'tableClass' => null,
                    'useWidth' => false,
                    'thead' => ' ',
                ])))) : '');
            } else if ($field->type instanceof \ProcessWire\FieldtypeTextareas) {
                $field_value = $this->getFormattedFieldValue($object, $field->name);
                return new IndexValue(is_object($field_value) ? $field_value->render('') : '');
            } else if ($field->type instanceof \ProcessWire\FieldtypeCombo) {
                $field_value = $object->get($field->name);
                return new IndexValue(is_object($field_value) ? $this->processor->processIndex(array_values($object->get($field->name)->getArray()), [
                    'withMeta' => false,
                    'withTags' => true,
                ]) : '');
            } else if ($field->type instanceof \ProcessWire\FieldtypeOptions) {
                $field_value = $this->getFormattedFieldValue($object, $field->name);
                return new IndexValue(is_object($field_value) ? $field_value->render() : '');
            }
        }
        return new IndexValue($this->getFormattedFieldValue($object, $field_name));
    }

    /**
     * Get index value for a repeatable page field
     *
     * Index value for a repeatable field is a combination of index values for each individual item.
     *
     * @param \ProcessWire\Page $page
     * @param \ProcessWire\Field $field
     * @param array $indexed_fields
     * @param string $prefix
     * @return array
     */
    protected function ___getRepeatableIndexValue(\Processwire\Page $page, \ProcessWire\Field $field, array $indexed_fields = [], string $prefix = ''): array {
        $index = [];
        $children = $page->get($field->name);
        if ($children === null) return $index;
        $index_num = 0;
        $prefixes = $this->getOptions()['prefixes'];
        foreach ($children as $child) {
            if ($child->status >= \ProcessWire\Page::statusHidden) continue;
            $args = [
                'id_prefix' => str_replace('{field.name}', $field->name, $prefixes['id'] ?? ''),
                'name_prefix' => str_replace('{field.name}', $field->name, $prefixes['name'] ?? ''),
            ];
            // Note: union operator is slightly faster than array_merge() and makes sense here since we're working with
            // associative arrays only.
            $index += $this->getPageIndex($child, $indexed_fields, $prefix . $field->name . '.' . $index_num . '.', $args);
            ++$index_num;
        }
        return $index;
    }

    /**
     * Get index value for a Page Reference field
     *
     * @param \ProcessWire\WireData $object
     * @param \ProcessWire\Field $field
     * @param array $indexed_fields
     * @param string $prefix
     * @return array
     */
    protected function ___getPageReferenceIndexValue(\ProcessWire\WireData $object, \ProcessWire\Field $field, array $indexed_fields = [], string $prefix = ''): array {
        $index = [];
        $page_ref = $this->getUnformattedFieldValue($object, $field->name);
        if ($page_ref instanceof \ProcessWire\Page) {
            $page_ref = $this->wire(new \ProcessWire\PageArray())->add($page_ref);
        }
        if ($page_ref instanceof \ProcessWire\PageArray && $page_ref->count()) {
            $index_num = 0;
            $prefixes = $this->getOptions()['prefixes'];
            $args = [
                'id_prefix' => str_replace('{field.name}', $field->name, $prefixes['id'] ?? ''),
                'name_prefix' => str_replace('{field.name}', $field->name, $prefixes['name'] ?? ''),
            ];
            foreach ($page_ref as $page_ref_page) {
                $page_ref_prefix = $prefix . $field->name . '.';
                if ($field->get('derefAsPage') === \ProcessWire\FieldtypePage::derefAsPageArray) {
                    $page_ref_prefix .= $index_num . '.';
                }
                $index += $this->getPageIndex($page_ref_page, $indexed_fields, $page_ref_prefix, $args);
                ++$index_num;
            }
        }
        return $index;
    }

    /**
     * Get index value for a Pagefile
     *
     * @param \ProcessWire\Pagefile $file
     * @param array $indexed_fields
     * @return string|null
     */
    protected function ___getPagefileIndexValue(\ProcessWire\Pagefile $file, array $indexed_fields = []): ?string {
        $field_values = [];
        if (!empty($indexed_fields)) {
            $fields_template = $file->pagefiles->getFieldsTemplate();
            if ($fields_template) {
                foreach ($fields_template->fields as $field) {
                    if ($this->isIndexedField($field, [
                        'indexed_fields' => $indexed_fields,
                        'object' => $file,
                    ])) {
                        $field_index = $this->getFieldIndex($field, $file, $indexed_fields);
                        if (empty($field_index)) continue;
                        $field_values += $field_index;
                    }
                }
                if (!empty($field_values)) {
                    $field_values = $this->processor->processIndex($field_values, [
                        'withMeta' => false,
                        'withTags' => true,
                    ]);
                }
            }
        }
        return implode(' ', array_filter([
            'description' => $this->isIndexedField('description', [
                'object' => $file,
                'indexed_fields' => $indexed_fields,
            ]) ? $file->description : null,
            'tags' => $this->isIndexedField('tags', [
                'object' => $file,
                'indexed_fields' => $indexed_fields,
            ]) ? $file->tags : null,
            'field_values' => $field_values,
        ]));
    }

    /**
     * Get formatted field value from WireData derived object
     *
     * @param \ProcessWire\WireData $object
     * @param string $field_name
     * @return mixed
     */
    protected function getFormattedFieldValue(\ProcessWire\WireData $object, string $field_name) {
        if ($object instanceof \ProcessWire\Page) {
            return $object->getFormatted($field_name);
        }
        if ($object instanceof \ProcessWire\Field && method_exists($object, 'getFieldValue')) {
            return $object->getFieldValue($field_name, true);
        }
        return $object->get($field_name);
    }

    /**
     * Get unformatted field value from WireData derived object
     *
     * @param \ProcessWire\WireData $object
     * @param string $field_name
     * @return mixed
     */
    protected function getUnformattedFieldValue(\ProcessWire\WireData $object, string $field_name) {
        if ($object instanceof \ProcessWire\Page) {
            return $object->getUnformatted($field_name);
        }
        if ($object instanceof \ProcessWire\Field && method_exists($object, 'getFieldValue')) {
            return $object->getFieldValue($field_name, false);
        }
        return $object->get($field_name);
    }

    /**
     * Check if a field should be indexed
     *
     * @param \ProcessWire\Field|string $field
     * @param array $args Additional arguments:
     *  - indexed_fields: Fields selected for indexing via module config
     *  - object: object (e.g. Page or Pagefile) that this field belongs to
     * @return bool
     */
    protected function isIndexedField($field, array $args = []): bool {
        if (empty($args['indexed_fields'])) {
            return false;
        }
        $field_name = $field instanceof \ProcessWire\Field ? $field->name : $field;
        if (!empty($args['object']) && $args['object'] instanceof \ProcessWire\Pagefile) {
            if (in_array($args['object']->field . '.*', $args['indexed_fields'])) {
                return true;
            }
            $field_name = $args['object']->field . '.' . $field_name;
        }
        return in_array($field_name, $args['indexed_fields']);
    }

    /**
     * Check if a field is repeatable
     *
     * Repeatable fields contain Page objects (or objects with a class derived from the Page class) as their values.
     *
     * @param \ProcessWire\Field $field
     * @return bool
     */
    protected function isRepeatableField(\ProcessWire\Field $field): bool {
        return in_array($field->type, [
            'FieldtypePageTable',
            'FieldtypeRepeater',
            'FieldtypeRepeaterMatrix',
        ]);
    }

}
