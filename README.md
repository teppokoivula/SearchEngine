SearchEngine ProcessWire module
-------------------------------

SearchEngine is a module that creates an index of page contents, and makes it easy to search.

## Usage

1) Install SearchEngine module.

The module will automatically create an index field during installation, so you should define a custom field (via site config) *before installing* if you don't want it to be called "search_index". You can change the field name later as well, but you'll have to update the "index_field" option in site config or module settings (in Admin) after renaming the field.

2) Add the index field to templates you want to make searchable.

You can add the index field to templates via template settings, field settings (the "Actions" tab), or via the "indexed templates" setting found from the config screen of the SearchEngine module. After the field has been added to a template, every time a page using that template is saved, content from indexable fields will be automatically stored into the (typically hidden) index field.

3) Use selectors to query values in site search index, or add appropriate render render-calls to your template files.

At this point there are essentially three ways to use SearchEngine, and which one fits your needs best depends a lot on the site in question:

- You can query the search_index field (or whatever you decided to name it earlier) just like any other textarea field in ProcessWire. This allows you to build custom search features with ease, while SearchEngine takes care of indexing content.
- You can use SearchEngine to find results by calling `$modules->get('SearchEngine')->find($input->get->q)`. Obviously you can pass any search string to the find() method – we're using the "q" GET var here just as an example.
- You can let SearchEngine handle the whole search feature by adding a render call – `$modules->get('SearchEngine')->render()` – to your search page's template file. The `render()` method is a shortcut that calls multiple methods behind the scenes, but you can also call `renderStyles()`, `renderScripts()`, `renderResults()`, and `renderForm()` separately.

First approach is the simple one and only involves one method call:

```
<?php namespace ProcessWire; ?>
<body>
    <?= $modules->get('SearchEngine')->render() ?>
</body>
```

You can also pass an array to render(), rendering only specific features. Here's an example of rendering just the form and results list without styles or scripts:

```
<?php namespace ProcessWire; ?>
<body>
    <?= $modules->get('SearchEngine')->render(['form', 'results']) ?>
</body>
```

Finally, here's the "manual approach" – this involves some additional steps, but provides more control over the rendered output than other methods:

```
<?php namespace ProcessWire;
$searchEngine = $modules->get('SearchEngine');
...
<head>
    <?= $searchEngine->renderStyles() ?>
    <?= $searchEngine->renderScripts() ?>
</head>
<body>
    <?php
    // Note: results are rendered before form because this way the form instantly
    // has access to whitelisted query string (if a search was already performed).
    $results = $searchEngine->renderResults();
    $form = $searchEngine->renderForm();
    echo $form . $results;
    ?>
</body>
```

### Multilanguage use (language support)

SearchEngine supports indexing multilingual content. Once ProcessWire's native multilanguage features are enabled, support for multilanguage indexing can be enabled by converting the index field to FieldtypeTextareaLanguage.

### Advanced use

If you render the results list with one of the methods mentioned earlier, SearchEngine will automatically look for the query param defined in its settings – and if this param is found (GET), it will perform a search query for you. If you don't want the module to handle this part for you, you can also perform the query yourself (or just don't render the results list).

You can access the search index field just like any other ProcessWire field with selectors:

```php
if ($q = $sanitizer->selectorValue($input->get->q)) {
    // This finds pages matching the query string and returns them as a PageArray:
    $results = $pages->find('search_index%=' . $q . ', limit=25');

    // Render results and pager with PageArray::render() and PageArray::renderPager():
    echo $results->render(); // PageArray::render()
    echo $results->renderPager(); // PageArray::renderPager()

    // ... or you iterate over the results and render them manually:
    echo "<ul>";
    foreach ($results as $result) {
        echo "<li><a href='{$result->url}'>{$result->title}</a></li>";
    }
    echo "</ul>";
}
```

*Note: while you can use any operator for your selectors, you will likely find the `*=` and `%=` operators most useful while querying the index. You can read more about selector operators from [ProcessWire's documentation on selectors](https://processwire.com/docs/selectors/). By default this module will use the `*=` operator for built-in features.*

Alternatively you can delegate the find operation to the SearchEngine module:

```php
// This performs the query and returns a \SearchEngine\Query object:
$query = $modules->get('SearchEngine')->find($input->get->q);

// This object can be rendered manually, or you can use \SearchEngine\Renderer to render it:
// (First param is an array of custom arguments, second is the Query object.)
echo $modules->get('SearchEngine')->renderResults([], $query);
```

### JSON output

The Renderer class provides support for returning search results as a JSON string, which can be particularly useful for implementing AJAX search features. JSON output is returned by the `Renderer::renderResultsJSON()` method, and you can customise returned keys and values via results_json_fields configuration option.

The results_json_fields is an array of keys and field names. There are two "special" field name prefixes – `template.` and `parent.` – which provide access to template and parent page properties.

Here's an example of how you might use the renderResultsJSON() method in your own code:

```
// Get the SearchEngine module.
$se = $modules->get('SearchEngine');

// Find pages matching our keyword ("composer"). You could also omit the query and let SearchEngine
// grab the keyword from the configured query GET param (if present).
$query = $se->find('composer');

// Return results as JSON.
$json = $se->renderResultsJSON([
    'results_json_fields' => [
        'title' => 'title',
        'desc' => 'summary',
        'url' => 'url',
        'parent' => 'parent.title',
        'template' => 'template.label',
    ],
    'results_json_options' => JSON_PRETTY_PRINT,
], $query);

var_dump($json);
```

... which, for an example, might result in following output:

```
{
    "results": [
        {
            "title": "Git ignore (.gitignore) file",
            "desc": "<p>The Wireframe boilerplate site profile includes an opinionated .gitignore file based on Bare Minimum Git project.<\/p>",
            "url": "\/docs\/wireframe-boilerplate\/git-ignore-file\/",
            "parent": "Wireframe boilerplate",
            "template": "Basic page"
        },
        {
            "title": "Directory structure",
            "desc": "<p>The directory structure outlined here is loosely based on the <a href=\"http:\/\/framework.zend.com\/manual\/1.12\/en\/project-structure.project.html\" rel=\"nofollow\">recommended project directory structure for Zend Framework 1.x<\/a>. Each component has it's place in the tree, and each directory exists for a reason.<\/p>",
            "url": "\/docs\/directory-structure\/",
            "parent": "Docs",
            "template": "Basic page"
        },
        {
            "title": "Wireframe boilerplate",
            "desc": "<p>Wireframe boilerplate is a ProcessWire starter site profile based on the Wireframe output framework.<\/p>",
            "url": "\/docs\/wireframe-boilerplate\/",
            "parent": "Docs",
            "template": "Basic page"
        },
        {
            "title": "ProcessWire Composer Installer",
            "desc": "<p>ProcessWire Composer Installer provides <a href=\"https:\/\/getcomposer.org\/doc\/articles\/custom-installers.md\" rel=\"nofollow\">Composer custom installers<\/a> for ProcessWire CMS\/CMF modules and site profiles. While not strictly required by Wireframe, it is an easy way to get started with either Wireframe or the Wireframe Boilerplate site profile.<\/p>",
            "url": "\/docs\/processwire-composer-installer\/",
            "parent": "Docs",
            "template": "Basic page"
        }
    ],
    "count": 4,
    "total": 4
}
```

### Rebuilding the search index

If you want to rebuild (recreate) the search index for all pages or pages matching a specific selector, you can do that via the Admin GUI (module configuration screen), or you can perform following request via the API:

```php
// All indexable pages:
$modules->get('SearchEngine')->indexPages();

// Indexable pages matching a selector string:
$modules->get('SearchEngine')->indexPages('template=basic-page');

// Alternatively index just a single page (passing in a Page object):
$modules->get('SearchEngine')->indexPage($page);
```

## Options

By default the module will create a search index field called 'search_index' and store values from Page fields title, headline, summary, and body to said index field when a page is saved. You can modify the default behaviour via the Module config screen in the PocessWire Admin, or by defining $config->SearchEngine array in your site config file or other applicable location.

Below you'll find a summary of available options. Note that this is not a comprehensive list – please check the $defaultOptions array near the beginning of the SearchEngine.module.php file for a list with all available options. Some options can be modified via the admin (module config screen), while *all* options can be defined via code – and if you define an option via site config, it *can't* be overridden via admin.

You can also pass custom options to certain method calls. Most render-prefixed methods, for an example, accept an array of custom render arguments (see "render_args" in the list below) so that you can optionally override module and site defaults on a case-by-case basis.

```php
$config->SearchEngine = [

    // Index field defines the field to store indexed data in. Default value is "search_index".
    'index_field' => 'search_index',

    // This is an array of all the fields you want to index. Currently there is no way to
    // index "all compatible fields", but such an option may be added in the future. (This
    // is, in fact, a matter of security: by indexing *all* fields you could inadvertently
    // expose sensitive data.)
    'indexed_fields' => [
        // ...
    ],

    // This is an array of fieldtypes that the module should be considered compatible with.
    // You should only modify these values if you're sure that the field in question is
    // indeed compatible with the indexing process.
    'compatible_fieldtypes' => [
        // ...
    ],

    // Currently prefixes contains just the "link" prefix – more may be added in the future.
    'prefixes' => [
        // The "link" prefix is added to indexed links and enables "link:https://..." queries.
        'link' => 'link:',
    ],

    // Find arguments are pretty self-explanatory. These are the defaults and apply only to
    // SearchEngine::find(); custom selectors are not affected.
    'find_args' => [
        'limit' => 20,
        'sort' => 'sort',
        'operator' => '*=',
        'query_param' => 'q',
        // The "selector_extra" option is appended to automatically generated selector string.
        // You might use this to, for example, return just a subset of all indexed templates.
        'selector_extra' => '',
    ],

    // This array is passed directly to the MarkupPagerNav core module, see
    // https://processwire.com/docs/front-end/markup-pager-nav/ for more details.
    'pager_args' => [
        // ...
    ],

    // Render arguments affect the rendered output from the SearchEngine module.
    'render_args' => [

        // At the moment there's only one "theme" available ("default").
        'theme' => 'default',

        // By default theme-files (styles and scripts) are served minified; setting
        // this option to `false` will serve unmodified, original assets instead.
        'minified_resources' => true,

        // Various attributes used by the search form and results list when rendered.
        'form_action' => './',
        'form_id' => 'se-form',
        'form_input_id' => 'se-form-input',
        'results_summary_id' => 'se-results-summary',
        'results_id' => 'se-results',

        // Summary of each result (in the search results list) is the value of this field.
        'result_summary_field' => 'summary',

        // Highlighting basically wraps each instance of the search term in the summary
        // text with a predefined tag and class (default element is `<strong></strong>`).
        'results_highlight_query' => true,

        // These settings define the fields used when search results are rendered as JSON.
        'results_json_fields' => [
            'title' => 'title',
            'desc' => 'summary',
            'url' => 'url',
        ],

        // This integer is passed to the json_encode call in renderResultsJSON method.
        // See https://www.php.net/json_encode for supported values.
        'results_json_options' => 0,

        // This defines whether a pager should be rendered at the end of the results list.
        'pager' => true,

        // Array of classes to use in templates. You can add new classes here as well.
        'classes' => [
            // ...
        ],

        // Array of strings to use in templates. You can add new classes here as well. Note
        // that most of these are `null` in the $defaultOptions array; the reason for this
        // is simply that methods cannot be called when declaring class properties, so the
        // values are set in SearchEngine::__construct().
        'strings' => [
            // ...
        ],

        // Template strings. These are used to render markup on the site.
        'templates' => [
            // ...
        ],

    ],

    // Currently the requirements array only holds the query min length argument.
    'requirements' => [
        'query_min_length' => 3,
    ],

];
```

## Themes

SearchEngine supports a concept of themes. Themes are located in the `themes` directory, under their own subdirectories (e.g. `/themes/theme-name/`), and each one needs to include (at the very least) a config.php file. Here's an example of the contents of a theme config.php file:

```
<?php namespace ProcessWire;

$theme_args = [
    'theme_styles' => [
        // array of styles, such as:
        [
            'name' => 'style',
            'ext' => 'css',
        ],
    ],
    'theme_scripts' => [
        // array of scripts, such as:
        [
            'name' => 'script',
            'ext' => 'js',
        ],
    ],
    'render_args' => [
        // array of render arguments, such as:
        'form_id' => 'my-search-form',
        'strings' => [
            'results_heading' => __('Custom results heading'),
        ],
    ],
    'pager_args' => [
        // array of pager options
    ],
];
```

Again, technically the only requirement is a config.php file with the ProcessWire namespace, in which you declare a $theme_args array. Everything else is optional.

If a theme includes style and/or script files, for each type there should be a directory (i.e. `/themes/theme-name/scripts/` and/or `/themes/theme-name/styles/`), and for each file there should be both the file named by params (such as style.css) and a minified file with ".min" in its name (such as style.min.css).

## Requirements

- ProcessWire >= 3.0.112
- PHP >= 7.1.0

## Installing

This module can be installed – just like any other ProcessWire module – by downloading or cloning the SearchEngine directory into your /site/modules/ directory. Alternatively you can install SearchEngine with Composer by executing `composer require teppokoivula/search-engine` in your site directory.

## License

This project is licensed under the Mozilla Public License Version 2.0.
