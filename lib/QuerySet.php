<?php

namespace SearchEngine;

use ProcessWire\PageArray;

/**
 * SearchEngine QuerySet class
 *
 * This class represents a set of one or more Query objects.
 *
 * @version 0.1.4
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 *
 * @property-read string $selector Final selector string.
 * @property-read string $operator Operator used by the selector.
 * @property Query[] $items Query objects contained in this set.
 * @property-read PageArray|null $results Combined results from all Query objects, or null if none found.
 * @property-read int $resultsCount Number of combined visible results in all Query objects.
 * @property-read int $resultsTotal Number of combined total results in all Query objects.
 * @property-read string $resultsGroupedBy Identifier that Query results are grouped by. Same as groupedBy.
 * @property-read string $groupedBy Identifier that Query results are grouped by. Same as resultsGroupedBy.
 * @property-read string $pager Rendered pager or empty string if not supported.
 * @property-read string $resultsPager Rendered pager or empty string if not supported.
 */
class QuerySet extends QueryBase implements \IteratorAggregate {

    /**
     * Query objects contained in this set
     *
     * @var Query[]
     */
    protected $items = [];

    /**
     * The identifier that Query results are grouped by
     *
     * @var string
     */
    protected $grouped_by = '';

    /**
     * Magic getter method
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'selector':
                return $this->getSelector();
                break;
            case 'operator':
                return $this->getOperator();
                break;
            case 'results':
                $results = new PageArray();
                foreach ($this->items as $item) {
                    $item_results = $item->getResults();
                    if ($item_results !== null) {
                        $results->add($item_results);
                    }
                }
                return $results->count() ? $results : null;
                break;
            case 'resultsCount':
                $count = 0;
                foreach ($this->items as $item) {
                    $count += $item->resultsCount;
                }
                return $count;
                break;
            case 'resultsTotal':
                $count = 0;
                foreach ($this->items as $item) {
                    $count += $item->resultsTotal;
                }
                return $count;
                break;
            case 'groupedBy':
            case 'resultsGroupedBy':
                return $this->grouped_by;
                break;
        }
        return $this->$name;
    }

    /**
     * Add new query item
     *
     * @param Query $query
     * @return QuerySet Self-reference.
     */
    public function add($query): QuerySet {
        $this->items[] = $query;
        return $this;
    }

    /**
     * Magic setter method
     *
     * This method is added so that we can modify some values on storage (sanitize etc.)
     *
     * @param string $name Property name
     * @param mixed $value Property value
     */
    public function __set(string $name, $value) {
        if ($name === "items") {
            if (is_array($value)) {
                $value = array_filter($value, function($query) {
                    return $query instanceof Query;
                });
                $this->items = $value;
            }
        } else if ($name === "grouped_by") {
            $this->grouped_by = (string) $value;
        }
    }

    /**
     * Magic isset method
     *
     * @param string $name Property name
     * @return bool
     */
    public function __isset(string $name): bool {
        return !empty($this->$name) || !empty($this->__get($name));
    }

	/**
	 * Allows iteration of the QuerySet
	 *
	 * @return \ArrayObject
	 */
	public function getIterator(): \Traversable {
		return new \ArrayObject($this->items);
	}

}
