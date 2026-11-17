<?php

namespace SearchEngine;

/**
 * One search hit that is not the indexed page itself
 *
 * Used when find_args.match_by_source is enabled and the query matches an
 * indexed document. The result still exposes the page-like values the
 * renderer and the site search template read: title, url, parent, template,
 * and parents. `template` stays `file`. `page_template` is the template of the
 * page the file was uploaded to, and `page_templates` is that page's template
 * chain from the root down to the page.
 *
 * @version 0.1.0
 */
class SearchResult extends \ProcessWire\WireData {

    /**
     * @param array $data
     */
    public function __construct(array $data = []) {
        parent::__construct();
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

}

/**
 * Template stand-in for a file result
 *
 * String comparison follows the file name (`file`), so `$result->template == 'featured-event'`
 * stays false. `template.label` still resolves through get().
 */
class SearchResultTemplate extends \ProcessWire\WireData {

    /**
     * @param array $data name, label, and optional id
     */
    public function __construct(array $data = []) {
        parent::__construct();
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * @return string
     */
    public function __toString(): string {
        return (string) $this->get('name');
    }

}
