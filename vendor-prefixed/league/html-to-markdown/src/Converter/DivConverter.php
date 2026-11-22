<?php

declare(strict_types=1);

namespace Praline\Vendor\League\HTMLToMarkdown\Converter;

use Praline\Vendor\League\HTMLToMarkdown\Configuration;
use Praline\Vendor\League\HTMLToMarkdown\ConfigurationAwareInterface;
use Praline\Vendor\League\HTMLToMarkdown\ElementInterface;
use Praline\Vendor\League\HTMLToMarkdown\RawHtml;

class DivConverter implements ConverterInterface, ConfigurationAwareInterface
{
    /** @var Configuration */
    protected $config;

    public function setConfig(Configuration $config): void
    {
        $this->config = $config;
    }

    public function convert(ElementInterface $element): string
    {
        if ($this->config->getOption('strip_tags', false)) {
            return $element->getValue() . "\n\n";
        }

        return RawHtml::fromElement($element);
    }

    /**
     * @return string[]
     */
    public function getSupportedTags(): array
    {
        return ['div'];
    }
}
