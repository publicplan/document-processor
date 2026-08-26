<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Paragraph;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Tests für den DocumentLoader Service.
 */
class DocumentLoaderTest extends TestCase
{
    private DocumentLoader $loader;
    private string $testFilesDir;

    protected function setUp(): void
    {
        $this->loader = new DocumentLoader();
        $this->testFilesDir = __DIR__ . '/../_fixtures';
    }

    protected function tearDown(): void
    {
        Style::resetStyles();
    }

    /**
     * Test: Nicht existierende Datei wirft Exception.
     */
    public function testLoadNonExistentFileThrowsException(): void
    {
        $this->expectException(DocumentLoadException::class);
        $this->expectExceptionMessage('Dokument nicht gefunden');

        $this->loader->load('/nonexistent/file.docx');
    }

    /**
     * Test: Nicht lesbare Datei wirft Exception.
     */
    public function testLoadUnreadableFileThrowsException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        touch($tempFile);
        chmod($tempFile, 0000);

        try {
            $this->expectException(DocumentLoadException::class);
            $this->expectExceptionMessage('Dokument nicht lesbar');

            $this->loader->load($tempFile);
        } finally {
            chmod($tempFile, 0644);
            unlink($tempFile);
        }
    }

    /**
     * Test: Ungültige DOCX-Datei wirft Exception.
     */
    public function testLoadInvalidDocxThrowsException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        file_put_contents($tempFile, 'This is not a valid DOCX file');

        try {
            $this->expectException(DocumentLoadException::class);
            $this->expectExceptionMessage('Fehler beim Laden des Dokuments');

            $this->loader->load($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Test: hasUnacceptedChanges wirft Exception für nicht existierende Datei.
     */
    public function testHasUnacceptedChangesNonExistentFileThrowsException(): void
    {
        $this->expectException(DocumentLoadException::class);
        $this->expectExceptionMessage('Konnte die Datei nicht öffnen');

        $this->loader->hasUnacceptedChanges('/nonexistent/file.docx');
    }

    /**
     * Test: hasUnacceptedChanges mit ungültiger Datei.
     */
    public function testHasUnacceptedChangesInvalidFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        file_put_contents($tempFile, 'Not a valid zip/docx');

        try {
            $this->expectException(DocumentLoadException::class);
            
            $this->loader->hasUnacceptedChanges($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentDefaultFontSizeFromStylesXml(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontSize(14);
        $phpWord->addSection()->addText('Test');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        try {
            $size = $this->loader->extractDocumentDefaultFontSize($tempFile);
            $this->assertSame(14.0, $size);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentBaseFontSizeMetadataUsesDocDefaults(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontSize(12);
        $phpWord->addSection()->addText('Test');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        try {
            $metadata = $this->loader->extractDocumentBaseFontSizeMetadata($tempFile);

            $this->assertSame(12.0, $metadata['sizePt']);
            $this->assertSame('docDefaults', $metadata['source']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentBaseFontSizeMetadataFallsBackToNormalStyle(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('Test');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        $this->mutateDocxXmlPart($tempFile, 'word/styles.xml', function (DOMDocument $document, DOMXPath $xpath): void {
            foreach ($xpath->query('/w:styles/w:docDefaults') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }

            $normalStyle = $xpath->query('//w:style[@w:type="paragraph"][@w:styleId="Normal"]')?->item(0);
            if (!$normalStyle instanceof \DOMElement) {
                $normalStyle = $xpath->query('//w:style[@w:type="paragraph"][w:name[@w:val="Normal"]]')?->item(0);
            }
            if (!$normalStyle instanceof \DOMElement) {
                $normalStyle = $xpath->query('//w:style[@w:type="paragraph"][@w:default="1"]')?->item(0);
            }
            if (!$normalStyle instanceof \DOMElement) {
                $this->fail('Normal/default paragraph style not found in styles.xml');
            }

            $this->setStyleHalfPointFontSize($document, $xpath, $normalStyle, 22);
        });

        try {
            $metadata = $this->loader->extractDocumentBaseFontSizeMetadata($tempFile);

            $this->assertSame(11.0, $metadata['sizePt']);
            $this->assertContains($metadata['source'], ['normalStyle', 'styleChain']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentBaseFontSizeMetadataUsesBodyRunsWithoutStyles(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Body 1', ['size' => 12]);
        $section->addText('Body 2', ['size' => 12]);
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(3000)->addText('Cell 1', ['size' => 9]);
        $table->addCell(3000)->addText('Cell 2', ['size' => 9]);
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        $this->mutateDocxXmlPart($tempFile, 'word/styles.xml', function (DOMDocument $document, DOMXPath $xpath): void {
            foreach ($xpath->query('/w:styles/w:docDefaults') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
            foreach ($xpath->query('//w:sz | //w:szCs') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        });

        try {
            $metadata = $this->loader->extractDocumentBaseFontSizeMetadata($tempFile);

            $this->assertSame(12.0, $metadata['sizePt']);
            $this->assertSame('bodyRuns', $metadata['source']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentBaseFontSizeMetadataUsesFallbackWhenNoSizesExist(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('Body text');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        $this->mutateDocxXmlPart($tempFile, 'word/styles.xml', function (DOMDocument $document, DOMXPath $xpath): void {
            foreach ($xpath->query('/w:styles/w:docDefaults') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
            foreach ($xpath->query('//w:sz | //w:szCs') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
        });

        $this->mutateDocxXmlPart($tempFile, 'word/document.xml', function (DOMDocument $document, DOMXPath $xpath): void {
            foreach ($xpath->query('//w:rPr/w:sz | //w:rPr/w:szCs') ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }
            foreach ($xpath->query('//w:body//w:r') ?: [] as $runNode) {
                $runNode->parentNode?->removeChild($runNode);
            }
        });

        try {
            $metadata = $this->loader->extractDocumentBaseFontSizeMetadata($tempFile);

            $this->assertSame(12.0, $metadata['sizePt']);
            $this->assertSame('fallback', $metadata['source']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentBaseFontSizeMetadataConvertsHalfPointsToPt(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('Test');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        $this->mutateDocxXmlPart($tempFile, 'word/styles.xml', function (DOMDocument $document, DOMXPath $xpath): void {
            $docDefaults = $xpath->query('/w:styles/w:docDefaults')?->item(0);
            if (!$docDefaults instanceof \DOMElement) {
                $this->fail('docDefaults not found in styles.xml');
            }

            foreach ($xpath->query('.//w:rPrDefault/w:rPr/w:sz | .//w:rPrDefault/w:rPr/w:szCs', $docDefaults) ?: [] as $node) {
                $node->parentNode?->removeChild($node);
            }

            $rPr = $xpath->query('.//w:rPrDefault/w:rPr', $docDefaults)?->item(0);
            if (!$rPr instanceof \DOMElement) {
                $this->fail('docDefaults/rPrDefault/rPr not found in styles.xml');
            }

            $sizeNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:sz');
            $sizeNode->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', '22');
            $rPr->appendChild($sizeNode);
        });

        try {
            $metadata = $this->loader->extractDocumentBaseFontSizeMetadata($tempFile);

            $this->assertSame(11.0, $metadata['sizePt']);
            $this->assertSame('docDefaults', $metadata['source']);
        } finally {
            unlink($tempFile);
        }
    }

    public function testLoadRegistersParagraphStyleIndentationFromStylesXml(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('Test');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        $zip = new ZipArchive();
        try {
            $this->assertTrue($zip->open($tempFile) === true);
            $stylesXml = $zip->getFromName('word/styles.xml');
            $this->assertIsString($stylesXml);

            $customStyle = '<w:style w:type="paragraph" w:styleId="Listenabsatz">'
                . '<w:name w:val="List Paragraph"/>'
                . '<w:basedOn w:val="Standard"/>'
                . '<w:pPr><w:ind w:left="720" w:hanging="180" w:firstLine="0"/></w:pPr>'
                . '</w:style>';
            $patchedStylesXml = str_replace('</w:styles>', $customStyle . '</w:styles>', $stylesXml);
            $this->assertTrue($zip->addFromString('word/styles.xml', $patchedStylesXml));
        } finally {
            $zip->close();
        }

        Style::resetStyles();
        $this->loader->load($tempFile);
        $style = Style::getStyle('Listenabsatz');

        try {
            $this->assertInstanceOf(Paragraph::class, $style);
            $this->assertSame(720.0, $style->getIndentLeft());
            $this->assertSame(180.0, $style->getHanging());
            $this->assertSame('Standard', $style->getBasedOn());
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractAstStyleSnapshotContainsStylesAndNumbering(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->addParagraphStyle('BaseP', ['spaceAfter' => 480]);
        $phpWord->addParagraphStyle('ChildP', ['basedOn' => 'BaseP']);
        $section = $phpWord->addSection();
        $section->addText('Hello', [], 'ChildP');
        $list = $section->addListItemRun(0);
        $list->addText('Item');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        try {
            $snapshot = $this->loader->extractAstStyleSnapshot($tempFile);

            $this->assertIsArray($snapshot);
            $this->assertArrayHasKey('styles', $snapshot);
            $this->assertArrayHasKey('numbering', $snapshot);
            $this->assertArrayHasKey('paragraph', $snapshot['styles']);
            $this->assertArrayHasKey('ChildP', $snapshot['styles']['paragraph']);
            $this->assertSame('BaseP', $snapshot['styles']['paragraph']['ChildP']['basedOn']);
            $this->assertArrayHasKey('numMap', $snapshot['numbering']);
            $this->assertArrayHasKey('levels', $snapshot['numbering']);

            $firstAbstractNum = reset($snapshot['numbering']['levels']);
            $this->assertIsArray($firstAbstractNum);
            $firstLevel = $firstAbstractNum['0'] ?? reset($firstAbstractNum);
            $this->assertIsArray($firstLevel);
            $this->assertArrayHasKey('rawNumFmt', $firstLevel);
            $this->assertArrayHasKey('lvlText', $firstLevel);
            $this->assertArrayHasKey('lvlSuffix', $firstLevel);
            $this->assertArrayHasKey('lvlJc', $firstLevel);
            $this->assertArrayHasKey('font', $firstLevel);
        } finally {
            unlink($tempFile);
        }
    }

    private function mutateDocxXmlPart(string $docxPath, string $partPath, callable $mutator): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($docxPath) === true);

        try {
            $xml = $zip->getFromName($partPath);
            $this->assertIsString($xml);

            $document = new DOMDocument();
            $document->preserveWhiteSpace = false;
            $document->formatOutput = false;
            $this->assertTrue($document->loadXML($xml));

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $mutator($document, $xpath);

            $updated = $document->saveXML();
            $this->assertIsString($updated);
            $this->assertTrue($zip->addFromString($partPath, $updated));
        } finally {
            $zip->close();
        }
    }

    private function setStyleHalfPointFontSize(
        DOMDocument $document,
        DOMXPath $xpath,
        \DOMElement $styleNode,
        int $halfPoints
    ): void {
        $runProperties = $xpath->query('w:rPr', $styleNode)?->item(0);
        if (!$runProperties instanceof \DOMElement) {
            $runProperties = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:rPr');
            $styleNode->appendChild($runProperties);
        }

        foreach ($xpath->query('w:sz | w:szCs', $runProperties) ?: [] as $node) {
            $runProperties->removeChild($node);
        }

        $sizeNode = $document->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:sz');
        $sizeNode->setAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:val', (string)$halfPoints);
        $runProperties->appendChild($sizeNode);
    }
}
