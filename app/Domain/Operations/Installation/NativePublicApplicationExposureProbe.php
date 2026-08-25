<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\PublicApplicationExposureProbe;
use Illuminate\Http\Client\Factory;
use Throwable;

final readonly class NativePublicApplicationExposureProbe implements PublicApplicationExposureProbe
{
    /** @var list<string> */
    private const PROTECTED_PATHS = [
        '/application/.env.example',
        '/application/vendor/autoload.php',
        '/application/storage/logs/laravel.log',
    ];

    public function __construct(private Factory $http) {}

    public function protected(string $baseUrl): ?bool
    {
        try {
            foreach (self::PROTECTED_PATHS as $path) {
                $response = $this->http
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get(rtrim($baseUrl, '/').$path.'?waymark-protection-check='.bin2hex(random_bytes(8)));

                if ($response->status() < 400) {
                    return false;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return true;
    }
}
