# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.28.1] - 2021-02-15

### Fixed
- Fixed an issue where recreating entire index wasn't working due to wrong variable name in Indexer::indexPages() method.

## [0.28.0] - 2021-02-14

### Added
- Support for Indexer actions, the first one of which adds support for rendering FormBuilder forms as part of the search index. This feature is currently considered experimental.
- Option to specify custom path for front-end themes via the Advanced settings section in module config.
- EditorConfig (.editorconfig) and ESLint (.eslintrc.json) config files for defining coding style for IDEs.
- Support for collapsible content blocks in Debugger.

### Changed
- Indexed templates option in module config is now AsmSelect, which makes it possible to organize indexed templates by preferred priority.
- Query class converts lesser than and greater than to HTML entities to allow matching said entities, as well as encoded HTML markup.
- All Debugger CSS classes refactored to follow `.pwse-` format.

## [0.27.1] - 2021-02-09

### Fixed
- Fixed an issue where Debugger was displaying a notice due to missing returnAllCols argument for PageFinder::getQuery().

## [0.27.0] - 2020-11-18

### Added
- Page ID and name made available as indexed field options in module configuration.

### Changed
- Various changes to metadata processing: better support for nested data structures and better support for finding results based on things such as page ref values.

### Fixed
- Fixed an issue where file/image fields could sometimes return singular value, resulting in errors during indexing.

## [0.26.0] - 2020-08-27

### Changed
- SearchEngine::initOnce() is now a public method just in case that the module needs to be initialized from the outside.

## [0.25.2] - 2020-08-13

### Fixed
- SearchEngine::savedPageIndex() gets called also when indexing multiple pages.

## [0.25.1] - 2020-07-16

### Fixed
- Config screen error affecting ProcessWire versions < 3.0.160.

## [0.25.0] - 2020-07-12

### Added
- Support for new text search selector operators added in ProcessWire 3.0.160.
- New JavaScript class for configuration screen features (PWSE_Config).

## [0.24.0] - 2020-07-07

### Added
- Support for automatically generating result descriptions from indexed content by specifying _auto_desc as field name. Note that this is considered beta; be sure to read the "automatically generating search result descriptions" notes from the README!
- Multi-language support for Debugger.

### Changed
- Various minor upgrades and optimizations.
- Debugger::getDebugContainer() renamed as Debugger::renderDebugContainer().

### Fixed
- Minor issue where search result description highlighting didn't work properly if the hit was at the very beginning of the description.
- Notices during debugging when using Query::getSQL().
- Potential issue where Indexer::indexPage() could fail when index field is multi-lingual and page being indexed had output formatting enabled.

## [0.23.0] - 2020-06-01

### Added
- Improved indexing for FieldtypeOptions.
- getSQL() method and sql property for the Query class.
- Display resulting SQL when debugging queries (as a superuser in the module config screen).
- Enable reindexing the Page object currently being debugged with a single button click.
- PWSE_Core JavaScript class (window.SearchEngine) as a basis for a work-in-progress JS API.

### Changed
- Debugger JavaScript features converted from a jQuery script to vanilla JS (PWSE_Debugger class).
- Debugger PHP AJAX API endpoint moved from Config to Debugger.

## [0.22.0] - 2020-05-24

### Added
- New Debugger class and ability to trigger Debugger from module config screen (superusers only).

### Fixed
- Minor inconsistency in Query class, where result/total counters returned empty strings instead of integer 0 for zero results.

## [0.21.0] - 2020-05-11

### Added
- New hookable method SearchEngine::savedPageIndex(Page $page).
- New args (array) param for Indexer::indexPage() and args (array) + save (bool) params for Indexer::indexPages().
- Both Indexer::indexPage() and Indexer::indexPages() can now optionally return the index itself as an array.

## [0.20.3] - 2020-04-29

### Fixed
- Attempt to fix an issue causing errors with some RepeaterMatrix + FieldsetPage combinations.

## [0.20.2] - 2020-04-14

### Changed
- Minor optimizations for Indexer.

### Fixed
- Make Processor::processIndex() method public.

## [0.20.1] - 2020-04-14

### Changed
- Minor code cleanup here and there.
- Split processing methods from Indexer to a new Processor class.

## [0.20.0] - 2020-04-07

### Added
- Added support for Fieldset (Page).

## [0.19.0] - 2020-03-16

### Added
- New argument 'no_validate' for the Query class. Setting this as true skips query param validation.

### Changed
- Results list no longer needs to be rendered before search form in order to prepopulate form input value.

## [0.18.0] - 2020-03-08

### Added
- Added support for ProFields: Textareas.

### Changed
- Improved the readability of the search index.

## [0.17.3] - 2020-03-08

### Fixed
- Make sure that hidden or unpublished Repeater or PageTable items are skipped while building the index.

## [0.17.2] - 2020-02-16

### Fixed
- Properly index values for single value Page reference fields.

## [0.17.1] - 2020-02-15

### Fixed
- A potential Indexer UTF-8 PCRE issue occurring on macOS environments.

## [0.17.0] - 2020-02-12

### Added
- Added support for ProFields: Table.

## [0.16.0] - 2020-01-19

### Added
- Module config setting for selecting the operator used for finding content.

### Changed
- Updated tools for configuring the module either via module config *or* site config.
- Minor improvements to the Renderer class.
- Reorganized the Config class structure.

### Fixed
- An issue affecting MySQL InnoDB full text searches.

## [0.15.0] - 2020-01-01

### Added
- New module config setting for selecting indexed templates. This is essentially a shortcut for adding/removing index field to/from templates.

### Changed
- If $index_field_name for SearchEngine::getIndexField($index_field_name) is null, use default name from options.

## [0.14.0] - 2020-01-01

### Added
- Display additions and removals in module config screen when compatible fieldtypes have been modified.

### Changed
- Adjustments to MarkupPagerNav default settings and the default theme for a more generic layout.

## [0.13.1] - 2020-01-01

### Fixed
- Added the missing error description for when the query is empty.

## [0.13.0] - 2019-11-09

### Added
- New validations for index field for module config screen, and an option for automatically creating the index field.

### Fixed
- An issue where module config screen wasn't detecting FieldtypeTextareaLanguage as a valid index field type.

## [0.12.1] - 2019-11-07

### Fixed
- Potential conflicts with SearchEngine classes.

## [0.12.0] - 2019-11-07

### Added
- Support for indexing multilanguage content (language support).

### Fixed
- An issue preventing File and Image field descriptions getting indexed.

## [0.11.1] - 2019-09-26

### Changed
- Index value gets saved in Pages::savedPageOrField instead of Pages::saved.

## [0.11.0] - 2019-09-26

### Added
- Support for indexing Page Reference fields.
- Support for indexing non-field Page properties (id, name).
- New hookable method Indexer::___getPageReferenceIndexValue().

### Changed
- Index value gets saved in Pages::saved instead of Pages::saveReady so that we can avoid messing with the regular save process.

### Fixed
- The "save" behaviour of the Indexer::indexPage() method.

## [0.10.1] - 2019-09-04

### Fixed
- Small typo in module settings.

## [0.10.0] - 2019-09-01

### Added
- New Renderer::___renderResultsJSON() method for rendering search results as a JSON string.
- Additional CSS rules to make sure that visited links appear correctly in the default output.

## [0.9.1] - 2019-08-29

### Fixed
- Clear the "what" array if the first param for Renderer::render() is an array of arguments.

## [0.9.0] - 2019-08-01

### Added
- Added new "pass through" rules (__call()) to the module for Indexer::index* methods.
- PHPDoc @method tags to the module for additional methods available via the __call() magic method.

## [0.8.3] - 2019-07-29

### Changed
- Revert earlier info JSON file format change.

## [0.8.2] - 2019-07-29

### Changed
- Changed SearchEngine.info.json format as an attempt to make the Modules directory read it properly.

## [0.8.1] - 2019-07-28

### Fixed
- Handle multiple space-separated classes properly in parent placeholders.
- Accessing non-hookable Renderer methods via the SearchEngine module.

## [0.8.0] - 2019-07-26

### Added
- Support for parent placeholders in render args class names.

### Changed
- Changed default render args class names to use parent placeholders.

## [0.7.0] - 2019-07-26

### Added
- Added new SearchEngine::setOptions() method to override previously defined runtime options.

## [0.6.4] - 2019-07-25

### Fixed
- An issue where search result links and paths were wrong on subdirectory installs.

## [0.6.3] - 2019-07-25

### Changed
- Changed ProcessWire version requirement in composer.json to match the version defined in the module info JSON file.

## [0.6.2] - 2019-07-25

### Fixed
- Translatable default strings are defined run-time in SearchEngine::getDefaultStrings(), which resolves various issues related to language detection.

## [0.6.1] - 2019-07-24

### Changed
- SearchEngine::getModuleConfigInputfields() was passing an extraneous options array to Config::__construct().

## [0.6.0] - 2019-07-24

### Changed
- Unless an overriding selector has been provided, Indexer::indexPages() now processes all non-trashed pages. Previously hidden or unpublished pages were not included.

## [0.5.1] - 2019-07-24

### Added
- Display a warning message for cases where manual indexing doesn't find any pages to index.

### Fixed
- Avoid warnings caused by type declarations differing from those specified by the parent class.

## [0.5.0] - 2019-07-17

### Added
- Added support for theme-specific config files. See README for more details.

## [0.4.1] - 2019-07-17

### Fixed
- Breaking issues introduced with last minute changes to 0.4.0.

## [0.4.0] - 2019-07-17

### Added
- New Renderer class for rendering a search form and/or a list of search results.
- New Data class to serve as a wrapper for WireData with improved getDot() support.
- SearchEngine::render*() methods matching each render-prefixed method in Renderer.

### Changed
- Default selector operator changed from `%=` to `*=`.

## [0.3.3] - 2019-07-15

### Fixed
- Correctly identify FieldtypeRepeaterMatrix as one of the repeatable fieldtypes.

## [0.3.2] - 2019-07-15

### Fixed
- Content from repeatable fields is only indexed if the parent field is included in the indexed_fields array.

## [0.3.1] - 2019-07-13

### Changed
- README updates.

## [0.3.0] - 2019-07-13

### Added
- New Indexer::___getIndexValue and Indexer::___getRepeatableIndexValue methods.

### Changed
- Changed 'link_prefix' option to 'link' item in 'prefixes' array.
- Improved indexing for file and image fields (FieldtypeFile).
- Changed default sort value for Finder::find() to 'sort'.
- Documented available find args in the README file.

## [0.2.0] - 2019-07-13

### Added
- Finder, Query, and Base classes, new SearchEngine::find() method.

### Changed
- Loads of behind the scenes changes to the codebase.
- Improvements and additions to the README file.

## [0.1.0] - 2019-07-09

### Added
- Initial version of the module.
