<?php

declare(strict_types=1);

namespace Praline\Vendor\League\HTMLToMarkdown\Converter;

use Praline\Vendor\League\HTMLToMarkdown\ElementInterface;

interface ConverterInterface
{
    public function convert(ElementInterface $element): string;

    /**
     * @return string[]
     */
    public function getSupportedTags(): array;
}
