<?php

namespace SearchEngine;

use \ProcessWire\Inputfield,
    \ProcessWire\Page;

/**
 * SearchEngine Renderer
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Renderer extends Base {

    /**
     * Default string values
     *
     * These get populated in the constructor method.
     *
     * @var array
     */
    protected $defaultStrings = [];

    /**
     * Constructor method
     */
    public function __construct() {
        parent::__construct();
        $this->defaultStrings = [
            'form_label' => $this->_x('Search', 'input label'),
            'form_input_placeholder' => $this->_('Search the site...'),
            'form_submit' => $this->_x('Search', 'submit button text'),
            'results_heading' => $this->_('Search results'),
            'results_summary_one' => $this->_('One result for "%s":'),
            'results_summary_many' => $this->_('%2$d results for "%1$s":'),
            'results_summary_none' => $this->_('No results for "%s".'),
        ];
    }

    /**
     * Render a search form
     *
     * @param array $args Optional arguments.
     * @return string Markup for the search form.
     */
    public function ___renderForm(array $args = []): string {

        // Prepare options array.
        $options = $this->prepareRenderOptions($args);

        // Render search form.
        $form_content = sprintf(
            $options['templates']['form'],
            $options['templates']['form_label']
          . $options['templates']['form_input']
          . $options['templates']['form_submit']
        );

        // Replace placeholders (string tags).
        $form = \ProcessWire\wirePopulateStringTags($form_content, $options);

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
        $options = array_replace_recursive($this->options['render_args'], $args);

        // Search form.
        $form = $modules->get('InputfieldForm');
        $form->method = 'GET';
        $form->id = $options['form_id'];
        $form_action = $options['form_action'];
        if ($form_action instanceof Page) {
            $form_action = $form_action->path;
        }
        $form->action = $form_action;

        // Query (text) field.
        $input = $modules->get('InputfieldText');
        $input->name = $this->options['find_args']['query_param'] ?? 'q';
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
     * Render a list of search results
     *
     * @param array $args Optional arguments.
     * @param Query|null $query Optional prepopulated Query object.
     * @return string
     *
     * @throws WireException if query parameter is unrecognized. 
     */
    public function ___renderResultsList(array $args = [], Query $query = null): string {

        // Prepare options.
        $options = $this->prepareRenderOptions($args);

        // If query is null, fetch results automatically.
        if (is_null($query)) {
            $query_term = $this->wire('input')->get($this->options['find_args']['query_param']);
            if (!empty($query_term)) {
                $query = $this->wire('modules')->get('SearchEngine')->find($query_term, $this->options['find_args']);
            }
        }

        // Bail out early if not provided with – and unable to fetch automatically – results.
        if (!$query instanceof Query) {
            return '';
        }

        // Header for results.
        $results_heading = sprintf(
            $options['templates']['results_heading'],
            $options['strings']['results_heading']
        );

        // Summary for results.
        $results_summary_type = empty($query->results) ? 'none' : (count($query->results) > 1 ? 'many' : 'one');
        $results_summary_text = $options['strings']['results_summary_' . $results_summary_type];
        $results_summary = sprintf(
            $options['templates']['results_summary'],
            vsprintf($results_summary_text, [$query->query, $query->resultsTotal])
        );

        // Results list.
        $results_list = '';
        if ($results_summary_type !== 'none') {
            $results_list_items = '';
            foreach ($query->results as $result) {
                $results_list_items .= sprintf(
                    $options['templates']['results_list_item'],
                    $this->renderResult($result, $options, $query)
                );
            }
            $results_list = sprintf(
                $options['templates']['results_list'],
                $results_list_items
            );
        }

        // Final markup for results.
        $results = \ProcessWire\wirePopulateStringTags(
            sprintf(
                $options['templates']['results'],
                $results_heading
              . $results_summary
              . $results_list
            ),
            $options
        );

        return $results;
    }

    /**
     * Render a single search result
     *
     * @param Page $result Single result object.
     * @param Data $options Options as a Data object.
     * @param Query $query Query object.
     * @return string
     */
    public function ___renderResult(Page $result, Data $options, Query $query): string {
        return \ProcessWire\wirePopulateStringTags(
            sprintf(
                $options['templates']['result'],
                $options['templates']['result_link']
              . $options['templates']['result_path']
              . $this->renderResultDesc($result, $options, $query)
            ),
            $options->set('item', $result)
        );
    }

    /**
     * Render a single search result description
     *
     * @param Page $result Single result object.
     * @param Data $options Options as a Data object.
     * @param Query $query Query object.
     * @return string
     */
    protected function ___renderResultDesc(Page $result, Data $options, Query $query): string {
        $desc = $result->get($options['result_summary_field']) ?? '';
        if (!empty($desc)) {
            $desc = $this->wire('sanitizer')->text($desc);
            $desc = $this->maybeHighlight($desc, $query->query, $options);
            $desc = sprintf($options['templates']['result_desc'], $desc);
        }
        return $desc;
    }

    /**
     * Render a search form and a list of search results
     *
     * @param array $args Optional arguments.
     * @return string
     */
    public function ___render(array $args = []) {
        $resultsList = $this->renderResultsList($args);
        $form = $this->renderForm($args);
        return $form . $resultsList;
    }

    /**
     * Highlight query string in given string (description text)
     *
     * @param string $string Original string.
     * @param string $query Query as a string.
     * @param Data $options Options as a Data object.
     * @return string String with highlights, or the original string if no matches found.
     */
    protected function maybeHighlight(string $string, string $query, Data $options): string {
        if ($options['results_highlight_query'] && stripos($string, $query)) {
            $string = preg_replace(
                '/' . preg_quote($query, '/') . '/i',
                sprintf(
                    $options['templates']['result_highlight'],
                    '$0'
                ),
                $string
            );
        }
        return $string;
    }

    /**
     * Prepare render options for use
     *
     * @param array $args Optional arguments.
     * @return Data Options object.
     */
    protected function prepareRenderOptions(array $args = []): Data {

        $options = array_replace_recursive($this->options['render_args'], $args);

        // Merge strings with defaults and convert array to a Data object.
        foreach ($this->defaultStrings as $string => $value) {
            if (is_null($options['strings'][$string])) {
                $options['strings'][$string] = $value;
            }
        }
        if (empty($options['strings']['form_input_value'])) {
            $options['strings']['form_input_value'] = $this->wire('input')->whitelist($this->options['find_args']['query_param']);
        }
        $options['strings'] = $this->wire(new Data($options['strings']));

        // Convert find_args to a Data object, adding it first if it doesn't yet exist.
        if (empty($options['find_args'])) {
            $options['find_args'] = $this->options['find_args'];
        }
        $options['find_args'] = $this->wire(new Data($this->options['find_args']));

        // Convert classes to a Data object.
        $options['classes'] = $this->wire(new Data($options['classes']));

        return new Data($options);
    }

    /**
     * Get a named string from the strings array
     *
     * @param string $name String name.
     * @return string|null String value, or null if string doesn't exist and fallback isn't provided.
     */
    protected function getString(string $name, string $fallback = null): ?string {
        $string = $this->options['render_args']['strings'][$name] ?? $fallback;
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
    public function __get(string $name) {
        switch ($name) {
            case 'form':
                return $this->renderForm();
                break;
            case 'inputfieldForm':
                return $this->renderInputfieldForm();
                break;
            case 'resultsList':
                return $this->renderResultsList();
                break;
        }
        return $this->$name;
    }

}
