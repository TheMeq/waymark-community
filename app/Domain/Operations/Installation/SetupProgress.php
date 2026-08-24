<?php

namespace App\Domain\Operations\Installation;

use Illuminate\Contracts\Session\Session;

final readonly class SetupProgress
{
    private const string CURRENT_STEP_KEY = 'waymark.setup.current_step';

    private const string DATA_KEY = 'waymark.setup.data';

    public function __construct(private Session $session) {}

    public function current(): SetupStep
    {
        $number = max(1, min(count(SetupStep::cases()), (int) $this->session->get(self::CURRENT_STEP_KEY, 1)));

        return SetupStep::cases()[$number - 1];
    }

    public function canVisit(SetupStep $step): bool
    {
        return $step->number() <= $this->current()->number();
    }

    public function advanceFrom(SetupStep $step): SetupStep
    {
        $next = $step->next() ?? $step;

        if ($next->number() > $this->current()->number()) {
            $this->session->put(self::CURRENT_STEP_KEY, $next->number());
        }

        return $next;
    }

    /** @return array<string, mixed> */
    public function data(string $section): array
    {
        $value = $this->session->get(self::DATA_KEY.'.'.$section, []);

        return is_array($value) ? $value : [];
    }

    /** @param array<string, mixed> $values */
    public function save(string $section, array $values): void
    {
        $this->session->put(self::DATA_KEY.'.'.$section, $values);
    }

    /** @return array<string, array<string, mixed>> */
    public function allData(): array
    {
        $data = $this->session->get(self::DATA_KEY, []);

        return is_array($data) ? $data : [];
    }

    public function clear(): void
    {
        $this->session->forget([self::CURRENT_STEP_KEY, self::DATA_KEY]);
    }
}
