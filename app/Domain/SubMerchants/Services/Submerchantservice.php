<?php

namespace App\Domain\SubMerchants\Services;

use App\Domain\SubMerchants\Models\SubMerchant;
use App\Domain\Auth\Models\Application;
use Illuminate\Support\Str;

class SubMerchantService
{
    public function create(array $data, string $applicationId): SubMerchant
    {
        $application = Application::find($applicationId);
        $aggregator  = $application->aggregator;

        // Générer la référence avec le nom de l'agrégateur
        $prefix    = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $aggregator->legal_name));
        $prefix    = substr($prefix, 0, 6);
        $reference = 'SM-' . $prefix . '-' . strtoupper(Str::random(6));

        return SubMerchant::create([
            'application_id'     => $applicationId,
            'legal_name'         => $data['legal_name'],
            'business_type'      => $data['business_type'],
            'address'            => $data['address'],
            'webhook_url'        => $data['webhook_url'],
            'external_reference' => $reference,
            'status'             => 'PENDING',
        ]);
    }

    public function activate(SubMerchant $subMerchant): SubMerchant
    {
        $subMerchant->update(['status' => 'ACTIVE']);
        return $subMerchant;
    }

    public function suspend(SubMerchant $subMerchant): SubMerchant
    {
        $subMerchant->update(['status' => 'SUSPENDED']);
        return $subMerchant;
    }

    public function close(SubMerchant $subMerchant): SubMerchant
    {
        $subMerchant->update(['status' => 'CLOSED']);
        return $subMerchant;
    }

    public function update(SubMerchant $subMerchant, array $data): SubMerchant
    {
        $subMerchant->update(array_filter([
            'legal_name'    => $data['legal_name'] ?? null,
            'business_type' => $data['business_type'] ?? null,
            'address'       => $data['address'] ?? null,
            'webhook_url'   => $data['webhook_url'] ?? null,
        ], fn($value) => !is_null($value)));

        return $subMerchant;
    }
}