<?php

namespace App\Domain\Content\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

final class CmsBlockRenderer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)->allowSafeElements()->allowRelativeLinks()->allowLinkSchemes(['https', 'http', 'mailto']);
        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function richText(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
