<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Integration;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Service\Ast\AstDocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use ZipArchive;

class AstListMarkerMetadataTest extends TestCase
{
    public function testAstContainsExactDashBulletMarkerFromLvlText(): void
    {
        $docxPath = $this->createDocxWithPatchedNumberingLevel([
            'numFmt' => 'bullet',
            'lvlText' => '-',
            'suff' => 'space',
            'start' => '1',
            'lvlJc' => 'left',
        ]);

        try {
            $marker = $this->extractFirstListMarkerFromAst($docxPath);

            $this->assertSame('bullet', $marker['rawNumFmt']);
            $this->assertSame('-', $marker['lvlText']);
            $this->assertSame('-', $marker['text']);
            $this->assertSame('space', $marker['lvlSuffix']);
            $this->assertSame('space', $marker['suffix']);
            $this->assertSame(1, $marker['start']);
            $this->assertSame('left', $marker['lvlJc']);
            $this->assertSame('left', $marker['justification']);
        } finally {
            @unlink($docxPath);
        }
    }

    public function testAstContainsExactBulletDotAndMarkerFont(): void
    {
        $docxPath = $this->createDocxWithPatchedNumberingLevel(
            [
                'numFmt' => 'bullet',
                'lvlText' => '•',
                'suff' => 'tab',
                'start' => '1',
                'lvlJc' => 'left',
            ],
            [
                'ascii' => 'Symbol',
                'hAnsi' => 'Symbol',
                'hint' => 'default',
            ]
        );

        try {
            $marker = $this->extractFirstListMarkerFromAst($docxPath);

            $this->assertSame('bullet', $marker['rawNumFmt']);
            $this->assertSame('•', $marker['lvlText']);
            $this->assertSame('•', $marker['text']);
            $this->assertSame('tab', $marker['lvlSuffix']);
            $this->assertIsArray($marker['font']);
            $this->assertSame('Symbol', $marker['font']['ascii'] ?? null);
        } finally {
            @unlink($docxPath);
        }
    }

    public function testAstContainsDecimalMarkerDefinitionWithDotSuffixInLvlText(): void
    {
        $docxPath = $this->createDocxWithPatchedNumberingLevel([
            'numFmt' => 'decimal',
            'lvlText' => '%1.',
            'suff' => 'tab',
            'start' => '3',
            'lvlJc' => 'left',
        ]);

        try {
            $marker = $this->extractFirstListMarkerFromAst($docxPath);
            $item = $this->extractFirstListItemFromAst($docxPath);

            $this->assertSame('number', $item['numFormat']);
            $this->assertSame('decimal', $marker['rawNumFmt']);
            $this->assertSame('%1.', $marker['lvlText']);
            $this->assertSame('%1.', $marker['text']);
            $this->assertSame('tab', $marker['suffix']);
            $this->assertSame(3, $marker['start']);
        } finally {
            @unlink($docxPath);
        }
    }

    public function testAstMapsLowerLetterAndUpperRomanNumberFormats(): void
    {
        $lowerLetterDocx = $this->createDocxWithPatchedNumberingLevel([
            'numFmt' => 'lowerLetter',
            'lvlText' => '%1)',
            'suff' => 'space',
            'start' => '1',
            'lvlJc' => 'left',
        ]);
        $upperRomanDocx = $this->createDocxWithPatchedNumberingLevel([
            'numFmt' => 'upperRoman',
            'lvlText' => '%1.',
            'suff' => 'tab',
            'start' => '1',
            'lvlJc' => 'right',
        ]);

        try {
            $lowerLetterItem = $this->extractFirstListItemFromAst($lowerLetterDocx);
            $lowerLetterMarker = $lowerLetterItem['resolvedLayout']['marker'];
            $this->assertSame('letter-lower', $lowerLetterItem['numFormat']);
            $this->assertSame('lowerLetter', $lowerLetterMarker['rawNumFmt']);

            $upperRomanItem = $this->extractFirstListItemFromAst($upperRomanDocx);
            $upperRomanMarker = $upperRomanItem['resolvedLayout']['marker'];
            $this->assertSame('roman', $upperRomanItem['numFormat']);
            $this->assertSame('upperRoman', $upperRomanMarker['rawNumFmt']);
            $this->assertSame('right', $upperRomanMarker['lvlJc']);
            $this->assertSame('right', $upperRomanMarker['justification']);
        } finally {
            @unlink($lowerLetterDocx);
            @unlink($upperRomanDocx);
        }
    }

    /**
     * @param array{numFmt:string,lvlText:string,suff:string,start:string,lvlJc:string} $levelValues
     * @param array<string, string>|null $markerFont
     */
    private function createDocxWithPatchedNumberingLevel(array $levelValues, ?array $markerFont = null): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addListItem('Marker probe', 0);

        $tempFile = tempnam(sys_get_temp_dir(), 'ast-list-marker-');
        if ($tempFile === false) {
            $this->fail('Temp file could not be created.');
        }

        $docxPath = $tempFile . '.docx';
        @unlink($tempFile);
        IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);

        $zip = new ZipArchive();
        if ($zip->open($docxPath) !== true) {
            @unlink($docxPath);
            $this->fail('DOCX zip archive could not be opened.');
        }

        try {
            $numberingXml = $zip->getFromName('word/numbering.xml');
            if (!is_string($numberingXml) || $numberingXml === '') {
                $this->fail('word/numbering.xml missing in generated DOCX.');
            }

            $document = new DOMDocument();
            $document->loadXML($numberingXml);
            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $levelNode = $xpath->query('/w:numbering/w:abstractNum[1]/w:lvl[@w:ilvl="0"]')?->item(0);
            if (!$levelNode instanceof DOMElement) {
                $levelNode = $xpath->query('/w:numbering/w:abstractNum[1]/w:lvl')?->item(0);
            }
            if (!$levelNode instanceof DOMElement) {
                $this->fail('No list level node found in numbering.xml.');
            }

            $this->setWordValueNode($document, $xpath, $levelNode, 'numFmt', $levelValues['numFmt']);
            $this->setWordValueNode($document, $xpath, $levelNode, 'lvlText', $levelValues['lvlText']);
            $this->setWordValueNode($document, $xpath, $levelNode, 'suff', $levelValues['suff']);
            $this->setWordValueNode($document, $xpath, $levelNode, 'start', $levelValues['start']);
            $this->setWordValueNode($document, $xpath, $levelNode, 'lvlJc', $levelValues['lvlJc']);

            if ($markerFont !== null) {
                $rPrNode = $xpath->query('w:rPr', $levelNode)?->item(0);
                if (!$rPrNode instanceof DOMElement) {
                    $rPrNode = $document->createElementNS(
                        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                        'w:rPr'
                    );
                    $levelNode->appendChild($rPrNode);
                }

                $rFontsNode = $xpath->query('w:rFonts', $rPrNode)?->item(0);
                if (!$rFontsNode instanceof DOMElement) {
                    $rFontsNode = $document->createElementNS(
                        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                        'w:rFonts'
                    );
                    $rPrNode->appendChild($rFontsNode);
                }

                foreach ($markerFont as $key => $value) {
                    $rFontsNode->setAttributeNS(
                        'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                        'w:' . $key,
                        $value
                    );
                }
            }

            $zip->addFromString('word/numbering.xml', $document->saveXML() ?: $numberingXml);
        } finally {
            $zip->close();
        }

        return $docxPath;
    }

    private function setWordValueNode(
        DOMDocument $document,
        DOMXPath $xpath,
        DOMElement $levelNode,
        string $nodeName,
        string $value
    ): void {
        $node = $xpath->query('w:' . $nodeName, $levelNode)?->item(0);
        if (!$node instanceof DOMElement) {
            $node = $document->createElementNS(
                'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
                'w:' . $nodeName
            );
            $levelNode->appendChild($node);
        }

        $node->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', $value);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFirstListMarkerFromAst(string $docxPath): array
    {
        $item = $this->extractFirstListItemFromAst($docxPath);
        $marker = $item['resolvedLayout']['marker'] ?? null;
        $this->assertIsArray($marker);
        return $marker;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractFirstListItemFromAst(string $docxPath): array
    {
        $processor = new AstDocumentProcessor(new DocumentLoader());
        $ast = $processor->processToAst($docxPath, basename($docxPath))->ast;
        $item = $ast['sections'][0]['paragraphs'][0]['items'][0] ?? null;
        $this->assertIsArray($item);
        return $item;
    }
}
