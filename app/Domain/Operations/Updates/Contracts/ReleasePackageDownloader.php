<?php

namespace App\Domain\Operations\Updates\Contracts;

interface ReleasePackageDownloader
{
    public function download(string $url, string $destination): void;
}
