<?php

namespace SearchEngine;

use ProcessWire\Inputfield;
use ProcessWire\InputfieldAsmSelect;
use ProcessWire\InputfieldCheckbox;
use ProcessWire\InputfieldFieldset;
use ProcessWire\InputfieldMarkup;
use ProcessWire\InputfieldPageListSelect;
use ProcessWire\InputfieldSelect;
use ProcessWire\InputfieldSelector;
use ProcessWire\InputfieldText;
use ProcessWire\InputfieldTextarea;
use ProcessWire\InputfieldWrapper;

/**
 * SearchEngine Config
 *
 * @version 0.11.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 */
class Config extends Base {

    /**
     * Config data array passed from the SearchEngine module
     *
     * @var array
     */
    protected $data = [];

    /**
     * SearchEngine module Runtime options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Constructor method
     *
     * @param array $data Config data array.
     */
    public function __construct(array $data) {
        parent::__construct();

        // In case autoload has been disabled for SearchEngine (unusual, but possible) make sure that necessary init
        // is called to avoid errors
        $this->modules->get('SearchEngine')->initOnce();

        $this->data = $data;
        $this->options = $this->getOptions();
    }

    /**
     * Validate (and optionally create new) index field
     */
    public function validateIndexField() {
        $index_field_name = $this->data['index_field'] ?? $this->options['index_field'] ?? null;
        if (!empty($index_field_name)) {
            $index_field_name = $this->wire('sanitizer')->fieldName($index_field_name);
            $create_link_base = $this->wire('config')->urls->admin . 'module/edit?name=SearchEngine&create_index_field=';
            $search_engine = $this->wire('modules')->get('SearchEngine');
            if ($this->wire('input')->get('create_index_field') == 1) {
                $index_field = $search_engine->createIndexField($index_field_name, $create_link_base . '2');
            }
            $index_field = $search_engine->getIndexField($index_field_name);
            if (!$index_field) {
                $create_link = ' <a href="' . $create_link_base . '1">'
                             . $this->_('Click here to create the index field automatically.')
                             . '</a>';
                $this->wire->message(sprintf(
                    $this->_('Index field "%s" (FieldtypeTextarea or FieldtypeTextareaLanguage) doesn\'t exist.'),
                    $index_field_name
                ) . $create_link, \ProcessWire\Notice::allowMarkup);
            } else if (!$index_field->_is_valid_index_field) {
                $this->wire->error(sprintf(
                    $this->_('Index field "%s" exists but is of incompatible type (%s). Please create a new index field or convert existing field to a supported type (FieldtypeTextarea or FieldtypeTextareaLanguage).'),
                    $index_field_name,
                    $index_field->type->name
                ));
            } else if (!$index_field->getTemplates()->count()) {
                $this->wire->message(sprintf(
                    $this->_('Index field "%s" hasn\'t been added to any templates yet. Add to one or more templates to start indexing content.'),
                    $index_field_name
                ));
            }
        }
    }

    /**
     * Get all config fields for the module
     *
     * @return InputfieldWrapper InputfieldWrapper with module config inputfields.
     */
    public function getFields(): InputfieldWrapper {

        // Debugger AJAX endpoint
        (new Debugger)->initAJAXAPI();

        // inject scripts
        $this->wire('config')->scripts->add(
            $this->wire('config')->urls->get('SearchEngine') . 'js/dist/admin.js'
        );

        // inject styles
        foreach (['config'] as $styles) {
            $this->wire('config')->styles->add(
                $this->wire('config')->urls->get('SearchEngine') . 'css/' . $styles . '.css'
            );
        }

        $fields = $this->wire(new InputfieldWrapper());

        $fields->add($this->getIndexingOptionsFieldset());
        $fields->add($this->getFinderSettingsFieldset());
        $fields->add($this->getManualIndexingFieldset());
        $fields->add($this->getAdvancedSettingsFieldset());
        if ($this->wire('user')->isSuperuser()) {
            $fields->add($this->getDebuggerSettingsFieldset());
        }

        return $fields;
    }

    /**
     * Get a fieldset containing indexing settings
     *
     * @return InputfieldFieldset
     */
    protected function getIndexingOptionsFieldset(): InputfieldFieldset {

        /** @var InputfieldFieldset Indexing options */
        $indexing_options = $this->wire('modules')->get('InputfieldFieldset');
        $indexing_options->label = $this->_('Indexing options');
        $indexing_options->icon = 'database';

        /** @var InputfieldAsmSelect Indexed fields */
        $indexed_fields = $this->wire('modules')->get('InputfieldAsmSelect');
        $indexed_fields->name = 'indexed_fields';
        $indexed_fields->label = $this->_('Select indexed fields');
        $indexed_fields->description = $this->_('Select indexed fields from the list. Note that some fields, e.g. file fields with custom fields enabled, have separate options for main field and subfields.');
        $indexed_fields->columnWidth = 50;
        $indexed_fields->hideDeleted = false;
        $indexed_fields->addOptions([
            'id' => 'id',
            'name' => 'name',
        ]);
        $compatible_fieldtype_options = $this->options['compatible_fieldtypes'] ?? [];
        if (!empty($this->data['override_compatible_fieldtypes'])) {
            $compatible_fieldtype_options = $this->data['compatible_fieldtypes'] ?? [];
        }
        if (!empty($compatible_fieldtype_options)) {
            foreach ($this->wire('fields')->getAll() as $field) {
                if (!in_array($field->type, $compatible_fieldtype_options) || $field->name === $this->options['index_field']) {
                    continue;
                }
                $indexed_fields->addOption($field->name);
                if ($field->type == 'FieldtypeFile') {
                    $indexed_fields->addOption($field->name . '.*');
                    $indexed_fields->addOption($field->name . '.description');
                    $indexed_fields->addOption($field->name . '.tags');
                    if (method_exists($field->type, 'getFieldsTemplate')) {
                        $fields_template = $field->type->getFieldsTemplate($field);
                        if ($fields_template) {
                            foreach ($fields_template->fields as $subfield) {
                                $indexed_fields->addOption($field->name . '.' . $subfield);
                            }
                        }
                    }
                }
            }
        }
        if (!empty($this->wire('config')->SearchEngine[$indexed_fields->name])) {
            $indexed_fields->notes = $this->_('Indexed fields are currently defined in site config. You cannot override config settings here.');
            $indexed_fields->value = $this->options[$indexed_fields->name];
            $indexed_fields->collapsed = Inputfield::collapsedNoLocked;
        } else {
            $indexed_fields->value = $this->data[$indexed_fields->name] ?? $this->options[$indexed_fields->name] ?? null;
        }
        $indexing_options->add($indexed_fields);

        /** @var InputfieldAsmSelect Indexed templates */
        $indexed_templates = $this->wire('modules')->get('InputfieldAsmSelect');
        $indexed_templates->name = 'indexed_templates';
        $indexed_templates->label = $this->_('Indexed templates');
        $indexed_templates->description = $this->_('In order for a template to be indexed, it needs to include the index field. Selecting a template here will automatically add the index field to it.');
        $indexed_templates->columnWidth = 50;
        $indexed_templates->hideDeleted = false;
        $index_field_templates = $this->wire('fields')->get($this->options['index_field'])->getTemplates()->get('name[]');
        foreach ($this->wire('templates')->getAll() as $template) {
            $option_attributes = null;
            if ($template->flags & \ProcessWire\Template::flagSystem) {
                if (!in_array($template->name, $index_field_templates)) continue;
                $option_attributes = ['disabled' => 'disabled'];
                $indexed_templates->notes = $this->_('One or more system templates are indexed. In order to make system templates indexable (or non-indexable) you need to modify template settings directly.');
            }
            $indexed_templates->addOption($template->name, null, $option_attributes);
        }
        $indexed_templates->value = $this->options[$indexed_templates->name];
        $indexing_options->add($indexed_templates);

        return $indexing_options;
    }

    /**
     * Get a fieldset containing finder settings
     *
     * @return InputfieldFieldset
     */
    protected function getFinderSettingsFieldset(): InputfieldFieldset {

        /** @var InputfieldFieldset Finder settings */
        $finder_settings = $this->wire('modules')->get('InputfieldFieldset');
        $finder_settings->label = $this->_('Finder settings');
        $finder_settings->icon = 'search';

        /** @var InputfieldText Sort order */
        $sort = $this->wire('modules')->get('InputfieldText');
        $sort->name = 'find_args__sort';
        $sort->label = $this->_('Sort order');
        $sort->description = $this->_('Sort order used when finding content. See [documentation for sorting results](https://processwire.com/docs/selectors/#sort) for more details.');
        $sort->notes = $this->_('You can use multiple sort fields by separating each field with a comma (sort,title,-date_from).')
            . ' '
            . $this->_('Custom values specific to SearchEngine:')
            . "\n\n"
            . $this->_('- **_indexed_templates**: Sort results by the order of indexed templates');
        $sort = $this->maybeUseConfig($sort);
        $finder_settings->add($sort);

        /** @var InputfieldSelect Operator */
        $operator = $this->wire('modules')->get('InputfieldSelect');
        $operator->name = 'find_args__operator';
        $operator->label = $this->_('Operator');
        $operator->description = $this->_('Operator used when finding content. See [documentation for operators](https://processwire.com/docs/selectors/#operators) for more details.');
        $operator_options = [
            '*=',
            '~=',
            '%=',
        ];
        if (version_compare($this->wire('config')->version, '3.0.160') > -1) {
            // new operators introduced in ProcessWire 3.0.160
            $operator_options = array_merge($operator_options, [
                '~*=',
                '~~=',
                '~%=',
                '~+=',
                '~|=',
                '~|*=',
                '~|%=',
                '~|+=',
                '*+=',
                '**=',
                '**+=',
                '#=',
            ]);
            foreach ($operator_options as $operator_option) {
                $operator_option_class = \ProcessWire\Selectors::$selectorTypes[$operator_option] ?? null;
                if ($operator_option_class) {
                    $operator_option_class = '\ProcessWire\\' . $operator_option_class;
                    $operator->addOption(
                        $operator_option,
                        '[' . $operator_option . '] ' . $operator_option_class::getLabel()
                    );
                }
            }
        } else {
            // fallback for ProcessWire < 3.0.160
            $operator->addOptions([
                '*=' => '[*=] ' . $this->_('Contains the exact word or phrase'),
                '~=' => '[~=] ' . $this->_('Contains all the words'),
                '%=' => '[%=] ' . $this->_('Contains the exact word or phrase (using slower SQL LIKE)'),
            ]);
        }
        $operator->notes .= $this->_('More information in the [documentation page for operators](https://processwire.com/docs/selectors/operators/).');
        $operator = $this->maybeUseConfig($operator);
        $finder_settings->add($operator);

        if (version_compare($this->wire('config')->version, '3.0.160') > -1) {
            /** @var InputfieldMarkup Additional information for each available operator */
            $operator_details = $this->wire('modules')->get('InputfieldMarkup');
            $operator_details->value = '<ul id="pwse-operator-details" class="pwse-operator-details" tabindex="-1" data-toggle-label="' . $this->_('Toggle operator details') . '">';
            $operator_data_array = \ProcessWire\Selectors::getOperators([
                'getValueType' => 'verbose',
            ]);
            $valid_operators = array_keys($operator->options);
            foreach ($operator_data_array as $operator_data) {
                if (!in_array($operator_data['operator'], $valid_operators)) continue;
                $operator_details_active = $operator->value == $operator_data['operator'] ? 'pwse-operator-details__list-item--active' : '';
                $operator_details->value .= '<li class="pwse-operator-details__list-item ' . $operator_details_active . '">'
                                          . '<div class="pwse-operator-details__header">'
                                          . '<button class="pwse-operator-details__button uk-button" data-operator="' . $operator_data['operator'] . '">' . $operator_data['operator'] . '</button>'
                                          . '<span class="pwse-operator-details__label">' . $operator_data['label'] . '</span>'
                                          . '</div>'
                                          . '<div class="pwse-operator-details__description">' . $operator_data['description'] . '</div>'
                                          . '</li>';
            }
            $operator_details->value .= '</ul>'
                                      . '<script>document.getElementById("pwse-operator-details").setAttribute("hidden", "");</script>';
            $finder_settings->add($operator_details);
        }

        return $finder_settings;
    }

    /**
     * Get a fieldset containing manual indexing options
     *
     * @return InputfieldFieldset
     */
    protected function getManualIndexingFieldset(): InputfieldFieldset {

        /** @var InputfieldFieldset Manual indexing options */
        $manual_indexing = $this->wire('modules')->get('InputfieldFieldset');
        $manual_indexing->label = $this->_('Manual indexing');
        $manual_indexing->icon = 'rocket';

        /** @var InputfieldCheckbox Checkbox for triggering page indexing */
        $index_pages_now = $this->wire('modules')->get('InputfieldCheckbox');
        $index_pages_now->name = 'index_pages_now';
        $index_pages_now->label = $this->_('Index pages now?');
        $index_pages_now->description = $this->_('If you check this field and save module settings, SearchEngine will automatically index all applicable pages.');
        $index_pages_now->notes = $this->_('Note: this operation may take a long time.');
        $index_pages_now->attr('checked', !empty($this->data[$index_pages_now->name]));
        $manual_indexing->add($index_pages_now);

        /** @var InputfieldSelector Optional selector for automatic page indexing */
        $index_pages_now_selector = $this->wire('modules')->get('InputfieldSelector');
        $index_pages_now_selector->name = 'index_pages_now_selector';
        $index_pages_now_selector->label = $this->_('Selector for indexed pages');
        $index_pages_now_selector->description = $this->_('You can use this field to choose the pages that should be indexed. This only takes effect if the "Index pages now?" option has been checked.');
        $index_pages_now_selector->showIf = 'index_pages_now=1';
        $index_pages_now_selector->value = $this->data[$index_pages_now_selector->name] ?? null;
        $manual_indexing->add($index_pages_now_selector);

        return $manual_indexing;
    }

    /**
     * Get fieldset for advanced settings
     *
     * @return InputfieldFieldset
     */
    protected function getAdvancedSettingsFieldset(): InputfieldFieldset {

        /** @var InputfieldFieldset Advanced options */
        $advanced_settings = $this->wire('modules')->get('InputfieldFieldset');
        $advanced_settings->label = $this->_('Advanced settings');
        $advanced_settings->icon = 'graduation-cap';
        $advanced_settings->collapsed = Inputfield::collapsedYes;

        /** @var InputfieldSelect Index field */
        $index_field = $this->wire('modules')->get('InputfieldSelect');
        $index_field->name = 'index_field';
        $index_field->label = $this->_('Index field');
        foreach ($this->wire('fields')->getAll() as $field) {
            if ($field->type != 'FieldtypeTextarea' && $field->type != 'FieldtypeTextareaLanguage') {
                continue;
            }
            $index_field->addOption($field->name);
        }
        $index_field->notes = $this->_('If you select a field that already contains values, those values *will* be overwritten the next time someone triggers manual indexing of pages *or* a page containing selected field is saved. Making changes to this setting can result in *permanent* data loss!');
        $index_field = $this->maybeUseConfig($index_field);
        $advanced_settings->add($index_field);

        /** @var InputfieldCheckbox Override values for compatible fieldtypes */
        $override_compatible_fieldtypes = $this->wire('modules')->get('InputfieldCheckbox');
        $override_compatible_fieldtypes->name = 'override_compatible_fieldtypes';
        $override_compatible_fieldtypes->label = $this->_('Override compatible fieldtypes');
        $override_compatible_fieldtypes->description = $this->_('Check this field if you want to override default compatible fieldtype values here.');
        $override_compatible_fieldtypes->attr('checked', !empty($this->data['override_compatible_fieldtypes']));
        $advanced_settings->add($override_compatible_fieldtypes);

        /** @var InputfieldAsmSelect Fieldtypes considered compatible with this module */
        $compatible_fieldtypes = $this->wire('modules')->get('InputfieldAsmSelect');
        $compatible_fieldtypes->name = 'compatible_fieldtypes';
        $compatible_fieldtypes->label = $this->_('Compatible fieldtypes');
        $compatible_fieldtypes->description = $this->_('Fieldtypes considered compatible with this module.');
        $compatible_fieldtypes->showIf = 'override_compatible_fieldtypes=1';
        $compatible_fieldtypes->hideDeleted = false;
        $incompatible_fieldtype_options = [
            'FieldtypePassword',
            'FieldtypeFieldsetOpen',
            'FieldtypeFieldsetClose',
            'FieldtypeFieldsetTabOpen',
            'FieldtypeFieldsetTabClose',
            'FieldtypeFieldsetPage',
        ];
        foreach ($this->wire('modules')->find('className^=Fieldtype') as $fieldtype) {
            if (in_array($fieldtype->name, $incompatible_fieldtype_options)) {
                continue;
            }
            $compatible_fieldtypes->addOption($fieldtype->name);
        }
        if (!empty($this->wire('config')->SearchEngine[$compatible_fieldtypes->name])) {
            $compatible_fieldtypes->notes = $this->_('Compatible fieldtypes are currently defined in site config. You cannot override config settings here.');
            $compatible_fieldtypes->value = $this->options[$compatible_fieldtypes->name];
            $compatible_fieldtypes->collapsed = Inputfield::collapsedNoLocked;
        } else {
            $compatible_fieldtypes->value = $this->data[$compatible_fieldtypes->name] ?? $this->options[$compatible_fieldtypes->name] ?? null;
            $compatible_fieldtypes->notes = $this->_('Please note that selecting fieldtypes not selected by default may result in various problems. Change these values only if you\'re sure that you know what you\'re doing.');
        }
        $compatible_fieldtypes->notes .= $this->getCompatibleFieldtypeDiff($compatible_fieldtypes->value);
        $advanced_settings->add($compatible_fieldtypes);

        /** @var InputfieldAsmSelect Indexer extra actions */
        $indexer_actions = $this->wire('modules')->get('InputfieldAsmSelect');
        $indexer_actions->name = 'indexer_actions';
        $indexer_actions->label = $this->_('Indexer actions');
        $indexer_actions->description = $this->_('Optional, predefined actions triggered while Indexer is processing page content.')
            . " "
            . $this->_('Note: this option is currently considered experimental. Please test carefully before enabling on a live site!');
        $actions_by_context = (new IndexerActions())->getActions();
        foreach ($actions_by_context as $action_context) {
            foreach ($action_context as $action_name => $action_description) {
                $indexer_actions->notes .= sprintf('- **%s**: %s', $action_name, $action_description);
                $indexer_actions->addOption($action_name, $action_name);
            }
        }
        $indexer_actions->hideDeleted = false;
        $indexer_actions = $this->maybeUseConfig($indexer_actions);
        $advanced_settings->add($indexer_actions);

        /** @var InputfieldText Themes directory */
        $themes_directory = $this->wire('modules')->get('InputfieldText');
        $themes_directory->name = 'render_args__themes_directory';
        $themes_directory->label = $this->_('Themes directory');
        $themes_directory->pattern = '^[^.](?:(?!\.\.|\/\/).)*$';
        $themes_directory->description = sprintf(
            $this->_('Directory containing themes used on the front-end. Leave this field empty to use the default location (%s).'),
            $this->wire('config')->paths->SearchEngine . 'themes/'
        );
        $themes_directory->notes = $this->_('For security reasons only subdirectories of the templates directory are allowed: if the value provided here is **SearchEngine/themes**, resulting lookup directory will be /site/templates/**SearchEngine/themes**/. In addition the directory specified here may *not* start with a dot ("`.`"), or contain double slashes ("`//`") or directory traversal ("`..`").');
        $themes_directory = $this->maybeUseConfig($themes_directory);
        $advanced_settings->add($themes_directory);

        return $advanced_settings;
    }

    /**
     * Get fieldset for Debugger settings
     *
     * @return InputfieldFieldset
     */
    protected function getDebuggerSettingsFieldset(): InputfieldFieldset {

        // init Debugger
        $debugger = new Debugger;
        if (!empty($this->data['debugger_page'])) {
            $debugger->setPage($this->data['debugger_page']);
        }
        if (!empty($this->data['debugger_query'])) {
            $debugger->setQuery($this->data['debugger_query']);
        }
        if (!empty($this->data['debugger_query_args'])) {
            $debugger->setQueryArgs($this->data['debugger_query_args']);
        }

        /** @var InputfieldFieldset Fieldset for Debugger */
        $debugger_settings = $this->wire('modules')->get('InputfieldFieldset');
        $debugger_settings->label = $this->_('Debugger');
        $debugger_settings->collapsed = Inputfield::collapsedYes;
        $debugger_settings->icon = 'bug';

        /** @var InputfieldMarkup Index details */
        $debugger_index_markup = $this->wire('modules')->get('InputfieldMarkup');
        $debugger_index_markup->value = $debugger->renderDebugContainer('', [
            'debug-button-label' => $this->_('Debug Index'),
            'type' => 'index',
        ]);
        $debugger_settings->add($debugger_index_markup);

        /** @var InputfieldPageListSelect Select page to debug */
        $debugger_page = $this->wire('modules')->get('InputfieldPageListSelect');
        $debugger_page->name = 'debugger_page';
        $debugger_page->label = $this->_('Selected Page');
        $debugger_page->description = $this->_('Select a Page to debug.');
        $debugger_page->value = $this->data[$debugger_page->name] ?? null;
        $debugger_settings->add($debugger_page);

        /** @var InputfieldMarkup Page debug output */
        $debugger_page_markup = $this->wire('modules')->get('InputfieldMarkup');
        $debugger_page_markup->value = $debugger->renderDebugContainer('', [
            'debug-button-label' => $this->_('Debug Page'),
            'reindex-button-label' => $this->_('Reindex Page'),
            'type' => 'page',
        ]);
        $debugger_settings->add($debugger_page_markup);

        /** @var InputfieldText Query to debug */
        $debugger_query = $this->wire('modules')->get('InputfieldText');
        $debugger_query->name = 'debugger_query';
        $debugger_query->label = $this->_('Query');
        $debugger_query->type = 'search';
        $debugger_query->description = $this->_('Type in the search to debug.');
        $debugger_query->value = $this->data[$debugger_query->name] ?? '';
        $debugger_settings->add($debugger_query);

        /** @var InputfieldTextarea Additional arguments for query */
        $debugger_query_args = $this->wire('modules')->get('InputfieldTextarea');
        $debugger_query_args->name = 'debugger_query_args';
        $debugger_query_args->label = $this->_('Additional arguments');
        $debugger_query_args->description = $this->_('Additional arguments passed to the Finder.');
        $debugger_query_args->notes = $this->_('Note: provided value needs to be valid JSON.');
        $debugger_query_args->value = $this->data[$debugger_query_args->name] ?? '{}';
        $debugger_settings->add($debugger_query_args);

        /** @var InputfieldMarkup Query debug output */
        $debugger_query_markup = $this->wire('modules')->get('InputfieldMarkup');
        $debugger_query_markup->value = $debugger->renderDebugContainer('', [
            'debug-button-label' => $this->_('Debug Query'),
            'type' => 'query',
        ]);
        $debugger_settings->add($debugger_query_markup);

        return $debugger_settings;
    }

    /**
     * Check if a config setting is already defined in site config
     *
     * Given an inputfield object, this method checks if the value of the config option it
     * represents has already been defined via site config, and modifies the properties of
     * the inputfield accordingly.
     *
     * @param Inputfield $field Inputfield object.
     * @return Inputfield Processed Inputfield object.
     */
    protected function maybeUseConfig(Inputfield $field): Inputfield {

        // attempt to get value from site config
        $config_value = $this->getValue($field->name, $this->wire('config')->SearchEngine);

        if (empty($config_value)) {
            // set default value for inputfield
            if (!empty($this->data[$field->name])) {
                $field->value = $this->data[$field->name];
            } else {
                $field->value = $this->getValue($field->name, $this->options);
            }
        } else {
            // value defined in site config, disable inputfield
            $field->notes = ($field->notes ? $field->notes . "\n\n" : "") . sprintf(
                $this->_('*"%s" is currently defined in site config and cannot be changed here.*'),
                $field->label
            );
            $field->value = $this->getValue($field->name, $this->options);
            $field->collapsed = Inputfield::collapsedNoLocked;
        }

        return $field;
    }

    /**
     * Get value from nested array of values
     *
     * @param string $key Key for the value.
     * @param array|null $values Values array.
     * @return mixed Value or null.
     */
    protected function getValue(string $key, ?array $values = []) {
        $value = $values[$key] ?? null;
        if ($separator_pos = strpos($key, '__')) {
            $value = $values[substr($key, 0, $separator_pos)][substr($key, $separator_pos + 2)] ?? null;
        }
        return $value;
    }

    /**
     * Get a list of changes (additions and removals) made to the compatible fieldtypes setting
     *
     * @param array $compatible_fieldtypes Current list of compatible fieldtypes.
     * @return string String representation of the changes.
     */
    protected function getCompatibleFieldtypeDiff(array $compatible_fieldtypes): string {

        // get a diff by comparing module default setting value and current setting value
        $base = \ProcessWire\SearchEngine::$defaultOptions['compatible_fieldtypes'];
        $diff = array_filter([
            'added' => implode(', ', array_diff($compatible_fieldtypes, $base)),
            'removed' => implode(', ', array_filter(array_diff($base, $compatible_fieldtypes), function($fieldtype) {
                return $this->wire('modules')->isInstalled($fieldtype);
            })),
        ]);

        // construct output string
        $out = "";
        if (!empty($diff)) {
            $out .= "\n";
            if (!empty($diff['added'])) {
                $out .= "\n+ " . sprintf($this->_('Added fieldtypes: %s'), $diff['added']);
            }
            if (!empty($diff['removed'])) {
                $out .= "\n- " . sprintf($this->_('Removed fieldtypes: %s'), $diff['removed']);
            }
        }

        return $out;
    }

}
