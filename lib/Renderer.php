<?php

namespace SearchEngine;

use \ProcessWire\Inputfield;

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
     * @var array
     */
    protected $defaultStrings = [];

    /**
     * Constructor method
     */
    public function __construct() {
        parent::__construct();
        $this->defaultStrings = [
            'input_label' => $this->_('Search for'),
            'input_placeholder' => $this->_('Search for...'),
            'submit' => $this->_('Search'),
        ];
    }

    /**
     * Render a search form
     *
     * @param array $args Optional arguments.
     * @return string Markup for the search form.
     */
    public function ___renderForm(array $args = []) {

        $options = array_replace_recursive($this->options['render_args'], $args);

        // Merge strings with default strings and create a Data object.
        foreach ($this->defaultStrings as $string => $value) {
            if (is_null($options['strings'][$string])) {
                $options['strings'][$string] = $value;
            }
        }
        $options['strings'] = $this->wire(new Data($options['strings']));

        // Render search form.
        $form_content = sprintf(
            $options['templates']['form'],
            implode([
                $options['templates']['label'],
                $options['templates']['input'],
                $options['templates']['submit'],
            ]),
        );

        // Replace placeholders (string tags).
        $form = \ProcessWire\wirePopulateStringTags($form_content, new Data($options));

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
    public function ___getInputfieldForm(array $args = []) {

        $modules = $this->wire('modules');
        $options = array_replace_recursive($this->options['render_args'], $args);

        // Search form.
        $form = $modules->get('InputfieldForm');
        $form->method = 'GET';
        $form->id = $options['form_id'];
        $form_action = $options['form_action'];
        if ($form_action instanceof \ProcessWire\Page) {
            $form_action = $form_action->path;
        }
        $form->action = $form_action;

        // Query (text) field.
        $input = $modules->get('InputfieldText');
        $input->name = $this->options['find_args']['query_param'] ?? 'q';
        $input->label = $this->getString('input', $this->_('Search'));
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
        if ($name === 'form') {
            return $this->renderForm();
        }
        return $this->$name;
    }

}
