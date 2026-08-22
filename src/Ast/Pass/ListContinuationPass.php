<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;

/**
 * Pass 2: Behandelt Listenfortsetzung wenn eine Listennummer nach einer Unterbrechung wieder auftritt.
 * 
 * **Eingabe**: AST mit ListNodes aus Pass 1
 * **Ausgabe**: ListItemNodes haben startNumeration gesetzt für Fortsetzungen
 * 
 * **Invarianten nach Pass**:
 * - startNumeration ist gesetzt, wenn eine numId-Gruppe nach einer Unterbrechung wieder auftritt
 * - Die Numerierung ist durchlaufend dokumentiert
 */
class ListContinuationPass implements AstPass
{
    public function getName(): string
    {
        return 'ListContinuation';
    }

    public function getDescription(): string
    {
        return 'Verwaltet Numerierungsfortsetzung wenn eine Liste unterbrochen und fortgesetzt wird';
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
        
        // Trackt die letzte Itemanzahl für jede numId, um Fortsetzungen zu erkennen
        $numIdCounts = [];
        
        foreach ($paragraphs as $para) {
            if ($para instanceof ListNode) {
                foreach ($para->getItems() as $item) {
                    if ($item instanceof ListItemNode) {
                        $numId = $item->getNumId();
                        $currentCount = $numIdCounts[$numId] ?? 0;
                        
                        if ($item->getStartNumeration() === null && $currentCount > 0) {
                            // Bereits Items dieser numId gesehen -> Fortsetzung
                            // startNumeration wird implizit durch die aktuelle Position bestimmt
                            // Für explizite Dokumentation: setzen auf nächste erwartete Nummer
                            $item->setStartNumeration($currentCount + 1);
                        }
                        
                        // Zähle Items auf dieser Tiefe
                        $numIdCounts[$numId] = $currentCount + 1;
                    }
                }
            } else {
                // Nicht-List-Element: Reset der Zähler für diese numId
                // (die Liste wurde unterbrochen)
                // Wir behalten die Zähler, um Fortsetzungen zu erkennen
                // aber setzen einen Flag
            }
        }
    }
}
