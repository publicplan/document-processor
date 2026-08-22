<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\BorderGroupNode;

/**
 * Pass 4: Gruppiert aufeinanderfolgende Absätze mit identischen Border-Stilen in BorderGroupNodes.
 * 
 * **Eingabe**: AST mit einzelnen ParagraphNodes mit Border-Styles
 * **Ausgabe**: Absätze mit gleichen Border-Eigenschaften sind in BorderGroupNodes verschachtelt
 * 
 * **Invarianten nach Pass**:
 * - BorderGroupNodes enthalten nur ParagraphNodes mit identischen Border-Stilen
 * - Border-Styles sind aus den ParagraphNodes entfernt (jetzt auf dem Container)
 * - Die ParagraphNodes im Container haben keine doppelten Border-Styles
 */
class BorderGroupingPass implements AstPass
{
    public function getName(): string
    {
        return 'BorderGrouping';
    }

    public function getDescription(): string
    {
        return 'Gruppiert Absätze mit identischen Border-Stilen in BorderGroupNode-Container';
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
        $i = 0;

        while ($i < count($paragraphs)) {
            $current = $paragraphs[$i];

            if ($current instanceof ParagraphNode && $this->hasBorders($current)) {
                // Sammle alle aufeinanderfolgenden Absätze mit gleichen Border-Stilen
                $group = [$current];
                $borderSignature = $this->getBorderSignature($current);

                $j = $i + 1;
                while ($j < count($paragraphs)) {
                    $next = $paragraphs[$j];
                    if ($next instanceof ParagraphNode 
                        && $this->getBorderSignature($next) === $borderSignature) {
                        $group[] = $next;
                        $j++;
                    } else {
                        break;
                    }
                }

                // Erstelle einen BorderGroupNode mit den gesammelten Absätzen
                $borderGroup = new BorderGroupNode(
                    children: $group
                );
                $newParagraphs[] = $borderGroup;

                $i = $j;
            } else {
                $newParagraphs[] = $current;
                $i++;
            }
        }

        $section->setParagraphs($newParagraphs);
    }

    private function hasBorders(ParagraphNode $para): bool
    {
        $style = $para->getResolvedStyle() ?? [];
        
        return !empty($style['borderTop']) 
            || !empty($style['borderBottom']) 
            || !empty($style['borderLeft']) 
            || !empty($style['borderRight']);
    }

    private function getBorderSignature(ParagraphNode $para): ?string
    {
        if (!$this->hasBorders($para)) {
            return null;
        }

        $style = $para->getResolvedStyle() ?? [];
        
        // Erstelle eine eindeutige Signatur basierend auf den Border-Eigenschaften
        $signature = [
            'top' => $style['borderTop'] ?? null,
            'bottom' => $style['borderBottom'] ?? null,
            'left' => $style['borderLeft'] ?? null,
            'right' => $style['borderRight'] ?? null,
        ];

        return md5(json_encode($signature));
    }
}
