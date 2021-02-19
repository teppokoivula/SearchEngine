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
     * Find content matching provided query string
     *
     * @param string|null $query The query
     * @param array $args Additional arguments, see Query::__construct() for details. Values specific to Finder:
     *  - pinned_templates (array, each item should be a template name)
     * @return Query Resulting Query object
     */
    public function find($query = null, array $args = []): Query {

        // Prepare a Query object
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

        // Check if finding results should be delegated to findByTemplates
        $templates = !empty($args['pinned_templates']) && is_array($args['pinned_templates']) ? $args['pinned_templates'] : [];
        $args_sort = empty($query->args['sort']) ? null : array_filter(explode(',', preg_replace('/\s+/', '', $query->args['sort'])));
        if ($args_sort !== null && in_array('_indexed_templates', $args_sort)) {
            $templates = array_merge($templates, $this->getOptions()['indexed_templates']);
        }
        if (!empty($templates)) {
            $query->results = $this->findByTemplates($query, $templates);
            return $query;
        }

        // Find results
        $query->results = $this->wire('pages')->find($query->getSelector());

        return $query;
    }

    /**
     * Find content sorted by provided array of templates
     *
     * @param Query $query
     * @param array $templates Array of template names
     * @return PageArray
     */
    protected function findByTemplates(Query $query, array $templates): PageArray {

        // Sanitize templates array
        if (!empty($templates)) {
            $templates = array_unique(array_filter($templates, function($template) {
                return is_string($template) && $this->sanitizer->templateName($template) === $template;
            }));
        }

        // If templates is empty, fall back to regular Pages::find
        if (empty($templates)) {
            return $this->wire('pages')->find($query->getSelector());
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
            ->select('field(pwse_t.name, "' . implode('", "', array_reverse($templates)) . '") as pwse_score')
            ->leftjoin('templates AS pwse_t ON pwse_t.id=pages.templates_id')
            ->orderby('pwse_score DESC', true);
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
