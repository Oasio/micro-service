<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adresse de livraison reçue à la création d'une commande (POST /orders).
 */
class ShippingAddressInput
{
    #[Assert\NotBlank(message: 'La rue est obligatoire.')]
    #[Assert\Length(max: 255)]
    public ?string $street = null;

    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(max: 120)]
    public ?string $city = null;

    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Length(max: 20)]
    public ?string $postalCode = null;

    #[Assert\NotBlank(message: 'Le pays est obligatoire.')]
    #[Assert\Length(max: 80)]
    public ?string $country = null;
}
