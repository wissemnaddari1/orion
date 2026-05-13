<?php

namespace App\DTO;

final class AggregateCountDto
{
    private string $label;
    private int $total;

    public function __construct(
        int|string $label,
        int|string|float|null $total,
    ) {
        $this->label = (string) $label;
        $this->total = (int) ($total ?? 0);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTotal(): int
    {
        return $this->total;
    }
}
