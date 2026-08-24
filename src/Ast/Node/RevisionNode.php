<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;

class RevisionNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private TrackChangeType $changeType = TrackChangeType::Deleted,
        private ?string $author = null,
        private ?\DateTime $date = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(AstNode $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    public function setChildren(array $children): self
    {
        $this->children = $children;
        return $this;
    }

    public function getChangeType(): TrackChangeType
    {
        return $this->changeType;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function toArray(): array
    {
        return [
            'type' => 'revision',
            'changeType' => $this->changeType->value,
            'author' => $this->author,
            'date' => $this->date?->format('Y-m-d H:i:s'),
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
