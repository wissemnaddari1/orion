<?php

namespace App\DTO;

final class AggregateAmountDto
{
    private string $label;
    private float $total;

    public function __construct(
        int|string $label,
        int|string|float|null $total,
    ) {
        $this->label = (string) $label;
        $this->total = (float) ($total ?? 0.0);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getTotal(): float
    {
        return $this->total;
    }
}
