<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\ScaleNode;
use Publicplan\DocumentProcessor\Ast\Node\AstNode;

/**
 * Pass 6: Normalisiert Inline-Scale-Nodes.
 * 
 * **Eingabe**: AST mit möglicherweise redundanten oder verschachtelten ScaleNodes
 * **Ausgabe**: ScaleNodes sind flach und nicht-redundant
 * 
 * **Invarianten nach Pass**:
 * - Keine verschachtelten ScaleNodes (Scale kann nicht Scale enthalten)
 * - Mehrere ScaleNodes mit gleichem Skalierungsfaktor werden nicht konsolidiert (bleiben getrennt)
 * - ScaleNode-Struktur wird beibehalten für Render-Hinweise
 */
class InlineScalePass implements AstPass
{
    public function getName(): string
    {
        return 'InlineScale';
    }

    public function getDescription(): string
    {
        return 'Normalisiert Inline-Scale-Nodes und verhindert Verschachtelung';
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
                $this->normalizeParagraphScale($para);
            }
        }
    }

    private function normalizeParagraphScale(ParagraphNode $para): void
    {
        $children = $para->getChildren();
        $newChildren = [];

        foreach ($children as $child) {
            if ($child instanceof ScaleNode) {
                // Prüfe ob ScaleNode verschachtelte Scales enthält
                $normalizedScale = $this->flattenScaleNode($child);
                $newChildren[] = $normalizedScale;
            } else {
                $newChildren[] = $child;
            }
        }

        // Setze normalisierte Children zurück
        $para->setChildren($newChildren);
    }

    private function flattenScaleNode(ScaleNode $scale): ScaleNode
    {
        // Wenn ScaleNode andere ScaleNodes enthält, extrahiere diese
        $children = $scale->getChildren();
        $newChildren = [];

        foreach ($children as $child) {
            if ($child instanceof ScaleNode) {
                // Verschachtelte Scale -> extrahiere ihre Children
                $newChildren = array_merge($newChildren, $child->getChildren());
            } else {
                $newChildren[] = $child;
            }
        }

        if (count($newChildren) !== count($children)) {
            // Struktur geändert -> neue ScaleNode
            return new ScaleNode(
                children: $newChildren,
                scaleX: $scale->getScaleX(),
                scaleY: $scale->getScaleY()
            );
        }

        return $scale;
    }
}
