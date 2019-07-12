<?php

namespace SearchEngine;

/**
 * SearchEngine Finder
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 http://mozilla.org/MPL/2.0/
 */
class Finder extends Base {

    /**
     * Find content matching provided query string.
     *
     * @param mixed $query The query.
     * @param array $args Additional arguments, see Query::__construct() for details.
     * @return Query Resulting Query object.
     */
    public function find($query = null, array $args = []): Query {

        // Resulting Query object.
        $query = $this->wire(new Query($query, $args));

        // Bail out early if query is empty.
        if (empty($query->query)) {
            return $query;
        }

        // Merge arguments with defaults.
        $query->args = array_replace_recursive(
            $this->options['find_args'],
            $query->args
        );

        // Find results.
        $query->results = $this->wire('pages')->find($query->getSelector());

        return $query;
    }

}
