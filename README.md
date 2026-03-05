# Publicplan Document Processor

Standalone DOCX to HTML processor for PHP 8.4+

## Installation

```bash
composer require publicplan/document-processor
```

## Usage

```php
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;

$loader = new DocumentLoader();
$processor = new DocumentProcessor($loader);

$result = $processor->process('/path/to/file.docx', 'filename.docx');

$html = $result->html;
$hasChanges = $result->hasUnacceptedChanges;
$messages = $result->getAllMessages();
```

## Requirements

- PHP 8.4+
- phpoffice/phpword ^1.0

## Testing

```bash
composer test
```

## License

MIT
