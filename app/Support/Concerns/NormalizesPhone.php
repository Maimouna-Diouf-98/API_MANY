<?php

namespace App\Support\Concerns;

trait NormalizesPhone
{
    protected function normalizedPhoneWhere($query, string $column, string $rawPhone)
    {
        $normalized = str_replace(' ', '', $rawPhone);
        return $query->whereRaw("REPLACE({$column}, ' ', '') = ?", [$normalized]);
    }

    protected function normalizePhone(string $phone): string
    {
        return str_replace(' ', '', $phone);
    }
}