<?php

namespace App\Domain\Operations\Backups;

final class BackupEncryptor
{
    private const string MAGIC = "WAYMARKENC1\n";

    private const int SALT_BYTES = 16;

    private const int NONCE_PREFIX_BYTES = 8;

    private const int TAG_BYTES = 16;

    private const int KEY_ITERATIONS = 200000;

    public function encrypt(string $source, string $destination, string $passphrase): void
    {
        $input = fopen($source, 'rb');
        $output = fopen($destination, 'wb');
        if ($input === false || $output === false) {
            throw new \RuntimeException('Backup encryption files could not be opened.');
        }

        try {
            $salt = random_bytes(self::SALT_BYTES);
            $noncePrefix = random_bytes(self::NONCE_PREFIX_BYTES);
            $key = $this->key($passphrase, $salt);
            fwrite($output, self::MAGIC.$salt.$noncePrefix);
            $counter = 0;

            while (! feof($input)) {
                $plain = fread($input, 64 * 1024);
                if ($plain === false || $plain === '') {
                    break;
                }
                $tag = '';
                $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $noncePrefix.pack('N', $counter), $tag);
                if ($cipher === false || strlen($tag) !== self::TAG_BYTES) {
                    throw new \RuntimeException('Backup encryption failed.');
                }
                fwrite($output, pack('N', strlen($cipher)).$tag.$cipher);
                $counter++;
            }

            $finalTag = '';
            if (openssl_encrypt('', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $noncePrefix.pack('N', $counter), $finalTag) === false) {
                throw new \RuntimeException('Backup encryption failed.');
            }
            fwrite($output, pack('N', 0).$finalTag);
        } finally {
            fclose($input);
            fclose($output);
        }
    }

    public function decrypt(string $source, string $destination, string $passphrase): bool
    {
        $input = @fopen($source, 'rb');
        $output = @fopen($destination, 'wb');
        if ($input === false || $output === false) {
            return false;
        }

        $successful = false;
        try {
            if (fread($input, strlen(self::MAGIC)) !== self::MAGIC) {
                return false;
            }
            $salt = fread($input, self::SALT_BYTES);
            $noncePrefix = fread($input, self::NONCE_PREFIX_BYTES);
            if (strlen((string) $salt) !== self::SALT_BYTES || strlen((string) $noncePrefix) !== self::NONCE_PREFIX_BYTES) {
                return false;
            }
            $key = $this->key($passphrase, $salt);
            $counter = 0;
            while (! feof($input)) {
                $lengthBytes = fread($input, 4);
                if (strlen((string) $lengthBytes) !== 4) {
                    return false;
                }
                $length = unpack('N', $lengthBytes)[1];
                $tag = $this->readExactly($input, self::TAG_BYTES);
                if (strlen($tag) !== self::TAG_BYTES) {
                    return false;
                }
                $nonce = $noncePrefix.pack('N', $counter);
                if ($length === 0) {
                    $successful = openssl_decrypt('', 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag) === ''
                        && fread($input, 1) === '';
                    break;
                }
                $cipher = $this->readExactly($input, $length);
                if (strlen($cipher) !== $length) {
                    return false;
                }
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
                if ($plain === false || fwrite($output, $plain) === false) {
                    return false;
                }
                $counter++;
            }
        } catch (\Throwable) {
            $successful = false;
        } finally {
            fclose($input);
            fclose($output);
            if (! $successful) {
                @unlink($destination);
            }
        }

        return $successful;
    }

    private function key(string $passphrase, string $salt): string
    {
        return hash_pbkdf2('sha256', $passphrase, $salt, self::KEY_ITERATIONS, 32, true);
    }

    /** @param resource $stream */
    private function readExactly($stream, int $length): string
    {
        $value = '';
        while (strlen($value) < $length && ! feof($stream)) {
            $chunk = fread($stream, $length - strlen($value));
            if ($chunk === false) {
                break;
            }
            $value .= $chunk;
        }

        return $value;
    }
}
