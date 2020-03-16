<?php

namespace SearchEngine;

/**
 * SearchEngine Query class
 *
 * @version 0.3.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 *
 * @property-read string $selector Final selector string.
 * @property-read string $resultsString Results rendered as a list (PageArray).
 * @property-read int $resultsCount Number of visible results.
 * @property-read int $resultsTotal Number of total results.
 * @property-read string $pager Rendered pager or empty string if not supported.
 * @property-read string $resultsPager Rendered pager or empty string if not supported.
 */
class Query extends Base {

    /**
     * The query provided for the find operation
     *
     * @var mixed
     */
    protected $query = '';

    /**
     * The original, unmodified query provided for the find operation
     *
     * @var mixed
     */
    protected $original_query = '';

    /**
     * Additional arguments provided for the find operation
     *
     * @var array
     */
    public $args = [];

    /**
     * Original, unmodified additional arguments provided for the find operation
     *
     * @var array
     */
    protected $original_args = [];

    /**
     * Result returned by performing the query.
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
     * Errors array
     *
     * @var array
     */
    public $errors = [];

    /**
     * Constructor method
     *
     * @param string|null $query The query.
     * @param array $args Additional arguments:
     *  - limit (int, limit value, defaults to `50`)
     *  - operator (string, index field comparison operator, defaults to `*=`)
     *  - query_param (string, used for whitelisting the query param, defaults to no query param)
     *  - selector_extra (string|array, additional selector or array of selectors, defaults to blank string)
     *  - sort (string, sort value, may contain multiple comma-separated values, defaults to no defined sort)
     *  - no_validate (bool, set to `true` in order to skip the query validation step)
     */
    public function __construct(?string $query = '', array $args = []) {

        parent::__construct();

        // Merge default find arguments with provided custom values.
        $this->args = array_replace_recursive($this->getOptions()['find_args'], $args);

        // Sanitize query string and whitelist query param (if possible).
        $this->query = $this->sanitizeQuery($query);
        if (!empty($this->query) && !empty($this->args['query_param'])) {
            $this->wire('input')->whitelist($this->args['query_param'], $this->query);
        }

        // Validate query.
        if (empty($args['no_validate'])) {
            $this->errors = $this->validateQuery($this->query);
        }

        // Store original query and original args in class properties.
        $this->original_query = $query;
        $this->original_args = $args;
    }


    /**
     * Sanitize provided query string.
     *
     * @param string|null $query Query string.
     * @return string Sanitized query string.
     */
    protected function sanitizeQuery(?string $query = ''): string {
        if (empty($query)) {
            return '';
        }
        if ($this->wire('config')->dbEngine == 'InnoDB' && $this->args['operator'] == '*=') {
            // Further sanitization is required in order to avoid a MySQL bug affecting InnoDB
            // fulltext search (seemingly related to https://bugs.mysql.com/bug.php?id=78485)
            $query = str_replace('@', ' ', $query);
        }
        $query = $this->wire('sanitizer')->selectorValue($query);
        return $query;
    }

    /**
     * Validate provided query string.
     *
     * @param string $query Query string.
     * @return array $errors Errors array.
     */
    public function validateQuery(string $query = ''): array {

        // Get the strings array.
        $strings = $this->getStrings();

        // Validate query.
        $errors = [];
        if (empty($query) || $query == '""') {
            $errors[] = $strings['error_query_missing'];
        } else {
            $requirements = $this->getOptions()['requirements'];
            if (!empty($requirements['query_min_length']) && mb_strlen($query) < $requirements['query_min_length']) {
                $errors['error_query_too_short'] = sprintf(
                    $strings['error_query_too_short'],
                    $requirements['query_min_length']
                );
            }
        }

        return $errors;
    }

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
            case 'resultsString':
                return !empty($this->results) && method_exists($this->results, '___getMarkup') ? $this->results->render() : '';
                break;
            case 'resultsCount':
                return !empty($this->results) ? $this->results->count() : '';
                break;
            case 'resultsTotal':
                return !empty($this->results) ? $this->results->getTotal() : '';
                break;
            case 'pager':
            case 'resultsPager':
                if (empty($this->pager) && !empty($this->results) && $this->results instanceof \ProcessWire\PaginatedArray) {
                    $options = $this->wire('modules')->get('SearchEngine')->options;
                    $this->pager = $this->results->renderPager($this->getOptions()['pager_args']);
                }
                return $this->pager;
                break;
        }
        return $this->$name;
    }

    /**
     * Magic setter method
     *
     * This method is added so that we can modify some values on storage (sanitize query etc.)
     *
     * @param string $name Property name.
     * @param mixed $value Property value.
     */
    public function __set(string $name, $value) {
        if ($name === "query") {
            $this->query = $this->sanitizeQuery($value);
        } else if ($name === "results") {
            if (!empty($value) && $value instanceof \ProcessWire\WireArray) {
                $this->$name = count($value) ? $value : null;
            }
        }
    }

    /**
     * Magic isset method
     *
     * @param string $name Property name.
     * @return bool True if set, false if not set.
     */
    public function __isset(string $name): bool {
        return !empty($this->$name);
    }

    /**
     * Returns a run-time, stringified version of an argument
     *
     * @param string $name Argument name.
     * @return string Stringified argument value.
     */
    protected function getStringArgument(string $name): string {
        if (empty($this->args[$name])) {
            return '';
        }
        if (is_array($this->args[$name])) {
            return implode(',', $this->args[$name]);
        }
        return (string) $this->args[$name];
    }

    /**
     * Returns a selector string based on all provided arguments
     *
     * @return string
     */
    public function getSelector(): string {

        $options = $this->wire('modules')->get('SearchEngine')->options;

        // define sort order
        $sort = [];
        if (!empty($this->args['sort'])) {
            $sort = [];
            $sort_values = explode(',', $this->args['sort']);
            foreach ($sort_values as $sort_value) {
                $sort_value = trim($sort_value, " \t\n\r\0\x0B\"");
                if (!empty($sort_value)) {
                    $sort[] = 'sort=' . $this->wire('sanitizer')->selectorValue($sort_value);
                }
            }
        }

        // construct and return selector string
        return implode(', ', array_filter([
            empty($this->args['limit']) ? '' : 'limit=' . $this->args['limit'],
            empty($sort) ? '' : implode(', ', $sort),
            implode([$options['index_field'], $this->args['operator'], $this->query]),
            $this->getStringArgument('selector_extra'),
        ]));
    }

}
