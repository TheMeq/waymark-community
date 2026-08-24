<?php

namespace Tests\Support;

final class UpdateSigningFixture
{
    private const string PRIVATE_KEY_BASE64 = 'LS0tLS1CRUdJTiBQUklWQVRFIEtFWS0tLS0tCk1JR0hBZ0VBTUJNR0J5cUdTTTQ5QWdFR0NDcUdTTTQ5QXdFSEJHMHdhd0lCQVFRZ21LVGl1ZXFSQVk4Y1FycjEKcUFscUdER3Q1bVNQdUZTWGlZWjF3ZlJadXBpaFJBTkNBQVI0Tk9COUNlRllBWFJNTzlNNCt4UkZhUXl5YXkvYwppUVZWMDhEbmZXUVArS0ZtMmdITFJUd0kwejVlYW5WeFRCN3pab1hlVjRuWTRBRjRsQ0NzdmtyKwotLS0tLUVORCBQUklWQVRFIEtFWS0tLS0tCg==';

    private const string PUBLIC_KEY_BASE64 = 'LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUZrd0V3WUhLb1pJemowQ0FRWUlLb1pJemowREFRY0RRZ0FFZURUZ2ZRbmhXQUYwVER2VE9Qc1VSV2tNc21zdgozSWtGVmRQQTUzMWtEL2loWnRvQnkwVThDTk0rWG1wMWNVd2U4MmFGM2xlSjJPQUJlSlFnckw1Sy9nPT0KLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0tCg==';

    public static function privateKey(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private(base64_decode(self::PRIVATE_KEY_BASE64, true));
        if (! $key instanceof \OpenSSLAsymmetricKey) {
            throw new \RuntimeException('The non-production update signing fixture is invalid.');
        }

        return $key;
    }

    public static function publicKeyBase64(): string
    {
        return self::PUBLIC_KEY_BASE64;
    }
}
