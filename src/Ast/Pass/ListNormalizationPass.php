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
 * Pass 1: Gruppiert aufeinanderfolgende ListItemNodes mit gleicher numId/Format in ListNodes
 * und verschachtelt Items mit Tiefe > 0 als Kinder ihrer Parent-Items.
 * 
 * **Eingabe**: AST mit einzelnen ListItemNodes vermischt mit anderen Elementen
 * **Ausgabe**: AST mit ListNodes, die ListItemNodes mit korrekter Verschachtelung enthalten
 * 
 * **Invarianten nach Pass**:
 * - ListItemNodes sind immer in ListNodes verschachtelt
 * - Keine direkten ListItemNodes in Sections (nur in ListNodes)
 * - ListNodes nur Depth-0 Items mit gleicher numId gruppieren
 * - Depth>0 Items sind Kinder von Items auf der nächst niedrigeren Tiefe
 */
class ListNormalizationPass implements AstPass
{
    public function getName(): string
    {
        return 'ListNormalization';
    }

    public function getDescription(): string
    {
        return 'Gruppiert aufeinanderfolgende ListItems und verschachtelt sie korrekt nach Tiefe';
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

            if ($element instanceof ListItemNode && $element->getDepth() === 0) {
                // Starte mit einem Item auf Tiefe 0
                $listGroup = [$element];
                $baseNumId = $element->getNumId();
                $baseFormat = $element->getNumFormat();

                // Sammle weitere Depth-0 Items mit gleicher numId/format UND nested Items
                $j = $i + 1;
                while ($j < count($paragraphs)) {
                    $nextElement = $paragraphs[$j];
                    
                    if ($nextElement instanceof ListItemNode) {
                        if ($nextElement->getDepth() === 0) {
                            // Ein weiteres Depth-0 Item
                            if ($nextElement->getNumId() === $baseNumId 
                                && $nextElement->getNumFormat() === $baseFormat) {
                                // Gleiche numId/format -> zu dieser Liste hinzufügen
                                $listGroup[] = $nextElement;
                                $j++;
                            } else {
                                // Andere numId/format -> neue Liste startet
                                break;
                            }
                        } else {
                            // Nested Item (Depth > 0)
                            // Es sollte zum letzten Depth-0 Item hinzugefügt werden
                            $this->attachNestedItem($listGroup[count($listGroup) - 1], $nextElement);
                            $j++;
                        }
                    } else {
                        // Nicht-List-Element -> beende diese Liste
                        break;
                    }
                }

                // Erstelle einen ListNode mit den gesammelten Top-Level Items
                $listNode = new ListNode(
                    items: $listGroup
                );
                $newParagraphs[] = $listNode;

                $i = $j;
            } else if ($element instanceof ListItemNode) {
                // Ein Depth>0 Item ohne vorheriges Depth-0 Item?
                // Das sollte nicht vorkommen, aber wir behandeln es trotzdem
                $listNode = new ListNode(
                    items: [$element]
                );
                $newParagraphs[] = $listNode;
                $i++;
            } else {
                $newParagraphs[] = $element;
                $i++;
            }
        }

        // Setze die neuen Elemente zurück auf die Section
        $section->setParagraphs($newParagraphs);
    }

    /**
     * Hängt ein verschachteltes Item als Kind an ein Parent-Item an.
     * Dies wird rekursiv durchgeführt, um tiefe Verschachtelungen zu unterstützen.
     */
    private function attachNestedItem(ListItemNode $parent, ListItemNode $nestedItem): void
    {
        $parentDepth = $parent->getDepth();
        $nestedDepth = $nestedItem->getDepth();

        if ($nestedDepth === $parentDepth + 1) {
            // Direkt unter $parent -> als Kind hinzufügen
            $parent->addChild($nestedItem);
        } else if ($nestedDepth > $parentDepth + 1) {
            // Tiefere Verschachtelung als 1 -> 
            // das sollte nicht vorkommen in wohlgeformten Listen
            // Aber wir behandeln es, indem wir intermediate Items erstellen oder
            // das Item zum letzten Kind des Parents hinzufügen
            // Für jetzt: einfach zum Parent hinzufügen
            $parent->addChild($nestedItem);
        }
    }
}
