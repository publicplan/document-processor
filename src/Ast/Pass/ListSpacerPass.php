<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;

/**
 * Pass 3: Entfernt leere Absätze, die als Spacer zwischen Listenelementen dienen.
 * 
 * **Eingabe**: AST mit ListNodes aus Pass 1-2
 * **Ausgabe**: Leere Absätze zwischen ListNodes werden entfernt
 * 
 * **Invarianten nach Pass**:
 * - Keine leeren ParagraphNodes direkt zwischen zwei ListNodes
 * - Absatzabstände sind auf den ListItemNodes selbst dokumentiert
 * - Intentionale leere Absätze (nicht zwischen Listen) bleiben erhalten
 */
class ListSpacerPass implements AstPass
{
    public function getName(): string
    {
        return 'ListSpacer';
    }

    public function getDescription(): string
    {
        return 'Entfernt leere Absätze, die als Spacer zwischen Listenelementen verwendet wurden';
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
            
            // Prüfe ob dies ein leerer Absatz zwischen zwei Listen ist
            if ($this->isSpacerBetweenLists($paragraphs, $i)) {
                // Spacer überspringen - sein Spacing wurde bereits auf dem ListItem dokumentiert
                continue;
            }

            $newParagraphs[] = $current;
        }

        $section->setParagraphs($newParagraphs);
    }

    private function isSpacerBetweenLists(array $paragraphs, int $index): bool
    {
        // Ein Spacer ist ein leerer Absatz zwischen zwei ListNodes
        if (!$this->isEmptyParagraph($paragraphs[$index])) {
            return false;
        }

        $hasPrevList = $index > 0 && $paragraphs[$index - 1] instanceof ListNode;
        $hasNextList = $index < count($paragraphs) - 1 && $paragraphs[$index + 1] instanceof ListNode;

        return $hasPrevList && $hasNextList;
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
        // Hilfer-Funktion: prüft ob ein Node nur Whitespace enthält
        if (method_exists($node, 'getContent')) {
            $content = $node->getContent();
            return trim($content) === '';
        }
        return false;
    }
}
