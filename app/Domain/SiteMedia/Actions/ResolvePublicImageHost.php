<?php

namespace App\Domain\SiteMedia\Actions;

class ResolvePublicImageHost
{
    public function resolve(string $host): ?string
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
            ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $address;
            }
        }

        return null;
    }
}
