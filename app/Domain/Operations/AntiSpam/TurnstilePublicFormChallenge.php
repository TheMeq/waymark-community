<?php

namespace App\Domain\Operations\AntiSpam;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

final class TurnstilePublicFormChallenge implements PublicFormChallenge
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(Request $request): void
    {
        $configuration = (array) config('waymark.anti_spam.turnstile', []);
        if (! ($configuration['enabled'] ?? false)) {
            return;
        }

        $token = $request->input('cf-turnstile-response');
        $secret = $configuration['secret_key'] ?? null;
        if (! is_string($token) || trim($token) === '' || ! is_string($secret) || $secret === '') {
            $this->reject();
        }

        try {
            $response = Http::asForm()->timeout(5)->post(self::VERIFY_URL, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);
        } catch (\Throwable) {
            $this->reject();
        }

        if (! $response->successful() || $response->json('success') !== true) {
            $this->reject();
        }
    }

    private function reject(): never
    {
        throw ValidationException::withMessages(['turnstile' => 'Please complete the anti-spam check and try again.']);
    }
}
