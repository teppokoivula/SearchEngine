<?php

namespace SearchEngine;

/**
 * SearchEngine Finder
 *
 * @version 0.1.1
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
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

        // Resulting Query object
        $query = $this->wire(new Query($query, $args));

        // Bail out early if query is empty
        if (empty($query->query)) {
            return $query;
        }

        // Merge arguments with defaults
        $query->args = array_replace_recursive(
            $this->getOptions()['find_args'],
            $query->args
        );

        // Find results
        if (isset($args['sort']) && $args['sort'] === '_indexed_templates') {
            $this->addHookBefore('PageFinder::getQuery', function(\ProcessWire\HookEvent $event) {
                if (empty($event->arguments[1]['PWSE_Query'])) return;
                $event->replace = true;
                $event->return = $event->arguments[1]['PWSE_Query'];
            });
            $pwse_query = $query->getQuery()
                ->select(
                    'field(pwse_t.name, "'
                    . implode('", "', $this->getOptions()['indexed_templates'])
                    . '") as pwse_score'
                )
                ->leftjoin('templates AS pwse_t ON pwse_t.id=pages.templates_id')
                ->orderby('pwse_score ASC', true);
            $query->results = $this->wire('pages')->find($pwse_query->selectors, [
                'PWSE_Query' => $pwse_query,
            ]);
            if ($query->results !== null) {
                list($start, $limit) = explode(',', $pwse_query->limit[0] ?? ',');
                $query->results->setStart($start ?? 0);
                $query->results->setLimit($limit ?? $query->args['limit'] ?? 20);
            }
        } else {
            $query->results = $this->wire('pages')->find($query->getSelector());
        }

        return $query;
    }

}
