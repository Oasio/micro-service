<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Adresse de livraison — objet-valeur embarqué dans Order (Doctrine Embeddable).
 *
 * Stockée à plat dans la table `orders` (colonnes shipping_address_street, shipping_address_city, …).
 */
#[ORM\Embeddable]
class ShippingAddress
{
    public function __construct(
        #[ORM\Column(length: 255)]
        #[Groups(['order:read'])]
        private string $street = '',

        #[ORM\Column(length: 120)]
        #[Groups(['order:read'])]
        private string $city = '',

        #[ORM\Column(length: 20)]
        #[Groups(['order:read'])]
        private string $postalCode = '',

        #[ORM\Column(length: 80)]
        #[Groups(['order:read'])]
        private string $country = '',
    ) {
    }

    public function getStreet(): string
    {
        return $this->street;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function getCountry(): string
    {
        return $this->country;
    }
}
