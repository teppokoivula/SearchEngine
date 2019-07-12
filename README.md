SearchEngine ProcessWire module
-------------------------------

SearchEngine is a module that creates an index of page contents, and makes it easy to search.

## Usage

1) Install SearchEngine module.

*Note: the module will automatically create an index field install time, so be sure to define a custom field before installation if you don't want it to be called "search_index".*

2) Add the site search index field to templates you want to make searchable.
3) Use selectors to query values in site search index.

*Note: you can use any operator for your selectors, you will likely find the '*=' and '%=' operators most useful here. You can read more about selector operators from [ProcessWire's documentation](https://processwire.com/docs/selectors/).'

## Options

By default the module will create a search index field called 'search_index' and store values from Page fields title, headline, summary, and body to said index field when a page is saved. You can modify this behaviour (field name and/or indexed page fields) by defining $config->SearchEngine array in your site config file or other applicable location:

```php
$config->SearchEngine = [
    'index_field' => 'search_index',
    'indexed_fields' => [
        'title',
        'headline',
        'summary',
        'body',
    ],
    'link_prefix' => 'link:',
];
```

You can access the search index field just like any other ProcessWire field with selectors:

```php
if ($q = $sanitizer->selectorValue($input->get->q)) {
    $results = $pages->find('search_index%=' . $query_string . ', limit=25');
    echo $results->render();
    echo $results->renderPager();
}
```

Alternatively you can delegate the find operation to the SearchEngine module as well:

```php
$query = $modules->get('SearchEngine')->find($input->get->q);
echo $query->resultsString; // alias for $query->results->render()
echo $query->pager; // alias for $query->results->renderPager()
```

## Requirements

- ProcessWire >= 3.0.112
- PHP >= 7.1.0

## Installing

This module can be installed – just like any other ProcessWire module – by downloading or cloning the SearchEngine directory into your /site/modules/ directory. Alternatively you can install SearchEngine with Composer by executing `composer require teppokoivula/search-engine` in your site directory.

## License

This project is licensed under the Mozilla Public License Version 2.0.