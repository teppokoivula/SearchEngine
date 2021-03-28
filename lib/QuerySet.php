<?php

namespace SearchEngine;

/**
 * SearchEngine QuerySet class
 *
 * This class represents a set of one or more Query objects.
 *
 * @version 0.1.0
 * @author Teppo Koivula <teppo.koivula@gmail.com>
 * @license Mozilla Public License v2.0 https://mozilla.org/MPL/2.0/
 *
 * @property-read string $selector Final selector string.
 * @property array $items Query objects contained in this set.
 * @property-read \ProcessWire\PageArray|null $results Combined results from all Query objects.
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
     * @var array
     */
    protected $items = [];

    /**
     * The identifier that Query results are grouped by
     *
     * @var string
     */
    protected $grouped_by = '';

    /**
     * Constructor method
     *
     * @param string|null $query The query
     * @param array $args Additional arguments, see QueryBase class for details
     */
    public function __construct(?string $query = '', array $args = []) {
        parent::__construct($query, $args);
    }

    /**
     * Magic getter method
     *
     * @param string $name Property name.
     * @return mixed
     */
    public function __get($name) {
        switch ($name) {
            case 'results':
                $results = new \ProcessWire\PageArray();
                foreach ($this->items as $item) {
                    $results->add($item->results);
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
        return !empty($this->$name);
    }

	/**
	 * Allows iteration of the QuerySet
	 *
	 * @return \ArrayObject
	 */
	public function getIterator() {
		return new \ArrayObject($this->items);
	}

}
