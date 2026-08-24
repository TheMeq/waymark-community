<?php

namespace App\Domain\Operations\Installation;

final class SharedHostingSecurity
{
    public function inspect(SharedHostingEnvironment $environment): PreflightReport
    {
        $applicationExposed = $this->contains($environment->documentRoot, $environment->applicationRoot);
        $environmentExposed = $this->contains($environment->documentRoot, $environment->environmentPath);
        $publicRootMatches = $this->same($environment->documentRoot, $environment->publicPath);
        $debugUnsafe = $environment->production && $environment->debug;

        return new PreflightReport([
            new PreflightCheck(
                'document-root',
                'Document root',
                $applicationExposed ? 'blocker' : ($publicRootMatches ? 'pass' : 'warning'),
                $applicationExposed
                    ? 'The web server document root contains the Waymark application files.'
                    : ($publicRootMatches ? 'Only Waymark public files are inside the document root.' : 'The document root differs from Waymark’s public path.'),
                $applicationExposed
                    ? 'Move the application outside the document root and serve only its public directory.'
                    : ($publicRootMatches ? null : 'Confirm that this document root contains only the copied public entry point and assets.'),
            ),
            new PreflightCheck(
                'environment-exposure',
                'Environment file exposure',
                $environmentExposed ? 'blocker' : 'pass',
                $environmentExposed ? 'The .env file is under the served document root.' : 'The .env file is outside the served document root.',
                $environmentExposed ? 'Move .env and the application outside the public document root before continuing.' : null,
            ),
            new PreflightCheck(
                'production-debug',
                'Production debug mode',
                $debugUnsafe ? 'blocker' : ($environment->debug ? 'warning' : 'pass'),
                $debugUnsafe ? 'Detailed framework errors are enabled in production.' : ($environment->debug ? 'Debug mode is enabled outside production.' : 'Detailed framework errors are disabled.'),
                $debugUnsafe ? 'Set APP_DEBUG=false and reload setup before installing.' : null,
            ),
        ]);
    }

    private function contains(string $root, string $path): bool
    {
        $root = $this->normalise($root);
        $path = $this->normalise($path);

        return $path === $root || str_starts_with($path, $root.'/');
    }

    private function same(string $first, string $second): bool
    {
        return $this->normalise($first) === $this->normalise($second);
    }

    private function normalise(string $path): string
    {
        $normalised = rtrim(str_replace('\\', '/', trim($path)), '/');

        return preg_match('/^[A-Za-z]:\//', $normalised) === 1 ? strtolower($normalised) : $normalised;
    }
}
