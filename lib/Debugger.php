<?php

namespace SearchEngine;

use ProcessWire\Field;
use ProcessWire\Language;
use ProcessWire\Page;
use ProcessWire\User;
use ProcessWire\WireException;
use ProcessWire\WirePermissionException;

/**
 * SearchEngine Debugger
 *
 * @version 0.5.2
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Debugger extends Base {

    /**
     * Selected Page
     *
     * @var Page
     */
    protected $page;

    /**
     * Search query
     *
     * @var string
     */
    protected $query = '';

    /**
     * Renderer instance
     *
     * @var Renderer
     */
    protected $renderer;

    /**
     * Search query args
     *
     * @var array
     */
    protected $query_args = [];

    /**
     * Index field
     *
     * @var Field
     */
    protected $index_field;

    /**
     * Constructor
     *
     * @throws WireException if index field can't be found
     */
    public function __construct() {
        $this->index_field = $this->wire('fields')->get($this->getOptions()['index_field']);
        if (!$this->index_field || !$this->index_field->id) {
            throw new WireException('Index field not found');
        }
        $this->renderer = new Renderer;
    }

    /**
     * Set Page
     *
     * @param int|Page $page Page ID or Page object
     * @return Debugger Self-reference
     *
     * @throws WireException if Page is invalid or can't be found
     */
    public function setPage($page): Debugger {
        if (is_int($page)) {
            $page = $this->wire('pages')->get($page);
        }
        if (!$page instanceof Page || !$page->id) {
            throw new WireException('Invalid or missing Page');
        }
        $this->page = $page;
        return $this;
    }

    /**
     * Set query
     *
     * @param string $query Search query
     * @return Debugger Self-reference
     */
    public function setQuery(string $query): Debugger {
        $this->query = $query;
        return $this;
    }

    /**
     * Set query args
     *
     * @param array|string $args Search query args
     * @return Debugger Self-reference
     */
    public function setQueryArgs($args = []): Debugger {
        if (is_string($args)) {
            $args = json_decode($args, true, 3, JSON_OBJECT_AS_ARRAY);
        }
        if (is_array($args)) {
            $this->query_args = $args;
        }
        return $this;
    }

    /**
     * Get index for Page
     *
     * @param Page $page
     * @param Language|null $language Optional language
     * @return array
     */
    public function getIndexFor(Page $page, ?Language $language = null): array {
        if ($this->index_field->type == 'FieldtypeTextareaLanguage') {
            if ($language !== null) {
                return [
                    $language->name => $page->getLanguageValue($language, $this->index_field->name),
                ];
            }
            $index = [];
            foreach ($this->wire('languages') as $language) {
                $index[$language->name] = $page->getLanguageValue($language, $this->index_field->name);
            }
            return $index;
        }
        return [
            null => $page->get($this->index_field->name),
        ];
    }

    /**
     * Check if Page has an index field
     *
     * @param Page $page
     * @return bool
     */
    public function pageHasIndexfield(Page $page): bool {
        return $page->template->hasField($this->index_field->id);
    }

    /**
     * Debug Index and return resulting markup
     *
     * @param bool $include_container Include container?
     * @return string
     */
    public function debugIndex(bool $include_container = true): string {

        // container for debug output
        $debug = [
            'indexable_content' => [
                'heading' => $this->_('Content being indexed'),
                'content' => '',
            ],
            'indexed_content' => [
                'heading' => $this->_('Indexed content'),
                'content' => [],
            ],
        ];

        // common variables
        $indexed_templates = implode('|', $this->getOptions()['indexed_templates']);
        $indexed_fields = $this->getOptions()['indexed_fields'];

        // content being indexed
        $debug['indexable_content']['content'] = $this->renderList([
            [
                'label' => $this->_('Indexed templates'),
                'value' => str_replace('|', ', ', $indexed_templates),
            ],
            [
                'label' => $this->_('Indexed fields'),
                'value' => implode(', ', $indexed_fields),
            ],
            [
                'label' => $this->_('Indexable pages'),
                'value' => $this->wire('pages')->count('template=' . $indexed_templates . ', include=unpublished, status!=trash'),
            ],
        ]);

        // languages
        $languages = [null];
        if ($this->index_field->type == 'FieldtypeTextareaLanguage') {
            $languages = [];
            foreach ($this->wire('languages') as $language) {
                $languages[] = $language;
            }
        }

        // display debug for each language
        foreach ($languages as $language) {
            $index = '';
            foreach ($this->wire('pages')->findMany($this->index_field . '!=, include=unpublished, status!=trash') as $indexed_page) {
                $page_index = $this->getIndexfor($indexed_page, $language);
                $index .= ' ' . reset($page_index);
            }
            $index_words = $this->getWords($index, true);
            $debug['indexed_content']['content'][$language === null ? null : $language->name] = [
                'heading' => $language === null ? null : $language->name,
                'content' => $this->renderList([
                    [
                        'label' => $this->_('Indexed pages'),
                        'value' => $this->wire('pages')->count($this->index_field->name . '!=, include=unpublished, status!=trash'),
                    ],
                    [
                        'label' => $this->_('Characters'),
                        'value' => mb_strlen($index),
                    ],
                    [
                        'label' => $this->_('Words'),
                        'value' => str_word_count($index),
                    ],
                    [
                        'label' => $this->_('Unique words'),
                        'value' => count($index_words)
                                . '<pre class="pwse-pre pwse-collapse">' . implode(', ', $index_words) . '</pre>',
                    ],
                ]),
            ];
        }

        // return markup
        return $this->renderSection($debug, $include_container, [
            'type' => 'index',
        ]);
    }

    /**
     * Debug Page and return resulting markup
     *
     * @param bool $include_container Include container?
     * @return string
     */
    public function debugPage(bool $include_container = true): string {

        // bail out early if no valid page is defined
        if (!$this->page || !$this->page->id) {
            return '';
        }

        // container for debug output
        $debug = [
            'info' => [
                'heading' => $this->_('Page info'),
                'content' => '',
            ],
            'index' => [
                'heading' => $this->_('Indexed content'),
                'content' => [],
            ],
        ];

        // page info
        $debug['info']['content'] = $this->renderList([
            [
                'label' => $this->_('ID'),
                'value' => $this->page->id,
            ],
            [
                'label' => $this->_('Name'),
                'value' => $this->page->name,
            ],
            [
                'label' => $this->_('URL'),
                'value' => $this->page->url,
            ],
            [
                'label' => $this->_('Status'),
                'value' => $this->page->statusStr ?: 'on',
            ],
            [
                'label' => $this->_('Created'),
                'value' => $this->page->createdStr
                        . ' (' . ($this->page->createdUser instanceof User ? $this->page->createdUser->name : '#' . $this->page->created_users_id) . ')',
            ],
            [
                'label' => $this->_('Published'),
                'value' => $this->page->publishedStr,
            ],
            [
                'label' => $this->_('Modified'),
                'value' => $this->page->modifiedStr
                        . ' (' . ($this->page->modifiedUser instanceof User ? $this->page->modifiedUser->name : '#' . $this->page->modified_users_id) . ')',
            ],
        ]);

        // contents of the index
        if ($this->pageHasIndexfield($this->page)) {
            $index = $this->getIndexFor($this->page);
            foreach ($index as $index_language => $index_content) {
                if (!empty($index_language)) {
                    $debug['index']['content'][$index_language] = [
                        'label' => $index_language,
                        'content' => '',
                    ];
                }
                if (empty($index_content)) {
                    $debug['index']['content'][$index_language]['content'] = '<em>Index is empty for this page.</em>';
                    continue;
                }
                $index_words = $this->getWords($index_content, true);
                $metadata = [];
                if (strpos($index_content, '{') !== false && strpos($index_content, '}')) {
                    if (preg_match('/{.*?}\z/sim', $index_content, $metadata_matches)) {
                        $metadata = json_decode($metadata_matches[0]);
                    }
                }
                $debug['index']['content'][$index_language]['content'] = $this->renderList([
                    [
                        'label' => $this->_('Characters'),
                        'value' => mb_strlen($index_content),
                    ],
                    [
                        'label' => $this->_('Words'),
                        'value' => str_word_count($index_content),
                    ],
                    [
                        'label' => $this->_('Unique words'),
                        'value' => count($index_words)
                                . '<pre class="pwse-pre">' . implode(', ', $index_words) . '</pre>',
                    ],
                    [
                        'label' => $this->_('Metadata'),
                        'value' => '<pre class="pwse-pre">' . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</pre>',
                    ],
                    [
                        'label' => $this->_('Index content'),
                        'value' => '<pre class="pwse-pre pwse-collapse">' . $this->sanitizer->entities($index_content) . '</pre>',
                    ],
                ]);
            }
        } else {
            $debug['index']['content'] = '<em>' . $this->_('Selected page has no index field.') . '</em>';
        }

        // return markup
        return $this->renderSection($debug, $include_container, [
            'type' => 'page',
        ]);
    }

    /**
     * Debug Query and return resulting markup
     *
     * @param bool $include_container Include container?
     * @return string
     */
    public function debugQuery(bool $include_container = true): string {

        // bail out early if no query is defined
        if (!$this->query) {
            return '';
        }

        // init SearchEngine and fetch Renderer
        $se = $this->wire('modules')->get('SearchEngine');
        $se->initOnce();
        $renderer = $se->renderer;

        // container for debug output
        $debug = [
            'info' => [
                'heading' => $this->_('Query info'),
                'content' => [],
            ],
            'results' => [
                'heading' => $this->_('Results'),
                'content' => [],
            ],
        ];

        // languages
        $original_language = null;
        $languages = [null];
        if ($this->index_field->type == 'FieldtypeTextareaLanguage') {
            $original_language = $this->wire('user')->language;
            $languages = [];
            foreach ($this->wire('languages') as $language) {
                $languages[] = $language;
            }
        }

        // display debug for each language
        foreach ($languages as $language) {

            // set up timer
            $timer = \ProcessWire\Debug::timer();

            // perform query
            if ($language !== null) {
                $this->wire('user')->language = $language;
            }

            // query response may be a Query object, or an array of Query objects if grouped result set was requested
            $query = $se->find($this->query, $this->query_args);
            $query_timer = sprintf($this->_('%s seconds'), \ProcessWire\Debug::timer($timer));

            // query info
            $info_content = $this->renderList([
                [
                    'label' => $this->_('Original query'),
                    'value' => '<pre class="pwse-pre">' . $this->sanitizer->entities($query->original_query) . '</pre>'
                            . '<p>(' . sprintf($this->_n('%d character', '%d characters', mb_strlen($query->original_query)), mb_strlen($query->original_query)) . ')</p>',
                ],
                [
                    'label' => $this->_('Sanitized query'),
                    'value' => '<pre class="pwse-pre">' . $this->sanitizer->entities($query->query) . '</pre>'
                            . '<p>(' . sprintf($this->_n('%d character', '%d characters', mb_strlen($query->query)), mb_strlen($query->query)) . ')</p>',
                ],
                [
                    'label' => $this->_('Sanitization modified query'),
                    'value' => (
                        $query->original_query === $query->query || $query->original_query === trim($query->query, '"')
                        ? $this->_('No') . ' <i class="fa fa-check" style="color: green" aria-hidden="true"></i>'
                        : $this->_('Yes')  . ' <i class="fa fa-exclamation-triangle" style="color: red" aria-hidden="true"></i>'
                    ),
                ],
                [
                    'label' => $this->_('Query args'),
                    'value' => '<pre class="pwse-pre">' . $this->sanitizer->entities(json_encode($query->args, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '</pre>',
                ],
                [
                    'label' => $this->_('Resulting selector string'),
                    'value' => '<pre class="pwse-pre">' . $this->sanitizer->entities($query->getSelector()) . '</pre>',
                ],
                [
                    'label' => $this->_('Resulting SQL query'),
                    'value' => $query instanceof QuerySet ? $this->renderer->renderTabs('debugger-sql-query', array_map(function($query) {
                        return [
                            'label' => $query->label ?: 'Query',
                            'content' => '<pre class="pwse-pre">' . $this->sanitizer->entities($query->getSQL()) . '</pre>',
                        ];
                    }, $query->items)) : '<pre class="pwse-pre">' . $this->sanitizer->entities($query->getSQL()) . '</pre>',
                ],
                [
                    'label' => $this->_('Time spent finding results'),
                    'value' => '<pre class="pwse-pre">' . $query_timer . '</pre>',
                ],
            ]);
            if ($language !== null) {
                $debug['info']['content'][$language->name] = [
                    'label' => $language->name,
                    'content' => $info_content,
                ];
            } else {
                $debug['info']['content'] = $info_content;
            }

            // results
            $json_args = $renderer->prepareArgs([
                'results_json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            ]);
            $json_args['results_json_fields'] = array_merge([
                'title' => 'title',
                'desc' => 'summary',
                '_auto_desc' => '_auto_desc',
                'url' => 'url',
                'template' => 'template.name',
            ], $json_args['results_json_fields']);
            $results_content = $this->renderList([
                [
                    'label' => $this->_('Results'),
                    'value' => $query->resultsCount . ' / ' . $query->resultsTotal
                        . $this->renderer->renderTabs('debugger-results', [
                            'json' => [
                                'label' => 'JSON',
                                'content' => '<pre class="pwse-pre">' . $se->renderResultsJSON($json_args, $query) . '</pre>',
                            ],
                            'html' => [
                                'label' => 'HTML',
                                'content' => $se->renderStyles() . $se->renderResults([
                                    'pager_args' => $se::$defaultOptions['pager_args'],
                                    'autoload_result_groups' => true,
                                ], $query),
                                'class_modifier' => 'alt',
                            ],
                        ]),
                ],
            ]);
            if ($language !== null) {
                $debug['results']['content'][$language->name] = [
                    'label' => $language->name,
                    'content' => $results_content,
                ];
            } else {
                $debug['results']['content'] = $results_content;
            }
        }

        // reset language
        if ($original_language) {
            $this->wire('user')->language = $original_language;
        }

        // return markup
        return $this->renderSection($debug, $include_container, [
            'type' => 'query',
        ]);
    }

    /**
     * Render container for debug markup
     *
     * @param string $content
     * @param array $data
     * @return string
     */
    public function renderDebugContainer(string $content = '', array $data = []): string {

        // inject scripts
        $this->wire('config')->scripts->add(
            $this->wire('config')->urls->get('SearchEngine') . 'js/dist/admin.js'
        );

        // inject styles
        foreach (['tabs', 'debugger'] as $styles) {
            $this->wire('config')->styles->add(
                $this->wire('config')->urls->get('SearchEngine') . 'css/' . $styles . '.css'
            );
        }

        // data attributes for debug output container
        $data = array_merge([
            'debug-button-label' => $this->_('Debug'),
            'refresh-button-label' => $this->_('Refresh'),
            'show-more-button-label' => $this->_('Show more'),
            'show-less-button-label' => $this->_('Show less'),
            'page-id' => $this->page && $this->page->id ? $this->page->id : null,
            'query' => $this->query,
            'type' => 'page',
        ], $data);

        // construct and return container markup
        return '<div class="pwse-debug" '
            . implode(" ", array_map(function($key, $value) {
                return 'data-' . $key . '="' . $value . '"';
            }, array_keys($data), $data))
            . '">'
            . $content
            . '</div>';
    }

    /**
     * Render unordered list from an array of debug items
     *
     * @param array $items
     * @return string
     */
    protected function renderList(array $items): string {

        // filter items and bail out early if the resulting array is empty
        $items = array_filter($items);
        if (empty($items)) {
            return '';
        }

        // container for output
        $out = '';

        // append items
        foreach ($items as $item) {
            if (is_null($item['value']) || $item['value'] == '') continue;
            $out .= '<li>'
                . '<strong>' . $item['label'] . '</strong>: '
                . $item['value']
                . '</li>';
        }

        // return list markup
        return $out == '' ? '' : '<ul>' . $out . '</ul>';
    }

    /**
     * Render debug section
     *
     * @param array $data
     * @param bool $include_container
     * @param array $container_data
     * @return string
     */
    protected function renderSection(array $data, bool $include_container = true, array $container_data = []): string {
        $out = '';
        foreach ($data as $key => $subsection) {
            $out .= '<h2>' . $subsection['heading'] . '</h2>';
            if (is_array($subsection['content'])) {
                if (isset($subsection['content'][null])) {
                    // single language content
                    $subsection['content'] = $subsection['content'][null]['content'];
                } else {
                    // multilanguage content, render tabs
                    $out .= $this->renderer->renderTabs('debugger-' . $container_data['type'] ?? $key, $subsection['content']);
                    continue;
                }
            }
            $out .= $subsection['content'];
        }
        return $include_container ? $this->renderDebugContainer($out, $container_data) : $out;
    }

    /**
     * Get words from an index
     *
     * Note: numeric sequences are also considered words by this method, but they have a slightly
     * stricter length requirement.
     *
     * @param string $index
     * @param bool $unique
     * @return array
     */
    protected function getWords(string $index = '', bool $unique = false): array {

        // prepare index
        $index = trim($index);
        $index = $this->wire('sanitizer')->unentities($index);

        // get words
        preg_match_all("/[\w\-']+/ui", $index, $index_words);
        $index_words = $index_words[0] ?? [];
        $index_words = array_map(function($word) {
            $word = trim($word, " \t\n\r\x0B-&");
            $word = mb_strtolower($word);
            return $word;
        }, $index_words);
        $index_words = array_filter($index_words, function($word) {
            $word = str_replace('-', '', $word);
            if (is_numeric($word)) {
                return strlen($word) > 3;
            }
            return mb_strlen($word) > 2;
        });

        // unique only?
        if ($unique) {
            $index_words = array_unique($index_words);
        }

        // sort words alphabetically
        sort($index_words);

        return $index_words;
    }

    /**
     * Init AJAX API endpoint
     *
     * @throws WirePermissionException if current user doesn't have the superuser role
     */
    public function initAJAXAPI() {

        // bail out early if se-debug GET param isn't set
        if (!$this->wire('input')->get('se-debug')) return;

        // require superuser role
        if (!$this->wire('user')->isSuperuser()) {
            throw new WirePermissionException("You don't have permission to execute that action");
        }

        if ($this->wire('input')->get('se-debug-page-id')) {

            // debug single page
            $this->setPage((int) $this->wire('input')->get('se-debug-page-id'));
            exit($this->debugPage(false));

        } else if ($this->wire('input')->get('se-reindex-page-id')) {

            // reindex single page
            $indexPageID = (int) $this->wire('input')->get('se-reindex-page-id');
            $indexPage = $this->wire('pages')->get($indexPageID);
            if ($indexPage && $indexPage->id) {
                $indexer = new Indexer;
                if ($indexer->indexPage($indexPage)) {
                    exit('<div class="uk-alert-success" style="color: #32d296; background: #edfbf6" uk-alert>' . $this->_('Page indexed succesfully.') . '</div>');
                }
                exit('<div class="uk-alert-warning" uk-alert>' . $this->_('Error occurred while trying to index the page.') . '</div>');
            }
            exit('<div class="uk-alert-danger" uk-alert>' . sprintf($this->_('Page not found: %d.'), $indexPageID) . '</div>');

        } else if ($this->wire('input')->get('se-debug-query')) {

            // debug query
            $this->setQuery($this->wire('input')->get('se-debug-query'));
            $this->setQueryArgs($this->wire('input')->get('se-debug-query-args'));

            exit($this->debugQuery(false));

        } else if ($this->wire('input')->get('se-debug-index')) {

            // debug index
            exit($this->debugIndex(false));

        }
    }

}
