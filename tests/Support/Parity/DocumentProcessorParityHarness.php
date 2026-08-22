<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Support\Parity;

use Publicplan\DocumentProcessor\Enum\RenderMode;
use Publicplan\DocumentProcessor\Model\ProcessingOptions;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\HtmlParityComparator;

final class DocumentProcessorParityHarness
{
    /**
     * @param \Closure(string, string, ?ProcessingOptions): ProcessedDocument $astRenderer
     */
    public function __construct(
        private readonly DocumentProcessor $legacyProcessor,
        private readonly \Closure $astRenderer,
        private readonly HtmlParityComparator $comparator,
        private readonly ?ParityArtifactWriter $artifactWriter = null
    )
    {
    }

    public function render(
        string $caseId,
        string $filePath,
        string $sourceFilename,
        RenderMode $renderMode = RenderMode::Compare,
        ?ProcessingOptions $processingOptions = null
    ): ParityHarnessResult
    {
        return match ($renderMode) {
            RenderMode::Legacy => new ParityHarnessResult(
                renderMode: $renderMode,
                legacyDocument: $this->legacyProcessor->process($filePath, $sourceFilename, $processingOptions)
            ),
            RenderMode::Ast => new ParityHarnessResult(
                renderMode: $renderMode,
                astDocument: ($this->astRenderer)($filePath, $sourceFilename, $processingOptions)
            ),
            RenderMode::Compare => $this->renderCompare($caseId, $filePath, $sourceFilename, $processingOptions),
        };
    }

    private function renderCompare(
        string $caseId,
        string $filePath,
        string $sourceFilename,
        ?ProcessingOptions $processingOptions
    ): ParityHarnessResult
    {
        $legacyDocument = $this->legacyProcessor->process($filePath, $sourceFilename, $processingOptions);
        $astDocument    = ($this->astRenderer)($filePath, $sourceFilename, $processingOptions);
        $comparison     = $this->comparator->compare($legacyDocument->html, $astDocument->html);
        $artifactDir    = $this->artifactWriter?->write($caseId, $comparison);

        return new ParityHarnessResult(
            renderMode: RenderMode::Compare,
            legacyDocument: $legacyDocument,
            astDocument: $astDocument,
            comparison: $comparison,
            artifactDirectory: $artifactDir
        );
    }
}
