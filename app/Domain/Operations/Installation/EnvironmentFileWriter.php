<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\EnvironmentWriter;

final class EnvironmentFileWriter implements EnvironmentWriter
{
    private readonly string $targetPath;

    private readonly string $examplePath;

    public function __construct(?string $targetPath = null, ?string $examplePath = null)
    {
        $this->targetPath = $targetPath ?? base_path('.env');
        $this->examplePath = $examplePath ?? base_path('.env.example');
    }

    public function write(array $values): EnvironmentWriteResult
    {
        $contents = $this->build($values);
        $directory = dirname($this->targetPath);
        $instructions = 'Create a file named .env beside the Waymark application files, outside the public web root. Paste the supplied content, save it, then re-check this step.';

        if (! is_dir($directory) || ! is_writable($directory) || (is_file($this->targetPath) && ! is_writable($this->targetPath))) {
            return new EnvironmentWriteResult(false, $contents, $instructions);
        }

        $temporaryPath = $this->targetPath.'.tmp';

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            return new EnvironmentWriteResult(false, $contents, $instructions);
        }

        @chmod($temporaryPath, 0600);

        if (! rename($temporaryPath, $this->targetPath)) {
            @unlink($temporaryPath);

            return new EnvironmentWriteResult(false, $contents, $instructions);
        }

        return new EnvironmentWriteResult(true, $contents, 'Environment configuration saved.');
    }

    /** @param array<string, string> $values */
    private function build(array $values): string
    {
        $sourcePath = is_file($this->targetPath) ? $this->targetPath : $this->examplePath;
        $source = is_file($sourcePath) ? (string) file_get_contents($sourcePath) : '';
        $lines = preg_split('/\R/', $source) ?: [];
        $written = [];

        foreach ($lines as &$line) {
            if (preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches) !== 1 || ! array_key_exists($matches[1], $values)) {
                continue;
            }

            $key = $matches[1];
            $line = $key.'='.$this->encode($values[$key]);
            $written[$key] = true;
        }
        unset($line);

        foreach ($values as $key => $value) {
            if (! isset($written[$key])) {
                $lines[] = $key.'='.$this->encode($value);
            }
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    private function encode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z0-9_\.\/:+@=-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(
            ['\\', '"', '$', "\r", "\n"],
            ['\\\\', '\\"', '\\$', '', '\\n'],
            $value,
        ).'"';
    }
}
