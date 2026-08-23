<?php

namespace Database\Seeders;

use App\Domain\Communication\Models\PolicyPage;
use Illuminate\Database\Seeder;

final class PolicyStarterTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'privacy' => ['Privacy Policy', "Starter template — review for your group. This is not legal advice.\n\nDescribe what personal data the group collects, why it is used, retention periods, people's rights, and the privacy contact route."],
            'cookies' => ['Cookie Policy', 'Starter template — review for your group. This is not legal advice. Describe essential and optional cookies, configured analytics providers, retention, and how preferences can be changed.'],
            'photo-upload' => ['Photo Upload Policy', 'Starter template — review for your group. This is not legal advice. Explain public publication after approval, moderation, attribution, reporting, removal, acceptable uploads, and image privacy.'],
            'accessibility' => ['Accessibility Statement', 'Starter template — review for your group. This is not legal advice. State the WCAG 2.2 AA target, known limitations, how the site was tested, and the accessibility contact route.'],
            'terms' => ['Terms and Site Use', 'Starter template — review for your group. This is not legal advice. Set out acceptable use, content ownership, site availability, and group responsibilities.'],
        ] as $key => [$title, $body]) {
            $page = PolicyPage::query()->firstOrCreate(['policy_key' => $key], ['title' => $title, 'slug' => $key, 'review_notice' => 'Starter template for group and professional review; this is not legal advice.']);
            $page->versions()->firstOrCreate(['version_number' => 1], ['body' => $body, 'publication_state' => 'draft']);
        }
    }
}
