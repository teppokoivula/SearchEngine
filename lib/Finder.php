<?php

namespace SearchEngine;

/**
 * SearchEngine Finder
 *
 * @version 0.3.0
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

        // Check if finding results should be delegated to findByTemplatesGrouped
        if ($query->args['group_by'] === 'template') {
            $templates = array_merge($args['pinned_templates'] ?? [], $this->getOptions()['indexed_templates']);
            $group_by_allow = $query->args['group_by_allow'] ?? [];
            foreach ($group_by_allow as $key => $value) {
                if (is_string($value) && strpos($value, '|') !== false) {
                    unset($group_by_allow[$key]);
                    $group_by_allow = array_merge($group_by_allow, explode('|', $value));
                }
            }
            if (!empty($group_by_allow)) {
                // if find args include an array of allowed group values, group by said values only
                $templates = array_intersect($templates, $group_by_allow);
            }
            if (!empty($query->args['group_by_disallow'])) {
                // if find args include an array of disallowed group values, remove those
                $templates = array_diff($templates, $query->args['group_by_disallow']);
            }
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
            'cache' => !isset($args['cache']) || !empty($args['cache']),
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
     * @param bool $matching_only Include templates with matches only?
     * @return QuerySet
     */
    protected function findByTemplatesGrouped(Query $query, array $templates, bool $matching_only = true): QuerySet {

        // Placeholder for results
        $queries = $this->wire(new QuerySet($query->query, $query->args));

        // "All results" Query
        $query->label = $this->getStrings()['tab_label_all'];
        $queries->add($query);

        if ($matching_only) {
            // Optionally include matching templates only
            $templates = $this->getMatchingTemplates($query, $templates);
        } else {
            // Make sure that we have a valid ID for each template
            $templates_with_ids = [];
            foreach ($templates as $template_name) {
                $template = $this->templates->get('name=' . $this->wire('sanitizer')->templateName($template_name));
                if ($template && $template->id) {
                    $templates_with_ids[$template->id] = $template->name;
                }
            }
            $templates = $templates_with_ids;
        }

        // Construct separate query for each template or template group
        $group_by_allow = $query->args['group_by_allow'] ?? [];
        if (empty($group_by_allow)) {
            foreach ($templates as $id => $name) {
                $new_query = clone $query;
                $new_query->label = $query->args['group_labels'][$name] ?? $this->templates->get($id)->getLabel();
                $new_query->group = $this->templates->get($id)->name;
                $new_query = $this->findByTemplates($new_query, [$name], [
                    'named_only' => true,
                    'lazy' => true,
                ]);
                $queries->add($new_query);
            }
        } else {
            $grouped_templates = [];
            foreach ($group_by_allow as $group_name => $group) {
                $group_name = is_string($group_name) ? $group_name : $group;
                $group_templates = strpos($group, '|') === false ? [$group] : explode('|', $group);
                $templates_by_name = array_flip($templates);
                foreach ($group_templates as $group_template) {
                    if (isset($templates_by_name[$group_template])) {
                        $grouped_templates[$group_name][$templates_by_name[$group_template]] = $group_template;
                    }
                }
            }
            foreach ($grouped_templates as $group => $group_templates) {
                $group_template = count($group_templates) === 1 ? key($group_templates) : null;
                $new_query = clone $query;
                $new_query->label = $query->args['group_labels'][$group] ?? ($group_template ? $this->templates->get($group_template)->getLabel() : $group);
                $new_query->group = $group;
                $new_query = $this->findByTemplates($new_query, $group_templates, [
                    'named_only' => true,
                    'lazy' => true,
                ]);
                $queries->add($new_query);
            }
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

        // Get DatabaseQuerySelect from Query
        $query_select = $query->getQuery();

        // Get "select" statements from the DatabaseQuerySelect object and remove any values we don't need here
        $select = $query_select->get('select') ?? [];
        foreach ($select as $select_key => $select_value) {
            $select_value = trim($select_value);
            if (strpos($select_value, 'MATCH(') === 0 && strpos($select_value, ' AS _score_')) continue;
            unset($select[$select_key]);
        }

        // Prepend new select statement for template name and ID
        $select = ['pwse_t.name, pages.templates_id'] + $select;

        // Modify the DatabaseQuerySelect object
        $query_select->set('select', $select);
        $query_select->set('groupby', ['pages.templates_id']);
        $query_select->leftjoin('templates AS pwse_t ON pwse_t.id=pages.templates_id');
        $query_select->set('limit', []);

        // Perform SQL query and process returned results
        $db_statement = $this->database->query($query->getSQL($query_select));
        $db_templates = $db_statement->fetchAll(\PDO::FETCH_GROUP);
        $sorted_templates = [];
        foreach ($templates as $template) {
            if (!isset($db_templates[$template])) continue;
            $sorted_templates[$db_templates[$template][0]['templates_id']] = $template;
        }

        return $sorted_templates;
    }

}
