<?php

namespace SearchEngine;

use ProcessWire\WireArray;
use ProcessWire\PageArray;
use ProcessWire\HookEvent;
use ProcessWire\DatabaseQuerySelect;

/**
 * SearchEngine Query class
 *
 * @version 0.6.3
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
 * @property-read string $label Query object label, or empty string if label not found.
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
     * The group in a grouped set of queries that this particular Query object represents. May be, for an exaqmple, a
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

}
