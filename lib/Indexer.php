<?php

namespace SearchEngine;

use ProcessWire\HookEvent;

/**
 * SearchEngine Indexer
 *
 * @version 0.12.0
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
     * Module specific extra actions
     *
     * @var array
     */
    protected $moduleExtras = [];

    /**
     * Local instance of FormBuilder module
     *
     * @var \ProcessWire\FormBuilder|null
     */
    protected $formBuilder = null;

    /**
     * Constructor method
     */
    public function __construct() {
        parent::__construct();
        $this->processor = new Processor;
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
            $this->initModuleExtras();
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
                $page->save($index_field, [
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
     * Store page data in search index and return it as an array
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
                    } else if ($field->type->className() == 'FieldtypeFieldsetPage') {
                        $index += $this->getPageIndex($page->getUnformatted($field->name), $indexed_fields, $prefix . $field->name . '.', $args);
                    } else if ($field->type instanceof \ProcessWire\FieldtypePage) {
                        // Note: unlike with FieldtypeFieldsetPage above, here we want to check for both FieldtypePage
                        // AND any class that might potentially extend it, which is why we're using instanceof.
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
            $prefixes = $this->getOptions()['prefixes'];
            foreach ($properties as $property_key => $property) {
                if (in_array($property, $indexed_fields)) {
                    $provided_prefix = empty($prefix) ? 'page' : rtrim($prefix, '.');
                    if (!isset($args[$property . '_prefix']) && !empty($prefixes[$property]) && $prefixes[$property] != ':') {
                        $args[$property . '_prefix'] = str_replace('{field.name}', $provided_prefix, $prefixes[$property]);
                    } else if (empty($prefix)) {
                        $args[$property . '_prefix'] = $property . ':';
                    }
                    $property_prefix = $args[$property . '_prefix'] ?? '';
                    $property_prefix = (ctype_alnum($property_prefix[0] ?? '') ? '.' : '') . $property_prefix;
                    $index[self::META_PREFIX . $property_key . '.' . $provided_prefix] = $property_prefix . $this->getIndexValue($page, $property);
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
                $field_value = $page->getUnformatted($field->name);
                if ($field_value instanceof \ProcessWire\WireArray) {
                    return $field_value->implode(' ', function($item) {
                        return implode(' ', array_filter([
                            $item->name,
                            $item->description,
                        ]));
                    });
                } else if ($field_value instanceof \ProcessWire\Pagefile) {
                    return implode(' ', array_filter([
                        $field_value->name,
                        $field_value->description,
                    ]));
                }
            } else if ($field->type instanceof \ProcessWire\FieldtypeTable) {
                return $this->sanitizer->unentities(strip_tags(str_replace('</td><td>', ' ', $page->getFormatted($field->name)->render([
                    'tableClass' => null,
                    'useWidth' => false,
                    'thead' => ' ',
                ]))));
            } else if ($field->type instanceof \ProcessWire\FieldtypeTextareas) {
                return $page->getFormatted($field->name)->render('');
            } else if ($field->type instanceof \ProcessWire\FieldtypeOptions) {
                return $page->getFormatted($field->name)->render();
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
            if ($child->status >= \ProcessWire\Page::statusHidden) continue;
            $prefixes = $this->getOptions()['prefixes'];
            $args = [
                'id_prefix' => str_replace('{field.name}', $field->name, $prefixes['id'] ?? ''),
                'name_prefix' => str_replace('{field.name}', $field->name, $prefixes['name'] ?? ''),
            ];
            $index += $this->getPageIndex($child, $indexed_fields, $prefix . $field->name . '.' . $index_num . '.', $args);
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
     * Init module extras
     */
    protected function initModuleExtras() {
        if (!isset($this->moduleExtras['FormBuilder']) && $this->wire('modules')->isInstalled('FormBuilder')) {
            $this->moduleExtras['FormBuilder'] = in_array('FormBuilder', $this->getOptions()['module_extras'] ?? []);
            if ($this->moduleExtras['FormBuilder']) {
                $this->addHookAfter('FieldtypeTextarea::formatValue', $this, 'embedFormBuilder');
            }
        }
    }

    /**
     * Embed FormBuilder forms
     *
     * This method looks for FormBuilder embed tag(s) within text content and attempts to replace them with rendered
     * markup of said form(s) in order to make actual form content searchable.
     *
     * @param HookEvent $event
     */
    protected function embedFormBuilder(HookEvent $event) {
        $field = $event->arguments[1];
        if ($this->formBuilder === null) {
            $this->formBuilder = $this->wire('modules')->get('FormBuilder');
        }
        if (!in_array($field->id, $this->formBuilder->embedFields) || strpos($event->return, ">" . $this->formBuilder->embedTag . "/") === false) {
            // field cannot be used for embedding forms or no embed codes detected, bail out early
            return;
        }
        if (preg_match_all('!<([^>]+)>' . $this->formBuilder->embedTag . '/([-_a-zA-z0-9]+)\s*</\\1>!', $event->return, $matches)) {
            foreach ($matches[0] as $key => $tag) {
                try {
                    $this->formBuilderRender = $this->formBuilder->render($matches[2][$key]);
                    if ($this->formBuilderRender instanceof \ProcessWire\FormBuilderRender) {
                        $event->return = str_replace($tag, $this->formBuilderRender->render(), $event->return);
                    }
                } catch (\Exception $e) {
                    // rendering the form failed, not much we can do about that now
                }
            }
        }
    }

}
