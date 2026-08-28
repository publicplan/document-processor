# Template syntax profiles

This library can optionally annotate template-like syntax on the public AST without evaluating it. The feature is intended for consuming applications that want to detect placeholders, conditions or similar control fragments in DOCX templates while keeping business semantics outside the library.

## Activation

Template annotation is opt-in. Pass a profile through `ProcessingOptions` when using `AstDocumentProcessor`.

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
```

If no profile is provided, no template detection is performed.

## Profile contract

Profiles implement `Publicplan\DocumentProcessor\Service\Ast\Template\TemplateSyntaxProfile`:

```php
interface TemplateSyntaxProfile
{
    public function getName(): string;

    /**
     * @return list<DetectedTemplateFragment>
     */
    public function detect(string $inlineSequence): array;
}
```

### Input

`detect(string $inlineSequence)` receives a flattened, lossless inline sequence built from the AST after normalization:

- text content stays text content
- tabs become `"\t"`
- line breaks become `"\n"`
- split text runs are merged logically for detection purposes

The profile should only detect syntax patterns in that sequence. It should not evaluate or replace content.

### Output

The profile returns `DetectedTemplateFragment` objects. Each fragment describes:

- `kind` - currently used for values such as `placeholder` or `control`
- `status` - for example `complete` or `malformed`
- `startOffset` / `endOffset` - byte offsets within the flattened inline sequence
- `raw` - the exact matched fragment
- `role` - optional sub-classification such as `when`, `else_if`, `else`, `end`

The library maps those fragments back to the affected AST nodes and stores them under:

```php
$node['metadata']['sourceRef']['xmlAttributes']['templateAnnotations']
```

Each stored annotation contains:

- `matchId`
- `profile`
- `kind`
- `role`
- `status`
- `raw`
- `normalizedRaw`
- `normalizedInner`
- `fragmentRange`
- `sequenceRange`
- `sliceRange`
- `nodeRange`
- `partIndex` / `partCount`
- `isStart` / `isEnd`
- `hasLeadingLiteral` / `hasTrailingLiteral`
- `fragment.openDelimiter` / `fragment.closeDelimiter`
- `fragment.inner` / `fragment.normalizedInner`
- `fragment.normalizedRaw`

`fragmentRange` / `sequenceRange` point to the full fragment in the flattened inline sequence. `sliceRange` and `nodeRange` point to the covered slice within the sequence and the individual AST node. `partIndex` and `partCount` make multi-part fragments stable without reconstructing them from the raw text. `hasLeadingLiteral` and `hasTrailingLiteral` explicitly mark extra literal content around the fragment inside the same node.

## Bundled reference profile

The bundled `GenericTemplateSyntaxProfile` is intentionally generic and syntax-oriented.

It currently detects:

- `{{ ... }}` as `placeholder`
- `#{ ... }` as `placeholder`
- `{% ... %}` as `control`

Control fragments are additionally classified by their normalized leading keyword:

- `wenn ...` -> `when`
- `sonst wenn ...` -> `else_if`
- `sonst` -> `else`
- `ende` -> `end`

Fragments with an opening delimiter but no closing delimiter are still returned as `malformed`. The library does not repair, balance, validate or interpret such fragments.

For the default delimiters, empty content (for example `{{ }}`) and non-interpretable control expressions (for example `{% Leerzeile löschen %}`) are marked as `malformed`.

The generic profile also normalizes raw fragments by collapsing repeated whitespace inside the detected tag, so consumers can compare `normalizedRaw` instead of slicing the source text themselves.

Functions are not modeled as a separate `kind` in the reference profile. They are simply part of the enclosing placeholder or control fragment.

## Custom profiles

Applications can support their own dialect by implementing `TemplateSyntaxProfile`.

Example:

```php
use Publicplan\DocumentProcessor\Service\Ast\Template\DetectedTemplateFragment;
use Publicplan\DocumentProcessor\Service\Ast\Template\TemplateSyntaxProfile;

final class MyAppTemplateProfile implements TemplateSyntaxProfile
{
    public function getName(): string
    {
        return 'my-app';
    }

    public function detect(string $inlineSequence): array
    {
        $fragments = [];

        if (preg_match_all('/\[\[([^\]]+)\]\]/', $inlineSequence, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as [$raw, $offset]) {
            $fragments[] = new DetectedTemplateFragment(
                kind: 'placeholder',
                status: 'complete',
                startOffset: $offset,
                endOffset: $offset + strlen($raw),
                raw: $raw,
            );
        }

        return $fragments;
    }
}
```

Recommended profile behavior:

- keep detection lossless
- return exact offsets into the provided sequence
- mark incomplete but recognizable syntax as `malformed`
- avoid semantic evaluation
- avoid silently correcting invalid syntax

## Consumer integration boundary

Template syntax profiles are part of the library's enablement surface, not its business layer.

In practice this means:

1. the library detects and annotates syntax-like fragments
2. the consuming application decides what `placeholder`, `control`, `when`, `else_if`, `else`, or `end` mean
3. placeholder replacement, condition execution, and dialect-specific business rules stay outside this repository

Typical app-side flow:

```php
$result = $processor->processToAst(
    '/path/to/template.docx',
    'template.docx',
    new ProcessingOptions(templateSyntaxProfile: $profile)
);

$annotations = $result->ast['sections'][0]['paragraphs'][0]['metadata']['sourceRef']['xmlAttributes']['templateAnnotations'] ?? [];

foreach ($annotations as $annotation) {
    if ($annotation['status'] !== 'complete') {
        continue;
    }

    // App-specific interpretation happens here, not in the library.
}
```

## Scope boundaries

Template syntax profiles are intentionally limited:

- no balancing validation
- no nesting validation
- no business interpretation
- no placeholder replacement
- no condition evaluation

They exist to help applications locate template syntax in the AST, not to execute it.
