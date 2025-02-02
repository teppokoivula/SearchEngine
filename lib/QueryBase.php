<?php

namespace SearchEngine;

/**
 * Base class for Query type objects (Query, QuerySet)
 *
 * @version 0.4.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
abstract class QueryBase extends Base {

    /**
     * The query provided for the find operation
     *
     * @var mixed
     */
    protected $query = '';

    /**
     * The original, unmodified query provided for the find operation
     *
     * @var string|null
     */
    protected $original_query = '';

    /**
     * Version of the query intended for front-end display (before potential internal modifications)
     *
     * @var string|null
     */
    protected $display_query = '';

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
     * Errors array
     *
     * @var array
     */
    public $errors = [];

    /**
     * Constructor method
     *
     * @param string|null $query The query
     * @param array $args Additional arguments:
     *  - limit (int, limit value, defaults to `50`)
     *  - operator (string, index field comparison operator, defaults to `*=`)
     *  - query_param (string, used for whitelisting the query param, defaults to no query param)
     *  - selector_extra (string|array, additional selector or array of selectors, defaults to blank string)
     *  - sort (string, sort value, may contain multiple comma-separated values, defaults to no defined sort)
     *  - no_sanitize (bool, set to `true` in order to skip the query sanitization step)
     *  - no_validate (bool, set to `true` in order to skip the query validation step)
     */
    public function __construct(?string $query = '', array $args = []) {

        parent::__construct();

        // Store original query and original args in class properties
        $this->original_query = $query;
        $this->original_args = $args;

        // Merge default find arguments with provided custom values.
        $this->args = array_replace_recursive($this->getOptions()['find_args'], $args);

        // Sanitize query string and whitelist query param (if possible)
        $this->query = empty($args['no_sanitize'])
            ? $this->sanitizeQuery($query)
            : $query;
        if (!empty($this->query) && !empty($this->args['query_param'])) {
            $this->wire('input')->whitelist($this->args['query_param'], $this->query);
        }

        // Validate query
        if (empty($args['no_validate'])) {
            $this->errors = $this->validateQuery($this->query);
        }

        // Set display query and run hookable prepared method
        $this->display_query = $this->query;
        $this->prepared();
    }

    /**
     * Prepared method for hooks, executed after query object instance is constructed
     *
     * This method can be used in case you need to modify the user-provided query string before using it to find the
     * results. Please note that there's a separate display_query property that is (by default) used by the end user
     * visible rendered templates ("One result for "[display_query]:" etc.)
     *
     * Example use:
     *
     * ```
     * wire()->addHookAfter('Query::prepared', function(HookEvent $event) {
     *     // add "url:" and "href:" as alternatives to "link:"
     *     $event->object->query = preg_replace(
     *         '/\b(?:url|href):([^\s\b]+)$/i',
     *         'link:$1',
     *         $event->object->query
     *     );
     * });
     * ```
     *
     * @since 0.3.0 (SearchEngine 0.32.0)
     */
    protected function ___prepared() {}

    /**
     * Sanitize provided query string.
     *
     * @param string|null $query Query string
     * @return string Sanitized query string
     */
    protected function sanitizeQuery(?string $query = ''): string {

        if (empty($query)) {
            return '';
        }

        // Further sanitization is required in order to avoid a MySQL bug affecting InnoDB fulltext search (seemingly
        // related to https://bugs.mysql.com/bug.php?id=78485).
        if ($this->wire('config')->dbEngine == 'InnoDB' && $this->args['operator'] == '*=') {
            $query = str_replace('@', ' ', $query);
        }

        // For best results we escape lesser than and greater than; this will allow matches in case the index contains
        // encoded HTML markup, but won't cause it to miss umlauts etc.
        $query = str_replace(['<', '>'], ['&lt;', '&gt;'], $query);
        $query = $this->wire('sanitizer')->selectorValue($query);
        return $query;
    }

    /**
     * Validate provided query string.
     *
     * @param string $query Query string
     * @return array $errors Errors array
     */
    public function validateQuery(string $query = ''): array {

        // Get the strings array
        $strings = $this->getStrings();

        // Validate query
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
     * Returns a run-time, stringified version of an argument
     *
     * @param string $name Argument name
     * @return string Stringified argument value
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
     * Returns operator based on provided arguments
     *
     * @return string
     */
    public function getOperator(): string {
        return $this->args['operator'];
    }

    /**
     * Returns a selector string based on all provided arguments
     *
     * @return string
     */
    public function getSelector(): string {

        $options = $this->wire('modules')->get('SearchEngine')->options;

        // Define sort order
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

        // Construct and return selector string
        return implode(', ', array_filter([
            empty($this->args['limit']) ? '' : 'limit=' . $this->args['limit'],
            empty($sort) ? '' : implode(', ', $sort),
            implode([$options['search_field'] ?? $options['index_field'], $this->args['operator'], $this->query]),
            $this->getStringArgument('selector_extra'),
        ]));
    }

}
