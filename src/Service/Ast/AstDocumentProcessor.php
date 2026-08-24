<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use DOMDocument;
use Exception;
use LibXMLError;
use Publicplan\DocumentProcessor\Ast\Pass\AstNormalizationException;
use Publicplan\DocumentProcessor\Ast\Pass\AstNormalizationPipeline;
use Publicplan\DocumentProcessor\Ast\Pass\NormalizationPipelineFactory;
use Publicplan\DocumentProcessor\Ast\Pass\TemplateAnnotationPass;
use Publicplan\DocumentProcessor\Enum\ControlCharacter;
use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use Publicplan\DocumentProcessor\Exception\DocumentProcessorException;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;
use Publicplan\DocumentProcessor\Model\ProcessedAstAndHtmlDocument;
use Publicplan\DocumentProcessor\Model\ProcessedAstDocument;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Model\ProcessingOptions;
use Publicplan\DocumentProcessor\Service\Converter\DeletedContentHelper;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use PhpOffice\PhpWord\PhpWord;

final class AstDocumentProcessor
{
    private readonly AstNormalizationPipeline $normalizationPipeline;

    public function __construct(
        private readonly DocumentLoader $documentLoader,
        private readonly WordToAstConverter $astConverter = new WordToAstConverter(),
        private readonly AstHtmlRenderer $astRenderer = new AstHtmlRenderer(),
        private readonly PublicAstSerializer $publicAstSerializer = new PublicAstSerializer(),
        ?AstNormalizationPipeline $normalizationPipeline = null
    ) {
        $this->normalizationPipeline = $normalizationPipeline ?? NormalizationPipelineFactory::createStandardPipeline();
    }

    public function process(
        string $filePath,
        string $sourceFilename = '',
        ?ProcessingOptions $processingOptions = null
    ): ProcessedDocument {
        return $this->processToHtml($filePath, $sourceFilename, $processingOptions);
    }

    public function processToHtml(
        string $filePath,
        string $sourceFilename = '',
        ?ProcessingOptions $processingOptions = null
    ): ProcessedDocument {
        $processedState = $this->executeProcessing($filePath, $sourceFilename, $processingOptions);
        $html = $this->astRenderer->render($processedState['document']);
        $html = $this->postProcessHtml($html, $processedState['processingOptions']->removeDeletedContent);

        $isHtmlFragmentValid = null;
        if ($processedState['processingOptions']->validateHtml) {
            $isHtmlFragmentValid = $this->validateHtmlFragment($html, $processedState['context']);
        }

        return new ProcessedDocument(
            html: $html,
            lastModified: $processedState['lastModified'],
            hasUnacceptedChanges: $processedState['hasUnacceptedChanges'],
            messages: $processedState['context']->getMessages(),
            sourceFilename: $processedState['sourceFilename'],
            isHtmlFragmentValid: $isHtmlFragmentValid
        );
    }

    public function processToAst(
        string $filePath,
        string $sourceFilename = '',
        ?ProcessingOptions $processingOptions = null
    ): ProcessedAstDocument {
        $processedState = $this->executeProcessing($filePath, $sourceFilename, $processingOptions);

        return new ProcessedAstDocument(
            astVersion: PublicAstSerializer::AST_VERSION,
            ast: $this->publicAstSerializer->serialize($processedState['document']),
            lastModified: $processedState['lastModified'],
            hasUnacceptedChanges: $processedState['hasUnacceptedChanges'],
            messages: $processedState['context']->getMessages(),
            sourceFilename: $processedState['sourceFilename']
        );
    }

    public function processToAstAndHtml(
        string $filePath,
        string $sourceFilename = '',
        ?ProcessingOptions $processingOptions = null
    ): ProcessedAstAndHtmlDocument {
        $processedState = $this->executeProcessing($filePath, $sourceFilename, $processingOptions);
        $html = $this->astRenderer->render($processedState['document']);
        $html = $this->postProcessHtml($html, $processedState['processingOptions']->removeDeletedContent);

        $isHtmlFragmentValid = null;
        if ($processedState['processingOptions']->validateHtml) {
            $isHtmlFragmentValid = $this->validateHtmlFragment($html, $processedState['context']);
        }

        return new ProcessedAstAndHtmlDocument(
            astVersion: PublicAstSerializer::AST_VERSION,
            ast: $this->publicAstSerializer->serialize($processedState['document']),
            html: $html,
            lastModified: $processedState['lastModified'],
            hasUnacceptedChanges: $processedState['hasUnacceptedChanges'],
            messages: $processedState['context']->getMessages(),
            sourceFilename: $processedState['sourceFilename'],
            isHtmlFragmentValid: $isHtmlFragmentValid
        );
    }

    /**
     * @return array{
     *     document: \Publicplan\DocumentProcessor\Ast\Node\DocumentNode,
     *     context: ConversionContext,
     *     hasUnacceptedChanges: bool,
     *     lastModified: DateTimeInterface,
     *     sourceFilename: string,
     *     processingOptions: ProcessingOptions
     * }
     */
    private function executeProcessing(
        string $filePath,
        string $sourceFilename = '',
        ?ProcessingOptions $processingOptions = null
    ): array {
        try {
            $processingOptions ??= new ProcessingOptions();
            $hasChanges = false;
            $defaultFontSize = null;
            $loadedDocument = $this->documentLoader->loadWithDocumentMetadata($filePath, $hasChanges, $defaultFontSize);

            $context = new ConversionContext();
            $context->setDefaultFontSize($defaultFontSize);
            $context->setRemoveDeletedContent($processingOptions->removeDeletedContent);

            $ast = $this->astConverter->convert($loadedDocument, $context);
            $normalization = $this->normalizationPipeline->normalize($ast);
            $document = $normalization['document'];

            if ($processingOptions->templateSyntaxProfile !== null) {
                $document = (new TemplateAnnotationPass($processingOptions->templateSyntaxProfile))->apply($document);
            }

            if ($hasChanges) {
                $context->addMessage(
                    ParserError::create(
                        ParserError::CONTAINS_UNACCEPTED_CHANGES,
                        ParserError::SEVERITY_ERROR,
                        'Das Dokument enthält nicht übernommene Änderungen (Änderungsverfolgung).'
                    ),
                    true
                );
            }

            return [
                'document' => $document,
                'context' => $context,
                'hasUnacceptedChanges' => $hasChanges,
                'lastModified' => $this->extractLastModified($loadedDocument),
                'sourceFilename' => $sourceFilename !== '' ? $sourceFilename : basename($filePath),
                'processingOptions' => $processingOptions,
            ];
        } catch (DocumentLoadException $e) {
            throw $e;
        } catch (AstNormalizationException $e) {
            throw new DocumentProcessorException(
                'Fehler bei der AST-Normalisierung: ' . $e->getMessage(),
                $filePath,
                0,
                $e
            );
        } catch (Exception $e) {
            throw new DocumentProcessorException(
                'Fehler bei der Dokumentverarbeitung (AST): ' . $e->getMessage(),
                $filePath,
                0,
                $e
            );
        }
    }

    private function postProcessHtml(string $html, bool $removeDeletedContent): string
    {
        if ($removeDeletedContent) {
            $html = preg_replace(
                sprintf(
                    '/(<p.*>)?(%s)+(%s|%s)?(<br\h?\/>)?(<\/p>)?\v?/',
                    preg_quote(DeletedContentHelper::DELETED_MARKER, '/'),
                    preg_quote(ControlCharacter::BREAK->value, '/'),
                    preg_quote(ControlCharacter::PARAGRAPH->value, '/')
                ),
                '',
                $html
            ) ?? $html;
        }

        return str_replace('</p>', '</p>' . PHP_EOL, $html);
    }

    private function validateHtmlFragment(string $html, ConversionContext $context): bool
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $wrappedHtml = sprintf('<div>%s</div>', $html);
            $document->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $errors = array_filter(
                libxml_get_errors(),
                static fn (LibXMLError $error): bool => $error->level >= LIBXML_ERR_WARNING
            );

            foreach ($errors as $error) {
                $context->addMessage(
                    ParserError::create(
                        ParserError::CONTAINS_INVALID_HTML,
                        ParserError::SEVERITY_WARNING,
                        $this->formatHtmlValidationMessage($error)
                    ),
                    true
                );
            }

            return $errors === [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function formatHtmlValidationMessage(LibXMLError $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error->message) ?? $error->message);

        if ($error->line > 0 && $error->column > 0) {
            return sprintf(
                'Das erzeugte HTML-Fragment ist nicht parser-tauglich: %s (Zeile %d, Spalte %d).',
                $message,
                $error->line,
                $error->column
            );
        }

        return sprintf('Das erzeugte HTML-Fragment ist nicht parser-tauglich: %s.', $message);
    }

    private function extractLastModified(PhpWord $document): DateTimeInterface
    {
        $modified = $document->getDocInfo()->getModified();
        $dateTime = DateTime::createFromFormat('U', (string)$modified);

        if ($dateTime === false) {
            throw new DocumentProcessorException('Konnte Änderungsdatum nicht parsen', '');
        }

        return $dateTime->setTimezone(new DateTimeZone('Europe/Berlin'));
    }
}
