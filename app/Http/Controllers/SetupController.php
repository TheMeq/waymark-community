<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Installation\InstallationState;
use App\Domain\Operations\Installation\SetupHealth;
use App\Domain\Operations\Installation\SetupProgress;
use App\Domain\Operations\Installation\SetupStep;
use App\Domain\Operations\Installation\SubmitSetupStep;
use App\Domain\Operations\Installation\WaymarkInstaller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class SetupController
{
    public function __construct(
        private readonly SubmitSetupStep $submitStep,
        private readonly WaymarkInstaller $installer,
        private readonly SetupHealth $setupHealth,
        private readonly InstallationState $installationState,
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

            $attempt = $this->installer->install($setupData, $request->root());

            if (! $attempt->successful) {
                if (! $attempt->environment->written) {
                    $request->session()->put('waymark.setup.environment_file', $attempt->environment->contents);
                    $request->session()->put('waymark.setup.environment_instructions', $attempt->environment->instructions);
                }

                return $this->redirectToStep($requestedStep)->withErrors([
                    $attempt->environment->written ? 'install' : 'environment' => $attempt->message,
                ]);
            }

            $request->session()->put('waymark.setup.installed', true);
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

    private function redirectToStep(SetupStep $step): RedirectResponse
    {
        return $step === SetupStep::Welcome
            ? to_route('setup')
            : to_route('setup.step', $step->value);
    }
}
