<?php

namespace App\Domain\Communication\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['template_key', 'subject', 'intro_text', 'action_label', 'closing_text'])]
final class EmailTemplate extends Model
{
    protected static function booted(): void { self::saving(function (self $template): void { foreach ([$template->subject, $template->intro_text, $template->action_label, $template->closing_text] as $value) { if (is_string($value) && strip_tags($value) !== $value) { throw ValidationException::withMessages(['intro_text' => 'Email templates accept wording only, not HTML.']); } } }); }
}
