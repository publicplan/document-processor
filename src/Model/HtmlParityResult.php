<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

readonly class HtmlParityResult
{
    /**
     * @param array<string, mixed> $stringDiff
     * @param list<array<string, mixed>> $domDiff
     */
    public function __construct(
        public string $legacyHtml,
        public string $astHtml,
        public bool $stringsMatch,
        public bool $domMatches,
        public array $stringDiff,
        public array $domDiff
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'stringParity' => [
                'matches'      => $this->stringsMatch,
                'legacySha256' => hash('sha256', $this->legacyHtml),
                'astSha256'    => hash('sha256', $this->astHtml),
                'legacyLength' => strlen($this->legacyHtml),
                'astLength'    => strlen($this->astHtml),
                'diff'         => $this->stringDiff,
            ],
            'domParity' => [
                'matches'     => $this->domMatches,
                'differences' => $this->domDiff,
            ],
        ];
    }
}
