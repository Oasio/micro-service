<?php

namespace App\DataFixtures;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\ShippingAddress;
use App\Enum\OrderStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Jeu de commandes de démonstration.
 *
 * Chargement : php bin/console doctrine:fixtures:load
 * (les Order sont construits directement, sans passer par l'orchestration).
 */
class OrderFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $samples = [
            [
                'customerId' => 'CUST001',
                'cartId' => 'CART123',
                'status' => OrderStatus::PAID,
                'address' => ['10 rue de Paris', 'Rennes', '35000', 'France'],
                'items' => [
                    ['1', 'Casque Bluetooth', 2, 4995],
                    ['2', 'Souris sans fil', 1, 1999],
                ],
            ],
            [
                'customerId' => 'CUST001',
                'cartId' => 'CART124',
                'status' => OrderStatus::SHIPPED,
                'address' => ['10 rue de Paris', 'Rennes', '35000', 'France'],
                'items' => [
                    ['3', 'Clavier mécanique', 1, 8990],
                ],
            ],
            [
                'customerId' => 'CUST002',
                'cartId' => 'CART200',
                'status' => OrderStatus::PENDING_PAYMENT,
                'address' => ['5 avenue de Lyon', 'Lyon', '69000', 'France'],
                'items' => [
                    ['1', 'Casque Bluetooth', 1, 4995],
                ],
            ],
        ];

        foreach ($samples as $s) {
            $order = new Order($s['customerId']);
            $order->setCartId($s['cartId']);
            $order->setShippingAddress(new ShippingAddress(...$s['address']));

            foreach ($s['items'] as [$productId, $name, $qty, $cents]) {
                $order->addItem(new OrderItem($productId, $name, $qty, $cents));
            }

            $order->recalculateTotal();
            $order->setStatus($s['status']);

            $manager->persist($order);
        }

        $manager->flush();
    }
}
