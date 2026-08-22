<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Metadata;

class RenderHints
{
    /**
     * @param array<string, mixed> $hints
     */
    public function __construct(
        private array $hints = [],
    ) {}

    public function getHint(string $key): mixed
    {
        return $this->hints[$key] ?? null;
    }

    public function hasHint(string $key): bool
    {
        return isset($this->hints[$key]);
    }

    public function set(string $key, mixed $value): self
    {
        $this->hints[$key] = $value;
        return $this;
    }

    public function toArray(): array
    {
        return $this->hints;
    }

    public static function empty(): self
    {
        return new self();
    }
}
