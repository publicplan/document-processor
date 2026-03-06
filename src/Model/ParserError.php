<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

/**
 * DTO für Parser-Fehlermeldungen.
 */
class ParserError
{
    public const string LIST_INFO                     = 'parsing of list not reliable';
    public const string CONTAINS_FORM_FIELDS          = 'contains form fields';
    public const string CONTAINS_UNHANDLED_ELEMENTS   = 'contains unhandled elements';

    public const string SEVERITY_ERROR   = 'error';
    public const string SEVERITY_INFO    = 'info';

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
