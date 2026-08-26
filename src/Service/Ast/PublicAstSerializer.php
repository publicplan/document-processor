<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;

final class PublicAstSerializer
{
    public const AST_VERSION = '1.5.0';

    /**
     * @return array<string, mixed>
     */
    public function serialize(DocumentNode $document): array
    {
        return $this->sanitizeNodeArray($document->toArray());
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function sanitizeNodeArray(array $node): array
    {
        $sanitized = [];

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                if ($this->isNodeArray($value)) {
                    $sanitized[$key] = $this->sanitizeNodeArray($value);
                    continue;
                }

                $sanitized[$key] = array_map(
                    fn (mixed $entry): mixed => is_array($entry) && $this->isNodeArray($entry)
                        ? $this->sanitizeNodeArray($entry)
                        : $entry,
                    $value
                );
                continue;
            }

            $sanitized[$key] = $value;
        }

        if (isset($sanitized['metadata']) && is_array($sanitized['metadata'])) {
            $sanitized['metadata'] = [
                'sourceRef' => $sanitized['metadata']['sourceRef'] ?? null,
            ];
        }

        return $sanitized;
    }

    /**
     * @param array<mixed> $value
     */
    private function isNodeArray(array $value): bool
    {
        return isset($value['type']) && is_string($value['type']);
    }
}
