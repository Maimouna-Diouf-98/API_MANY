<?php

namespace App\Domain\Transactions\Services;

class FeeCalculatorService
{
    const MANY_FEE_RATE = 0.01; // 1%

    public function calculate(int $amount): array
    {
        $manyFee         = (int) round($amount * self::MANY_FEE_RATE);
        $netToAggregator = $amount - $manyFee;

        return [
            'many_fee'          => $manyFee,
            'net_to_aggregator' => $netToAggregator,
        ];
    }
}