<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\AstNode;
use Publicplan\DocumentProcessor\Ast\Node\BorderGroupNode;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\FormatNode;
use Publicplan\DocumentProcessor\Ast\Node\LinkNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\RevisionNode;
use Publicplan\DocumentProcessor\Ast\Node\ScaleNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TableCellNode;
use Publicplan\DocumentProcessor\Ast\Node\TableNode;
use Publicplan\DocumentProcessor\Ast\Node\TableRowNode;
use Publicplan\DocumentProcessor\Ast\Node\TextBoxNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;

/**
 * Pass 8: Vereinfacht die AST-Struktur durch Zusammenfassung aufeinanderfolgender TextNodes.
 * 
 * **Eingabe**: AST mit möglicherweise fragmentierten TextNodes (z.B. durch Split bei Style-Änderungen)
 * **Ausgabe**: Aufeinanderfolgende TextNodes mit identischer Formatierung werden zu einem TextNode zusammengefasst
 * 
 * **Invarianten nach Pass**:
 * - Keine zwei aufeinanderfolgenden TextNodes mit identischer Formatierung
 * - Formatierung (bold, italic, underline, fontSize, preserveSpace, trackChange) muss identisch sein
 * - Die Zusammenfassung reduziert Struktur-Komplexität für app-Konsum
 * - MetadataFields bleiben vom ersten ursprünglichen Node erhalten
 */
class TextCoalescingPass implements AstPass
{
    public function getName(): string
    {
        return 'TextCoalescing';
    }

    public function getDescription(): string
    {
        return 'Fasst aufeinanderfolgende TextNodes mit gleicher Formatierung zusammen';
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
        foreach ($section->getParagraphs() as $node) {
            if ($node instanceof AstNode) {
                $this->processBlockNode($node);
            }
        }
    }

    private function processBlockNode(AstNode $node): void
    {
        if ($node instanceof ParagraphNode || $node instanceof ListItemNode) {
            $this->coalesceInlineChildren($node);
            return;
        }

        if ($node instanceof BorderGroupNode || $node instanceof TextBoxNode || $node instanceof TableCellNode) {
            foreach ($node->getChildren() as $child) {
                if ($child instanceof AstNode) {
                    $this->processBlockNode($child);
                }
            }
            return;
        }

        if ($node instanceof ListNode) {
            foreach ($node->getItems() as $item) {
                if ($item instanceof AstNode) {
                    $this->processBlockNode($item);
                }
            }
            return;
        }

        if ($node instanceof TableNode) {
            foreach ($node->getRows() as $row) {
                if (!$row instanceof TableRowNode) {
                    continue;
                }

                foreach ($row->getCells() as $cell) {
                    if ($cell instanceof TableCellNode) {
                        $this->processBlockNode($cell);
                    }
                }
            }
        }
    }

    private function coalesceInlineChildren(ParagraphNode|ListItemNode|LinkNode|FormatNode|RevisionNode|ScaleNode $node): void
    {
        $children = [];

        foreach ($node->getChildren() as $child) {
            if ($child instanceof LinkNode
                || $child instanceof FormatNode
                || $child instanceof RevisionNode
                || $child instanceof ScaleNode) {
                $this->coalesceInlineChildren($child);
            }

            $children[] = $child;
        }

        $node->setChildren($this->coalesceTextNodes($children));
    }

    /**
     * Fasst aufeinanderfolgende TextNodes mit identischer Formatierung zusammen.
     * 
     * @param AstNode[] $children
     * @return AstNode[]
     */
    private function coalesceTextNodes(array $children): array
    {
        $result = [];
        $currentText = null;
        $currentFormatting = null;

        foreach ($children as $child) {
            if (!($child instanceof TextNode)) {
                // Nicht-TextNode: flush aktuellen TextNode und add non-text child
                if ($currentText !== null) {
                    $result[] = $currentText;
                    $currentText = null;
                    $currentFormatting = null;
                }
                $result[] = $child;
                continue;
            }

            $formatting = $this->getFormattingKey($child);
            
            if ($currentFormatting === null) {
                // Erster TextNode oder nach nicht-TextNode
                $currentText = $child;
                $currentFormatting = $formatting;
            } elseif ($currentFormatting === $formatting) {
                // Gleiche Formatierung: Inhalte zusammenfassen
                $currentText = $this->mergeTextNodes($currentText, $child);
            } else {
                // Formatierung unterschiedlich: flush current, start new
                $result[] = $currentText;
                $currentText = $child;
                $currentFormatting = $formatting;
            }
        }

        // Flush remaining TextNode
        if ($currentText !== null) {
            $result[] = $currentText;
        }

        return $result;
    }

    /**
     * Erstellt einen eindeutigen Schlüssel für die Formatierung eines TextNodes.
     * Wird zum Vergleich aufeinanderfolgender TextNodes verwendet.
     */
    private function getFormattingKey(TextNode $node): string
    {
        return implode('|', [
            $node->isBold() ? '1' : '0',
            $node->isItalic() ? '1' : '0',
            $node->isUnderline() ? '1' : '0',
            (string)($node->getFontSize() ?? ''),
            $node->isPreserveSpace() ? '1' : '0',
            $node->getTrackChange()->value,
        ]);
    }

    /**
     * Fasst zwei TextNodes mit identischer Formatierung zusammen.
     * Der neue Node behält die Metadaten des ersten Nodes.
     */
    private function mergeTextNodes(TextNode $first, TextNode $second): TextNode
    {
        return new TextNode(
            content: $first->getContent() . $second->getContent(),
            bold: $first->isBold(),
            italic: $first->isItalic(),
            underline: $first->isUnderline(),
            fontSize: $first->getFontSize(),
            preserveSpace: $first->isPreserveSpace(),
            trackChange: $first->getTrackChange(),
            sourceRef: $first->getSourceRef(),
            phpWordType: $first->getPhpWordType(),
            resolvedStyle: $first->getResolvedStyle(),
            renderHints: $first->getRenderHints(),
            whitespaceFlags: $first->getWhitespaceFlags(),
            originFlags: $first->getOriginFlags(),
        );
    }
}
