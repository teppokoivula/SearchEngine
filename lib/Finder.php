<?php

namespace SearchEngine;

use ProcessWire\PageArray;

/**
 * SearchEngine Finder
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Finder extends Base {

    /**
     * Find content matching provided query string
     *
     * @param string|null $query The query
     * @param array $args Additional arguments, see Query::__construct() for details. Values specific to Finder:
     *  - pinned_templates (array, each item should be a template name)
     * @return Query|QuerySet Resulting Query object, or QuerySet containing multiple Query objects in case of a grouped result set
     */
    public function find($query = null, array $args = []): QueryBase {

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

        // Check if finding results should be delegated to findByTemplatesGrouped
        // @todo provide a way to specify templates used in front-end grouping
        if ($query->args['results_grouped_by'] === 'template') {
            $templates = array_merge($args['pinned_templates'] ?? [], $this->getOptions()['indexed_templates']);
            return $this->findByTemplatesGrouped($query, $templates);
        }

        // Check if finding results should be delegated to findByTemplates
        $templates = !empty($args['pinned_templates']) && is_array($args['pinned_templates']) ? $args['pinned_templates'] : [];
        $args_sort = empty($query->args['sort']) ? null : array_filter(explode(',', preg_replace('/\s+/', '', $query->args['sort'])));
        if ($args_sort !== null && in_array('_indexed_templates', $args_sort)) {
            $templates = array_merge($templates, $this->getOptions()['indexed_templates']);
        }
        if (!empty($templates)) {
            return $this->findByTemplates($query, $templates);
        }

        return $query;
    }

    /**
     * Find content sorted by provided array of templates
     *
     * @param Query $query
     * @param array $templates Array of template names or IDs (all items must be of the same type)
     * @param array $args Additional arguments:
     *   - 'named_only': include only content matching provided templates array in returned results
     *   - 'cache': enable caching of selectors and loaded pages (default=true)
     *   - 'lazy': enable "lazy loading" of pages (default=false)
     * @return Query Resulting Query object
     */
    protected function findByTemplates(Query $query, array $templates, array $args = []): Query {

        // Sanitize templates array
        $is_int_array = null;
        if (!empty($templates)) {
            $templates = array_unique(array_filter($templates, function($template) use ($is_int_array) {
                if ($is_int_array === null) $is_int_array = is_int($template);
                return $is_int_array && is_int($template) || is_string($template) && $this->sanitizer->templateName($template) === $template;
            }));
        }

        // Bail out early if templates is empty
        if (empty($templates)) {
            return $query;
        }

        // Get query object, modify it, and replace original query with the modified version
        $pwse_query = $query->getQuery();
        $pwse_query->set('_pwse', [
            'cache' => !isset($args['no_cache']) || !empty($args['no_cache']),
            'lazy' => !empty($args['lazy']),
        ]);
        if (!empty($args['named_only'])) {
            $template_ids = $templates;
            if (!$is_int_array) {
                // If name array was provided, fetch IDs
                $template_ids = $this->templates->find('name=' . implode('|', $templates))->explode('id');
            }
            $pwse_query->where('pages.templates_id IN (' . implode(', ', $template_ids) . ')');
        }
        if (empty($args['named_only']) || count($templates) > 1) {
            $template_names = $templates;
            if ($is_int_array) {
                // If int array was provided, fetch names and sort them to match the original integer array
                $template_names = $this->templates->find('id=' . implode('|', $templates))->explode('name');
                $template_names = array_replace(array_flip($templates), $template_names);
            }
            $pwse_query
                ->select('field(pwse_t.name, "' . implode('", "', array_reverse($template_names)) . '") as pwse_score')
                ->leftjoin('templates AS pwse_t ON pwse_t.id=pages.templates_id')
                ->orderby('pwse_score DESC', true);
        }
        $query->setQuery($pwse_query);

        return $query;
    }

    /**
     * Find content sorted and grouped by templates
     *
     * A Query object represents a single SQL query, yet here we need multiple separate queries. As such, this method
     * takes a single Query object as it's param, but returns a QuerySet of multiple individual Query objects. Each
     * Query object is a clone of the original Query object.
     *
     * @param Query $query
     * @param array $templates Array of template names
     * @return QuerySet
     */
    protected function findByTemplatesGrouped(Query $query, array $templates): QuerySet {

        // Placeholder for results
        $queries = $this->wire(new QuerySet($query->query, $query->args));

        // "All results" Query
        $query->label = $this->getStrings()['tab_label_all'];
        $queries->add($query);

        // Find matching templates and perform separate query for each
        $matching_templates = $this->getMatchingTemplates($query, $templates);
        foreach ($matching_templates as $id => $name) {
            $new_query = clone $query;
            $new_query->label = $this->templates->get($id)->getLabel();
            $new_query = $this->findByTemplates($new_query, [$name], [
                'named_only' => true,
                'lazy' => true,
            ]);
            $queries->add($new_query);
        }

        // Add metadata
        $queries->grouped_by = 'template';

        return $queries;
    }

    /**
     * Get templates matching a query
     *
     * @param Query $query
     * @param array $templates Array of template names
     * @return array
     */
    protected function getMatchingTemplates(Query $query, array $templates): array {
        $query_select = $query->getQuery();
        $query_select->set('select', ['pwse_t.name, pages.templates_id']);
        $query_select->set('groupby', ['pages.templates_id']);
        $query_select->leftjoin('templates AS pwse_t ON pwse_t.id=pages.templates_id');
        $query_select->set('limit', []);
        $db_statement = $this->database->query($query_select->getQuery());
        $db_templates = $db_statement->fetchAll(\PDO::FETCH_KEY_PAIR);
        $sorted_templates = [];
        foreach ($templates as $template) {
            if (!isset($db_templates[$template])) continue;
            $sorted_templates[$db_templates[$template]] = $template;
        }
        return $sorted_templates;
    }

}
