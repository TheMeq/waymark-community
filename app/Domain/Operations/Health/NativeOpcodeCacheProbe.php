<?php

namespace App\Domain\Operations\Health;

final class NativeOpcodeCacheProbe implements OpcodeCacheProbe
{
    public function enabled(): bool
    {
        return extension_loaded('Zend OPcache')
            && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL);
    }
}
