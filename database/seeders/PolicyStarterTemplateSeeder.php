<?php

namespace Database\Seeders;

use App\Domain\Communication\Models\PolicyPage;
use Illuminate\Database\Seeder;

final class PolicyStarterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'privacy' => ['Privacy Policy', 'Describe what personal data the group collects, why it is used, retention, rights, and contact details.'],
            'cookies' => ['Cookie Policy', 'List essential and optional cookies and explain how preferences can be changed.'],
            'photo-upload' => ['Photo Upload Policy', 'Explain public publication, moderation, attribution, reporting, removal, and acceptable uploads.'],
            'accessibility' => ['Accessibility Statement', 'State the WCAG 2.2 AA target, known limitations, and the accessibility contact route.'],
            'terms' => ['Terms and Site Use', 'Set out acceptable use, content ownership, availability, and group responsibilities.'],
        ] as $key => [$title, $body]) {
            $page = PolicyPage::query()->firstOrCreate(['policy_key' => $key], ['title' => $title, 'slug' => $key, 'review_notice' => 'Starter template for group and professional review; this is not legal advice.']);
            $page->versions()->firstOrCreate(['version_number' => 1], ['body' => $body, 'publication_state' => 'draft']);
        }
    }
}
