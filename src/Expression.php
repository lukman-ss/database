<?php

declare(strict_types=1);

namespace Lukman\Database;

final class Expression
{
    public function __construct(private readonly string $value)
    {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
