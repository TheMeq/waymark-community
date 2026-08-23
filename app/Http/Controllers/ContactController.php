<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Mail\ContactSubmissionMail;
use App\Domain\Communication\Models\ContactDepartment;
use App\Domain\Communication\Models\ContactSubmission;
use App\Domain\Communication\Queries\ContactDestination;
use App\Domain\Operations\Models\SiteProfile;
use App\Domain\Operations\Support\BrandTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ContactController
{
    public function create(): View
    {
        return view('contact.create', [...$this->site(), 'departments' => ContactDepartment::query()->where('active', true)->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, ContactDestination $destination): RedirectResponse
    {
        $values = $request->validate(['contact_department_id' => ['required', Rule::exists('contact_departments', 'id')->where('active', true)], 'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255'], 'message' => ['required', 'string', 'max:10000'], 'website' => ['nullable', 'prohibited']]);
        $submission = ContactSubmission::query()->create(['contact_department_id' => $values['contact_department_id'], 'name' => $values['name'], 'email' => $values['email'], 'message' => $values['message'], 'source_ip' => $request->ip(), 'retention_expires_at' => now()->addDays(max(1, min(30, (int) config('waymark.contact_submission_retention_days', 14))))]);
        Mail::to($destination->for($submission->department, $submission->message))->send(new ContactSubmissionMail($submission));

        return redirect()->route('contact.create')->with('status', 'Thanks — your message has been sent.');
    }

    private function site(): array
    {
        $profile = SiteProfile::query()->find(SiteProfile::SINGLETON_ID) ?? new SiteProfile;

        return ['site' => ['name' => $profile->group_name ?? 'Waymark Community', 'strapline' => 'A local walking community'], 'theme' => BrandTheme::fromSiteProfile($profile)];
    }
}
