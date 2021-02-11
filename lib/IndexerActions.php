<?php

namespace SearchEngine;

use ProcessWire\HookEvent;

/**
 * SearchEngine Indexer Actions
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class IndexerActions extends Base {

    /**
     * Prepared context run-time cache
     *
     * @var array
     */
    protected $prepared_for = [];

    /**
     * Get available actions
     *
     * @return array
     */
    public function getActions(): array {
        return [
            'indexPage' => [
                'FormBuilder' => $this->_('Look for FormBuilder forms and embed them as a part of the indexed content'),
            ],
        ];
    }

    /**
     * Prepare actions
     *
     * @param string $context
     */
    public function prepareFor(string $context) {
        if (isset($this->prepared_for[$context])) {
            return;
        }
        $this->prepared_for[$context] = true;
        $enabled_actions = array_intersect(
            array_keys($this->getActions()[$context]),
            $this->getOptions()['indexer_actions']
        );
        foreach ($enabled_actions as $action) {
            call_user_func([$this, $action . 'Action']);
        }
    }

    /**
     * FormBuilder Action
     *
     * This method looks for FormBuilder embed tag(s) within text content and attempts to replace them with rendered
     * markup of said form(s) in order to make actual form content searchable.
     */
    protected function FormBuilderAction() {
        if (!$this->wire('modules')->isInstalled('FormBuilder')) {
            return;
        }
        $formBuilder = $this->wire('modules')->get('FormBuilder');
        $this->addHookAfter('FieldtypeTextarea::formatValue', function(HookEvent $event) use ($formBuilder) {
            $field = $event->arguments[1];
            $embed_tag = $formBuilder->embedTag;
            if (empty($embed_tag) || !in_array($field->id, $formBuilder->embedFields) || strpos($event->return, ">" . $embed_tag . "/") === false) {
                // field cannot be used for embedding forms or no embed codes detected, bail out early
                return;
            }
            if (preg_match_all('!<([^>]+)>' . $embed_tag . '/([-_a-zA-z0-9]+)\s*</\\1>!', $event->return, $matches)) {
                foreach ($matches[0] as $key => $tag) {
                    try {
                        $formBuilderRender = $formBuilder->render($matches[2][$key]);
                        if ($formBuilderRender instanceof \ProcessWire\FormBuilderRender) {
                            $event->return = str_replace($tag, $formBuilderRender->render(), $event->return);
                        }
                    } catch (\Exception $e) {
                        // rendering the form failed, not much we can do about that now
                    }
                }
            }
        });
    }

}
