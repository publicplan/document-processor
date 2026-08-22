<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\AstNode;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;

/**
 * Pass 1: Gruppiert aufeinanderfolgende ListItemNodes mit gleicher numId/Format in ListNodes.
 * 
 * **Eingabe**: AST mit einzelnen ListItemNodes vermischt mit anderen Elementen
 * **Ausgabe**: AST mit ListNodes, die ListItemNodes enthalten
 * 
 * **Invarianten nach Pass**:
 * - ListItemNodes sind immer in ListNodes verschachtelt
 * - Keine direkten ListItemNodes in Sections (nur in ListNodes)
 * - ListNodes gruppieren Items mit gleicher numId und numFormat
 */
class ListNormalizationPass implements AstPass
{
    public function getName(): string
    {
        return 'ListNormalization';
    }

    public function getDescription(): string
    {
        return 'Gruppiert aufeinanderfolgende ListItems mit gleicher numId in ListNode-Container';
    }

    public function apply(DocumentNode $document): DocumentNode
    {
        foreach ($document->getSections() as $section) {
            $this->normalizeSectionLists($section);
        }
        return $document;
    }

    private function normalizeSectionLists(SectionNode $section): void
    {
        $paragraphs = $section->getParagraphs();
        $newParagraphs = [];
        $i = 0;

        while ($i < count($paragraphs)) {
            $element = $paragraphs[$i];

            if ($element instanceof ListItemNode) {
                // Sammle alle aufeinanderfolgenden ListItems mit gleicher numId
                $listGroup = [$element];
                $baseNumId = $element->getNumId();
                $baseFormat = $element->getNumFormat();

                $j = $i + 1;
                while ($j < count($paragraphs)) {
                    $nextElement = $paragraphs[$j];
                    if ($nextElement instanceof ListItemNode 
                        && $nextElement->getNumId() === $baseNumId
                        && $nextElement->getNumFormat() === $baseFormat) {
                        $listGroup[] = $nextElement;
                        $j++;
                    } else {
                        break;
                    }
                }

                // Erstelle einen ListNode mit den gesammelten Items
                $listNode = new ListNode(
                    items: $listGroup
                );
                $newParagraphs[] = $listNode;

                $i = $j;
            } else {
                $newParagraphs[] = $element;
                $i++;
            }
        }

        // Setze die neuen Elemente zurück auf die Section
        $section->setParagraphs($newParagraphs);
    }
}
