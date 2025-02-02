<?php

namespace SearchEngine;

use ProcessWire\Inputfield;
use ProcessWire\Page;
use ProcessWire\WireException;

/**
 * SearchEngine Renderer
 *
 * @property-read string $form Rendered search form.
 * @property-read string $inputfieldForm Rendered search form using ProcessWire InputfieldForm class.
 * @property-read string $results Rendered search results.
 * @property-read string $styles Rendered styles (link tags).
 * @property-read string $scripts Rendered styles (script tags).
 *
 * @version 0.9.4
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Renderer extends Base {

    /**
     * Path on disk for the themes directory
     *
     * Populated in __construct().
     *
     * @var string
     */
    protected $themePath;

    /**
     * URL for the themes directory
     *
     * Populated in __construct().
     *
     * @var string
     */
    protected $themeURL;

    /**
     * Constructor method
     */
    public function __construct() {
        parent::__construct();
        $themes_directory = $this->getOptions()['render_args']['themes_directory'] ?? null;
        if ($themes_directory !== null) {
            $themes_directory = trim($themes_directory, '/.') . '/';
            $templates_directory = $this->wire('config')->paths->templates;
            if ($this->wire('files')->allowPath($templates_directory . $themes_directory, $templates_directory)) {
                $this->themePath = $templates_directory . $themes_directory;
                $this->themeURL = $this->wire('config')->urls->templates . $themes_directory;
            }
        }
        if ($this->themePath === null) {
            $this->themePath = $this->wire('config')->paths->get('SearchEngine') . 'themes/';
            $this->themeURL = $this->wire('config')->urls->get('SearchEngine') . 'themes/';
        }
    }

    /**
     * Render a search form
     *
     * @param array $args Optional arguments.
     * @return string Markup for the search form.
     */
    public function ___renderForm(array $args = []): string {

        // Prepare args.
        $args = $this->prepareArgs($args);

        // Attempt to prefill form input value.
        if (empty($args['strings']['form_input_value'])) {
            $options = $this->getOptions();
            $query_param = $options['find_args']['query_param'];
            $args['strings']['form_input_value'] = $this->wire('input')->whitelist($query_param);
            if (empty($args['strings']['form_input_value'])) {
                $query_value = $this->wire('input')->get($query_param);
                if (!empty($query_value)) {
                    $query = $this->wire(new Query($query_value, [
                        'no_validate' => true,
                    ]));
                    $args['strings']['form_input_value'] = $this->wire('input')->whitelist($query_param);
                }
            }
        }

        // Render search form.
        $form_content = sprintf(
            $args['templates']['form'],
            $args['templates']['form_label']
          . $args['templates']['form_input']
          . $args['templates']['form_submit']
        );

        // Replace placeholders (string tags).
        $form = \ProcessWire\wirePopulateStringTags($form_content, $this->getData($args));

        return $form;
    }

    /**
     * Render a search form using InputfieldForm class
     *
     * @param array $args Optional arguments.
     * @return string Markup for the search form.
     */
    public function ___renderInputfieldForm(array $args = []) {
        return $this->getInputfieldForm($args)->render();
    }

    /**
     * Get InputfieldForm implementation for the search form
     *
     * @param array $args Optional arguments.
     * @return \ProcessWire\InputfieldForm
     */
    public function ___getInputfieldForm(array $args = []): \ProcessWire\Inputfieldform {

        $modules = $this->wire('modules');
        $options = $this->getOptions();
        $args = array_replace_recursive($options['render_args'], $args);

        // Search form.
        $form = $modules->get('InputfieldForm');
        $form->method = 'GET';
        $form->id = $args['form_id'];
        $form_action = $args['form_action'];
        if ($form_action instanceof Page) {
            $form_action = $form_action->path;
        }
        $form->action = $form_action;

        // Query (text) field.
        $input = $modules->get('InputfieldText');
        $input->name = $options['find_args']['query_param'] ?? 'q';
        $input->label = $this->getString('input', $this->_('Search'));
        $input->value = $this->wire('input')->whitelist($input->name);
        $input->placeholder = $this->getString('input_placeholder');
        $input->collapsed = Inputfield::collapsedNever;
        $form->add($input);

        // Submit (search) button.
        $submit = $modules->get('InputfieldSubmit');
        $submit->name = null;
        $submit->value = $this->getString('submit', $this->_('Search'));
        $submit->collapsed = Inputfield::collapsedNever;
        $form->add($submit);

        return $form;
    }

    /**
     * Render markup for search results
     *
     * @param array $args Optional arguments.
     * @param Query|QuerySet $query Optional prepopulated Query object or QuerySet containing one or more Query objects.
     * @return string
     *
     * @throws WireException if query parameter is unrecognized.
     */
    public function ___renderResults(array $args = [], QueryBase $query = null): string {

        // Prepare args and get Data object.
        $args = $this->prepareArgs($args);
        $options = $this->getOptions();
        $data = $this->getData($args);

        // If query is null, fetch results automatically.
        if ($query === null) {
            $query_term = $this->wire('input')->get($options['find_args']['query_param']);
            if (!empty($query_term)) {
                $query = $this->wire('modules')->get('SearchEngine')->find($query_term, $options['find_args']);
            }
        }

        // Bail out early if not provided with – and unable to fetch automatically – results.
        if (!$query instanceof QueryBase) {
            return '';
        }

        // Bail out early if there were errors with the query.
        if (!empty($query->errors)) {
            return $this->renderErrors($args, $query);
        }

        // Results list.
        $results_list = '';
        if ($query instanceof QuerySet) {
            // Get current group from query string.
            $group = !empty($args['find_args']['group_param']) ? $this->wire('input')->get($args['find_args']['group_param']) : null;
            if ($group !== null) {
                $group = $this->wire('sanitizer')->text($group);
                $this->wire('input')->whitelist($args['find_args']['group_param'], $group);
            }
            $is_first_group = true;
            $results_list .= $this->renderTabs('query-' . uniqid(), array_map(function($query) use ($data, $args, $group, &$is_first_group) {
                // Content should only be rendered if a) this is an active tab or b) autoload_result_groups is enabled.
                $content = null;
                if (!empty($args['autoload_result_groups']) || $group === null && $is_first_group || $group === $query->group) {
                    $content = $this->renderResultsList($query, $data, $args);
                }
                $is_first_group = false;
                return [
                    'label' => $this->renderTabLabel($query, $data, $args),
                    'link' => $this->getTabLink($query, $args),
                    'active' => $group !== null && $group === $query->group,
                    'content' => $content,
                ];
            }, $query->items), $data, $args);
        } else {
            $results_list = $this->renderResultsList($query, $data, $args);
        }

        // Final markup for results.
        $results = \ProcessWire\wirePopulateStringTags(
            sprintf(
                $args['templates']['results'],
                $this->renderResultsListHeading($query, $args)
              . $results_list
            ),
            $data
        );

        return $results;
    }

    /**
     * Get tab link
     *
     * @param Query $query
     * @param array $args
     * @return string|null
     */
    protected function getTabLink(Query $query, array $args): ?string {

        // if autoload_result_groups is enabled, link is not needed
        if (!empty($args['autoload_result_groups'])) {
            return null;
        }

        // get whitelisted params
        $params = $this->wire('input')->whitelist->getArray();

        // merge Query group with whitelited params
        if (!empty($args['find_args']['group_param'])) {
            unset($params[$args['find_args']['group_param']]);
            if ($query->group !== '') {
               $params = array_merge($params, [
                    $args['find_args']['group_param'] => $this->wire('sanitizer')->text($query->group),
                ]);
            }
        }

        // construct and return tab link
        return $this->wire('page')->url . $this->wire('input')->urlSegmentStr() . '?' . implode('&', array_filter(array_map(function($key, $value) {
            if (\is_string($value)) {
                return $key . '=' . urlencode($value);
            } else if (\is_array($value)) {
                return implode('&', array_map(function($value) use ($key) {
                    return $key . '[]=' . urlencode($value);
                }, array_filter($value)));
            }
            return '';
        }, array_keys($params), $params)));
    }

    /**
     * Render markup for a list of search results
     *
     * @param Query $query
     * @param Data $data
     * @param array $args
     * @return string
     */
    protected function renderResultsList(Query $query, Data $data, array $args): string {

        $results_list = '';

        // Bail out early if we don't have any results.
        if ($query->resultsCount === 0) {
            return $this->renderResultsListSummary('none', $query, $args);
        }

        // Render summary for results.
        $results_list .= $this->renderResultsListSummary($query->resultsTotal === 1 ? 'one' : 'many', $query, $args);

        // Prepare for grouping results.
        $groups = [];
        $group_by = $data['results_grouped_by'] ?? null;

        // Render individual list items.
        foreach ($query->results as $result) {
            $group_name = null;
            if ($group_by !== null && strpos($group_by, '.')) {
                $group_name = (string) $result->getDot($group_by);
            } else if ($group_by !== null) {
                $group_name = (string) $result->get($group_by);
            }
            if (!isset($groups[$group_name])) {
                $groups[$group_name] = [
                    'label' => $group_name,
                    'items' => [],
                ];
            }
            $groups[$group_name]['items'][] = sprintf(
                $data['templates']['results_list_item'],
                $this->renderResult($result, $data, $query)
            );
        }

        // Render combined results list(s).
        $results_list_group = '';
        foreach ($groups as $group_name => $group) {

            // Render results list group heading (if applicable)
            if ($group_by !== null && $group_name != $results_list_group) {
                $results_list_group = $group_name;
                $results_list .= sprintf(
                    $data['templates']['results_list_group_heading'],
                    $group['label']
                );
            }

            // Render wrapper for the results list.
            $results_list .= sprintf(
                $data['templates']['results_list'],
                implode($group['items'])
            );
        }

        // Render pager.
        if (!empty($args['pager'])) {
            $results_list .= $this->renderPager($args, $query);
        }

        return $results_list;
    }

    /**
     * Render markup for a results list heading
     *
     * @param Query|QuerySet $query
     * @param array $args
     * @return string
     */
    protected function ___renderResultsListHeading(QueryBase $query, array $args): string {
        return sprintf(
            $args['templates']['results_heading'],
            $args['strings']['results_heading']
        );
    }

    /**
     * Render markup for a results list summary
     *
     * @param string $type Type of summary (none, one, many).
     * @param Query $query
     * @param array $args
     * @return string
     */
    protected function ___renderResultsListSummary(string $type, Query $query, array $args): string {
        return sprintf(
            $args['templates']['results_summary'],
            vsprintf($args['strings']['results_summary_' . $type] ?? '', [
                trim($query->display_query, '\"'),
                $query->resultsTotal,
            ])
        );
    }

    /**
     * Render tabs
     *
     * @param string $name
     * @param array $tabs
     * @param Data|null $data
     * @param array|null $args
     * @return string
     */
    public function renderTabs(string $name, array $tabs, ?Data $data = null, ?array $args = null): string {
        $args = $args ?? $this->prepareArgs();
        $data = $data ?? $this->getData($args);
        $tab_list = [];
        $has_active_tab = false;
        foreach ($tabs as $tab) {
            if (empty($tab['active'])) continue;
            $has_active_tab = true;
            break;
        }
        foreach ($tabs as $key => $tab) {
            if ($has_active_tab === false) {
                $tab['active'] = true;
                $has_active_tab = true;
            }
            $tab_list[] = sprintf(
                $args['templates']['tabs_tablist-item'],
                sprintf(
                    $args['templates']['tabs_tab'],
                    $tab['link'] ?? '#pwse-tabpanel-' . $key . '-' . $key,
                    'pwse-tab-' . $key . '-' . $key,
                    empty($tab['active']) ? '' : ' aria-selected="true"',
                    $tab['label']
                )
            );
        }
        $tab_panels = [];
        foreach ($tabs as $key => $tab) {
            $tab_panels[] = sprintf(
                $args['templates']['tabs_tabpanel'],
                'pwse-tabpanel-' . $key . '-' . $key,
                $tab['content']
            );
        }
        return \ProcessWire\wirePopulateStringTags(
            sprintf(
                $args['templates']['tabs'],
                'pwse-tabs-' . $name,
                sprintf(
                    $args['templates']['tabs_tablist'],
                    implode($tab_list)
                )
                . implode($tab_panels)
            ),
            $data
        );
    }

    /**
     * Render tab label
     *
     * @param Query $query
     * @param Data $data
     * @param array $args
     * @return string
     */
    protected function ___renderTabLabel(Query $query, Data $data, array $args): string {
        return $query->label ?: $this->_('Label');
    }

    /**
     * Render search results as JSON
     *
     * @param array $args Optional arguments.
     * @param QueryBase|null $query Optional prepopulated Query object, a QuerySet containing one or more Query objects, or null.
     * @return string Results as JSON
     */
    public function ___renderResultsJSON(array $args = [], QueryBase $query = null): string {

        // Prepare args, options, and return value placeholder.
        $args = $this->prepareArgs($args);
        $options = $this->getOptions();
        $results = [];

        // If query is null, fetch results automatically.
        if (is_null($query)) {
            $results['query'] = $this->wire('input')->get($options['find_args']['query_param']);
            if (!empty($results['query'])) {
                $query = $this->wire('modules')->get('SearchEngine')->find($results['query'], $options['find_args']);
            }
        }

        // If provided a QuerySet instead of a single Query, render nested structure.
        if ($query instanceof QuerySet) {
            $results['items'] = [];
            foreach ($query as $key => $subquery) {
                if (!empty($subquery->results)) {
                    $results['items'][$key] = [
                        'results' => [],
                        'count' => $subquery->resultsCount,
                        'total' => $subquery->resultsTotal,
                        'label' => $subquery->label,
                    ];
                    foreach ($subquery->results as $result) {
                        $results['items'][$key]['results'][] = array_map(function($field) use ($result, $options, $subquery) {
                            return $this->getResultValue($result, $field, $subquery, $options['index_field']);
                        }, $args['results_json_fields']);
                    }
                }
            }
            $results += [
                'count' => $query->resultsCount,
                'total' => $query->resultsTotal,
                'results_grouped_by' => $query->resultsGroupedBy,
            ];
            return json_encode($results, $args['results_json_options']);
        }

        // Populate results data.
        if (!empty($query->results)) {
            $results['results'] = [];
            foreach ($query->results as $result) {
                $results['results'][] = array_map(function($field) use ($result, $options, $query) {
                    return $this->getResultValue($result, $field, $query, $options['index_field']);
                }, $args['results_json_fields']);
            }
            $results['count'] = $query->resultsCount;
            $results['total'] = $query->resultsTotal;
        }

        return json_encode($results, $args['results_json_options']);
    }

    /**
     * Render a single search result
     *
     * @param Page $result Single result object.
     * @param Data $data Options as a Data object.
     * @param Query $query Query object.
     * @return string
     */
    protected function ___renderResult(Page $result, Data $data, Query $query): string {
        return \ProcessWire\wirePopulateStringTags(
            sprintf(
                $data['templates']['result'],
                $data['templates']['result_link']
              . $data['templates']['result_path']
              . $this->renderResultDesc($result, $data, $query)
            ),
            $data->set('item', $result)
        );
    }

    /**
     * Render a single search result description
     *
     * @param Page $result Single result object.
     * @param Data $data Options as a Data object.
     * @param Query $query Query object.
     * @return string
     */
    protected function ___renderResultDesc(Page $result, Data $data, Query $query): string {
        $value = $this->getResultValue($result, $data['result_summary_field'], $query, $data['index_field'] ?: null);
        if (!is_string($value)) {
            if (!$this->canBeString($value)) return '';
            $value = (string) $value;
        }
        if (!empty($value)) {
            // Note: text sanitizer has maxLength of 255 by default. This currently limits the max length of the
            // description text, and also needs to be taken into account for in the getResultAutodesc() method.
            $value = $this->wire('sanitizer')->text($value);
            $value = $this->maybeHighlight($value, $query->display_query, $data);
            $value = sprintf($data['templates']['result_desc'], $value);
        }
        return $value;
    }

    /**
     * Check if a value can be converted to string
     *
     * @see https://stackoverflow.com/questions/5496656/check-if-item-can-be-converted-to-string
     *
     * @param $value mixed
     * @return bool
     */
    protected function canBeString($value): bool {
        return (!is_array($value) && !is_object($value) && settype($value, 'string') !== false) ||
               (is_object($value) && method_exists($value, '__toString' ));
    }

    /**
     * Return a value from result (Page) field
     *
     * This method also supports fallback values, as well as dynamic "pseudo" fields such as "_auto_desc".
     *
     * @param Page $result
     * @param string $field
     * @param Query $query
     * @param string $index_field Optional index field name.
     * @return mixed
     */
    protected function getResultValue(Page $result, string $field, Query $query, string $index_field = null) {
        $value = '';
        $fields = [$field];
        if (strpos($fields[0], '|') !== false) {
            // Note: custom fallback field logic is necessary because otherwise our dynamic fields wouldn't work as
            // expected; ProcessWire has no knowledge of these values, after all, so they'd likely always be null.
            $fields = explode('|', $fields[0]);
        }
        $value = '';
        foreach ($fields as $field) {
            if ($field === '_auto_desc') {
                if (is_null($index_field)) {
                    $index_field = $this->getOptions()['index_field'];
                }
                $value = $this->getResultAutoDesc($result, $query, $index_field);
            } else if (strpos($field, 'template.') === 0) {
                $value = $result->template->get(substr($field, 9));
            } else if (strpos($field, 'parent.') === 0) {
                $value = $result->parent->get(substr($field, 7));
            } else {
                $value = $result->get($field);
            }
            if (!empty($value)) break;
        }
        return $value;
    }

    /**
     * Get an automatically generated description for a single search result
     *
     * @param Page $result Single result object.
     * @param Query $query Query object.
     * @param string $index_field Index field.
     * @return string
     */
    protected function getResultAutoDesc(Page $result, Query $query, string $index_field): string {
        $desc = '';
        $index = $result->get($index_field) ?? '';
        if (!empty($index)) {
            $desc_max_length = 255;
            $desc_sep_length = 3;
            $query_string = trim($query->query, '"');
            $query_string_quoted = preg_quote($query_string, '/');
            $desc_padding = round(($desc_max_length - ($desc_sep_length * 2) - mb_strlen($query_string) - 2) / 2);
            $index = preg_split('/\r\n|\n/u', $index)[0];
            if (preg_match('/.{0,' . $desc_padding . '}(?:\b' . $query_string_quoted . '\b|' . $query_string_quoted . ').{0,' . $desc_padding . '}/ui', $index, $matches)) {
                // There's a match (exact or partial) for the query string in the index.
                $desc = $this->formatResultAutodesc($matches[0], $index, $desc);
            } else if (mb_strpos($query_string, ' ') !== false) {
                // Query string has multiple words, look for partial matches.
                $desc_length = 0;
                $desc_padding = 50;
                $match_offset = 0;
                $query_string = array_map(function($value) {
                    return preg_quote($value, '/');
                }, array_unique(array_filter(explode(' ', str_replace([',', '.'], '', $query_string)))));
                $query_string_parts = implode('|', $query_string);
                $query_string_exact = implode('|', array_map(function($value) {
                    return "\b" . $value . "\b";
                }, $query_string));
                while ($desc_length < $desc_max_length) {
                    if (!preg_match('/.{0,' . $desc_padding . '}(' . $query_string_exact . '|' . $query_string_parts . ').{0,' . $desc_padding . '}/ui', $index, $matches, \PREG_OFFSET_CAPTURE, $match_offset)) {
                        // No more matches found, break out of the while loop.
                        break;
                    }
                    $desc_part = $this->formatResultAutoDesc($matches[0][0], $index, $desc);
                    $desc_part_length = mb_strlen($desc_part);
                    $desc_length += $desc_part_length;
                    if ($desc_length > $desc_max_length) {
                        $desc_part = mb_substr($desc_part, 0, $desc_part_length - ($desc_length - $desc_max_length) - $desc_sep_length);
                        if (!preg_match('/' . $query_string_parts . '/ui', $desc_part)) {
                            // Drop last desc part if it doesn't contain our query string.
                            break;
                        }
                        $desc_part = $this->formatResultAutoDesc($desc_part, $index, $desc);
                    } else {
                        $match_offset = $matches[0][1] + mb_strlen($matches[0][0]);
                    }
                    $desc .= $desc_part;
                }
            }
        }
        return $desc;
    }

    /**
     * Format an automatically generated description match
     *
     * @param string $match
     * @param string $index
     * @param string $desc
     * @return string
     */
    protected function formatResultAutoDesc(string $match, string $index, string $desc): string {

        // Bail out early if match is empty.
        if ($match === '') return '';

        // Remove scraps of HTML entities from the end of a strings.
        $match = rtrim(preg_replace('/(?:<(?!.+>)|&(?!.+;)).*$/us', '', $match));
        if ($match === '') return '';

        // Add prefix/suffix if necessary.
        $match_length = mb_strlen($match);
        $add_prefix = (empty($desc) || mb_substr($desc, -3) !== '...') && (mb_strpos($match, '...') === 0 || mb_substr($index, 0, $match_length) !== $match);
        $add_suffix = mb_substr($index, -$match_length) !== $match || mb_strrpos($match, '.') !== $match_length;
        $match = ($add_prefix ? '...' . $match : $match) . ($add_suffix ? '...' : '');

        return $match;
    }

    /**
     * Render a pager for search results
     *
     * @param array $args Arguments.
     * @param Query Query object.
     * @return string Rendered pager markup.
     */
    public function renderPager(array $args, Query $query): string {

        // Return empty string if Query has no results.
        if ($query->results === null) {
            return '';
        }

        // If pager_args *haven't* been overridden in the args array, we can fetch the pager from
        // the Query object, where it could already be cached.
        return empty($args['pager_args']) ? $query->pager : $query->results->renderPager($args['pager_args']);
    }

    /**
     * Render error messages
     *
     * @param array $args Array of prepared arguments.
     * @param QueryBase Query object.
     * @return string Error messages, or empty string if none found.
     */
    protected function ___renderErrors(array $args, QueryBase $query): string {
        $errors = '';
        if (!empty($query->errors)) {
            $options = $this->getOptions();
            $strings = $this->getStrings($args['strings']);
            $errors_heading = sprintf(
                $options['render_args']['templates']['errors_heading'],
                $strings['errors_heading']
            );
            foreach ($query->errors as $error) {
                $errors .= sprintf($options['render_args']['templates']['errors_list-item'], $error);
            }
            $errors = \ProcessWire\wirePopulateStringTags(
                sprintf(
                    $options['render_args']['templates']['errors'],
                    $errors_heading
                  . sprintf(
                        $options['render_args']['templates']['errors_list'],
                        $errors
                    )
                ),
                $this->getData($args)
            );
        }
        return $errors;
    }

    /**
     * Get stylesheet filenames for a given theme
     *
     * This is an alias for getResources().
     *
     * @param array $args Optional arguments.
     * @return array Stylesheet filenames as an array.
     */
    public function getStyles(array $args = []): array {
        return $this->getResources($args, 'styles');
    }

    /**
     * Render link tags for stylesheet(s) of a given theme
     *
     * This is an alias for renderResources().
     *
     * @param array $args Optional arguments.
     * @return string Stylesheet tag(s).
     */
    public function renderStyles(array $args = []): string {
        return $this->renderResources($args, 'styles');
    }

    /**
     * Get script filenames for a given theme
     *
     * This is an alias for getResources().
     *
     * @param array $args Optional arguments.
     * @return array Script filenames as an array.
     */
    public function getScripts(array $args = []): array {
        return $this->getResources($args, 'scripts');
    }

    /**
     * Render script tags for a given theme
     *
     * This is an alias for renderResources().
     *
     * @param array $args Optional arguments.
     * @return string Script tag(s).
     */
    public function renderScripts(array $args = []): string {
        return $this->renderResources($args, 'scripts');
    }

    /**
     * Get resources of specified type for a given theme
     *
     * @param array $args Arguments.
     * @param string $type Type of returned resources (styles or scripts).
     * @return array Filenames as an array.
     *
     * @throws WireException if theme isn't found.
     */
    protected function getResources(array $args, string $type): array {

        // Prepare args.
        $args = $this->prepareArgs($args);
        $theme = $args['theme'];

        // Placeholder for returned resources.
        $resources = [];

        // If this is a JavaScript resource request, check if the core library should be loaded.
        if ($type === 'scripts' && !empty($args['find_args']['group_by'])) {
            $resources[] = $this->wire('config')->urls->get('SearchEngine') . 'js/dist/main.js';
        }

        // Bail out early if theme isn't defined or theme doesn't contain specified resources.
        if (empty($theme) || empty($args['theme_' . $type])) {
            return $resources;
        }

        // Append theme specific resources.
        $minified_resources = $args['minified_resources'];
        $resources = array_merge($resources, array_map(function($resource) use ($theme, $minified_resources) {
            $file = $resource['name'] . ($minified_resources ? '.min' : '') . '.' . $resource['ext'];
            return $this->themeURL . $theme . '/' . basename($file);
        }, $args['theme_' . $type]));

        return $resources;
    }

    /**
     * Render markup for including resources of a specific type from given theme
     *
     * @param array $args Arguments.
     * @param string $type Type of returned resources (styles or scripts).
     * @param string $template Template to wrap resource filename with.
     * @return string Markup for embedding resources.
     */
    protected function renderResources(array $args, string $type): string {

        // Prepare args.
        $args = $this->prepareArgs($args);

        // Get, render, and return resources.
        $resources = $this->getResources($args, $type);
        if (empty($resources)) {
            return '';
        }
        $template = $args['templates'][$type];
        return implode(array_map(function($resource) use ($template) {
            return sprintf($template, $resource);
        }, $resources));
    }

    /**
     * Render the whole search feature (styles, scripts, form, results, and pager)
     *
     * Note that you may omit the first param ($what) and instead provide the args array as the
     * first param.
     *
     * @param array $what Optional array of elements to render, or the args array. If used as the "what" array, may contain one or more of:
     *   - 'styles'
     *   - 'scripts'
     *   - 'form'
     *   - 'results'
     * @param array $args Optional arguments. If the "what" array is omitted, you may provide the "args" array as the first param instead.
     * @return string
     */
    public function ___render(array $what = [], array $args = []): string {

        // Optionally allow providing args as the first param. Since "what" will only have numeric
        // keys and "args" will only have non-numeric keys, we can easily check which is which.
        if (!is_int(key($what))) {
            $args = $what;
            $what = [];
        }

        // Add all options to the "what" array if it's empty.
        if (empty($what)) {
            $what = [
                'styles',
                'scripts',
                'form',
                'results',
            ];
        }

        // Prepare args.
        $args = $this->prepareArgs($args);
        $theme = $args['theme'];

        // Render and return rendered markup.
        $results = in_array('results', $what) ? $this->renderResults($args) : '';
        $form = in_array('results', $what) ? $this->renderForm($args) : '';
        return implode([
            $theme && in_array('styles', $what) ? $this->renderStyles($args) : '',
            $theme && in_array('scripts', $what) ? $this->renderScripts($args) : '',
            $form,
            $results,
        ]);
    }

    /**
     * Highlight query string in given string (description text)
     *
     * @param string $string Original string.
     * @param string $query Query as a string.
     * @param Data $data Predefined Data object.
     * @return string String with highlights, or the original string if no matches found.
     */
    protected function maybeHighlight(string $string, string $query, Data $data): string {

        // Bail out early if highlighting is disabled.
        if (!$data['results_highlight_query']) return $string;

        // Clean up quotes and spaces from the start and end of the (sanitized) query string.
        $query_string = trim($query, '" ');

        // Check if there are instances that can be highlighted.
        if (mb_stripos($string, $query_string) !== false) {
            $string = preg_replace(
                '/' . preg_quote($query_string, '/') . '/ui',
                sprintf(
                    $data['templates']['result_highlight'],
                    '$0'
                ),
                $string
            );
        } else if (mb_strpos($query_string, ' ') !== false) {
            $query_words = implode('|', array_map(function($value) {
                return preg_quote($value, '/');
            }, array_unique(array_filter(explode(' ', $query_string)))));
            $string = preg_replace(
                '/(' . $query_words . ')/ui',
                sprintf(
                    $data['templates']['result_highlight'],
                    '$0'
                ),
                $string
            );
        }

        return $string;
    }

    /**
     * Prepare arguments for use
     *
     * This method takes render args defined via configuration settings etc. and combines them with
     * provided array of custom arguments. Args required in this class are primarily based on the
     * render_args setting, but for convenience we're also merging in the find_args setting.
     *
     * @param array $args Original arguments array.
     * @return array Prepared arguments array.
     *
     * @throws WireException if theme is defined but not fully functional.
     */
    protected function prepareArgs(array $args = []): array {

        // Bail out early if args have already been prepared. This is mainly an optimization for
        // cases where the "args" array gets passed internally from method to method.
        if (!empty($args['_prepared'])) {
            return $args;
        }

        // Get run-time options.
        $options = $this->getOptions();

        // Merge default render arguments with provided custom values.
        $args = array_replace_recursive($options['render_args'], $args);

        // Merge theme config with custom values.
        if (!empty($args['theme'])) {
            $args['theme'] = basename($args['theme']);
            $theme_args_file = $this->themePath . $args['theme'] . '/config.php';
            $theme_init_done = false;
            if (is_file($theme_args_file)) {
                include $theme_args_file;
                if (!empty($theme_args)) {
                    // Theme config succesfully loaded.
                    if (!empty($theme_args['render_args'])) {
                        $args = array_replace_recursive($args, $theme_args['render_args']);
                    }
                    if (!empty($theme_args['pager_args'])) {
                        $args['pager_args'] = empty($args['pager_args']) ? $theme_args['pager_args'] : array_replace_recursive(
                            $args['pager_args'],
                            $theme_args['pager_args']
                        );
                    }
                    $args['theme_styles'] = $theme_args['theme_styles'] ?? [];
                    $args['theme_scripts'] = $theme_args['theme_scripts'] ?? [];
                    $theme_init_done = true;
                }
            }
            if (!$theme_init_done) {
                throw new WireException(sprintf(
                    $this->_('Unable to init theme "%s".'),
                    $args['theme']
                ));
            }
        }

        // Merge default string values with provided custom strings.
        $args['strings'] = $this->getStrings($args['strings']);

        // Add requirements to args array if not already present.
        if (empty($args['requirements'])) {
            $args['requirements'] = $options['requirements'];
        }

        // Add find arguments to args array if not already present.
        if (empty($args['find_args'])) {
            $args['find_args'] = $options['find_args'];
        }

        // Prefill form input value if query param has been whitelisted.
        if (empty($args['strings']['form_input_value'])) {
            $args['strings']['form_input_value'] = $this->wire('input')->whitelist($options['find_args']['query_param']);
        }

        // Add a flag to signal that the args array has been prepared.
        $args['_prepared'] = true;

        return $args;
    }

    /**
     * Convert arguments array to ready-to-use Data object
     *
     * The main purpose of this method is to enable populating string tags recursively from the
     * provided arguments using \ProcessWire\wirePopulateStringTags().
     *
     * @param array $args Arguments to build Data object from.
     * @return Data Data object.
     */
    protected function getData(array $args = []): Data {

        // Convert subarrays to Data objects.
        foreach (['strings', 'find_args', 'requirements', 'classes'] as $key) {
            $args[$key] = $this->wire(new Data($args[$key] ?? []));
        }

        // Additional sanitization for strings.
        foreach ($args['strings'] as $key => $string) {
            $args['strings'][$key] = $string === null ? null : trim($string, "\"");
        }

        // Replace parent selectors (ampersands, after SCSS syntax) in class names. Keys without
        // underscores are considered parents – or blocks, if you prefer BEM terminology.
        $parents = [];
        foreach ($args['classes'] as $key => $class) {
            $class = trim($class);
            if (strpos($key, '_') === false) {
                // Note: in case the class option contains multiple space-separated values, we use
                // the first one only.
                $space_pos = strpos($class, ' ');
                if ($space_pos !== false) {
                    $class = substr($class, 0, $space_pos);
                }
                $parents[$key] = $class;
            }
        }
        if (!empty($parents)) {
            foreach ($args['classes'] as $key => $class) {
                if (strpos($class, '&') !== false) {
                    $underscore_pos = strpos($key, '_');
                    $parent_class = $parents[$underscore_pos ? substr($key, 0, $underscore_pos) : $key] ?? '';
                    $args['classes'][$key] = str_replace('&', $parent_class, $class);
                }
            }
        }

        return new Data($args);
    }

    /**
     * Get a named string from the strings array
     *
     * @param string $name String name.
     * @return string|null String value, or null if string doesn't exist and fallback isn't provided.
     */
    protected function getString(string $name, string $fallback = null): ?string {
        $string = $this->getOptions()['render_args']['strings'][$name] ?? $fallback;
        return $string;
    }

    /**
     * Magic getter method
     *
     * This method is added so that we can provide alternatives and aliases for certain properties
     * and methods (or their formatted/rendered versions).
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'form':
                return $this->renderForm();
                break;
            case 'inputfieldForm':
                return $this->renderInputfieldForm();
                break;
            case 'results':
                return $this->renderResults();
                break;
            case 'styles':
                return $this->renderStyles();
                break;
            case 'scripts':
                return $this->renderScripts();
                break;
        }
        return $this->$name;
    }

}
