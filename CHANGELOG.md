# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- New Renderer class for rendering a search form and/or a list of search results.
- New Data class to serve as a wrapper for WireData with improved getDot() support.
- SearchEngine::render*() methods matching each render-prefixed method in Renderer.

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
