<?php

namespace SearchEngine;

use ProcessWire\Page;
use ProcessWire\User;

/**
 * SearchEngine Debugger
 *
 * @version 0.1.0
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
     * Get markup for debug inputfield
     *
     * @return string
     */
    public function getDebugMarkup(): string {

        // bail out early if no page is defined
        if (!$this->page || !$this->page->id) {
            return '<em>' . $this->_('No page found.') . '</em>';
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
                    '<strong>' . $this->_('Words (unique)') . '</strong>: ' . count($index_words)
                    . '<pre style="white-space: pre-wrap">' . implode(', ', array_unique($index_words)) . '</pre>',
                    '<strong>' . $this->_('Metadata') . '</strong>: '
                    . '<pre style="white-space: pre-wrap">' . json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</pre>',
                    '<strong>' . $this->_('Index') . '</strong>: '
                    .  '<pre style="white-space: pre-wrap">' . $index . '</pre>',
                ])) . '</li></ul>';
            }
        } else {
            $debug['index'] .= '<em>' . $this->_('Selected page has no index field.') . '</em>';
        }

        // return markup
        return '<div id="search-engine-debug">' . implode($debug) . '</div>';
    }

}
