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
 * @version 0.4.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
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
        $this->themePath = $this->wire('config')->paths->get('SearchEngine') . 'themes/';
        $this->themeURL = $this->wire('config')->urls->get('SearchEngine') . 'themes/';
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
     * Render a list of search results
     *
     * @param array $args Optional arguments.
     * @param Query|null $query Optional prepopulated Query object.
     * @return string
     *
     * @throws WireException if query parameter is unrecognized. 
     */
    public function ___renderResults(array $args = [], Query $query = null): string {

        // Prepare args and get Data object.
        $args = $this->prepareArgs($args);
        $options = $this->getOptions();
        $data = $this->getData($args);

        // If query is null, fetch results automatically.
        if (is_null($query)) {
            $query_term = $this->wire('input')->get($options['find_args']['query_param']);
            if (!empty($query_term)) {
                $query = $this->wire('modules')->get('SearchEngine')->find($query_term, $options['find_args']);
            }
        }

        // Bail out early if not provided with – and unable to fetch automatically – results.
        if (!$query instanceof Query) {
            return '';
        }

        // Bail out early if there were errors with the query.
        if (!empty($query->errors)) {
            return $this->renderErrors($args, $query);
        }

        // Header for results.
        $results_heading = sprintf(
            $args['templates']['results_heading'],
            $args['strings']['results_heading']
        );

        // Summary for results.
        $results_summary_type = empty($query->results) ? 'none' : (count($query->results) > 1 ? 'many' : 'one');
        $results_summary_text = $args['strings']['results_summary_' . $results_summary_type];
        $results_summary = sprintf(
            $args['templates']['results_summary'],
            vsprintf($results_summary_text, [
                trim($query->query, '\"'),
                $query->resultsTotal,
            ])
        );

        // Results list.
        $results_list = '';
        if ($results_summary_type !== 'none') {
            $results_list_items = '';
            foreach ($query->results as $result) {
                $results_list_items .= sprintf(
                    $args['templates']['results_list_item'],
                    $this->renderResult($result, $data, $query)
                );
            }
            $results_list = sprintf(
                $args['templates']['results_list'],
                $results_list_items
            );
        }

        // Results pager.
        $results_pager = '';
        if ($results_summary_type !== 'none' && $args['pager']) {
            $results_pager = $this->renderPager($args, $query);
        }

        // Final markup for results.
        $results = \ProcessWire\wirePopulateStringTags(
            sprintf(
                $args['templates']['results'],
                $results_heading
              . $results_summary
              . $results_list
              . $results_pager
            ),
            $data
        );

        return $results;
    }

    /**
     * Render search results as JSON
     *
     * @param array $args Optional arguments.
     * @param Query|null $query Optional prepopulated Query object.
     * @return string Results as JSON
     */
    public function ___renderResultsJSON(array $args = [], Query $query = null): string {

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

        // Populate results data.
        if (!empty($query->results)) {
            $results['results'] = [];
            foreach ($query->results as $result) {
                $results['results'][] = array_map(function($field) use ($result) {
                    if (strpos($field, 'template.') === 0) {
                        return $result->template->get(substr($field, 9));
                    } else if (strpos($field, 'parent.') === 0) {
                        return $result->parent->get(substr($field, 7));
                    } else {
                        return $result->get($field);
                    }
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
        $desc = $result->get($data['result_summary_field']) ?? '';
        if (!empty($desc)) {
            $desc = $this->wire('sanitizer')->text($desc);
            $desc = $this->maybeHighlight($desc, $query->query, $data);
            $desc = sprintf($data['templates']['result_desc'], $desc);
        }
        return $desc;
    }

    /**
     * Render a pager for search results
     *
     * @param array $args Optional arguments.
     * @param Query Query object.
     * @return string Rendered pager markup.
     */
    public function renderPager(array $args = [], Query $query): string {

        // If pager_args *haven't* been overridden in the args array, we can fetch the pager from
        // the Query object, where it could already be cached.
        return !empty($args['pager_args']) ? $query->results->renderPager($args['pager_args']) : $query->pager;
    }

    /**
     * Render error messages
     *
     * @param array $args Array of prepared arguments.
     * @param array $errors Array of error messages.
     * @return string Error messages, or empty string if none found.
     */
    protected function ___renderErrors(array $args, Query $query): string {
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
     * @param array $args Optional arguments.
     * @param string $type Type of returned resources (styles or scripts).
     * @return array Filenames as an array.
     *
     * @throws WireException if theme isn't found.
     */
    protected function getResources(array $args = [], string $type): array {

        // Prepare args.
        $args = $this->prepareArgs($args);
        $theme = $args['theme'];

        // Bail out early if theme isn't defined.
        if (empty($theme)) {
            return [];
        }

        // Get and return resources.
        $resources = $args['theme_' . $type] ?? [];
        if (empty($resources)) {
            return [];
        }
        $minified = $args['minified_resources'];
        return array_map(function($resource) use ($theme, $minified) {
            $file = $resource['name'] . ($minified ? '.min' : '') . '.' . $resource['ext'];
            return $this->themeURL . $theme . '/' . basename($file);
        }, $resources);
    }

    /**
     * Render markup for including resources of a specific type from given theme
     *
     * @param array $args Optional arguments.
     * @param string $type Type of returned resources (styles or scripts).
     * @param string $template Template to wrap resource filename with.
     * @return string Markup for embedding resources.
     */
    protected function renderResources(array $args = [], string $type): string {

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
        $results = $this->renderResults($args);
        $form = $this->renderForm($args);
        return implode([
            $theme && in_array('styles', $what) ? $this->renderStyles($args) : '',
            $theme && in_array('scripts', $what) ? $this->renderScripts($args) : '',
            in_array('form', $what) ? $form : '',
            in_array('results', $what) ? $results : '',
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
        if ($data['results_highlight_query'] && stripos($string, $query)) {
            $string = preg_replace(
                '/' . preg_quote($query, '/') . '/i',
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
            $args[$key] = $this->wire(new Data($args[$key]));
        }

        // Additional sanitization for strings.
        foreach ($args['strings'] as $key => $string) {
            $args['strings'][$key] = trim($string, "\"");
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
