<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

use DOMDocument;
use DOMElement;
use DOMNamedNodeMap;
use DOMNode;
use LibXMLError;
use Publicplan\DocumentProcessor\Model\HtmlParityResult;

final class HtmlParityComparator
{
    private const int DOM_DIFF_LIMIT = 25;
    private const int STRING_SNIPPET_RADIUS = 60;

    public function compare(string $legacyHtml, string $astHtml): HtmlParityResult
    {
        $stringDiff = $this->buildStringDiff($legacyHtml, $astHtml);
        $domDiff    = $this->buildDomDiff($legacyHtml, $astHtml);

        return new HtmlParityResult(
            legacyHtml: $legacyHtml,
            astHtml: $astHtml,
            stringsMatch: $stringDiff === [],
            domMatches: $domDiff === [],
            stringDiff: $stringDiff,
            domDiff: $domDiff
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStringDiff(string $legacyHtml, string $astHtml): array
    {
        if ($legacyHtml === $astHtml) {
            return [];
        }

        $legacyLength = strlen($legacyHtml);
        $astLength    = strlen($astHtml);
        $sharedPrefix = 0;

        while (
            $sharedPrefix < $legacyLength
            && $sharedPrefix < $astLength
            && $legacyHtml[$sharedPrefix] === $astHtml[$sharedPrefix]
        ) {
            $sharedPrefix++;
        }

        $sharedSuffix = 0;
        while (
            $sharedSuffix < ($legacyLength - $sharedPrefix)
            && $sharedSuffix < ($astLength - $sharedPrefix)
            && $legacyHtml[$legacyLength - $sharedSuffix - 1] === $astHtml[$astLength - $sharedSuffix - 1]
        ) {
            $sharedSuffix++;
        }

        return [
            'firstMismatch' => [
                'byteOffset' => $sharedPrefix,
                'line'       => substr_count(substr($legacyHtml, 0, $sharedPrefix), "\n") + 1,
                'legacyChar' => $legacyHtml[$sharedPrefix] ?? null,
                'astChar'    => $astHtml[$sharedPrefix] ?? null,
            ],
            'sharedPrefixLength' => $sharedPrefix,
            'sharedSuffixLength' => $sharedSuffix,
            'legacySnippet'      => $this->extractSnippet($legacyHtml, $sharedPrefix),
            'astSnippet'         => $this->extractSnippet($astHtml, $sharedPrefix),
        ];
    }

    private function extractSnippet(string $html, int $offset): string
    {
        $start   = max(0, $offset - self::STRING_SNIPPET_RADIUS);
        $length  = self::STRING_SNIPPET_RADIUS * 2;
        $snippet = substr($html, $start, $length);

        return str_replace(["\r", "\n"], ['\\r', '\\n'], $snippet);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildDomDiff(string $legacyHtml, string $astHtml): array
    {
        $legacyFragment = $this->loadFragment($legacyHtml);
        $astFragment    = $this->loadFragment($astHtml);
        $differences    = [];

        foreach ($legacyFragment['parseErrors'] as $parseError) {
            $differences[] = [
                'path'    => '/legacy',
                'reason'  => 'parse-error',
                'message' => $parseError,
            ];
        }

        foreach ($astFragment['parseErrors'] as $parseError) {
            $differences[] = [
                'path'    => '/ast',
                'reason'  => 'parse-error',
                'message' => $parseError,
            ];
        }

        if (!$legacyFragment['root'] instanceof DOMElement || !$astFragment['root'] instanceof DOMElement) {
            return $differences;
        }

        $this->compareNodeLists(
            $this->filterComparableNodes($legacyFragment['root']->childNodes),
            $this->filterComparableNodes($astFragment['root']->childNodes),
            '/fragment',
            $differences
        );

        return $differences;
    }

    /**
     * @return array{root: DOMElement|null, parseErrors: list<string>}
     */
    private function loadFragment(string $html): array
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                sprintf('<?xml encoding="UTF-8"><div>%s</div>', $html),
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            $root = $document->getElementsByTagName('div')->item(0);
            $errors = array_map(
                fn (LibXMLError $error): string => $this->formatLibXmlError($error),
                array_values(
                    array_filter(
                        libxml_get_errors(),
                        static fn (LibXMLError $error): bool => $error->level >= LIBXML_ERR_WARNING
                    )
                )
            );

            return [
                'root'        => $root instanceof DOMElement ? $root : null,
                'parseErrors' => $errors,
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function formatLibXmlError(LibXMLError $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error->message) ?? $error->message);

        if ($error->line > 0 && $error->column > 0) {
            return sprintf('%s (line %d, column %d)', $message, $error->line, $error->column);
        }

        return $message;
    }

    /**
     * @param list<DOMNode> $legacyNodes
     * @param list<DOMNode> $astNodes
     * @param list<array<string, mixed>> $differences
     */
    private function compareNodeLists(array $legacyNodes, array $astNodes, string $path, array &$differences): void
    {
        if (count($differences) >= self::DOM_DIFF_LIMIT) {
            return;
        }

        if (count($legacyNodes) !== count($astNodes)) {
            $differences[] = [
                'path'         => $path,
                'reason'       => 'child-count-mismatch',
                'legacyCount'  => count($legacyNodes),
                'astCount'     => count($astNodes),
            ];
        }

        $max = min(count($legacyNodes), count($astNodes));
        for ($i = 0; $i < $max; $i++) {
            if (count($differences) >= self::DOM_DIFF_LIMIT) {
                $differences[] = [
                    'path'   => $path,
                    'reason' => 'diff-truncated',
                    'limit'  => self::DOM_DIFF_LIMIT,
                ];
                return;
            }

            $legacyNode = $legacyNodes[$i];
            $astNode    = $astNodes[$i];
            $nodePath   = sprintf('%s/%s[%d]', $path, $this->describeNodeName($legacyNode), $i + 1);

            $this->compareNode($legacyNode, $astNode, $nodePath, $differences);
        }
    }

    /**
     * @param list<array<string, mixed>> $differences
     */
    private function compareNode(DOMNode $legacyNode, DOMNode $astNode, string $path, array &$differences): void
    {
        if ($legacyNode->nodeType !== $astNode->nodeType) {
            $differences[] = [
                'path'       => $path,
                'reason'     => 'node-type-mismatch',
                'legacyType' => $legacyNode->nodeType,
                'astType'    => $astNode->nodeType,
            ];
            return;
        }

        if ($legacyNode->nodeName !== $astNode->nodeName) {
            $differences[] = [
                'path'       => $path,
                'reason'     => 'node-name-mismatch',
                'legacyName' => $legacyNode->nodeName,
                'astName'    => $astNode->nodeName,
            ];
            return;
        }

        if ($legacyNode instanceof DOMElement && $astNode instanceof DOMElement) {
            $legacyAttributes = $this->attributesToArray($legacyNode->attributes);
            $astAttributes    = $this->attributesToArray($astNode->attributes);

            if ($legacyAttributes !== $astAttributes) {
                $differences[] = [
                    'path'             => $path,
                    'reason'           => 'attribute-mismatch',
                    'legacyAttributes' => $legacyAttributes,
                    'astAttributes'    => $astAttributes,
                ];
            }

            $this->compareNodeLists(
                $this->filterComparableNodes($legacyNode->childNodes),
                $this->filterComparableNodes($astNode->childNodes),
                $path,
                $differences
            );

            return;
        }

        $legacyValue = $legacyNode->nodeValue ?? '';
        $astValue    = $astNode->nodeValue ?? '';
        if ($legacyValue !== $astValue) {
            $differences[] = [
                'path'        => $path,
                'reason'      => 'text-mismatch',
                'legacyValue' => $legacyValue,
                'astValue'    => $astValue,
            ];
        }
    }

    private function describeNodeName(DOMNode $node): string
    {
        return $node->nodeType === XML_TEXT_NODE ? '#text' : $node->nodeName;
    }

    /**
     * @return array<string, string>
     */
    private function attributesToArray(?DOMNamedNodeMap $attributes): array
    {
        if ($attributes === null) {
            return [];
        }

        $result = [];
        foreach ($attributes as $attribute) {
            $result[$attribute->nodeName] = $attribute->nodeValue ?? '';
        }

        ksort($result);

        return $result;
    }

    /**
     * @return list<DOMNode>
     */
    private function filterComparableNodes(iterable $nodes): array
    {
        $result = [];

        foreach ($nodes as $node) {
            if (!$node instanceof DOMNode) {
                continue;
            }

            if ($node->nodeType === XML_COMMENT_NODE) {
                continue;
            }

            if ($node->nodeType === XML_TEXT_NODE && trim($node->nodeValue ?? '') === '') {
                continue;
            }

            $result[] = $node;
        }

        return $result;
    }
}
