<?php

namespace App\Filament\Components;

use Filament\Schemas\Components\Wizard;

final class AccessibleWizard extends Wizard
{
    public function toEmbeddedHtml(): string
    {
        $html = parent::toEmbeddedHtml();

        return preg_replace('/<ol(\s+)/', '<ol tabindex="0"$1', $html, 1) ?? $html;
    }
}
