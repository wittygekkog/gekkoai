<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TenantConnector;
use App\Models\TenantOperation;
use App\Services\Connectors\OperationCatalog;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'The Inner Cove', 'slug' => 'the-inner-cove', 'description' => 'Accommodation and booking management. The first connected business unit, running in simulation until a live connector is approved.', 'operations' => true],
            ['name' => 'Pearl Vista De Coron', 'slug' => 'pearl-vista', 'description' => 'Resort operations. No operations are enabled until its own connector is reviewed.', 'operations' => false],
            ['name' => 'Ticketbooth.ph', 'slug' => 'ticketbooth', 'description' => 'Ticketing and event management. No operations are enabled until its own connector is reviewed.', 'operations' => false],
            ['name' => 'Prestige Pass', 'slug' => 'prestige-pass', 'description' => 'RSVP and guest management. No operations are enabled until its own connector is reviewed.', 'operations' => false],
            ['name' => 'Corondeli', 'slug' => 'corondeli', 'description' => 'Ordering and inventory management. No operations are enabled until its own connector is reviewed.', 'operations' => false],
            ['name' => 'WittyGekko', 'slug' => 'wittygekko', 'description' => 'Internal operations, billing, CRM, and administration. No operations are enabled until its own connector is reviewed.', 'operations' => false],
        ];

        foreach ($units as $unit) {
            $tenant = Tenant::query()->firstOrCreate(
                ['slug' => $unit['slug']],
                [
                    'name' => $unit['name'],
                    'description' => $unit['description'],
                    'timezone' => 'Asia/Manila',
                    'locale' => 'en',
                    'status' => 'active',
                ],
            );

            TenantConnector::query()->firstOrCreate(
                ['tenant_id' => $tenant->id],
                [
                    'base_url' => 'simulation://'.$tenant->slug,
                    'mode' => 'simulation',
                    'connector_version' => '1.0.0',
                    'authentication_type' => 'hmac',
                    'key_id' => 'sim-'.$tenant->slug,
                    'status' => 'simulation',
                ],
            );

            if (! $unit['operations']) {
                continue;
            }

            foreach (OperationCatalog::definitions() as $definition) {
                TenantOperation::query()->firstOrCreate(
                    ['tenant_id' => $tenant->id, 'operation_name' => $definition->name],
                    [
                        'version' => $definition->version,
                        'description' => $definition->description,
                        'input_schema' => $definition->inputSchema,
                        'output_schema' => $definition->outputSchema,
                        'risk_level' => $definition->risk,
                        'enabled' => true,
                        'approval_required' => $definition->approvalRequired,
                        'read_only' => $definition->readOnly,
                        'required_fields' => $definition->requiredFields,
                    ],
                );
            }
        }
    }
}
