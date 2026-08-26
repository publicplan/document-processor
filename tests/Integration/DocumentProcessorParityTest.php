<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Integration;

use Closure;
use PhpOffice\PhpWord\Element\TextBox;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\Style\TextBox as TextBoxStyle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Enum\RenderMode;
use Publicplan\DocumentProcessor\Model\ProcessingOptions;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\HtmlParityComparator;
use Publicplan\DocumentProcessor\Service\Ast\AstDocumentProcessor;
use Publicplan\DocumentProcessor\Tests\Support\Parity\DocumentProcessorParityHarness;
use Publicplan\DocumentProcessor\Tests\Support\Parity\ParityArtifactWriter;

class DocumentProcessorParityTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/document-processor-parity-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->tempDir);
    }

    /**
     * @return iterable<string, array{0: string, 1: Closure(): PhpWord, 2: ProcessingOptions|null}>
     */
    public static function parityCorpusProvider(): iterable
    {
        yield 'simple-document' => [
            'simple-document',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $section->addText('Hallo Welt');

                return $phpWord;
            },
            null,
        ];

        yield 'lists' => [
            'lists',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $section->addListItem('Erster Punkt', 0);
                $section->addListItem('Zweiter Punkt', 0);
                $section->addText('Zwischentext');
                $section->addListItem('Dritter Punkt', 0);

                return $phpWord;
            },
            null,
        ];

        yield 'borders' => [
            'borders',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $border = new Paragraph();
                $border->setBorderTopSize(4);
                $border->setBorderTopColor('000000');
                $border->setBorderTopStyle('single');
                $border->setBorderLeftSize(4);
                $border->setBorderLeftColor('000000');
                $border->setBorderLeftStyle('single');
                $border->setBorderRightSize(4);
                $border->setBorderRightColor('000000');
                $border->setBorderRightStyle('single');
                $border->setBorderBottomSize(4);
                $border->setBorderBottomColor('000000');
                $border->setBorderBottomStyle('single');

                $section->addTextRun($border)->addText('Border 1');
                $section->addTextRun($border)->addText('Border 2');

                return $phpWord;
            },
            null,
        ];

        yield 'tables' => [
            'tables',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $table = $section->addTable();
                $table->addRow();
                $table->addCell(2000)->addText('A1');
                $table->addCell(2000)->addText('A2');
                $table->addRow();
                $table->addCell(2000)->addText('B1');
                $table->addCell(2000)->addText('B2');

                return $phpWord;
            },
            null,
        ];

        yield 'deleted-content-visible' => [
            'deleted-content-visible',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $section->addText('Sichtbar');
                $section->addText('Gelöscht', ['strikethrough' => true]);

                return $phpWord;
            },
            new ProcessingOptions(removeDeletedContent: false),
        ];

        yield 'text-breaks' => [
            'text-breaks',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $section->addText('Absatz 1');
                $section->addTextBreak();
                $section->addText('Absatz 2');

                return $phpWord;
            },
            null,
        ];

        yield 'textbox' => [
            'textbox',
            static function (): PhpWord {
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                $section->addText('Vor der Box');

                $style = new TextBoxStyle();
                $style->setBorderSize(4);
                $style->setBorderColor('FF0000');
                $style->setBgColor('FFFF00');

                $textBox = new TextBox($style);
                $textBox->addText('Boxinhalt');

                self::insertSectionElement($section, 1, $textBox);

                return $phpWord;
            },
            null,
        ];
    }

    /**
     * @dataProvider parityCorpusProvider
     */
    public function testCompareModeKeepsLegacyParityAcrossCorpus(
        string $caseId,
        Closure $documentFactory,
        ?ProcessingOptions $processingOptions
    ): void {
        $harness = new DocumentProcessorParityHarness(
            $this->createProcessorFromFactory($documentFactory),
            fn (string $filePath, string $sourceFilename, ?ProcessingOptions $options): ProcessedDocument => $this
                ->createAstProcessorFromFactory($documentFactory)
                ->process($filePath, $sourceFilename, $options),
            new HtmlParityComparator(),
            ParityArtifactWriter::fromEnvironment()
        );

        $result = $harness->render(
            caseId: $caseId,
            filePath: '/virtual/' . $caseId . '.docx',
            sourceFilename: $caseId . '.docx',
            renderMode: RenderMode::Compare,
            processingOptions: $processingOptions
        );

        $comparison = $result->comparison;
        $this->assertNotNull($comparison);
        $this->assertTrue(
            $comparison->stringsMatch,
            $this->buildFailureMessage($comparison->toArray(), $result->artifactDirectory)
        );
        $this->assertNotNull($result->legacyDocument);
        $this->assertNotNull($result->astDocument);
    }

    public function testCompareModeWritesRequiredArtifacts(): void
    {
        $documentFactory = static function (): PhpWord {
            $phpWord = new PhpWord();
            $section = $phpWord->addSection();
            $section->addText('Legacy');

            return $phpWord;
        };

        $artifactWriter = new ParityArtifactWriter($this->tempDir . '/artifacts');
        $harness = new DocumentProcessorParityHarness(
            $this->createProcessorFromFactory($documentFactory),
            static fn (string $filePath, string $sourceFilename, ?ProcessingOptions $options): ProcessedDocument => new ProcessedDocument(
                html: '<p>AST</p>' . PHP_EOL,
                lastModified: new \DateTimeImmutable(),
                hasUnacceptedChanges: false,
                messages: ['errors' => [], 'warnings' => [], 'notices' => [], 'infos' => []],
                sourceFilename: $sourceFilename
            ),
            new HtmlParityComparator(),
            $artifactWriter
        );

        $result = $harness->render(
            caseId: 'artifact-case',
            filePath: '/virtual/artifact-case.docx',
            sourceFilename: 'artifact-case.docx',
            renderMode: RenderMode::Compare
        );

        $this->assertNotNull($result->artifactDirectory);
        $this->assertFileExists($result->artifactDirectory . '/legacy.html');
        $this->assertFileExists($result->artifactDirectory . '/ast.html');
        $this->assertFileExists($result->artifactDirectory . '/parity-diff.json');
    }

    private function createProcessorFromFactory(Closure $documentFactory): DocumentProcessor
    {
        /** @var DocumentLoader&MockObject $loader */
        $loader = $this->createMock(DocumentLoader::class);
        $loader->method('loadWithDocumentMetadata')
            ->willReturnCallback(static function (...$args) use ($documentFactory): PhpWord {
                return $documentFactory();
            });

        return new DocumentProcessor($loader);
    }

    private function createAstProcessorFromFactory(Closure $documentFactory): AstDocumentProcessor
    {
        /** @var DocumentLoader&MockObject $loader */
        $loader = $this->createMock(DocumentLoader::class);
        $loader->method('loadWithDocumentMetadata')
            ->willReturnCallback(static function (...$args) use ($documentFactory): PhpWord {
                return $documentFactory();
            });

        return new AstDocumentProcessor($loader);
    }

    /**
     * @param array<string, mixed> $comparison
     */
    private function buildFailureMessage(array $comparison, ?string $artifactDirectory): string
    {
        $message = (string)json_encode($comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($artifactDirectory === null) {
            return $message;
        }

        return $message . PHP_EOL . 'Artifacts: ' . $artifactDirectory;
    }

    private static function insertSectionElement(object $section, int $position, object $element): void
    {
        $reflection = new \ReflectionClass($section);
        $property = $reflection->getProperty('elements');
        $property->setAccessible(true);

        $elements = $property->getValue($section);
        array_splice($elements, $position, 0, [$element]);
        $property->setValue($section, $elements);
    }

    private function recursiveDelete(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            if (is_dir($path)) {
                $this->recursiveDelete($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
