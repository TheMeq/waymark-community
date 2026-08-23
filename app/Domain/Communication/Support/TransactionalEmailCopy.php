<?php

namespace App\Domain\Communication\Support;

use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Operations\Models\SiteProfile;

final class TransactionalEmailCopy
{
    private const DEFAULTS = [
        'administrator_access_changed' => [
            'subject' => 'Administrator access changed',
            'intro_text' => '{{ account_name }} was {{ change_description }} an Administrator.',
            'action_label' => null,
            'closing_text' => 'Review administrator access in Waymark Community.',
        ],
        'installation_ownership_transferred' => [
            'subject' => 'Installation ownership changed',
            'intro_text' => '{{ ownership_message }}',
            'action_label' => null,
            'closing_text' => 'The other account is {{ other_account_name }}.',
        ],
    ];

    /** @param array<string, string> $variables
     * @return array{subject: string, intro_text: string, action_label: ?string, closing_text: ?string, group_name: string}
     */
    public function for(string $key, array $variables = []): array
    {
        $defaults = self::DEFAULTS[$key] ?? throw new \InvalidArgumentException('Unknown transactional email template.');
        $template = EmailTemplate::query()->where('template_key', $key)->first();
        $values = $template === null ? $defaults : [
            'subject' => $template->subject,
            'intro_text' => $template->intro_text,
            'action_label' => $template->action_label,
            'closing_text' => $template->closing_text,
        ];
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID);
        $variables['group_name'] = $profile?->group_name ?? 'Waymark Community';
        foreach ($values as $field => $value) {
            if (is_string($value)) {
                $values[$field] = $this->substitute($value, $variables);
            }
        }

        return [...$values, 'group_name' => $variables['group_name']];
    }

    /** @param array<string, string> $variables */
    private function substitute(string $copy, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{ '.$key.' }}'] = $value;
            $replace['{{'.$key.'}}'] = $value;
        }

        return strtr($copy, $replace);
    }
}
