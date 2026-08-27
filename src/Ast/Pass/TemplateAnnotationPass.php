<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Metadata\SourceReference;
use Publicplan\DocumentProcessor\Ast\Node\AstNode;
use Publicplan\DocumentProcessor\Ast\Node\BorderGroupNode;
use Publicplan\DocumentProcessor\Ast\Node\BreakNode;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\FieldTextNode;
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
use Publicplan\DocumentProcessor\Ast\Node\TabNode;
use Publicplan\DocumentProcessor\Ast\Node\TextBoxNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;
use Publicplan\DocumentProcessor\Service\Ast\Template\DetectedTemplateFragment;
use Publicplan\DocumentProcessor\Service\Ast\Template\TemplateSyntaxProfile;

final class TemplateAnnotationPass implements AstPass
{
    private int $matchSequence = 0;

    public function __construct(
        private readonly TemplateSyntaxProfile $profile,
    ) {
    }

    public function getName(): string
    {
        return 'TemplateAnnotation';
    }

    public function getDescription(): string
    {
        return sprintf('Annotiert erkannte Template-Syntax mit Profil "%s"', $this->profile->getName());
    }

    public function apply(DocumentNode $document): DocumentNode
    {
        foreach ($document->getSections() as $sectionIndex => $section) {
            if (!$section instanceof SectionNode) {
                continue;
            }

            foreach ($section->getParagraphs() as $elementIndex => $block) {
                if ($block instanceof AstNode) {
                    $this->annotateBlockNode($block, $sectionIndex, $elementIndex, "sections[$sectionIndex].paragraphs[$elementIndex]");
                }
            }
        }

        return $document;
    }

    private function annotateBlockNode(AstNode $node, int $sectionIndex, int $elementIndex, string $path): void
    {
        // Gelöschte RevisionNodes sollten nicht annotiert werden
        if ($node instanceof RevisionNode && $node->getChangeType() === TrackChangeType::Deleted) {
            return;
        }

        if ($node instanceof ParagraphNode) {
            $this->annotateInlineSequence($node->getChildren(), $sectionIndex, $elementIndex, $path . '.children');
            return;
        }

        if ($node instanceof ListItemNode) {
            $this->annotateInlineSequence($node->getChildren(), $sectionIndex, $elementIndex, $path . '.children');
            return;
        }

        if ($node instanceof TextNode
            || $node instanceof LinkNode
            || $node instanceof BreakNode
            || $node instanceof TabNode
            || $node instanceof FieldTextNode
            || $node instanceof FormatNode
            || $node instanceof ScaleNode) {
            $this->annotateInlineSequence([$node], $sectionIndex, $elementIndex, $path . '.inline');
            return;
        }

        // Gelöschte RevisionNodes auf Inline-Ebene auch ignorieren
        if ($node instanceof RevisionNode) {
            if ($node->getChangeType() === TrackChangeType::Deleted) {
                return;
            }
            $this->annotateInlineSequence([$node], $sectionIndex, $elementIndex, $path . '.inline');
            return;
        }

        if ($node instanceof ListNode) {
            foreach ($node->getItems() as $itemIndex => $item) {
                if ($item instanceof AstNode) {
                    $this->annotateBlockNode($item, $sectionIndex, $elementIndex, $path . ".items[$itemIndex]");
                }
            }
            return;
        }

        if ($node instanceof BorderGroupNode || $node instanceof TextBoxNode || $node instanceof TableCellNode) {
            foreach ($node->getChildren() as $childIndex => $child) {
                if ($child instanceof AstNode) {
                    $this->annotateBlockNode($child, $sectionIndex, $elementIndex, $path . ".children[$childIndex]");
                }
            }
            return;
        }

        if ($node instanceof TableNode) {
            foreach ($node->getRows() as $rowIndex => $row) {
                if (!$row instanceof TableRowNode) {
                    continue;
                }

                foreach ($row->getCells() as $cellIndex => $cell) {
                    if ($cell instanceof TableCellNode) {
                        $this->annotateBlockNode($cell, $sectionIndex, $elementIndex, $path . ".rows[$rowIndex].cells[$cellIndex]");
                    }
                }
            }
        }
    }

    /**
     * @param AstNode[] $nodes
     */
    private function annotateInlineSequence(array $nodes, int $sectionIndex, int $elementIndex, string $path): void
    {
        $tokens = $this->collectInlineTokens($nodes, $path);
        if ($tokens === []) {
            return;
        }

        $inlineSequence = '';
        foreach ($tokens as $token) {
            $inlineSequence .= $token['text'];
        }

        $fragments = $this->profile->detect($inlineSequence);
        if ($fragments === []) {
            return;
        }

        // Filter Fragments die in gelöschten Tokens landen
        $fragments = array_filter($fragments, function ($fragment) use ($tokens) {
            foreach ($tokens as $token) {
                // Fragment wird mit diesem Token annotiert
                if ($token['end'] <= $fragment->startOffset || $token['start'] >= $fragment->endOffset) {
                    continue;
                }

                // Wenn dieses Token gelöscht ist, ignoriere das Fragment
                if ($token['deleted']) {
                    return false;
                }
            }
            return true;
        });

        if ($fragments === []) {
            return;
        }

        $tokenAnnotations = [];
        foreach ($fragments as $fragment) {
            $matchId = sprintf('template-%d', ++$this->matchSequence);

            foreach ($tokens as $index => $token) {
                if ($token['end'] <= $fragment->startOffset || $token['start'] >= $fragment->endOffset) {
                    continue;
                }

                // Fragment sollte nicht in gelöschten Tokens sein (doppelte Prüfung für Sicherheit)
                if ($token['deleted']) {
                    continue;
                }

                $tokenAnnotations[$index][] = $this->buildTokenAnnotation($matchId, $fragment, $token);
            }
        }

        foreach ($tokenAnnotations as $index => $annotations) {
            $this->annotateTokenNode(
                token: $tokens[$index],
                sectionIndex: $sectionIndex,
                elementIndex: $elementIndex,
                annotations: $annotations,
            );
        }
    }

    /**
     * @param AstNode[] $nodes
     * @return list<array{node: AstNode, text: string, start: int, end: int, path: string}>
     */
    private function collectInlineTokens(array $nodes, string $path, bool $deletedContext = false): array
    {
        $tokens = [];
        $offset = 0;

        foreach ($nodes as $index => $node) {
            if (!$node instanceof AstNode) {
                continue;
            }

            foreach ($this->collectInlineTokensFromNode($node, $path . "[$index]", $deletedContext) as $entry) {
                $length = strlen($entry['text']);
                if ($length === 0) {
                    continue;
                }

                $tokens[] = [
                    'node' => $entry['node'],
                    'text' => $entry['text'],
                    'start' => $offset,
                    'end' => $offset + $length,
                    'path' => $entry['path'],
                    'deleted' => $entry['deleted'],
                ];
                $offset += $length;
            }
        }

        return $tokens;
    }

    /**
     * @return list<array{node: AstNode, text: string, path: string}>
     */
    private function collectInlineTokensFromNode(AstNode $node, string $path, bool $deletedContext = false): array
    {
        if ($node instanceof TextNode) {
            return [[
                'node' => $node,
                'text' => $node->getContent(),
                'path' => $path,
                'deleted' => $deletedContext || $node->getTrackChange() === TrackChangeType::Deleted,
            ]];
        }

        if ($node instanceof TabNode) {
            return [['node' => $node, 'text' => "\t", 'path' => $path, 'deleted' => $deletedContext]];
        }

        if ($node instanceof BreakNode) {
            return [['node' => $node, 'text' => "\n", 'path' => $path, 'deleted' => $deletedContext]];
        }

        if ($node instanceof FieldTextNode) {
            return [[
                'node' => $node,
                'text' => $node->getFieldResult() ?? $node->getFieldCode(),
                'path' => $path,
                'deleted' => $deletedContext,
            ]];
        }

        if ($node instanceof LinkNode || $node instanceof FormatNode || $node instanceof RevisionNode || $node instanceof ScaleNode) {
            $nextDeletedContext = $deletedContext;
            if ($node instanceof RevisionNode) {
                $nextDeletedContext = $deletedContext || $node->getChangeType() === TrackChangeType::Deleted;
            }

            return $this->collectInlineTokens($node->getChildren(), $path . '.children', $nextDeletedContext);
        }

        return [];
    }

    /**
     * @param array{node: AstNode, text: string, start: int, end: int, path: string} $token
     * @return array<string, mixed>
     */
    private function buildTokenAnnotation(string $matchId, DetectedTemplateFragment $fragment, array $token): array
    {
        $nodeStart = max(0, $fragment->startOffset - $token['start']);
        $nodeEnd = min($token['end'], $fragment->endOffset) - $token['start'];

        return [
            'matchId' => $matchId,
            'profile' => $this->profile->getName(),
            'kind' => $fragment->kind,
            'role' => $fragment->role,
            'status' => $fragment->status,
            'raw' => $fragment->raw,
            'isContinuation' => $fragment->startOffset < $token['start'],
            'sequenceRange' => [
                'start' => $fragment->startOffset,
                'end' => $fragment->endOffset,
            ],
            'nodeRange' => [
                'start' => $nodeStart,
                'end' => $nodeEnd,
            ],
        ];
    }

    /**
     * @param array{node: AstNode, text: string, start: int, end: int, path: string} $token
     * @param list<array<string, mixed>> $annotations
     */
    private function annotateTokenNode(array $token, int $sectionIndex, int $elementIndex, array $annotations): void
    {
        $node = $token['node'];
        $sourceRef = $node->getSourceRef();
        $xmlAttributes = $sourceRef?->getXmlAttributes() ?? [];
        $existingAnnotations = $xmlAttributes['templateAnnotations'] ?? [];
        if (!is_array($existingAnnotations)) {
            $existingAnnotations = [];
        }

        $xmlAttributes['astPath'] = $xmlAttributes['astPath'] ?? $token['path'];
        $xmlAttributes['templateAnnotations'] = array_values(array_merge($existingAnnotations, $annotations));

        $node->setSourceRef(new SourceReference(
            part: $sourceRef?->getPart() ?? 'document',
            sectionIndex: $sourceRef?->getSectionIndex() ?? $sectionIndex,
            elementIndex: $sourceRef?->getElementIndex() ?? $elementIndex,
            xmlPath: $sourceRef?->getXmlPath(),
            xmlAttributes: $xmlAttributes,
        ));
    }
}
