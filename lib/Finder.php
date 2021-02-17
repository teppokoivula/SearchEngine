<?php

namespace SearchEngine;

use ProcessWire\PageArray;
use ProcessWire\HookEvent;

/**
 * SearchEngine Finder
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Finder extends Base {

    /**
     * PageFinder::getQuery hook ID
     *
     * @var string|null
     */
    protected static $query_hook_id = null;

    /**
     * Find content matching provided query string.
     *
     * @param mixed $query The query.
     * @param array $args Additional arguments, see Query::__construct() for details.
     * @return Query Resulting Query object.
     */
    public function find($query = null, array $args = []): Query {

        // Resulting Query object
        $query = $this->wire(new Query($query, $args));

        // Bail out early if query is empty
        if (empty($query->query)) {
            return $query;
        }

        // Merge arguments with defaults
        $query->args = array_replace_recursive(
            $this->getOptions()['find_args'],
            $query->args
        );

        // Find results
        $sort = empty($query->args['sort']) ? null : array_filter(explode(',', preg_replace('/\s+/', '', $query->args['sort'])));
        if ($sort !== null && in_array('_indexed_templates', $sort)) {
            $query->results = $this->findByTemplates($query, $this->getOptions()['indexed_templates']);
        } else {
            $query->results = $this->wire('pages')->find($query->getSelector());
        }

        return $query;
    }

    /**
     * Find content sorted by provided array of templates
     *
     * @param Query $query
     * @param array $templates Array of Template objects, template names, or template IDs
     * @return PageArray
     */
    protected function findByTemplates(Query $query, array $templates): PageArray {

        // Bail out early if templates array is empty
        if (empty($templates)) {
            return $this->wire(new PageArray);
        }

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

        // Get query object, modify it, and replace original query with the modified version
        $pwse_query = $query->getQuery()
            ->select('field(pwse_t.name, "' . implode('", "', $templates) . '") as pwse_score')
            ->leftjoin('templates AS pwse_t ON pwse_t.id=pages.templates_id')
            ->orderby('pwse_score ASC', true);
        $query->setQuery($pwse_query);

        // Find results
        $results = $this->wire('pages')->find($pwse_query->selectors, [
            'PWSE_Query' => $pwse_query,
        ]);

        // Override start and limit values
        list($start, $limit) = explode(',', $pwse_query->limit[0] ?? ',');
        $results->setStart($start ?? 0);
        $results->setLimit($limit ?? $query->args['limit'] ?? 20);

        return $results;
    }

}
