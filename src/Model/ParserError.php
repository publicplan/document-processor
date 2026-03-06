<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

/**
 * DTO für Parser-Fehlermeldungen.
 */
class ParserError
{
    public const
        DOCUMENT_NOT_LOADED = 'document not loaded',
        DOCUMENT_HAS_CHANGES = 'document contains changes',
        LIST_INFO = 'parsing of list not reliable',
        CONTAINS_FORM_FIELDS = 'contains form fields',
        CONTAINS_UNHANDLED_ELEMENTS = 'contains unhandled elements',
        FUNCTION_INVALID = 'invalid function',
        PLACEHOLDER_NAME_MISSING = 'placeholder name is missing',
        PLACEHOLDER_FORMAT_INVALID = 'placeholder format is invalid',
        PLACEHOLDER_MAPPING_UNCERTAIN = 'uncertain placeholder mapping',
        PLACEHOLDER_MAPPING_MISSING = 'missing placeholder mapping',
        CONDITION_NOT_OPENED = 'missing opening condition',
        CONDITION_NOT_CLOSED = 'condition not properly closed',
        CONDITION_INVALID = 'condition not valid';

    public const
        SEVERITY_ERROR = 'error',
        SEVERITY_WARNING = 'warning',
        SEVERITY_NOTICE = 'notice',
        SEVERITY_INFO = 'info';

    protected string  $hash;
    protected string  $type;
    protected string  $severity;
    protected ?string $message   = null;
    protected ?string $htmlDomId = null;

    private function __construct(string $type, string $severity, ?string $message, ?string $htmlDomId)
    {
        $this->type      = $type;
        $this->severity  = $severity;
        $this->message   = $message;
        $this->htmlDomId = $htmlDomId;
        $this->hash      = self::calcHash($type, $severity, $message, $htmlDomId);
    }

    public static function create(string $type, string $severity, ?string $message = null, ?string $htmlDomId = null): self
    {
        return new self($type, $severity, $message, $htmlDomId);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): self
    {
        $this->severity = $severity;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getHtmlDomId(): ?string
    {
        return $this->htmlDomId;
    }

    public function setHtmlDomId(?string $htmlDomId): self
    {
        $this->htmlDomId = $htmlDomId;
        return $this;
    }

    public static function calcHash(string $type, string $severity, ?string $message, ?string $htmlDomId): string
    {
        return md5($type . $severity . $message . $htmlDomId);
    }
}
