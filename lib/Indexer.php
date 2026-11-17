<?php

namespace SearchEngine;

/**
 * SearchEngine Indexer
 *
 * @version 0.16.4
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
     * Meta key prefix for a structured record describing an indexed file
     *
     * The value after this marker is a JSON object. SearchEngineFileIndexer marks a Pagefile
     * before this record is written; pages with no indexed file omit it, and the index meta
     * `is_file` value stays null.
     *
     * @var string
     */
    const FILE_RECORD_MARKER = 'file.record:';

    /**
     * Separates page text from each indexed document's text inside the index body
     *
     * The page text is the first segment. Each following segment is one indexed file, in the
     * same order as the file records in the meta JSON. The separator is not used until the
     * index is rebuilt with this version of the module.
     *
     * @var string
     */
    const SOURCE_SEPARATOR = "\x1e";

    /**
     * Extracted text for indexed files on the page currently being indexed
     *
     * @var array
     */
    protected $indexedFileTexts = [];

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
    public function indexPages(?string $selector = null, bool $save = true, array $args = []) {
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
                    $this->indexedFileTexts = [];
                    $index[$language->id] = $this->getPageIndex($page, $options['indexed_fields'], '', [
                        'language' => $language,
                    ]);
                    $index[$language->id] = $this->appendIndexedFileTexts($this->processor->processIndex($index[$language->id]));
                    if ($index_field_exists) {
                        $page->getUnformatted($index_field)->setLanguageValue($language, $index[$language->id]);
                    }
                }
            } else {
                $this->indexedFileTexts = [];
                $index[0] = $this->getPageIndex($page, $options['indexed_fields'], '');
                $index[0] = $this->appendIndexedFileTexts($this->processor->processIndex($index[0]));
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
                        $file_meta = [
                            'file.name:' . $item->name,
                            'file.meta:' . $item->hash . '|' . $item->modified,
                        ];
                        // Set by SearchEngineFileIndexer when this file's content was indexed.
                        if ($item->get('_se_is_file')) {
                            $file_meta[] = self::FILE_RECORD_MARKER . json_encode(
                                $this->getIndexedFileRecord($item),
                                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                            );
                        }
                        $files[] = $file_meta;
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
     * Structured record stored for a file whose content was indexed
     *
     * `path` is the full public URL path (`/site/assets/files/...`). `url` is the absolute URL.
     * `page_*` refers to the viewable page the file belongs to (a repeater's owner page when
     * the file field lives on a repeater). `page_template` is that page's template name, and
     * `page_templates` is the template chain from the root down to that page. `stored_page_id`
     * is the page that actually owns the file field. `is_file` is 1 for every record written here.
     *
     * @param \ProcessWire\Pagefile $file
     * @return array
     */
    protected function ___getIndexedFileRecord(\ProcessWire\Pagefile $file): array {
        $stored = $file->page;
        $page = $stored;
        if ($stored && $stored->id && method_exists($stored, 'getForPage')) {
            $owner = $stored->getForPage();
            if ($owner instanceof \ProcessWire\Page && $owner->id) {
                $page = $owner;
            }
        }
        $field = $file->field;
        $this->indexedFileTexts[] = $this->normalizeIndexedText((string) $file->get('_se_file_text'));
        return [
            'is_file' => 1,
            'name' => $file->basename,
            'ext' => strtolower((string) $file->ext),
            'path' => (string) $file->url,
            'url' => (string) $file->httpUrl,
            'filesize' => (int) $file->filesize,
            'filesize_str' => (string) $file->filesizeStr,
            'description' => (string) $file->description,
            'field' => $field ? (string) $field->name : '',
            'page_id' => $page && $page->id ? (int) $page->id : null,
            'page_name' => $page && $page->id ? (string) $page->name : '',
            'page_url' => $page && $page->id ? (string) $page->url : '',
            'page_title' => $page && $page->id ? (string) $page->get('title') : '',
            'page_template' => $page && $page->id ? (string) $page->template : '',
            'page_template_label' => $page && $page->id && $page->template ? (string) $page->template->getLabel() : '',
            'page_templates' => $page && $page->id ? $this->pageTemplateHierarchy($page) : [],
            'stored_page_id' => $stored && $stored->id ? (int) $stored->id : null,
        ];
    }

    /**
     * Template chain for the page a file belongs to, from the root down to that page
     *
     * Template filters and groups match a page by its own template. A document result's
     * template is `file`, so this chain is what places that document in the same groups
     * as the page it was uploaded to. The last item is that page.
     *
     * @param \ProcessWire\Page $page
     * @return array<int, array{name: string, label: string, id: int|null}>
     */
    public function pageTemplateHierarchy(\ProcessWire\Page $page): array {
        $items = [];
        if (!$page->id) {
            return $items;
        }
        foreach ($page->parents() as $parent) {
            $items[] = $this->templateIdentity($parent);
        }
        $items[] = $this->templateIdentity($page);
        return $items;
    }

    /**
     * Name, label, and id for a page's template
     *
     * @param \ProcessWire\Page $page
     * @return array{name: string, label: string, id: int|null}
     */
    protected function templateIdentity(\ProcessWire\Page $page): array {
        $template = $page->template;
        return [
            'name' => $template ? (string) $template->name : '',
            'label' => $template ? (string) $template->getLabel() : '',
            'id' => $template && $template->id ? (int) $template->id : null,
        ];
    }

    /**
     * Append indexed document text after the page text and before the meta JSON
     *
     * @param string $index
     * @return string
     */
    protected function appendIndexedFileTexts(string $index): string {
        if (!$this->indexedFileTexts) {
            return $index;
        }
        $texts = implode(self::SOURCE_SEPARATOR, $this->indexedFileTexts);
        $pos = strrpos($index, "\n");
        if ($pos === false) {
            return $index . self::SOURCE_SEPARATOR . $texts;
        }
        return substr($index, 0, $pos) . self::SOURCE_SEPARATOR . $texts . substr($index, $pos);
    }

    /**
     * Normalize text the same way the page index does, so source checks match the stored words
     *
     * @param string $text
     * @return string
     */
    public function normalizeIndexedText(string $text): string {
        $text = str_replace('<', ' <', $text);
        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim((string) $text);
    }

    /**
     * Split a stored index into page text and one text body per indexed file
     *
     * `segmented` is false for indexes built before document text was stored separately.
     * Those indexes cannot tell a page hit from a file hit.
     *
     * @param string $index
     * @return array{page: string, files: array, segmented: bool}
     */
    public function readIndexSources(string $index): array {
        $meta_pos = strrpos($index, "\n");
        $body = $meta_pos === false ? $index : substr($index, 0, $meta_pos);
        $segmented = strpos($body, self::SOURCE_SEPARATOR) !== false;
        $parts = explode(self::SOURCE_SEPARATOR, $body);
        $page_text = (string) array_shift($parts);
        $files = $this->getIndexedFilesFromIndex($index);
        foreach ($files as $i => $file) {
            $files[$i]['text'] = $parts[$i] ?? '';
        }
        return [
            'page' => $page_text,
            'files' => $files,
            'segmented' => $segmented,
        ];
    }

    /**
     * Does $haystack satisfy the same operator the finder used against the whole index?
     *
     * @param string $haystack
     * @param string $query
     * @param string $operator
     * @return bool
     */
    public function sourceContains(string $haystack, string $query, string $operator): bool {
        $query = trim($query);
        if ($query === '' || $query === '""') {
            return false;
        }
        $haystack = mb_strtolower($haystack);
        $phrase = $query;
        $quoted = strlen($query) > 1 && $query[0] === '"' && substr($query, -1) === '"';
        if ($quoted) {
            $phrase = trim($query, '"');
        }
        $phrase = mb_strtolower($phrase);
        if ($phrase === '') {
            return false;
        }
        if ($operator === '=' || $operator === '==') {
            return $haystack === $phrase;
        }
        if ($operator === '^=' || $operator === '%^=') {
            return mb_strpos($haystack, $phrase) === 0;
        }
        if ($operator === '$=' || $operator === '%$=') {
            $length = mb_strlen($phrase);
            return $length > 0 && mb_substr($haystack, -$length) === $phrase;
        }
        if ($quoted || $operator === '%=' || $operator === '~%=') {
            return mb_strpos($haystack, $phrase) !== false;
        }
        foreach (preg_split('/\s+/u', $phrase, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            if (mb_strpos($haystack, mb_strtolower($word)) === false) {
                return false;
            }
        }
        return true;
    }

    /**
     * Read indexed-file records out of a stored index value
     *
     * Records are written into the index meta JSON by getIndexedFileRecord(). Pages that
     * have no indexed file return an empty array, which callers expose as is_file=null.
     *
     * @param string $index Stored index field value.
     * @return array
     */
    public function getIndexedFilesFromIndex(string $index): array {
        $meta = $this->getIndexMeta($index);
        if (empty($meta['files']) || !is_array($meta['files'])) {
            return [];
        }
        $files = [];
        $marker = self::FILE_RECORD_MARKER;
        $marker_length = strlen($marker);
        foreach ($meta['files'] as $entry) {
            if (!is_string($entry)) continue;
            $pos = strpos($entry, $marker);
            if ($pos === false) continue;
            $record = json_decode(substr($entry, $pos + $marker_length), true);
            if (!is_array($record)) continue;
            $record['is_file'] = !empty($record['is_file']) ? 1 : null;
            $files[] = $record;
        }
        return $files;
    }

    /**
     * Decode the meta object stored on the last line of an index value
     *
     * @param string $index
     * @return array
     */
    public function getIndexMeta(string $index): array {
        $pos = strrpos($index, "\n");
        if ($pos === false) return [];
        $meta = json_decode(substr($index, $pos + 1), true);
        return is_array($meta) ? $meta : [];
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

        $value = null;

        // Get initial value from object
        if ($object instanceof \ProcessWire\Page) {
            $value = $object->getFormatted($field_name);
        } elseif ($object instanceof \ProcessWire\Field && method_exists($object, 'getFieldValue')) {
            $value = $object->getFieldValue($field_name, true);
        } else {
            $value = $object->get($field_name);
        }

        // Handle array values
        if (is_array($value)) {
            $value = implode(' ', $value);
        }

        // Handle object values - commented out for now, needs more testing (see GitHub PR #29)
        // if (is_object($value) && method_exists($value, '__toString')) {
        //     $value = (string) $value;
        // } elseif (is_object($value)) {
        //     $value = '';
        // }

        return $value;
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
    protected function ___isRepeatableField(\ProcessWire\Field $field): bool {
        return in_array($field->type, [
            'FieldtypePageTable',
            'FieldtypeRepeater',
            'FieldtypeRepeaterMatrix',
        ]);
    }

}
