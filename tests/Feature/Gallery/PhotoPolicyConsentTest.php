<?php

namespace Tests\Feature\Gallery;

use App\Domain\Gallery\Actions\AcceptCurrentPhotoUploadPolicy;
use App\Domain\Gallery\Models\PhotoPolicyAcceptance;
use App\Domain\Gallery\PhotoUploadPolicyDecision;
use App\Domain\Gallery\PhotoUploadPolicyGate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class PhotoPolicyConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_acceptance_persists_the_authenticated_account_current_policy_version_and_timestamp(): void
    {
        config()->set('gallery.photo_policy.current_version', '2026-08');
        Carbon::setTestNow('2026-08-21 10:30:00');

        $account = User::factory()->create();
        $acceptance = app(AcceptCurrentPhotoUploadPolicy::class)->handle($account);

        $this->assertTrue($acceptance->account->is($account));
        $this->assertSame('2026-08', $acceptance->policy_version);
        $this->assertSame('2026-08-21 10:30:00', $acceptance->accepted_at?->toDateTimeString());
        $this->assertDatabaseHas('photo_policy_acceptances', [
            'id' => $acceptance->id,
            'user_id' => $account->id,
            'policy_version' => '2026-08',
        ]);
        $this->assertSame(1, PhotoPolicyAcceptance::query()->count());
    }

    public function test_verified_account_with_current_policy_acceptance_receives_the_later_batch_reminder(): void
    {
        config()->set('gallery.photo_policy.current_version', '2026-08');

        $account = User::factory()->create();
        app(AcceptCurrentPhotoUploadPolicy::class)->handle($account);

        $decision = app(PhotoUploadPolicyGate::class)->for($account);

        $this->assertSame(PhotoUploadPolicyDecision::UploadAllowedWithReminder, $decision);
    }

    public function test_verified_account_without_an_acceptance_requires_explicit_policy_acceptance_for_its_first_upload(): void
    {
        $account = User::factory()->create();

        $decision = app(PhotoUploadPolicyGate::class)->for($account);

        $this->assertSame(PhotoUploadPolicyDecision::PolicyAcceptanceRequired, $decision);
    }

    public function test_policy_version_change_requires_fresh_acceptance_and_preserves_the_account_history(): void
    {
        config()->set('gallery.photo_policy.current_version', '2026-08');

        $account = User::factory()->create();
        app(AcceptCurrentPhotoUploadPolicy::class)->handle($account);

        config()->set('gallery.photo_policy.current_version', '2026-09');

        $this->assertSame(
            PhotoUploadPolicyDecision::PolicyVersionAcceptanceRequired,
            app(PhotoUploadPolicyGate::class)->for($account),
        );

        app(AcceptCurrentPhotoUploadPolicy::class)->handle($account);

        $this->assertSame(PhotoUploadPolicyDecision::UploadAllowedWithReminder, app(PhotoUploadPolicyGate::class)->for($account));
        $this->assertDatabaseHas('photo_policy_acceptances', [
            'user_id' => $account->id,
            'policy_version' => '2026-08',
        ]);
        $this->assertDatabaseHas('photo_policy_acceptances', [
            'user_id' => $account->id,
            'policy_version' => '2026-09',
        ]);
        $this->assertSame(2, $account->photoPolicyAcceptances()->count());
    }

    public function test_unverified_account_is_blocked_even_with_a_current_policy_acceptance(): void
    {
        config()->set('gallery.photo_policy.current_version', '2026-08');

        $account = User::factory()->unverified()->create();
        PhotoPolicyAcceptance::query()->create([
            'user_id' => $account->id,
            'policy_version' => '2026-08',
            'accepted_at' => now(),
        ]);

        $decision = app(PhotoUploadPolicyGate::class)->for($account);

        $this->assertSame(PhotoUploadPolicyDecision::EmailVerificationRequired, $decision);
    }

    public function test_inactive_account_is_blocked_before_email_or_policy_checks(): void
    {
        $account = User::factory()->create(['account_status' => 'suspended']);

        $decision = app(PhotoUploadPolicyGate::class)->for($account);

        $this->assertSame(PhotoUploadPolicyDecision::AccountInactive, $decision);
    }
}
