# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.11.0] - 2019-09-26

### Added
- Support for indexing Page Reference fields.
- Support for indexing non-field Page properties (id, name).
- New hookable method Indexer::___getPageReferenceIndexValue().

### Changed
- Index value gets saved in Pages::saved instead of Pages::saveReady so that we can avoid messing with the regular save process.

### Fixed
- Fixed the "save" behaviour of the Indexer::indexPage() method.

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
- Fix an issue where search result links and paths were wrong on subdirectory installs.

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
- Fixed breaking issues introduced with last minute changes to 0.4.0.

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
