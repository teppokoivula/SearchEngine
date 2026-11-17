<?php

namespace SearchEngine;

use ProcessWire\WireArray;
use ProcessWire\PageArray;
use ProcessWire\HookEvent;
use ProcessWire\DatabaseQuerySelect;

/**
 * SearchEngine Query class
 *
 * @version 0.6.4
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 *
 * @property-read string $selector Final selector string.
 * @property-read string $operator Operator used by the selector.
 * @property-read string $sql Final SQL query.
 * @property-read string $resultsString Results rendered as a list.
 * @property-read PageArray|null $results Results as a PageArray, or null if none found.
 * @property-read int $resultsCount Number of visible results.
 * @property-read int $resultsTotal Number of total results.
 * @property-read string $pager Rendered pager or empty string if not supported.
 * @property-read string $resultsPager Rendered pager or empty string if not supported.
 * @property string $label Query object label, or empty string if label not found.
 * @property string $group Query object group, or empty string if group not found.
 * @property null|WireArray|PageArray $results Results as an array type object or null if none found.
 */
class Query extends QueryBase {

    /**
     * PageFinder::getQuery hook ID
     *
     * @var string|null
     */
    protected static $query_hook_id = null;

    /**
     * Database query object
     *
     * @var DatabaseQuerySelect|null
     */
    protected $database_query = null;

    /**
     * Result returned by performing the query
     *
     * @var null|WireArray|PageArray
     */
    protected $results = null;

    /**
     * Markup for a pager
     *
     * @var string
     */
    protected $pager = '';

    /**
     * Label
     *
     * A Query may have label applied in case it is, for an example, the result of a grouped find operation. In such a
     * caes the label can be used to identify individual Query objects returned as a part of a larger result set.
     *
     * @var string
     */
    protected $label = '';

    /**
     * Group
     *
     * The group in a grouped set of queries that this particular Query object represents. May be, for an example, a
     * template name.
     *
     * @var string
     */
    protected $group = '';

    /**
     * Magic getter method
     *
     * This method is added so that we can keep some properties (original_*) readable from the
     * outside but not writable (immutable), and also so that we can provide alternatives and
     * aliases for certain properties (or their formatted/rendered versions).
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'selector':
                return $this->getSelector();
                break;
            case 'operator':
                return $this->getOperator();
                break;
            case 'sql':
                return $this->getSQL();
                break;
            case 'resultsString':
                $results = $this->getResults();
                return !empty($results) && method_exists($results, '___getMarkup') ? $results->render() : '';
                break;
            case 'results':
                $results = $this->getResults();
                return !empty($results) ? $results : null;
                break;
            case 'resultsCount':
                $results = $this->getResults();
                return !empty($results) ? $results->count() : 0;
                break;
            case 'resultsTotal':
                $results = $this->getResults();
                return !empty($results) ? $results->getTotal() : 0;
                break;
            case 'pager':
            case 'resultsPager':
                $results = $this->getResults();
                if (empty($this->pager) && !empty($results) && $results instanceof \ProcessWire\PaginatedArray) {
                    $this->pager = $results->renderPager($this->getOptions()['pager_args']) ?? '';
                }
                return $this->pager;
                break;
            case 'label':
                return $this->getLabel();
                break;
        }
        return $this->$name ?? parent::get($name);
    }

    /**
     * Magic setter method
     *
     * This method is added so that we can modify some values on storage (sanitize query etc.)
     *
     * @param string $name Property name
     * @param mixed $value Property value
     */
    public function __set(string $name, $value) {
        if ($name === "query") {
            $query = $this->sanitizeQuery($value);
            if ($query !== $this->query) {
                $this->query = $query;
                $this->results = null;
                $this->pager = null;
                $this->database_query = null;
            }
        } else if ($name === "label" || $name === "group") {
            if (is_string($value) || is_int($value) || is_object($value) && method_exists($value, '__toString')) {
                $this->$name = (string) $value;
            }
        } else if ($name === "results") {
            $this->$name = $value instanceof \ProcessWire\WireArray && count($value) ? $value : null;
            $this->pager = null;
        }
    }

    /**
     * Magic isset method
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool {
        return !empty($this->$name) || !empty($this->__get($name));
    }

    /**
     * Returns database query object
     *
     * @internal
     *
     * @return DatabaseQuerySelect
     */
    public function getQuery(): DatabaseQuerySelect {

        // If we already have a database query, return it
        if ($this->database_query !== null) {
            return $this->database_query;
        }

        // Get selector string
        $selector = $this->getSelector();

        // Convert selector string into Selectors object
        $selectors = new \ProcessWire\Selectors($selector);

        // Use PageFinder to process our query and return resulting database query object
        $pageFinder = new \ProcessWire\PageFinder();
        $query = $pageFinder->find($selectors, [
            'returnVerbose' => true,
            'findOne' => false,
            // Rest of the options are expected by PageFinder
            'returnAllCols' => false,
            'returnParentIDs' => false,
            'returnQuery' => true,
            'reverseSort' => false,
            'alwaysAllowIDs' => [],
        ]);
        $query->set('selectors', $pageFinder->getSelectors());
        return $query;
    }

    /**
     * Set database query object
     *
     * @internal
     *
     * @param DatabaseQuerySelect|null
     * @return Query Self-reference
     */
    public function setQuery(?DatabaseQuerySelect $database_query): Query {
        $this->database_query = $database_query;
        $this->results = null;
        $this->pager = null;
        return $this;
    }

    /**
     * Returns SQL query based on all provided arguments
     *
     * @param DatabaseQuerySelect|null $query Optional database query object
     * @return string
     */
    public function getSQL(?DatabaseQuerySelect $query = null): string {
        $query = $query ?? $this->getQuery();
        if (method_exists($query, 'getDebugQuery')) {
            // ProcessWire 3.0.158+
            return $query->getDebugQuery();
        }
        return $query->getQuery();
    }

    /**
     * Returns label for this Query object, or empty string if label not defined
     *
     * @return string
     */
    public function getLabel(): string {
        return $this->label === '' ? $this->label : $this->wire('sanitizer')->entities($this->label);
    }

    /**
     * Returns results for this Query object
     *
     * This method is used for "lazy loading" results: if results are already defined and cached locally it'll return
     * existing results object, otherwise a new results object gets fetched, cached locally, and returned.
     *
     * @return null|WireArray|PageArray
     */
    public function getResults(): ?WireArray {

        // Bail out early if we already have a results object, or query string is empty
        if ($this->results !== null || empty($this->query)) {
            return $this->results;
        }

        if (!empty($this->args['match_by_source'])) {
            $this->results = $this->resultsBySource();
            return $this->results;
        }

        if ($this->database_query && is_array($this->database_query->_pwse)) {

            // Hook into PageFinder::getQuery to conditionally override the default query object
            if (static::$query_hook_id === null) {
                static::$query_hook_id = $this->addHookBefore('PageFinder::getQuery', function(HookEvent $event) {
                    if (empty($event->arguments[1]['PWSE_Query'])) {
                        return;
                    }
                    $event->replace = true;
                    $event->return = $event->arguments[1]['PWSE_Query'];
                });
            }

            // Find results using customized PWSE query; see Finder::findByTemplates() for more details
            $this->results = $this->wire('pages')->find($this->database_query->selectors, [
                'PWSE_Query' => $this->database_query,
                'cache' => $this->database_query->_pwse['cache'] ?? true,
                'lazy' => $this->database_query->_pwse['lazy'] ?? false,
            ]);

            // Override start and limit values
            list($start, $limit) = explode(',', $this->database_query->limit[0] ?? ',');
            $this->results->setStart($start ?? 0);
            $this->results->setLimit($limit ?? $this->args['limit'] ?? 20);
            return $this->results;
        }

        // Regular Pages find operation
        $this->results = $this->wire('pages')->find($this->getSelector());
        return $this->results;
    }

    /**
     * Find pages, then keep a page hit and emit a separate hit for each matching document
     *
     * Indexes saved before document text was stored separately cannot be split, so those
     * pages are returned unchanged.
     *
     * @return \ProcessWire\PaginatedArray
     */
    protected function resultsBySource(): \ProcessWire\PaginatedArray {
        $items = [];
        $indexer = $this->wire('modules')->get('SearchEngine')->indexer;
        $index_field = $this->getOptions()['index_field'];
        $operator = $this->getOperator();
        $pages = $this->wire('pages')->findMany($this->getSelector());
        foreach ($pages as $page) {
            $index = $page->get($index_field);
            if (is_object($index) && method_exists($index, '__toString')) {
                $index = (string) $index;
            }
            if (!is_string($index) || $index === '') {
                $items[] = $page;
                continue;
            }
            $sources = $indexer->readIndexSources($index);
            if (empty($sources['segmented'])) {
                $items[] = $page;
                continue;
            }
            if ($indexer->sourceContains($sources['page'], $this->query, $operator)) {
                $items[] = $page;
            }
            foreach ($sources['files'] as $file) {
                $haystack = trim(implode(' ', array_filter([
                    $file['name'] ?? '',
                    $file['text'] ?? '',
                ], function($value) {
                    return $value !== null && $value !== '';
                })));
                if ($haystack !== '' && $indexer->sourceContains($haystack, $this->query, $operator)) {
                    $items[] = $this->makeFileResult($page, $file);
                }
            }
        }

        $limit = (int) ($this->args['limit'] ?? 0);
        $total = count($items);
        $page_num = (int) $this->wire('input')->pageNum;
        if ($page_num < 1) {
            $page_num = 1;
        }
        $start = $limit > 0 ? ($page_num - 1) * $limit : 0;
        if ($limit > 0) {
            $items = array_slice($items, $start, $limit);
        }

        $results = $this->wire(new \ProcessWire\PaginatedArray());
        foreach ($items as $item) {
            $results->add($item);
        }
        $results->setStart($start);
        $results->setLimit($limit);
        $results->setTotal($total);
        return $results;
    }

    /**
     * Build a result that represents one matching document
     *
     * @param \ProcessWire\Page $page Page the file is stored on.
     * @param array $file Indexed file record, including its extracted text.
     * @return SearchResult
     */
    protected function makeFileResult(\ProcessWire\Page $page, array $file): SearchResult {
        $public = $file;
        unset($public['text']);
        $name = (string) ($file['name'] ?? '');
        $text = trim((string) ($file['text'] ?? ''));
        $summary = $text !== '' ? $this->fileSnippet($text, $this->query) : (string) ($file['description'] ?? '');
        // Include the page that stores the file. parents() is only the ancestors, so a file
        // on a child of Home otherwise breadcrumbs as Home alone.
        $parents = $this->wire(new \ProcessWire\PageArray());
        foreach ($page->parents() as $parent) {
            $parents->add($parent);
        }
        if ($page->id) {
            $parents->add($page);
        }
        return new SearchResult([
            'id' => $page->id,
            'title' => $name !== '' ? $name : (string) $page->title,
            'name' => $name,
            'url' => (string) ($file['path'] ?? ''),
            'httpUrl' => (string) ($file['url'] ?? ''),
            'summary' => $summary,
            '_file_text' => $text,
            'parent' => $page,
            'parents' => $parents,
            'template' => new SearchResultTemplate([
                'name' => 'file',
                'label' => strtoupper((string) ($file['ext'] ?? 'file')),
            ]),
            'is_file' => 1,
            'files' => [$public],
        ]);
    }

    /**
     * Short extract of a document around the query, for the result summary
     *
     * @param string $text
     * @param string $query
     * @return string
     */
    protected function fileSnippet(string $text, string $query): string {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $needle = trim($query, "\" \t");
        $pos = $needle === '' ? false : mb_stripos($text, $needle);
        if ($pos === false) {
            $words = preg_split('/\s+/u', $needle, -1, PREG_SPLIT_NO_EMPTY);
            $pos = empty($words) ? false : mb_stripos($text, $words[0]);
        }
        if ($pos === false) {
            return mb_substr($text, 0, 240);
        }
        $start = max(0, $pos - 80);
        $snippet = mb_substr($text, $start, 240);
        if ($start > 0) {
            $snippet = '...' . $snippet;
        }
        if ($start + 240 < mb_strlen($text)) {
            $snippet .= '...';
        }
        return $snippet;
    }

}
