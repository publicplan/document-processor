<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast\Template;

final class GenericTemplateSyntaxProfile implements TemplateSyntaxProfile
{
    /**
     * @var list<array{open: string, close: string, kind: string}>
     */
    private const DELIMITERS = [
        ['open' => '{{', 'close' => '}}', 'kind' => 'placeholder'],
        ['open' => '{%', 'close' => '%}', 'kind' => 'control'],
        ['open' => '#{', 'close' => '}', 'kind' => 'placeholder'],
    ];

    public function getName(): string
    {
        return 'generic-template';
    }

    public function detect(string $inlineSequence): array
    {
        $fragments = [];
        $cursor = 0;
        $length = strlen($inlineSequence);

        while ($cursor < $length) {
            $candidate = $this->findNextDelimiter($inlineSequence, $cursor);
            if ($candidate === null) {
                break;
            }

            $start = $candidate['position'];
            $open = $candidate['open'];
            $close = $candidate['close'];
            $closePosition = strpos($inlineSequence, $close, $start + strlen($open));

            if ($closePosition === false) {
                $end = $length;
                $raw = substr($inlineSequence, $start);
                $inner = substr($inlineSequence, $start + strlen($open));
                $status = 'malformed';
            } else {
                $end = $closePosition + strlen($close);
                $raw = substr($inlineSequence, $start, $end - $start);
                $inner = substr($inlineSequence, $start + strlen($open), $closePosition - ($start + strlen($open)));
                $status = 'complete';
            }

            $role = $candidate['kind'] === 'control'
                ? $this->detectControlRole($inner)
                : null;

            if ($status === 'complete' && trim($inner) === '') {
                $status = 'malformed';
            }

            if ($status === 'complete' && $candidate['kind'] === 'control' && $role === null) {
                $status = 'malformed';
            }

            $fragments[] = new DetectedTemplateFragment(
                kind: $candidate['kind'],
                status: $status,
                startOffset: $start,
                endOffset: $end,
                raw: $raw,
                role: $role,
            );

            $cursor = max($end, $start + strlen($open));
        }

        return $fragments;
    }

    /**
     * @return array{position: int, open: string, close: string, kind: string}|null
     */
    private function findNextDelimiter(string $inlineSequence, int $offset): ?array
    {
        $candidate = null;

        foreach (self::DELIMITERS as $delimiter) {
            $position = strpos($inlineSequence, $delimiter['open'], $offset);
            if ($position === false) {
                continue;
            }

            if ($candidate === null || $position < $candidate['position']) {
                $candidate = [
                    'position' => $position,
                    'open' => $delimiter['open'],
                    'close' => $delimiter['close'],
                    'kind' => $delimiter['kind'],
                ];
            }
        }

        return $candidate;
    }

    private function detectControlRole(string $expression): ?string
    {
        $normalized = strtolower(trim((string)preg_replace('/\s+/', ' ', $expression)));

        if ($normalized === '') {
            return null;
        }

        if (preg_match('/^sonst wenn\b/u', $normalized) === 1) {
            return 'else_if';
        }

        if (preg_match('/^wenn\b/u', $normalized) === 1) {
            return 'when';
        }

        if (preg_match('/^sonst\b/u', $normalized) === 1) {
            return 'else';
        }

        if (preg_match('/^ende\b/u', $normalized) === 1) {
            return 'end';
        }

        return null;
    }
}
