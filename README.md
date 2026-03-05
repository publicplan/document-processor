# Publicplan Document Processor

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Standalone DOCX to HTML processor for PHP 8.4+ with Strategy Pattern architecture.

## Features

- ✅ **DOCX to HTML conversion**
- ✅ **Strategy Pattern architecture** - 10 specialized element converters
- ✅ **Clean Architecture** - SRP, testable, maintainable
- ✅ **Stateless design** - Thread-safe processing
- ✅ **Comprehensive testing** - 33 tests, 71 assertions

## Installation

```bash
composer require publicplan/document-processor
```

## Quick Start

```php
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;

// Initialize
$loader = new DocumentLoader();
$processor = new DocumentProcessor($loader);

// Process document
$result = $processor->process('/path/to/file.docx', 'filename.docx');

// Access results
$html = $result->html;
$hasChanges = $result->hasUnacceptedChanges;
$messages = $result->getAllMessages();
```

## Framework Integration

### Symfony

Register services in `config/services.yaml`:

```yaml
Publicplan\DocumentProcessor\:
  resource: '../vendor/publicplan/document-processor/src/'
  autowire: true
  autoconfigure: true
  exclude:
    - '../vendor/publicplan/document-processor/src/Service/Converter/'
```

Then inject in your controller:

```php
use Publicplan\DocumentProcessor\Service\DocumentProcessor;

class MyController extends AbstractController
{
    public function upload(
        Request $request,
        DocumentProcessor $processor
    ): Response {
        $result = $processor->process('/path/to/file.docx', 'doc.docx');
        return $this->render('result.html.twig', ['html' => $result->html]);
    }
}
```

### Laravel

Register in `config/app.php`:

```php
'providers' => [
    // ...
    App\Providers\DocumentProcessorServiceProvider::class,
],
```

Or use directly:

```php
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;

Route::post('/upload', function (Request $request) {
    $processor = new DocumentProcessor(new DocumentLoader());
    $result = $processor->process($request->file('docx')->path(), 'upload.docx');
    return view('result', ['html' => $result->html]);
});
```

### Plain PHP

```php
require 'vendor/autoload.php';

use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;

$processor = new DocumentProcessor(new DocumentLoader());
$result = $processor->process('document.docx', 'document.docx');
echo $result->html;
```

## Architecture

```
DocumentProcessor
├── DocumentLoader (DOCX loading & validation)
├── Element Converters (Strategy Pattern)
│   ├── TextElementConverter
│   ├── TextRunElementConverter
│   ├── ListElementConverter
│   ├── TableElementConverter
│   ├── LinkElementConverter
│   ├── BreakElementConverter
│   ├── PageBreakElementConverter
│   └── PreserveTextElementConverter
└── ElementConverterRegistry
```

## Requirements

- PHP 8.4+
- phpoffice/phpword ^1.0
- ext-zip (for DOCX handling)

## Testing

```bash
composer install
composer test
```

## Contributing

1. Fork the repository
2. Create feature branch: `git checkout -b feature/my-feature`
3. Commit changes: `git commit -am 'Add some feature'`
4. Push to branch: `git push origin feature/my-feature`
5. Create Pull Request

Please ensure all tests pass and follow PSR-12 coding standards.

## Versioning

We use [SemVer](https://semver.org/):
- MAJOR: Breaking changes
- MINOR: New features (backward compatible)
- PATCH: Bug fixes (backward compatible)

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Credits

Created by [Publicplan GmbH](https://www.publicplan.de/)

## Related Projects

- [Jarvis](https://github.com/publicplan/jarvis) - Bridge system for Confluence/Jira integration

---

**Note**: This bundle was extracted from the Jarvis project to be a standalone, reusable component.
