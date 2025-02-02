<?php

namespace ProcessWire;

/**
 * SearchEngine ProcessWire module
 *
 * SearchEngine is a module that creates a searchable index of site contents and provides you with
 * the tools needed to easily set up a fast and effective site search feature.
 *
 * Methods provided by Indexer:
 *
 * @method int|array indexPages(string $selector = null, bool $save = true, array $args = []) Index multiple pages.
 * @method bool|array indexPage(Page $page, bool $save = true, array $args = []) Index single page.
 *
 * Methods provided by Renderer:
 *
 * @method string renderForm(array $args = []) Render a search form.
 * @method string renderInputfieldForm(array $args = []) Render a search form using InputfieldForm.
 * @method string renderResults(array $args = [], SearchEngine\Query $query = null) Render a list of search results.
 * @method string renderResultsJSON(array $args = [], SearchEngine\Query $query = null) Render a list of search results as JSON.
 * @method string renderPager(array $args = [], SearchEngine\Query $query) Render a pager for search results.
 * @method string renderStyles(array $args = []) Render link tags for stylesheets of a given theme.
 * @method string renderScripts(array $args = []) Render script tags for a given theme.
 * @method string render(array $what = [], array $args = []) Render entire search feature, or optionally just some parts of it (styles, scripts, form, results.)
 *
 * @version 0.38.2
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class SearchEngine extends WireData implements Module, ConfigurableModule {

    /**
     * Default options
     *
     * You can override the defaults by defining an array of custom values in $config->SearchEngine.
     *
     * @var array
     */
    public static $defaultOptions = [
        'index_field' => 'search_index',
        // Optional: separate field used for searching. If not defined, the index field is used.
        'search_field' => null,
        'indexed_fields' => [
            'title',
            'headline',
            'summary',
            'body',
        ],
        'indexer_actions' => [],
        'compatible_fieldtypes' => [
            'FieldtypeEmail',
            'FieldtypeFieldsetPage',
            'FieldtypeDatetime',
            'FieldtypeText',
            'FieldtypeTextLanguage',
            'FieldtypeTextarea',
            'FieldtypeTextareaLanguage',
            'FieldtypePageTitle',
            'FieldtypePageTitleLanguage',
            'FieldtypeCheckbox',
            'FieldtypeInteger',
            'FieldtypeFloat',
            'FieldtypeURL',
            'FieldtypeModule',
            'FieldtypeFile',
            'FieldtypeImage',
            'FieldtypeSelector',
            'FieldtypeOptions',
            'FieldtypeRepeater',
            'FieldtypeRepeaterMatrix',
            'FieldtypePageTable',
            'FieldtypePage',
            'FieldtypeTable',
            'FieldtypeTextareas',
            'FieldtypeCombo',
        ],
        'prefixes' => [
            'id' => ':',
            'link' => 'link:',
            'name' => ':',
        ],
        'find_args' => [
            'limit' => 20,
            'sort' => '',
            'operator' => '*=',
            'selector_extra' => '',
            'query_param' => 'q',
            'group_param' => 't',
            // Supported values for group_by: null (default) and "template".
            'group_by' => null,
            // Optional: values allowed for grouping.
            // Note: if you provide a string value as key, it is considered a group name. In case
            // of templates single group value may contain multiple pipe-separated template names.
            'group_by_allow' => [],
            // Optional: values not allowed for grouping.
            'group_by_disallow' => [],
            // Optional: labels for groups.
            'group_labels' => [],
        ],
        'pager_args' => [
            // These arguments are passed to MarkupPagerNav. You can find more details from the
            // documentation: https://processwire.com/docs/front-end/markup-pager-nav/.
            'listMarkup' => '<div class="search-results-pager"><ul class="search-results-pager__list">{out}</ul></div>',
            'itemMarkup' => '<li class="search-results-pager__list-item {class}">{out}</li>',
            'linkMarkup' => '<a class="search-results-pager__item" href="{url}"><span class="search-results-pager__item-text">{out}</span></a>',
            'currentLinkMarkup' => '<a class="search-results-pager__item search-results-pager__item--current" href="{url}"><span class="search-results-pager__item-text">{out}</span></a>',
            'separatorItemClass' => 'search-results-pager__separator',
            'nextItemClass' => 'search-results-pager__list-item--next',
            'previousItemClass' => 'search-results-pager__list-item--previous',
            'firstItemClass' => 'search-results-pager__item--first',
            'firstNumberItemClass' => 'search-results-pager__item search-results-pager__item--first-num',
            'firstItemClass' => 'search-results-pager__item--first',
            'lastItemClass' => 'search-results-pager__list-item--last',
            'firstNumberItemClass' => 'search-results-pager__list-item--first-num',
            'lastNumberItemClass' => 'search-results-pager__list-item--last-num',
            'currentItemClass' => 'search-results-pager__list-item--current',
        ],
        'render_args' => [
            'theme' => 'default',
            'themes_directory' => null,
            'minified_resources' => true,
            'form_action' => './',
            'form_id' => 'se-form',
            'form_input_id' => 'se-form-input',
            'results_summary_id' => 'se-results-summary',
            'results_id' => 'se-results',
            'result_summary_field' => 'summary',
            'results_highlight_query' => true,
            'results_grouped_by' => null,
            'results_json_fields' => [
                'title' => 'title',
                'desc' => 'summary',
                'url' => 'url',
            ],
            // Autoloading applies to tabbed interface: if enabled, all results will be loaded as
            // soon as possible. This is more resource intensive than loading them one by one.
            'autoload_result_groups' => false,
            'results_json_options' => 0,
            'pager' => true,
            'classes' => [
                // Keys without underscores are considered parents (blocks). If a child class name
                // contains an ampersand (&), it'll be replaced run-time with closest parent class.
                'form' => 'search-form',
                'form_input' => '&__input',
                'form_label' => '&__label',
                'form_label_text' => '&__label-text',
                'form_submit' => '&__submit',
                'form_submit_text' => '&__submit-text',
                'errors' => 'search-errors',
                'errors_heading' => '&__heading',
                'errors_list' => '&__list',
                'errors_list-item' => '&__list-item',
                'results' => 'search-results',
                'results_heading' => '&__heading',
                'results_summary' => '&__summary',
                'results_list' => '&__list',
                'results_list_item' => '&__list-item',
                'results_list_group_heading' => '&__group-heading',
                'result' => 'search-result',
                'result_link' => '&__link',
                'result_path' => '&__path',
                'result_desc' => '&__desc',
                'result_highlight' => '&__highlight',
                'tabs' => 'pwse-tabs',
                'tabs_tablist' => '&__tablist',
                'tabs_tablist-item' => '&__tablist-item',
                'tabs_tab' => '&__tab',
                'tabs_tabpanel' => '&__tabpanel',
            ],
            'strings' => [
                'form_label' => null,
                'form_input_placeholder' => null,
                'form_input_value' => null,
                'form_submit' => null,
                'errors_heading' => null,
                'error_query_missing' => null,
                'error_query_too_short' => null,
                'results_heading' => null,
                'results_summary_one' => null,
                'results_summary_many' => null,
                'results_summary_none' => null,
                'tab_label_all' => null,
            ],
            'templates' => [
                'form' => '<form id="{form_id}" class="{classes.form}" action="{form_action}" role="search">%s</form>',
                'form_label' => '<label for="{form_input_id}" class="{classes.form_label}"><span class="{classes.form_label_text}">{strings.form_label}</span></label>',
                'form_input' => '<input type="search" name="{find_args.query_param}" value="{strings.form_input_value}" minlength="{requirements.query_min_length}" autocomplete="off" placeholder="{strings.form_input_placeholder}" class="{classes.form_input}" id="{form_input_id}">',
                'form_submit' => '<button type="submit" class="{classes.form_submit}"><span class="{classes.form_submit_text}">{strings.form_submit}</span></button>',
                'errors' => '<div class="{classes.errors}">%s</div>',
                'errors_heading' => '<h2 class="{classes.errors_heading}">%s</h2>',
                'errors_list' => '<ul class="{classes.errors_list}">%s</ul>',
                'errors_list-item' => '<li class="{classes.errors_list_item}">%s</li>',
                'results' => '<div id="{results_id}">%s</div>',
                'results_heading' => '<h2 class="{classes.results_heading}">%s</h2>',
                'results_summary' => '<p class="{classes.results_summary}" id="{results_summary_id}">%s</p>',
                'results_list' => '<ul class="{classes.results_list}" aria-labelledby="{results_summary_id}">%s</ul>',
                'results_list_item' => '<li class="{classes.results_list_item}">%s</li>',
                'results_list_group_heading' => '<h3 class="{classes.results_list_group_heading}">%s</h3>',
                'result' => '<div class="{classes.result}">%s</div>',
                'result_link' => '<a class="{classes.result_link}" href="{item.url}">{item.title}</a>',
                'result_path' => '<div class="{classes.result_path}">{item.url}</div>',
                'result_desc' => '<div class="{classes.result_desc}">%s</div>',
                'result_highlight' => '<strong class="{classes.result_highlight}">%s</strong>',
                'tabs' => '<div class="{classes.tabs}" id="%s">%s</div>',
                'tabs_tablist' => '<ul class="{classes.tabs_tablist}" role="tablist">%s</ul>',
                'tabs_tablist-item' => '<li class="{classes.tabs_tablist-item}">%s</li>',
                'tabs_tab' => '<a href="%s" role="tab" id="%s"%s class="{classes.tabs_tab}">%s</a>',
                'tabs_tabpanel' => '<div id="%s" class="{classes.tabs_tabpanel}" role="tabpanel" tabindex="-1">%s</div>',
                'styles' => '<link rel="stylesheet" type="text/css" href="%s">',
                'scripts' => '<script src="%s"></script>',
            ],
        ],
        'requirements' => [
            'query_min_length' => 3,
        ],
    ];

    /**
     * Runtime options, populated in init
     *
     * @var array
     */
    protected $options = [];

    /**
     * An instance of Indexer
     *
     * @var \SearchEngine\Indexer
     */
    protected $indexer;

    /**
     * An instance of Finder
     *
     * @var \SearchEngine\Finder
     */
    protected $finder;

    /**
     * An instance of Renderer
     *
     * @var \SearchEngine\Renderer
     */
    protected $renderer;

    /**
     * Has the module been initialized?
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * The "init" method is executed right after the module has been loaded
     *
     * In this method we add necessary hooks.
     */
    public function init() {

        // Trigger manual indexing when module config is saved
        $this->addHookBefore('Modules::saveModuleConfigData', $this, 'saveConfigData');

        // Update search index when a page is saved. Note: this needs to happen after save (savedPageOrField) instead
        // of before it (savePageOrFieldReady) due to issues related to enabling output formatting state on the fly.
        $this->addHookBefore('Pages::savedPageOrField', $this, 'savePageIndex');
    }

    /**
     * Return inputfields necessary to configure the module
     *
     * @param array $data Data array.
     * @return InputfieldWrapper Wrapper with inputfields needed to configure the module.
     */
    public function getModuleConfigInputfields(array $data) {
        $this->initOnce();
        $config = new \SearchEngine\Config($data);
        $config->validateIndexField();
        return $config->getFields();
    }

    /**
     * This method gets triggered when module config is saved
     *
     * We use this method to perform manual field indexing.
     *
     * @param HookEvent $event
     */
    protected function saveConfigData(HookEvent $event) {

        // Bail out early if saving another module's config
        if ($event->arguments[0] !== $this->className) {
            return;
        }

        // Make sure that the module has been initialized
        $this->initOnce();

        // The config data being saved
        $data = $event->arguments[1];

        // Index field name.
        $index_field = $this->wire('sanitizer')->text($data['index_field'] ?? '');

        // Add/remove the index field to/from templates
        if (!empty($index_field)) {
            $indexed_templates = $data['indexed_templates'];
            foreach ($this->wire('templates') as $template) {
                if ($template->flags & Template::flagSystem) continue;
                $is_indexed_template = !empty($indexed_templates) && in_array($template->name, $indexed_templates);
                if ($is_indexed_template && !$template->hasField($index_field)) {
                    $template->fieldgroup->add($index_field);
                    $template->fieldgroup->save();
                    $this->message(sprintf(
                        $this->_('Index field "%s" was added to template "%s".'),
                        $index_field,
                        $template->name
                    ));
                } else if (!$is_indexed_template && $template->hasField($index_field)) {
                    $template->fieldgroup->remove($index_field);
                    $template->fieldgroup->save();
                    $this->message(sprintf(
                        $this->_('Index field "%s" was removed from template "%s".'),
                        $index_field,
                        $template->name
                    ));
                }
            }
        }

        // Build an index and make sure that the index_pages_now setting doesn't get saved
        if (!empty($data['index_pages_now'])) {
            $indexing_selector = $data['index_pages_now_selector'] ?? null;
            $indexing_started = Debug::timer();
            $indexed_pages = $this->indexer->indexPages($indexing_selector);
            $elapsed_time = Debug::timer($indexing_started);
            if ($indexed_pages === 0) {
                $this->warning(sprintf(
                    $this->_('SearchEngine couldn\'t find any pages to index. Please make sure that your indexing settings are configured properly, and your index field "%s" has been added to at least one template with existing pages.'),
                    $index_field
                ));
            } else {
                $this->message(sprintf(
                    $this->_('%d pages indexed in %s seconds.'),
                    $indexed_pages,
                    $elapsed_time
                ));
            }
            unset($data['index_pages_now']);
            $event->arguments(1, $data);
        }
    }

    /**
     * Generate search index when a page is saved
     *
     * @param HookEvent $event
     */
    protected function savePageIndex(HookEvent $event) {
        $this->initOnce();
        $page = $event->arguments[0];
        $this->indexer->indexPage($page);
    }

    /**
     * Hookable method run right after Page index was saved
     *
     * @param Page $page
     */
    public function ___savedPageIndex(Page $page) {}

    /**
     * Find content matching provided query.
     *
     * This method is a wrapper for \SearchEngine\Finder::find().
     *
     * @param mixed $query The query.
     * @param array $args Additional arguments, see Query::__construct() for details.
     * @return \SearchEngine\Query|\SearchEngine\QuerySet Resulting Query, or QuerySet in case of a grouped result set
     */
    public function find($query = null, array $args = []): \SearchEngine\QueryBase {
        $this->initOnce();
        return $this->finder->find($query, $args);
    }

    /**
     * Initialize the module
     *
     * If the module hasn't been initialized yet, this method will perform the required init setup.
     * This is done in a seprate module to avoid loading or doing unnecessary stuff, since we can't
     * really limit the scope of the module autoload (need to be able to catch any page save, etc.)
     *
     * @return bool True on first run, false if already initialized.
     */
    public function initOnce(): bool {

        // Bail out early if the module has already been initialized
        if ($this->initialized) {
            return false;
        }

        // Init runtime options
        $this->initOptions();

        // Init class autoloader
        $this->wire('classLoader')->addNamespace(
            'SearchEngine',
            $this->wire('config')->paths->SearchEngine . 'lib/'
        );

        // Init SearchEngine Indexer
        $this->indexer = $this->wire(new \SearchEngine\Indexer());

        // Init SearchEngine Finder
        $this->finder = $this->wire(new \SearchEngine\Finder());

        // Init SearchEngine Renderer
        $this->renderer = $this->wire(new \SearchEngine\Renderer());

        // Remember that the module has been initialized
        $this->initialized = true;

        // return true on first run
        return true;
    }

    /**
     * Return the default strings for the module
     *
     * @return array Associative array of translatable strings.
     */
    public function getDefaultStrings() {
        return [
            'form_label' => $this->_x('Search', 'input label'),
            'form_input_placeholder' => $this->_('Search the site...'),
            'form_submit' => $this->_x('Search', 'submit button text'),
            'results_heading' => $this->_('Search results'),
            'results_summary_one' => $this->_('One result for "%s":'),
            'results_summary_many' => $this->_('%2$d results for "%1$s":'),
            'results_summary_none' => $this->_('No results for "%s".'),
            'tab_label_all' => $this->_x('All', 'Tab label'),
            'errors_heading' => $this->_('Sorry, we were unable to process your query'),
            'error_query_missing' => $this->_('Your query was empty. Please provide a proper query.'),
            'error_query_too_short' => $this->_('Your query was too short. Please use at least %d characters.'),
        ];
    }

    /**
     * Override previously defined run-time options
     *
     * @param array $options Custom options.
     * @return SearchEngine Self-reference.
     */
    public function setOptions(array $options): SearchEngine {
        $this->initOnce();
        $this->options = array_replace_recursive(
            $this->options,
            $options
        );
        return $this;
    }

    /**
     * Init runtime options for the module
     *
     * Runtime options are constructed by combining default values, specific settings from module
     * config, and values that might've been defined via site config.
     */
    protected function initOptions() {

        // Named module config settings that should be included in runtime options
        $module_config = [];
        $enabled_settings = [
            'index_field',
            'indexed_fields',
            'find_args__sort',
            'find_args__operator',
            'render_args__themes_directory',
            'indexer_actions',
        ];
        foreach ($enabled_settings as $setting) {
            $setting_value = $this->get($setting);
            if (!empty($setting_value)) {
                if (strpos($setting, '__')) {
                    list($setting_parent, $setting_child) = explode('__', $setting);
                    if (empty($module_config[$setting_parent])) {
                        $module_config[$setting_parent] = [];
                    }
                    $module_config[$setting_parent][$setting_child] = $setting_value;
                } else {
                    $module_config[$setting] = $setting_value;
                }
            }
        }

        // Make sure that indexed templates is defined and includes configured templates as well as those that might
        // have had the index field manually added
        $module_config['indexed_templates'] = [];
        $index_field = !empty($module_config['index_field']) ? $this->fields->get($module_config['index_field']) : null;
        if ($index_field !== null) {
            $templates_with_index_field = $index_field->getTemplates()->get('name[]');
            $module_config['indexed_templates'] = array_unique(array_merge(
                array_intersect(
                    $this->get('indexed_templates') ?? [],
                    $templates_with_index_field
                ),
                $templates_with_index_field
            ));
        }

        // Set runtime options
        $this->options = array_replace_recursive(
            static::$defaultOptions,
            $module_config,
            is_array($this->wire('config')->SearchEngine) ? $this->wire('config')->SearchEngine : []
        );
    }

    /**
     * When the module is installed, create the search index field
     *
     * Note: if the index field is used by any templates when the module is uninstalled, it won't be
     * automatically removed. Instead the user will see a message prompting them to delete the field
     * if it's no longer needed.
     */
    public function install() {

        // Init runtime options
        $this->initOptions();

        // Create search index field (unless it already exists)
        $this->createIndexField($this->options['index_field']);
    }

    /**
     * Attempt to create the index field. If suitable field already exists, use the existing field.
     *
     * @param string $index_field_name Index field name.
     * @param string|null $redirect_url Optional redirect URL.
     * @return null|Field Index field, or null if unsuitable field with conflicting name was found.
     */
    public function createIndexField(string $index_field_name, string $redirect_url = null): ?Field {
        $index_field = $this->getIndexfield($index_field_name);
        if ($index_field) {
            if ($index_field->_is_valid_index_field) {
                // Use existing index field
                $this->message(sprintf(
                    $this->_('Index field "%s" already exists and is of expected type (%s). Using existing field.'),
                    $index_field->name,
                    $index_field->type->name
                ));
            } else {
                $this->error(sprintf(
                    $this->_('Index field "%s" already exists but is not of compatible type. Please remove this field and create the index field, or override the "index_field" setting of the SearchEngine module.'),
                    $index_field->name
                ));
                $index_field = null;
            }
        } else {
            // Create new index field
            $index_field = $this->wire(new Field());
            $index_field->type = 'FieldtypeTextarea';
            $index_field->name = $this->options['index_field'];
            $index_field->collapsed = Inputfield::collapsedHidden;
            $this->wire('fields')->save($index_field);
            $this->message(sprintf(
                $this->_('Index field "%s" created. Please add this field to templates you want to make searchable.'),
                $index_field->name
            ));
        }
        if (!empty($redirect_url)) {
            $this->wire('session')->redirect($redirect_url, false);
        }
        return $index_field;
    }

    /**
     * Get index field
     *
     * @param string|null $index_field_name Index field name. If name is null, get the default name from settings.
     * @return null|Field Index field or null.
     */
    public function getIndexField(string $index_field_name = null): ?Field {

        // If index field name is null, get default value from options
        if (is_null($index_field_name)) {
            $index_field_name = $this->options['index_field'];
        }

        // Bail out early if index field name is empty
        if (empty($index_field_name)) return null;

        $index_field = $this->wire('fields')->get($index_field_name);
        if ($index_field) {
            if ($index_field->type == 'FieldtypeTextarea' || $index_field->type == 'FieldtypeTextareaLanguage') {
                // Compatible index field found
                $index_field->_is_valid_index_field = true;
            } else {
                // Incompatible field found, display an error
                $index_field->_is_valid_index_field = false;
            }
        }
        return $index_field;
    }

    /**
     * Remove index field
     *
     * @param string|null $index_field_name Index field name. If name is null, get the default name from settings.
     */
    public function removeIndexField(string $index_field_name = null) {

        // If index field name is null, get default value from options
        if (is_null($index_field_name)) {
            $index_field_name = $this->options['index_field'];
        }

        $index_field = $this->getIndexField($index_field_name);
        if ($index_field && $index_field->_is_valid_index_field) {
            $used_by_templates = $index_field->getTemplates();
            if (count($used_by_templates)) {
                $this->message(sprintf(
                    $this->_('Index field "%s" is still used by one or more templates. Please remove this field manually if you no longer need it.'),
                    $index_field->name
                ));
            } else {
                $field_removed = $this->wire('fields')->delete($index_field);
                if ($field_removed) {
                    $this->message(sprintf(
                        $this->_('Index field "%s" was automatically removed.'),
                        $index_field->name
                    ));
                } else {
                    $this->error(sprintf(
                        $this->_('Index field "%s" couldn\'t be automatically removed. Please remove this field manually if you no longer need it.'),
                        $index_field->name
                    ));
                }
            }
        }
    }

    /**
     * When the module is uninstalled delete the index field or prompt to remove it manually
     *
     */
    public function uninstall() {

        // Init runtime options
        $this->initOptions();

        // Remove search index field (if it exists and unless it's still in use)
        $this->removeIndexField();
    }

    /**
     * Magic getter method
     *
     * This method is added so that we can keep some properties readable from the outside but not
     * writable. Falls back to parent class (Wire) if local property doesn't exist.
     *
     * @param string $key Property name.
     * @return mixed
     */
    public function __get($key) {
        return $this->$key ?? parent::get($key);
    }

    /**
     * Method overloading support
     *
     * This method provides easy access to Renderer and Indexer features: when a render* or index*
     * method is requested from the module, pass the method call to Renderer or Indexer instead.
     *
     * @param string $method Method name.
     * @param array $arguments Array of arguments.
     * @return mixed
     */
    public function __call($method, $arguments) {
        if (strpos($method, "render") === 0) {
            $this->initOnce();
            return call_user_func_array([$this->renderer, $method], $arguments);
        } else if (strpos($method, "index") === 0) {
            $this->initOnce();
            return call_user_func_array([$this->indexer, $method], $arguments);
        }
        return parent::__call($method, $arguments);
    }

}
