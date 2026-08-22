<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Support\Parity;

use Publicplan\DocumentProcessor\Model\HtmlParityResult;
use RuntimeException;

final readonly class ParityArtifactWriter
{
    public function __construct(
        private string $baseDirectory
    )
    {
    }

    public static function fromEnvironment(): ?self
    {
        $enabled = getenv('DOCUMENT_PROCESSOR_WRITE_PARITY_ARTIFACTS');
        if (!in_array(strtolower((string)$enabled), ['1', 'true', 'yes', 'on'], true)) {
            return null;
        }

        $baseDirectory = getenv('DOCUMENT_PROCESSOR_PARITY_ARTIFACTS_DIR');
        if ($baseDirectory === false || $baseDirectory === '') {
            $baseDirectory = getcwd() . '/build/parity';
        }

        return new self($baseDirectory);
    }

    public function write(string $caseId, HtmlParityResult $result): string
    {
        $caseDirectory = rtrim($this->baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->sanitizePathSegment($caseId);
        $this->ensureDirectory($caseDirectory);

        $this->writeFile($caseDirectory . DIRECTORY_SEPARATOR . 'legacy.html', $result->legacyHtml);
        $this->writeFile($caseDirectory . DIRECTORY_SEPARATOR . 'ast.html', $result->astHtml);
        $this->writeFile(
            $caseDirectory . DIRECTORY_SEPARATOR . 'parity-diff.json',
            (string)json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        return $caseDirectory;
    }

    private function sanitizePathSegment(string $value): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? $value;

        return trim($sanitized, '-');
    }

    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create parity artifact directory "%s".', $directory));
        }
    }

    private function writeFile(string $path, string $contents): void
    {
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException(sprintf('Could not write parity artifact "%s".', $path));
        }
    }
}
