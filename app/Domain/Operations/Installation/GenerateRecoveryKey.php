<?php

namespace App\Domain\Operations\Installation;

final readonly class GenerateRecoveryKey
{
    private const string UPPERCASE = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const string LOWERCASE = 'abcdefghijkmnopqrstuvwxyz';

    private const string NUMBERS = '23456789';

    private const string SYMBOLS = '!@#$%^&*-_=+';

    public function handle(int $length = 32): string
    {
        $length = max(24, $length);
        $characters = [
            $this->randomCharacter(self::UPPERCASE),
            $this->randomCharacter(self::LOWERCASE),
            $this->randomCharacter(self::NUMBERS),
            $this->randomCharacter(self::SYMBOLS),
        ];
        $alphabet = self::UPPERCASE.self::LOWERCASE.self::NUMBERS.self::SYMBOLS;

        while (count($characters) < $length) {
            $characters[] = $this->randomCharacter($alphabet);
        }

        for ($index = count($characters) - 1; $index > 0; $index--) {
            $swap = random_int(0, $index);
            [$characters[$index], $characters[$swap]] = [$characters[$swap], $characters[$index]];
        }

        return implode('', $characters);
    }

    private function randomCharacter(string $characters): string
    {
        return $characters[random_int(0, strlen($characters) - 1)];
    }
}
