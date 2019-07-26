<?php

namespace ProcessWire;

use SearchEngine\Config,
    SearchEngine\Finder,
    SearchEngine\Indexer,
    SearchEngine\Query,
    SearchEngine\Renderer;

/**
 * SearchEngine ProcessWire module
 *
 * SearchEngine is a module that creates a searchable index of site contents and provides you with
 * the tools needed to easily set up a fast and effective site search feature.
 *
 * @version 0.8.0
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
        'indexed_fields' => [
            'title',
            'headline',
            'summary',
            'body',
        ],
        'compatible_fieldtypes' => [
            'FieldtypeEmail',
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
        ],
        'prefixes' => [
            'link' => 'link:',
        ],
        'find_args' => [
            'limit' => 20,
            'sort' => 'sort',
            'operator' => '*=',
            'query_param' => 'q',
            'selector_extra' => '',
        ],
        'pager_args' => [
            // These arguments are passed to MarkupPagerNav. You can find more details from the
            // documentation: https://processwire.com/docs/front-end/markup-pager-nav/.
            'listMarkup' => '<div class="search-results-pager"><ul class="search-results-pager__list">{out}</ul></div>',
            'itemMarkup' => '<li class="search-results-pager__list-item {class}">{out}</li>',
            'linkMarkup' => '<a class="search-results-pager__item" href="{url}"><span class="search-results-pager__item-text">{out}</span></a>',
            'separatorItemClass' => 'search-results-pager__separator',
            'nextItemClass' => 'search-results-pager__item search-results-pager__item--next',
            'previousItemClass' => 'search-results-pager__item search-results-pager__item--previous',
            'firstItemClass' => 'search-results-pager__item search-results-pager__item--first',
            'firstNumberItemClass' => 'search-results-pager__item search-results-pager__item--first-num',
            'firstItemClass' => 'search-results-pager__item search-results-pager__item--first',
            'lastItemClass' => 'search-results-pager__item search-results-pager__item--last',
            'lastNumberItemClass' => 'search-results-pager__item search-results-pager__item--last-num',
            'currentItemClass' => 'search-results-pager__item search-results-pager__item--current',
        ],
        'render_args' => [
            'theme' => 'default',
            'minified_resources' => true,
            'form_action' => './',
            'form_id' => 'se-form',
            'form_input_id' => 'se-form-input',
            'results_summary_id' => 'se-results-summary',
            'results_id' => 'se-results',
            'result_summary_field' => 'summary',
            'results_highlight_query' => true,
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
                'result' => 'search-result',
                'result_link' => '&__link',
                'result_path' => '&__path',
                'result_path_item' => '&__path-item',
                'result_desc' => '&__desc',
                'result_highlight' => '&__highlight',
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
                'results_list' => '<ul class="{classes.results_list}" aria-labelled-by="{results_summary_id}">%s</ul>',
                'results_list_item' => '<li class="{classes.results_list_item}">%s</li>',
                'result' => '<div class="{classes.result}">%s</div>',
                'result_link' => '<a class="{classes.result_link}" href="{item.url}">{item.title}</a>',
                'result_path' => '<div class="{classes.result_path}">{item.url}</div>',
                'result_path_item' => '<li class="{classes.result_path_item}">{item.title}</li>',
                'result_desc' => '<div class="{classes.result_desc}">%s</div>',
                'result_highlight' => '<strong class="{classes.result_highlight}">%s</strong>',
                'styles' => '<link rel="stylesheet" type="text/css" href="%s">',
                'scripts' => '<script async="true" src="%s"></script>',
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

        // Trigger manual indexing when module config is saved.
        $this->addHookBefore('Modules::saveModuleConfigData', $this, 'saveConfigData');

        // Update search index when a page is saved.
        $this->addHook('Pages::saveReady', $this, 'savePageIndex');
    }

    /**
     * Return inputfields necessary to configure the module
     *
     * @param array $data Data array.
     * @return InputfieldWrapper Wrapper with inputfields needed to configure the module.
     */
    public function getModuleConfigInputfields(array $data) {
        $this->maybeInit();
        return $this->wire(new Config($data))->getFields();
    }

    /**
     * This method gets triggered when module config is saved
     *
     * We use this method to perform manual field indexing.
     *
     * @param HookEvent $event
     */
    protected function saveConfigData(HookEvent $event) {

        // Bail out early if saving another module's config.
        if ($event->arguments[0] !== $this->className) {
            return;
        }

        // Make sure that the module has been initialized.
        $this->maybeInit();

        // Build an index and make sure that the index_pages_now setting doesn't get saved.
        $data = $event->arguments[1];
        if (!empty($data['index_pages_now'])) {
            $index_pages_now_selector = $data['index_pages_now_selector'] ?? null;
            $indexing_started = new \DateTime();
            $indexed_pages = $this->indexer->indexPages($index_pages_now_selector);
            $elapsed_time = $indexing_started->diff(new \Datetime());
            if ($indexed_pages === 0) {
                $this->warning(sprintf(
                    $this->_('SearchEngine couldn\'t find any pages to index. Please make sure that your indexing settings are configured properly, and your index field "%s" has been added to at least one template with existing pages.'),
                    $this->wire('sanitizer')->text($data['index_field'] ?? '')
                ));
            } else {
                $this->message(sprintf(
                    $this->_('%d pages indexed in %d seconds.'),
                    $indexed_pages,
                    $elapsed_time->format('%s')
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
        $this->maybeInit();
        $page = $event->arguments[0];
        $this->indexer->indexPage($page, false);
    }

    /**
     * Find content matching provided query.
     *
     * This method is a wrapper for Finder::find().
     *
     * @param mixed $query The query.
     * @param array $args Additional arguments, see Query::__construct() for details.
     * @return Query Resulting Query object.
     */
    public function find($query = null, array $args = []): Query {
        $this->maybeInit();
        return $this->finder->find($query, $args);
    }

    /**
     * Initialize the module
     *
     * If the module hasn't been initialized yet, this method will perform the required init setup.
     * This is done in a seprate module to avoid loading or doing unnecessary stuff, since we can't
     * really limit the scope of the module autoload (need to be able to catch any page save, etc.)
     */
    protected function maybeInit() {

        // Bail out early if the module has already been initialized
        if ($this->initialized) {
            return;
        }

        // Init runtime options.
        $this->initOptions();

        // Init class autoloader.
        $this->wire('classLoader')->addNamespace(
            'SearchEngine',
            $this->wire('config')->paths->SearchEngine . 'lib/'
        );

        // Init SearchEngine Indexer.
        $this->indexer = $this->wire(new Indexer());

        // Init SearchEngine Finder.
        $this->finder = $this->wire(new Finder());

        // Init SearchEngine Renderer.
        $this->renderer = $this->wire(new Renderer());

        // Remember that the module has been initialized.
        $this->initialized = true;
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
            'errors_heading' => $this->_('Sorry, we were unable to process your query'),
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
        $this->maybeInit();
        $this->options = array_replace_recursive(
            $this->options,
            $options
        );
        return $this;
    }

    /**
     * Init runtime options for the module
     *
     * Runtime options are a combination of module defaults, values from module config, and values
     * defined in site config.
     */
    protected function initOptions() {

        // Module config settings.
        $module_config = [];
        foreach (['index_field', 'indexed_fields'] as $setting) {
            $setting_value = $this->get($setting);
            if (!empty($setting_value)) {
                $module_config[$setting] = $setting_value;
            }
        }

        // Set runtime options.
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
     *
     * @throws WireException if field matching the name of the search index field exists but is incompatible.
     */
    public function install() {

        // Init runtime options.
        $this->initOptions();

        // Create search index field (unless it already exists).
        $index_field = $this->wire('fields')->get($this->options['index_field']);
        if ($index_field) {
            if ($index_field->type == 'FieldtypeTextarea') {
                // Use existing index field.
                $this->message(sprintf(
                    $this->_('Index field "%s" already exists and is of expected type (FieldtypeTextarea). Using existing field.'),
                    $index_field->name
                ));
            } else {
                // Incompatible field found, throw WireException.
                throw new WireException(sprintf(
                    $this->_('Index field "%s" already exists but is not of compatible type (%s). Please remove this field first, or override the "index_field" setting of the SearchEngine module.'),
                    $index_field->name,
                    $index_field->type
                ));
            }
        } else {
            // Create new index field.
            $index_field = $this->wire(new Field());
            $index_field->type = 'FieldtypeTextarea';
            $index_field->name = $this->options['index_field'];
            $index_field->collapsed = Inputfield::collapsedHidden;
            $this->wire('fields')->save($index_field);
            $this->message(sprintf(
                $this->_('Index filed "%s" created. Please add this field to templates you want to make searchable.'),
                $index_field->name
            ));
        }
    }

    /**
     * When the module is uninstalled delete the index field or prompt to remove it manually
     *
     */
    public function uninstall() {

        // Init runtime options.
        $this->initOptions();

        // Remove search index field (if it exists and unless it's still in use).
        $index_field = $this->wire('fields')->get($this->options['index_field']);
        if ($index_field) {
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
     * This method provides easy access to Renderer features: when a render* method is requested
     * from this module, we check if Renderer has a matching method and then call that instead.
     *
     * @param string $method Method name.
     * @param array $arguments Array of arguments.
     * @return mixed
     */
    public function __call($method, $arguments) {
        if (strpos($method, "render") === 0) {
            $this->maybeInit();
            return $this->renderer->__call($method, $arguments);
        }
        return parent::__call($method, $arguments);
    }

}
