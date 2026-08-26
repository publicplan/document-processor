# Publicplan Document Processor

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

Standalone DOCX to HTML processor for PHP 8.4+ with Strategy Pattern architecture.

## Features

- ✅ **DOCX to HTML conversion**
- ✅ **Stable public AST API** - HTML, AST, or both from one processor
- ✅ **Optional template syntax annotation** - syntax detection without semantic evaluation
- ✅ **Optional HTML fragment validation**
- ✅ **Strategy Pattern architecture** - 10 specialized element converters
- ✅ **Clean Architecture** - SRP, testable, maintainable
- ✅ **Stateless design** - Thread-safe processing
- ✅ **List wrapper metadata** - HTML `<ul>/<ol>` tags carry `data-docx-list-id` and `data-docx-list-key`
- ✅ **Comprehensive testing**

## Installation

```bash
composer require publicplan/document-processor
```

## Quick Start

```php
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Service\Ast\AstDocumentProcessor;
use Publicplan\DocumentProcessor\Service\Ast\Template\GenericTemplateSyntaxProfile;
use Publicplan\DocumentProcessor\Model\ProcessingOptions;

// Initialize
$loader = new DocumentLoader();
$processor = new DocumentProcessor($loader);

// Process document
$result = $processor->process('/path/to/file.docx', 'filename.docx');

// Access results
$html = $result->html;
$hasChanges = $result->hasUnacceptedChanges;
$messages = $result->getAllMessages();

// Optional: keep deleted/strikethrough content visible in the HTML output
$resultWithDeletedContent = $processor->process(
    '/path/to/file.docx',
    'filename.docx',
    new ProcessingOptions(removeDeletedContent: false)
);

// Optional: validate that the generated HTML fragment is parser-tolerant
$validatedResult = $processor->process(
    '/path/to/file.docx',
    'filename.docx',
    new ProcessingOptions(validateHtml: true)
);

$isHtmlFragmentValid = $validatedResult->isHtmlFragmentValid;
$htmlValidationWarnings = $validatedResult->getWarnings();

// Optional: use the public AST API (stable integration contract)
$astProcessor = new AstDocumentProcessor($loader);
$astOnly = $astProcessor->processToAst('/path/to/file.docx', 'filename.docx');
$astAndHtml = $astProcessor->processToAstAndHtml('/path/to/file.docx', 'filename.docx');

// Optional: enable template syntax annotation on the public AST
$annotatedAst = $astProcessor->processToAst(
    '/path/to/file.docx',
    'filename.docx',
    new ProcessingOptions(templateSyntaxProfile: new GenericTemplateSyntaxProfile())
);

$astVersion = $astOnly->astVersion; // currently "1.5.0"
$ast = $astOnly->ast;               // public AST contract (no renderer internals)
$htmlFromAstRoute = $astAndHtml->html;
```

The optional validation checks whether the generated output can be parsed as an **HTML fragment**. It is intentionally diagnostic: the HTML is still returned, and parser findings are exposed via the existing message structure.

### Public AST contract and compatibility

- `AstDocumentProcessor::processToHtml(...)` returns HTML-only DTOs.
- `AstDocumentProcessor::processToAst(...)` returns AST-only DTOs (`astVersion` + `ast`).
- `AstDocumentProcessor::processToAstAndHtml(...)` returns both in one DTO.
- `astVersion` follows SemVer and is independent from package versioning.
- Internal renderer metadata (for example `legacy_html` render hints) is excluded from the public AST payload.

### Optional template syntax annotation

The AST route can optionally annotate placeholder and control syntax without evaluating it.

- Annotation is **disabled by default**.
- Enable it by passing a `TemplateSyntaxProfile` via `ProcessingOptions`.
- The bundled `GenericTemplateSyntaxProfile` recognizes `{{ ... }}`, `{% ... %}` and `#{ ... }`.
- Control fragments inside `{% ... %}` are additionally classified as `when`, `else_if`, `else` or `end` when they start with `wenn`, `sonst wenn`, `sonst` or `ende`.
- Incomplete fragments are preserved and marked as `malformed`.
- Detected fragments are exposed on `metadata.sourceRef.xmlAttributes.templateAnnotations`.

Example:

```php
use Publicplan\DocumentProcessor\Model\ProcessingOptions;
use Publicplan\DocumentProcessor\Service\Ast\AstDocumentProcessor;
use Publicplan\DocumentProcessor\Service\Ast\Template\GenericTemplateSyntaxProfile;
use Publicplan\DocumentProcessor\Service\DocumentLoader;

$processor = new AstDocumentProcessor(new DocumentLoader());

$result = $processor->processToAst(
    '/path/to/file.docx',
    'template.docx',
    new ProcessingOptions(
        templateSyntaxProfile: new GenericTemplateSyntaxProfile()
    )
);

$annotations = $result->ast['sections'][0]['paragraphs'][0]['metadata']['sourceRef']['xmlAttributes']['templateAnnotations'] ?? [];
```

Implement your own `TemplateSyntaxProfile` to support application-specific dialects. See [`doc/template-syntax-profiles.md`](doc/template-syntax-profiles.md).

### Boundary for consuming applications

The library stays intentionally syntax-oriented:

- it exposes document structure via the public AST
- it can optionally mark placeholder/control syntax ranges
- it does **not** evaluate conditions, resolve placeholders, or attach business meaning

Consuming applications should therefore:

1. choose the bundled `GenericTemplateSyntaxProfile` or provide their own profile
2. interpret `templateAnnotations` in the application layer
3. migrate business logic away from HTML re-parsing toward AST-driven processing where useful

### Paragraph Formatting Styles

The AST HTML renderer fully supports paragraph formatting styles from DOCX:

**Spacing:**
- `margin-top`: From document `spacingBefore` (converted to centimeters)
- `margin-bottom`: From document `spacingAfter` (converted to centimeters)

**Indentation:**
- `margin-left`: From `indentLeft` (centimeters)
- `margin-right`: From `indentRight` (centimeters)
- `text-indent`: From `indentFirstLine` (centimeters, supports negative values for hanging indents)

**Line Height:**
- `line-height`: Direct from document (unitless multiplier, e.g., 1.5 for 150%)

All measurements are automatically converted from TWIPS to centimeters.

Example HTML output:
```html
<p style="margin-top: 0.64cm; margin-bottom: 0.64cm; line-height: 1.5; margin-left: 1.27cm; text-indent: 0.64cm;">
  Text with comprehensive paragraph formatting
</p>
```

### List and table layout metadata in public AST

For AST-driven renderers (for example Twig exports), list and table nodes now expose explicit Word-aligned layout metadata, so inline styles can be derived without heuristic CSS resets:

- `listItem.indent.{left,right,firstLine,hanging}`
- `listItem.spacing.{before,after,line}`
- `listItem.level.{indentLeft,indentHanging,tabStop,markerOffset}`
- `listItem.resolvedLayout.marker.{rawNumFmt,lvlText,lvlSuffix,lvlJc,start,justification,font}` (+ compatibility aliases: `format,text,suffix,markerFont,restart`)
- `list.spacing.{before,after}` and `list.indent.left`
- `table.indent.left`, `table.spacing.{before,after}`, `table.cellSpacing`, `table.layout`, `table.cellMargins`

### Hybrid style strategy in public AST

The AST uses a hybrid approach for stable HTML/PDF parity:

- `styleRef` / `styleRefs` provide centralized references to paragraph/numbering/table/character styles
- `resolvedLayout` keeps renderer-ready inline values (especially relevant for wkhtmltopdf consistency)
- `styleProvenance` exposes per-field origin (`direct`, `style`, `basedOn`, `default`, `numberingLevel`, `rendererDefault`)

Resolution priority is deterministic:

1. direct node override (`document.xml`)
2. referenced style (`styles.xml` / `numbering.xml`)
3. `basedOn` chain
4. document defaults
5. renderer default

### Document base font size in public AST

`document.baseFontSizePt` is always present and defines the canonical base size for the whole document.

- `document.baseFontSizePt` (`float`, points): deterministic base size for relative typography
- `document.baseFontSizeSource` (`string`): source used for resolution (`docDefaults`, `normalStyle`, `styleChain`, `bodyRuns`, `fallback`)
- `document.baseFontSizeRaw` (`object`, optional): debug payload with raw resolution hints

Resolution priority is fixed (first valid source wins):

1. `styles.xml` → `docDefaults` → `w:rPrDefault/w:rPr/w:sz` (fallback `w:szCs`)
2. paragraph style `Normal` / primary body style including `basedOn` chain
3. most frequent body-run size from flowing text (`document.xml`, tables/TOC excluded)
4. hard fallback `12pt`

Relative values (for example `em` or `data-font-scale`) should be interpreted against `document.baseFontSizePt`. Downstream consumers therefore do not need their own base-font heuristics.

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
