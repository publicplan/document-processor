<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;

/**
 * Pass 5: Normalisiert Behandlung von leeren Absätzen.
 * 
 * **Eingabe**: AST mit möglicherweise redundanten oder strukturell ungültigen leeren ParagraphNodes
 * **Ausgabe**: Leere Absätze sind konsistent normalisiert
 * 
 * **Invarianten nach Pass**:
 * - Trailing-Absätze am Ende des Dokuments mit nur Whitespace werden entfernt
 * - Mehrere aufeinanderfolgende leere Absätze können konsolidiert werden
 * - Leere Absätze innerhalb von Listen werden respektiert (bereits entfernt in ListSpacerPass)
 */
class EmptyParagraphPass implements AstPass
{
    public function getName(): string
    {
        return 'EmptyParagraph';
    }

    public function getDescription(): string
    {
        return 'Normalisiert Behandlung von leeren Absätzen';
    }

    public function apply(DocumentNode $document): DocumentNode
    {
        foreach ($document->getSections() as $section) {
            $this->processSection($section);
        }
        return $document;
    }

    private function processSection(SectionNode $section): void
    {
        $paragraphs = $section->getParagraphs();
        $newParagraphs = [];

        for ($i = 0; $i < count($paragraphs); $i++) {
            $current = $paragraphs[$i];
            
            // Entferne leere Absätze am Ende des Dokuments
            if ($this->isEmptyParagraph($current) && $this->isAtEndOfDocument($paragraphs, $i)) {
                continue;
            }

            $newParagraphs[] = $current;
        }

        $section->setParagraphs($newParagraphs);
    }

    private function isEmptyParagraph($element): bool
    {
        if (!$element instanceof ParagraphNode) {
            return false;
        }

        $children = $element->getChildren();
        return count($children) === 0 || 
               (count($children) === 1 && $this->isWhitespaceOnly($children[0]));
    }

    private function isWhitespaceOnly($node): bool
    {
        if (method_exists($node, 'getContent')) {
            $content = $node->getContent();
            return trim($content) === '';
        }
        return false;
    }

    private function isAtEndOfDocument(array $paragraphs, int $index): bool
    {
        // Prüfe ob es nach diesem Absatz nur noch leere Absätze gibt
        for ($i = $index + 1; $i < count($paragraphs); $i++) {
            if (!$this->isEmptyParagraph($paragraphs[$i])) {
                return false;
            }
        }
        return true;
    }
}
