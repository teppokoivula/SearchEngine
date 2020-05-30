<?php

namespace SearchEngine;

use ProcessWire\Page;
use ProcessWire\User;
use ProcessWire\WireException;

/**
 * SearchEngine Debugger
 *
 * @version 0.3.1
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
     * Get index for Page
     *
     * @param Page $page
     * @return string|null
     *
     * @throws WireException if Page can't be found
     */
    public function getIndexFor(Page $page): ?string {
        $index_field = $this->getOptions()['index_field'];
        return $page->get($index_field);
    }

    /**
     * Check if Page has an index field
     *
     * @param Page $page
     * @return bool
     */
    public function pageHasIndexfield(Page $page): bool {
        $index_field = $this->getOptions()['index_field'];
        return $page->template->hasField($index_field);
    }

    /**
     * Debug Index and return resulting markup
     *
     * @param bool $include_container Include container?
     * @return string
     */
    public function debugIndex(bool $include_container = true): string {

        // container for debug output
        $debug = [];

        // prepare variables
        $index_field = $this->wire('fields')->get($this->getOptions()['index_field']);
        $indexed_templates = $index_field->getTemplates()->implode('|', 'name');
        $indexed_fields = $this->getOptions()['indexed_fields'];

        // entire index
        $index = '';
        foreach ($this->wire('pages')->findMany($index_field . '!=, include=unpublished, status!=trash') as $indexed_page) {
            $index .= ' ' . $indexed_page->get($index_field->name);
        }
        $index_words = $this->getWords($index, true);

        // content being indexed
        $debug['indexable_content'] = '<h2>' . $this->_('Content being indexed') . '</h2>';
        $debug['indexable_content'] .= $this->renderList([
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

        // indexed content
        $debug['indexed_content'] = '<h2>' . $this->_('Indexed content') . '</h2>';
        $debug['indexed_content'] .= $this->renderList([
            [
                'label' => $this->_('Indexed pages'),
                'value' => $this->wire('pages')->count($index_field->name . '!=, include=unpublished, status!=trash'),
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
                        . '<pre style="white-space: pre-wrap">' . implode(', ', $index_words) . '</pre>',
            ],
        ]);

        // return markup
        return $include_container ? $this->getDebugContainer(implode($debug), [
            'type' => 'index',
        ]) : implode($debug);
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
        $debug = [];

        // page info
        $debug['info'] = '<h2>' . $this->_('Page info') . '</h2>';
        $debug['info'] .= $this->renderList([
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
        $debug['index'] = '<h2>' . $this->_('Indexed content') . '</h2>';
        if ($this->pageHasIndexfield($this->page)) {
            $index = $this->getIndexFor($this->page);
            if (empty($index)) {
                $debug['index'] .= '<em>Index is empty for selected page.</em>';
            } else {
                $index_words = $this->getWords($index, true);
                $metadata = [];
                if (strpos($index, '{') !== false && strpos($index, '}')) {
                    if (preg_match('/{.*?}\z/sim', $index, $metadata_matches)) {
                        $metadata = json_decode($metadata_matches[0]);
                    }
                }
                $debug['index'] .= $this->renderList([
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
                                . '<pre style="white-space: pre-wrap">' . implode(', ', $index_words) . '</pre>',
                    ],
                    [
                        'label' => $this->_('Metadata'),
                        'value' => '<pre style="white-space: pre-wrap">' . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</pre>',
                    ],
                    [
                        'label' => $this->_('Index'),
                        'value' => '<pre style="white-space: pre-wrap">' . $index . '</pre>',
                    ],
                ]);
            }
        } else {
            $debug['index'] .= '<em>' . $this->_('Selected page has no index field.') . '</em>';
        }

        // return markup
        return $include_container ? $this->getDebugContainer(implode($debug), [
            'type' => 'page',
        ]) : implode($debug);
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

        // SearchEngine, Renderer, and Query
        $se = $this->wire('modules')->get('SearchEngine');
        $se->initOnce();
        $renderer = $se->renderer;
        $query = $se->find($this->query);

        // container for debug output
        $debug = [];

        // query info
        $debug['info'] = '<h2>' . $this->_('Query info') . '</h2>';
        $debug['info'] .= $this->renderList([
            [
                'label' => $this->_('Original query'),
                'value' => '<pre style="white-space: pre-wrap">' . $query->original_query . '</pre>'
                           . '<p>(' . sprintf($this->_n('%d character', '%d characters', mb_strlen($query->original_query)), mb_strlen($query->original_query)) . ')</p>',
            ],
            [
                'label' => $this->_('Sanitized query'),
                'value' => '<pre style="white-space: pre-wrap">' . $query->query . '</pre>'
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
                'label' => $this->_('Resulting selector string'),
                'value' => '<pre style="white-space: pre-wrap">' . $query->getSelector() . '</pre>',
			],
			[
                'label' => $this->_('Resulting SQL query'),
                'value' => '<pre style="white-space: pre-wrap">' . $query->getSQL() . '</pre>',
            ],
        ]);

        // results
        $json_args = $renderer->prepareArgs([
            'results_json_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ]);
        $json_args['results_json_fields'] = array_merge([
            'title' => 'title',
            'desc' => 'summary',
            'url' => 'url',
            'template' => 'template.name',
        ], $json_args['results_json_fields']);
        $debug['results'] = '<h2>' . $this->_('Results') . '</h2>';
        $debug['results'] .= $this->renderList([
            [
                'label' => $this->_('Results'),
                'value' => $query->resultsCount . ' / ' . $query->resultsTotal
                        . '<pre style="white-space: pre-wrap">' . $se->renderResultsJSON($json_args, $query) . '</pre>',
            ],
        ]);

        // return markup
        return $include_container ? $this->getDebugContainer(implode($debug), [
            'type' => 'query',
        ]) : implode($debug);
    }

    /**
     * Get container for debug markup
     *
     * @param string $content
     * @param array $data
     * @return string
     */
    public function getDebugContainer(string $content = '', array $data = []): string {

		// inject scripts
		foreach (['Core', 'Debugger'] as $script) {
			$this->wire('config')->scripts->add(
				$this->wire('config')->urls->get('SearchEngine') . 'js/' . $script . '.js'
			);
		}

        // data attributes for debug output container
        $data = array_merge([
            'debug-button-label' => $this->_('Debug'),
            'refresh-button-label' => $this->_('Refresh'),
            'page-id' => $this->page && $this->page->id ? $this->page->id : null,
            'query' => $this->query,
            'type' => 'page',
        ], $data);

        // construct and return container markup
        return '<div class="search-engine-debug" '
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
        preg_match_all("/[\w-']+/ui", $index, $index_words);
        $index_words = $index_words[0];
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

}
