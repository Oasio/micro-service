<?php

namespace App\Tests\Entity;

use App\Enum\OrderStatus;
use PHPUnit\Framework\TestCase;

/**
 * Test unitaire de la machine à états des commandes (Contrat §3).
 */
class OrderStatusTransitionTest extends TestCase
{
    public function testNominalFlowIsAllowed(): void
    {
        $this->assertTrue(OrderStatus::CREATED->canTransitionTo(OrderStatus::PENDING_PAYMENT));
        $this->assertTrue(OrderStatus::PENDING_PAYMENT->canTransitionTo(OrderStatus::PAID));
        $this->assertTrue(OrderStatus::PAID->canTransitionTo(OrderStatus::PREPARING));
        $this->assertTrue(OrderStatus::PREPARING->canTransitionTo(OrderStatus::SHIPPED));
        $this->assertTrue(OrderStatus::SHIPPED->canTransitionTo(OrderStatus::DELIVERED));
    }

    public function testBackwardTransitionIsForbidden(): void
    {
        $this->assertFalse(OrderStatus::DELIVERED->canTransitionTo(OrderStatus::CREATED));
        $this->assertFalse(OrderStatus::SHIPPED->canTransitionTo(OrderStatus::PAID));
        $this->assertFalse(OrderStatus::PREPARING->canTransitionTo(OrderStatus::CREATED));
    }

    public function testCancellationRules(): void
    {
        $this->assertTrue(OrderStatus::CREATED->isCancellable());
        $this->assertTrue(OrderStatus::PENDING_PAYMENT->isCancellable());
        $this->assertTrue(OrderStatus::PAID->isCancellable());
        $this->assertTrue(OrderStatus::PREPARING->isCancellable());

        // Plus annulable une fois expédiée / livrée / déjà annulée.
        $this->assertFalse(OrderStatus::SHIPPED->isCancellable());
        $this->assertFalse(OrderStatus::DELIVERED->isCancellable());
        $this->assertFalse(OrderStatus::CANCELLED->isCancellable());
    }

    public function testCancelTransitionAllowedWhileNotShipped(): void
    {
        $this->assertTrue(OrderStatus::PAID->canTransitionTo(OrderStatus::CANCELLED));
        $this->assertFalse(OrderStatus::SHIPPED->canTransitionTo(OrderStatus::CANCELLED));
    }
}
