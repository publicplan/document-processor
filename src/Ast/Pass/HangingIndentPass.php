<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;

/**
 * Pass 7: Normalisiert Hanging-Indent-Absätze.
 * 
 * **Eingabe**: AST mit ParagraphNodes, die möglicherweise Hanging-Indent-Eigenschaften haben
 * **Ausgabe**: Hanging-Indents sind konsistent dokumentiert und markiert
 * 
 * **Invarianten nach Pass**:
 * - ParagraphNodes mit indentFirstLine < indentLeft werden als hanging identifiziert
 * - RenderHints dokumentieren die Hanging-Indent-Struktur
 * - Keine strukturelle Änderung des AST, nur Metadaten-Anreicherung
 */
class HangingIndentPass implements AstPass
{
    public function getName(): string
    {
        return 'HangingIndent';
    }

    public function getDescription(): string
    {
        return 'Markiert Absätze mit Hanging-Indent-Struktur in RenderHints';
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
        
        foreach ($paragraphs as $para) {
            if ($para instanceof ParagraphNode) {
                $this->markHangingIndent($para);
            }
        }
    }

    private function markHangingIndent(ParagraphNode $para): void
    {
        $indentLeft = $para->getIndentLeft();
        $indentFirstLine = $para->getIndentFirstLine();

        // Ein Hanging-Indent liegt vor wenn indentFirstLine < indentLeft
        // (erste Zeile ist weniger eingerückt als die folgenden)
        if ($indentLeft !== null && $indentFirstLine !== null && $indentFirstLine < $indentLeft) {
            // Markiere als Hanging-Indent in RenderHints
            $hints = $para->getRenderHints();
            $hints->set('hanging_indent', true);
            $hints->set('hanging_indent_distance', $indentLeft - $indentFirstLine);
        }
    }
}
