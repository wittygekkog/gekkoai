<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Services\Connectors\InnerCove\CancelBookingHandler;
use App\Services\Connectors\InnerCove\CheckAvailabilityHandler;
use App\Services\Connectors\InnerCove\CreateBookingHandler;
use App\Services\Connectors\InnerCove\GetBookingHandler;
use App\Services\Connectors\InnerCove\UpdateBookingHandler;
use WittyGekko\SbuConnector\Registry\OperationDefinition;

/**
 * Operations this platform is willing to ask an SBU to perform.
 * Adding a tool requires a code change. A model cannot register one.
 */
final class OperationCatalog
{
    /**
     * @return list<OperationDefinition>
     */
    public static function definitions(): array
    {
        return [
            new OperationDefinition(
                name: 'booking.check_availability',
                version: '1.0',
                description: 'Check whether The Inner Cove retreat is free for an arrival and departure.',
                handler: CheckAvailabilityHandler::class,
                risk: 'low',
                inputSchema: ['type' => 'object'],
                outputSchema: ['type' => 'object'],
                requiredPermissions: ['ai.operations.execute'],
                requiredFields: ['arrival', 'departure', 'guests'],
                approvalRequired: false,
                readOnly: true,
                idempotency: 'not_required',
            ),
            new OperationDefinition(
                name: 'booking.create',
                version: '1.0',
                description: 'Create one guest booking. Email and mobile are required, matching The Inner Cove booking form.',
                handler: CreateBookingHandler::class,
                risk: 'medium',
                inputSchema: ['type' => 'object'],
                outputSchema: ['type' => 'object'],
                requiredPermissions: ['ai.operations.execute'],
                requiredFields: ['name', 'email', 'mobile', 'arrival', 'departure', 'guests'],
                approvalRequired: true,
                readOnly: false,
            ),
            new OperationDefinition(
                name: 'booking.get',
                version: '1.0',
                description: 'Read one booking by its reference.',
                handler: GetBookingHandler::class,
                risk: 'low',
                inputSchema: ['type' => 'object'],
                outputSchema: ['type' => 'object'],
                requiredPermissions: ['ai.operations.execute'],
                requiredFields: ['reference'],
                approvalRequired: false,
                readOnly: true,
                idempotency: 'not_required',
            ),
            new OperationDefinition(
                name: 'booking.update',
                version: '1.0',
                description: 'Change a booking and recheck the retreat calendar when the dates move. Simulation only until Inner Cove exposes an update API.',
                handler: UpdateBookingHandler::class,
                risk: 'medium',
                inputSchema: ['type' => 'object'],
                outputSchema: ['type' => 'object'],
                requiredPermissions: ['ai.operations.execute'],
                requiredFields: ['reference'],
                approvalRequired: true,
                readOnly: false,
            ),
            new OperationDefinition(
                name: 'booking.cancel',
                version: '1.0',
                description: 'Cancel a booking and release the nights. This does not refund a payment.',
                handler: CancelBookingHandler::class,
                risk: 'high',
                inputSchema: ['type' => 'object'],
                outputSchema: ['type' => 'object'],
                requiredPermissions: ['ai.operations.execute'],
                requiredFields: ['reference'],
                approvalRequired: true,
                readOnly: false,
            ),
        ];
    }
}
