<?php

namespace SearchEngine;

use ProcessWire\Page;
use ProcessWire\User;
use ProcessWire\WireException;

/**
 * SearchEngine Debugger
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
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
     * Debug Page and return resulting markup
     *
     * @param bool $include_container Include container?
     * @return string
     */
    public function debugPage(string $type = 'page', bool $include_container = true): string {

        // bail out early if no valid page is defined
        if (!$this->page || !$this->page->id) {
            return '';
        }

        // container for debug output
        $debug = [];

        // page info
        $debug['info'] = '<h2>' . $this->_('Page info') . '</h2>';
        $debug['info'] .= '<ul><li>' . implode('</li><li>', array_filter([
            '<strong>' . $this->_('ID') . '</strong>: ' . $this->page->id,
            '<strong>' . $this->_('Name') . '</strong>: ' . $this->page->name,
            '<strong>' . $this->_('URL') . '</strong>: ' . $this->page->url,
            '<strong>' . $this->_('Status') . '</strong>: ' . ($this->page->statusStr ?: 'on'),
            '<strong>' . $this->_('Created') . '</strong>: ' . $this->page->createdStr . ' (' . ($this->page->createdUser instanceof User ? $this->page->createdUser->name : '#' . $this->page->created_users_id) . ')',
            $this->page->publishedStr ? '<strong>' . $this->_('Published') . '</strong>: ' . $this->page->publishedStr : '',
            '<strong>' . $this->_('Modified') . '</strong>: ' . $this->page->modifiedStr . ' (' . ($this->page->modifiedUser instanceof User ? $this->page->modifiedUser->name : '#' . $this->page->modified_users_id) . ')',
        ])) . '</li></ul>';

        // contents of the index
        $debug['index'] = '<h2>' . $this->_('Indexed content') . '</h2>';
        if ($this->pageHasIndexfield($this->page)) {
            $index = $this->getIndexFor($this->page);
            if (empty($index)) {
                $debug['index'] .= '<em>Index is empty for selected page.</em>';
            } else {
                $index_words = array_unique(str_word_count($index, true));
                $metadata = [];
                if (strpos($index, '{') !== false && strpos($index, '}')) {
                    if (preg_match('/{.*?}\z/sim', $index, $metadata_matches)) {
                        $metadata = json_decode($metadata_matches[0]);
                    }
                }
                $debug['index'] .= '<ul><li>' . implode('</li><li>', array_filter([
                    '<strong>' . $this->_('Characters') . '</strong>: ' . mb_strlen($index),
                    '<strong>' . $this->_('Words') . '</strong>: ' . str_word_count($index),
                    '<strong>' . $this->_('Unique words') . '</strong>: ' . count($index_words)
                    . '<pre style="white-space: pre-wrap">' . implode(', ', array_unique($index_words)) . '</pre>',
                    '<strong>' . $this->_('Metadata') . '</strong>: '
                    . '<pre style="white-space: pre-wrap">' . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</pre>',
                    '<strong>' . $this->_('Index') . '</strong>: '
                    .  '<pre style="white-space: pre-wrap">' . $index . '</pre>',
                ])) . '</li></ul>';
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
        $debug['info'] .= '<ul><li>' . implode('</li><li>', array_filter([
            '<strong>' . $this->_('Original query') . '</strong>: '
            . '<pre style="white-space: pre-wrap">' . $query->original_query . '</pre>'
            . '<p>(' . sprintf($this->_n('%d character', '%d characters', mb_strlen($query->original_query)), mb_strlen($query->original_query)) . ')</p>',
            '<strong>' . $this->_('Sanitized query') . '</strong>: '
            . '<pre style="white-space: pre-wrap">' . $query->query . '</pre>'
            . '<p>(' . sprintf($this->_n('%d character', '%d characters', mb_strlen($query->query)), mb_strlen($query->query)) . ')</p>',
            '<strong>' . $this->_('Sanitization modified query') . '</strong>: '
            . (
                $query->original_query === $query->query || $query->original_query === trim($query->query, '"')
                ? $this->_('No') . ' <i class="fa fa-check" style="color: green" aria-hidden="true"></i>'
                : $this->_('Yes')  . ' <i class="fa fa-exclamation-triangle" style="color: red" aria-hidden="true"></i>'
            ),
            '<strong>' . $this->_('Resulting selector string') . '</strong>: '
            . '<pre style="white-space: pre-wrap">' . $query->getSelector() . '</pre>',
        ])) . '</li></ul>';

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
        $debug['results'] .= '<ul><li>' . implode('</li><li>', array_filter([
            '<strong>' . $this->_('Results') . '</strong>: ' . $query->resultsCount . ' / ' . $query->resultsTotal
            . '<pre style="white-space: pre-wrap">' . $se->renderResultsJSON($json_args, $query) . '</pre>',
        ])) . '</li></ul>';

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

        // inject Debugger script
        $this->wire('config')->scripts->add(
            $this->wire('config')->urls->get('SearchEngine') . 'js/Debugger.js'
        );

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

}
