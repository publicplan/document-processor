# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-03-05

### Added
- Initial release
- DOCX to HTML conversion
- Strategy Pattern architecture with 10 element converters
- Support for: text, lists, tables, links, formatting (bold, italic, underline)
- Comprehensive test suite (33 tests, 71 assertions)
- MIT License
- Full PHP 8.4+ compatibility

### Features
- `DocumentProcessor::process()` - Main facade method
- `DocumentLoader` - Loading and validation
- `ProcessedDocument` DTO for results
- Element converters for all major Word elements
- Bottom spacing support for list items
- Track Changes detection

[1.0.0]: https://github.com/publicplan/document-processor/releases/tag/v1.0.0
