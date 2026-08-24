<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\DatabaseConnectionTester;
use App\Domain\Operations\Installation\Contracts\MailConnectionTester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final readonly class SubmitSetupStep
{
    public function __construct(
        private ServerPreflight $serverPreflight,
        private NativeServerEnvironment $serverEnvironment,
        private DatabaseConnectionTester $databaseConnection,
        private MailConnectionTester $mailConnection,
        private SharedHostingSecurity $sharedHostingSecurity,
        private NativeSharedHostingEnvironment $sharedHostingEnvironment,
    ) {}

    public function preflight(Request $request): PreflightReport
    {
        $server = $this->serverPreflight->inspect($this->serverEnvironment->capture($request));
        $hosting = $this->sharedHostingSecurity->inspect($this->sharedHostingEnvironment->capture($request));

        return new PreflightReport([...$server->checks, ...$hosting->checks]);
    }

    public function handle(SetupStep $step, Request $request, SetupProgress $progress): SetupSubmissionResult
    {
        if ($step === SetupStep::ServerChecks) {
            return $this->preflight($request)->blocked()
                ? new SetupSubmissionResult(false, ['server' => 'Resolve the blocking server checks before continuing.'])
                : new SetupSubmissionResult(true);
        }

        if ($step === SetupStep::Database) {
            return $this->database($request, $progress);
        }

        if (! in_array($step, [SetupStep::GroupDetails, SetupStep::Branding, SetupStep::FirstAdministrator, SetupStep::Mail, SetupStep::Modules, SetupStep::Advanced], true)) {
            return new SetupSubmissionResult(true);
        }

        $validator = Validator::make($request->all(), $this->rules($step));
        $safeInput = $request->except(['password', 'password_confirmation', 's3_secret_key']);

        if ($validator->fails()) {
            return new SetupSubmissionResult(false, $validator->errors()->toArray(), $safeInput);
        }

        $validated = $validator->validated();

        if ($step === SetupStep::Mail) {
            $mail = MailConfiguration::fromArray($validated);
            $result = $this->mailConnection->test($mail);

            if (! $result->successful) {
                return new SetupSubmissionResult(false, ['mail' => $result->message], $safeInput);
            }

            $validated = $mail->toArray();
        }

        if ($step === SetupStep::FirstAdministrator) {
            unset($validated['password_confirmation']);
        }

        if ($step === SetupStep::Modules) {
            $validated = ['enabled' => array_values(array_unique($validated['modules']))];
        }

        $progress->save($step->value, $validated);

        return new SetupSubmissionResult(true);
    }

    private function database(Request $request, SetupProgress $progress): SetupSubmissionResult
    {
        $validator = Validator::make($request->all(), [
            'driver' => ['required', 'in:mysql,mariadb'],
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'database' => ['required', 'string', 'max:128'],
            'username' => ['required', 'string', 'max:128'],
            'password' => ['nullable', 'string', 'max:1024'],
        ]);
        $safeInput = $request->except(['password', 'password_confirmation']);

        if ($validator->fails()) {
            return new SetupSubmissionResult(false, $validator->errors()->toArray(), $safeInput);
        }

        $configuration = DatabaseConfiguration::fromArray($validator->validated());
        $result = $this->databaseConnection->test($configuration);

        if (! $result->successful) {
            return new SetupSubmissionResult(false, ['database' => $result->message], $safeInput);
        }

        $progress->save('database', $configuration->toArray());

        return new SetupSubmissionResult(true);
    }

    /** @return array<string, mixed> */
    private function rules(SetupStep $step): array
    {
        return match ($step) {
            SetupStep::GroupDetails => [
                'group_name' => ['required', 'string', 'max:160'],
                'short_name' => ['nullable', 'string', 'max:32'],
                'contact_email' => ['required', 'email:rfc', 'max:255'],
                'timezone' => ['required', Rule::in(timezone_identifiers_list())],
                'distance_unit' => ['required', Rule::in(['miles', 'kilometres'])],
                'ascent_unit' => ['required', Rule::in(['feet', 'metres'])],
            ],
            SetupStep::Branding => [
                'primary_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'accent_colour' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
                'typography_option' => ['required', Rule::in(['instrument', 'system'])],
            ],
            SetupStep::FirstAdministrator => [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email:rfc', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
                'password_confirmation' => ['required', 'string'],
            ],
            SetupStep::Mail => [
                'host' => ['required', 'string', 'max:255'],
                'port' => ['required', 'integer', 'between:1,65535'],
                'encryption' => ['nullable', Rule::in(['tls', 'ssl'])],
                'username' => ['nullable', 'string', 'max:255'],
                'password' => ['nullable', 'string', 'max:1024'],
                'from_address' => ['required', 'email:rfc', 'max:255'],
                'test_address' => ['required', 'email:rfc', 'max:255'],
            ],
            SetupStep::Modules => [
                'modules' => ['required', 'array', 'min:1'],
                'modules.*' => [Rule::in(['walks', 'socials', 'holidays', 'gallery', 'news', 'documents'])],
            ],
            SetupStep::Advanced => [
                'backup_disk' => ['required', Rule::in(['local', 's3'])],
                'release_metadata_url' => ['nullable', 'url:http,https', 'max:2048'],
                's3_endpoint' => ['nullable', 'url:http,https', 'max:2048'],
                's3_bucket' => ['nullable', 'string', 'max:255'],
                's3_access_key' => ['nullable', 'string', 'max:255'],
                's3_secret_key' => ['nullable', 'string', 'max:1024'],
            ],
            default => [],
        };
    }
}
