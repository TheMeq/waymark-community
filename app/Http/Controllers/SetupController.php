<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Installation\DatabaseConfiguration;
use App\Domain\Operations\Installation\GenerateRecoveryKey;
use App\Domain\Operations\Installation\InstallationAttemptRecord;
use App\Domain\Operations\Installation\InstallationAttemptStatus;
use App\Domain\Operations\Installation\InstallationAttemptStore;
use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Installation\ResetIncompleteInstallation;
use App\Domain\Operations\Installation\SetupHealth;
use App\Domain\Operations\Installation\SetupProgress;
use App\Domain\Operations\Installation\SetupStep;
use App\Domain\Operations\Installation\SubmitSetupStep;
use App\Domain\Operations\Installation\WaymarkInstaller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SetupController
{
    public function __construct(
        private readonly SubmitSetupStep $submitStep,
        private readonly WaymarkInstaller $installer,
        private readonly SetupHealth $setupHealth,
        private readonly InstallationState $installationState,
        private readonly InstallationAttemptStore $attemptStore,
        private readonly ResetIncompleteInstallation $resetIncompleteInstallation,
        private readonly GenerateRecoveryKey $generateRecoveryKey,
    ) {}

    public function show(Request $request, ?string $step = null): View|RedirectResponse
    {
        $requestedStep = $step === null ? SetupStep::Welcome : SetupStep::from($step);
        $progress = new SetupProgress($request->session());

        if (! $progress->canVisit($requestedStep)) {
            return $this->redirectToStep($progress->current());
        }

        $data = $progress->data($requestedStep->value);
        unset($data['password'], $data['password_confirmation'], $data['s3_secret_key'], $data['recovery_token'], $data['recovery_token_confirmation']);

        return view('setup.step', [
            'step' => $requestedStep,
            'data' => $data,
            'preflight' => $requestedStep === SetupStep::ServerChecks
                ? $this->submitStep->preflight($request)
                : null,
            'healthChecks' => $requestedStep === SetupStep::HealthCheck ? $this->setupHealth->checks($request->root()) : [],
            'environmentFile' => $request->session()->get('waymark.setup.environment_file'),
            'environmentInstructions' => $request->session()->get('waymark.setup.environment_instructions'),
            'groupDetails' => $progress->data('group-details'),
            'databaseRecovery' => $requestedStep === SetupStep::Database
                && (bool) $request->session()->get('waymark.setup.database_recovery', false),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $next = (new SetupProgress($request->session()))->advanceFrom(SetupStep::Welcome);

        return $this->redirectToStep($next);
    }

    public function store(Request $request, string $step): RedirectResponse
    {
        $requestedStep = SetupStep::from($step);
        $progress = new SetupProgress($request->session());

        if (! $progress->canVisit($requestedStep)) {
            return $this->redirectToStep($progress->current());
        }

        $submission = $this->submitStep->handle($requestedStep, $request, $progress);

        if (! $submission->successful) {
            return $this->redirectToStep($requestedStep)
                ->withErrors($submission->errors)
                ->withInput($submission->oldInput);
        }

        if ($requestedStep === SetupStep::Install) {
            if ($this->submitStep->preflight($request)->blocked()) {
                return $this->redirectToStep($requestedStep)
                    ->withErrors(['hosting' => 'Resolve the blocking server and hosting security checks before installing.']);
            }

            $setupData = $progress->allData();
            $requiredSections = ['database', 'group-details', 'branding', 'first-administrator', 'mail', 'modules', 'advanced'];

            if (array_diff($requiredSections, array_keys($setupData)) !== []) {
                return $this->redirectToStep($requestedStep)
                    ->withErrors(['install' => 'Some setup details are missing. Return to the earlier steps and save each one before installing.']);
            }

            $this->installer->start($setupData);

            return to_route('setup.install.progress');
        }

        if ($requestedStep === SetupStep::HealthCheck) {
            if (! $request->session()->get('waymark.setup.installed') || ! $this->setupHealth->ready($request->root())) {
                return $this->redirectToStep($requestedStep)
                    ->withErrors(['health' => 'Resolve the failed health checks before finishing setup.']);
            }

            $this->installationState->complete();
            $progress->clear();

            return to_route('filament.admin.pages.dashboard');
        }

        return $this->redirectToStep($progress->advanceFrom($requestedStep));
    }

    public function progress(Request $request): View|RedirectResponse
    {
        $attempt = $this->attemptStore->load();

        if ($attempt === null) {
            return to_route('setup.step', SetupStep::Install->value);
        }

        return view('setup.progress', [
            'attempt' => $attempt,
            'status' => $this->attemptStatus($attempt),
        ]);
    }

    public function advance(Request $request): JsonResponse|RedirectResponse
    {
        $progress = new SetupProgress($request->session());
        $data = $progress->allData();
        $requiredSections = ['database', 'group-details', 'branding', 'first-administrator', 'mail', 'modules', 'advanced'];

        if (array_diff($requiredSections, array_keys($data)) !== []) {
            return response()->json([
                'message' => 'Some setup details are missing. Return to the earlier steps and save each one before installing.',
            ], 422);
        }

        $attempt = $this->installer->advance($data, $request->root());
        $status = $this->attemptStatus($attempt);
        $environment = $this->installer->environmentWriteResult();

        if ($environment !== null && ! $environment->written) {
            $request->session()->put('waymark.setup.environment_file', $environment->contents);
            $request->session()->put('waymark.setup.environment_instructions', $environment->instructions);
        }

        if ($attempt->status === InstallationAttemptStatus::Completed) {
            $request->session()->put('waymark.setup.installed', true);
        }

        if ($request->expectsJson()) {
            return response()->json($status, $attempt->status === InstallationAttemptStatus::Failed ? 422 : 200);
        }

        return to_route('setup.install.progress');
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'max:64'],
        ]);
        $progress = new SetupProgress($request->session());
        $database = $progress->data('database');

        if ($database === []) {
            return to_route('setup.step', SetupStep::Database->value)
                ->withErrors(['database' => 'Re-enter and verify the database settings before attempting recovery.']);
        }

        $result = $this->resetIncompleteInstallation->handle(
            DatabaseConfiguration::fromArray($database),
            $this->attemptStore->load(),
            (string) $validated['confirmation'],
        );

        if (! $result->successful) {
            $destination = $this->attemptStore->load() === null
                ? to_route('setup.step', SetupStep::Database->value)
                : to_route('setup.install.progress');

            return $destination->withErrors(['reset' => $result->message]);
        }

        $this->attemptStore->clear();
        $progress->resetAfterIncompleteInstallation();

        return to_route('setup.step', SetupStep::Database->value)->with('status', $result->message);
    }

    public function retry(): RedirectResponse
    {
        $attempt = $this->attemptStore->load();

        if ($attempt === null || ! $this->installer->retryable($attempt)) {
            return to_route('setup.install.progress')
                ->withErrors(['retry' => 'This installation step cannot be retried safely. Use the action shown for the failed stage.']);
        }

        $this->installer->retry();

        return to_route('setup.install.progress');
    }

    public function recoveryKey(Request $request): JsonResponse
    {
        $progress = new SetupProgress($request->session());

        abort_unless($progress->canVisit(SetupStep::Advanced), 403);

        return response()->json([
            'recovery_key' => $this->generateRecoveryKey->handle(),
        ]);
    }

    /** @return array<string, mixed> */
    private function attemptStatus(InstallationAttemptRecord $attempt): array
    {
        return [
            'attempt_id' => $attempt->id,
            'status' => $attempt->status->value,
            'stage' => $attempt->stage->value,
            'stage_label' => $attempt->status === InstallationAttemptStatus::Failed
                ? $attempt->stage->label().' failed'
                : $attempt->stage->label(),
            'message' => $attempt->message,
            'changed' => $attempt->changed,
            'diagnostic_id' => $attempt->diagnosticId,
            'failure_category' => $attempt->failureCategory,
            'failure_category_label' => $this->failureCategoryLabel($attempt->failureCategory),
            'migration' => [
                'current' => $attempt->migrationIndex,
                'total' => $attempt->totalMigrations,
            ],
            'completed' => $attempt->status === InstallationAttemptStatus::Completed,
            'failed' => $attempt->status === InstallationAttemptStatus::Failed,
            'retryable' => $this->installer->retryable($attempt),
            'resettable' => $attempt->status === InstallationAttemptStatus::Failed
                && ($attempt->changed || $attempt->failureCategory === 'incomplete_waymark_installation_detected'),
            'return_to_database' => $attempt->status === InstallationAttemptStatus::Failed
                && in_array($attempt->failureCategory, ['database_not_empty_or_ambiguous', 'database_configuration_changed'], true),
        ];
    }

    private function failureCategoryLabel(?string $category): ?string
    {
        return match ($category) {
            'configuration_file_could_not_be_written' => 'Configuration file could not be written',
            'database_connection_or_permissions_failed' => 'Database connection or permissions',
            'database_schema_installation_failed' => 'Database schema installation',
            'database_not_empty_or_ambiguous' => 'Database is not empty or is ambiguous',
            'database_configuration_changed' => 'Database configuration changed',
            'incomplete_waymark_installation_detected' => 'Incomplete Waymark installation detected',
            'group_creation_failed' => 'Group settings creation',
            'administrator_creation_failed' => 'Administrator creation',
            'optional_configuration_failed' => 'Optional configuration',
            'health_verification_failed' => 'Health verification',
            'filesystem_or_completion_failure' => 'Installation completion',
            null => null,
            default => 'Installation',
        };
    }

    private function redirectToStep(SetupStep $step): RedirectResponse
    {
        return $step === SetupStep::Welcome
            ? to_route('setup')
            : to_route('setup.step', $step->value);
    }
}
