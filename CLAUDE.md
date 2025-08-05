# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the SearchEngine module for ProcessWire CMS/CMF. It creates an index of page contents and provides search functionality for ProcessWire sites. The module follows ProcessWire conventions and implements a comprehensive search solution with features like multilanguage support, theming, and JSON output for AJAX implementations.

## Development Commands

### Build Assets
- `npm run build` - Builds JavaScript assets with Parcel and minifies CSS themes
  - Requires: `npm install -g parcel-bundler clean-css clean-css-cli`
  - Bundles `/js/*.js` files to `/js/dist/`  
  - Minifies theme styles from `/themes/default/style.css` to `/themes/default/style.min.css`

### Testing & Development
No specific test commands are defined. This is a ProcessWire module that should be tested within a ProcessWire installation.

## Architecture

### Core Module Structure
- **SearchEngine.module.php** - Main module file extending `WireData` and implementing `Module, ConfigurableModule`
- **SearchEngine.info.json** - Module metadata and ProcessWire configuration
- **composer.json** - Composer package configuration for installation via `teppokoivula/search-engine`

### Library Classes (lib/ directory)
All classes extend the `SearchEngine\Base` class which provides common functionality:

- **Base.php** - Abstract base class providing options access and string merging
- **Indexer.php** - Handles indexing page content into search fields
- **Finder.php** - Performs search queries and returns results
- **Query.php** & **QueryBase.php** - Query objects for search operations
- **QuerySet.php** - Collection of Query objects for grouped results
- **Renderer.php** - Renders search forms, results, and associated markup
- **Processor.php** - Processes content before indexing
- **IndexerActions.php** - Actions performed during indexing
- **Config.php** - Configuration management
- **Data.php** - Data handling utilities
- **Debugger.php** - Debug functionality
- **IndexValue.php** - Represents indexed values

### Key Features
1. **Content Indexing** - Automatically indexes specified fields into a search index field
2. **Multilanguage Support** - Works with ProcessWire's multilanguage fields
3. **Flexible Rendering** - Provides methods to render complete search UI or individual components
4. **JSON Output** - Supports AJAX search implementations via JSON responses  
5. **Theming** - Theme system with CSS/JS asset management
6. **Grouped Results** - Can group results by template or other criteria

### Configuration System
The module uses a comprehensive options system defined in `SearchEngine::$defaultOptions`. Configuration can be set via:
- ProcessWire module configuration screen (Admin)
- Site config file: `$config->SearchEngine = [...]`
- Method parameter arrays for runtime overrides

Key configuration areas:
- **index_field** - Field name for storing indexed content (default: 'search_index')
- **indexed_fields** - Array of field names to index (default: title, headline, summary, body)
- **compatible_fieldtypes** - Fieldtypes that can be indexed
- **find_args** - Search behavior (limit, sort, operator, etc.)
- **render_args** - UI rendering options and templates

### ProcessWire Integration
- Hooks into page save operations for automatic indexing
- Uses ProcessWire's selector engine for search queries
- Integrates with ProcessWire's field system and multilanguage features
- Follows ProcessWire module conventions for installation and configuration

### Frontend Assets
- **js/src/** - Source JavaScript files (Config.js, Core.js, Debugger.js, Tabs.js)
- **js/dist/** - Built/bundled JavaScript files
- **css/** - Admin interface styles
- **themes/default/** - Default theme with styles and configuration

## Module Usage Patterns

### Basic Implementation
```php
// Simple render everything approach
echo $modules->get('SearchEngine')->render();

// Render specific components
echo $modules->get('SearchEngine')->render(['form', 'results']);

// Manual rendering with full control
$se = $modules->get('SearchEngine');
echo $se->renderForm();
echo $se->renderResults();
```

### Custom Search Implementation
```php
// Direct field querying
$results = $pages->find('search_index%=' . $sanitizer->selectorValue($q));

// Module-handled search
$query = $modules->get('SearchEngine')->find($input->get->q);
echo $modules->get('SearchEngine')->renderResults([], $query);
```

### JSON/AJAX Search
```php
$query = $modules->get('SearchEngine')->find('search term');
$json = $modules->get('SearchEngine')->renderResultsJSON([
    'results_json_fields' => [
        'title' => 'title',
        'url' => 'url',
        'desc' => 'summary'
    ]
], $query);
```