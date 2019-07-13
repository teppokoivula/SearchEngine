<?php

namespace ProcessWire;

use SearchEngine\Config,
    SearchEngine\Finder,
    SearchEngine\Indexer,
    SearchEngine\Query;

/**
 * SearchEngine ProcessWire module
 *
 * SearchEngine is a module that creates a searchable index of site contents and provides you with
 * the tools needed to easily set up a fast and effective site search feature.
 *
 * @version 0.2.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class SearchEngine extends WireData implements Module, ConfigurableModule {

    /**
     * Default options
     *
     * You can override these defaults by defining an array of custom values in $config->SearchEngine.
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
        ],
        'prefixes' => [
            'link' => 'link:',
        ],
        'find_args' => [
            'limit' => 25,
            'sort' => 'sort',
            'operator' => '%=',
            'query_param' => null,
            'selector_extra' => '',
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
     * Has the module been initialized?
     *
     * @var bool
     */
    protected $initialized = false;

    /**
     * Add hooks
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
        return $this->wire(new Config($data, $this->options))->getFields();
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
            $this->message(sprintf(
                $this->_('%d pages indexed in %d seconds.'),
                $indexed_pages,
                $elapsed_time->format('%s')
            ));
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

        // Remember that the module has been initialized.
        $this->initialized = true;
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

}
