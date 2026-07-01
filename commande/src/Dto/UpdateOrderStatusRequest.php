<?php

namespace App\Dto;

/**
 * Payload de PATCH /orders/{orderId}/status (cf. Contrat §2.5).
 *
 * ```json
 * { "status": "SHIPPED" }
 * ```
 *
 * La validité (statut connu → 400, transition autorisée → 409) est contrôlée
 * dans App\State\OrderStatusProcessor pour renvoyer les codes exacts du contrat.
 */
class UpdateOrderStatusRequest
{
    public ?string $status = null;
}
