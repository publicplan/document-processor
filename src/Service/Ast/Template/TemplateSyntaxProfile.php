<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast\Template;

interface TemplateSyntaxProfile
{
    public function getName(): string;

    /**
     * @return list<DetectedTemplateFragment>
     */
    public function detect(string $inlineSequence): array;
}
