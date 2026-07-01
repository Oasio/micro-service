<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload d'entrée de POST /orders (cf. Contrat §2.1).
 *
 * ```json
 * {
 *   "customerId": "CUST001",
 *   "cartId": "CART123",
 *   "shippingAddress": { "street": "...", "city": "...", "postalCode": "...", "country": "..." }
 * }
 * ```
 *
 * Le client ne transmet PAS les prix : Commande récupère le panier (Panier) puis
 * les prix officiels (Catalogue) — on ne fait jamais confiance au prix client.
 */
class CreateOrderRequest
{
    #[Assert\NotBlank(message: 'Le customerId est obligatoire.')]
    #[Assert\Length(max: 64)]
    public ?string $customerId = null;

    #[Assert\NotBlank(message: 'Le cartId est obligatoire.')]
    #[Assert\Length(max: 64)]
    public ?string $cartId = null;

    /**
     * Email du client. Optionnel ici (le contrat Commande ne le collecte pas) mais REQUIS
     * par le service Paiement : à défaut, Commande dérive "<customerId>@flexshop.local".
     */
    #[Assert\Email(message: "L'email du client est invalide.")]
    #[Assert\Length(max: 180)]
    public ?string $customerEmail = null;

    #[Assert\NotNull(message: "L'adresse de livraison est obligatoire.")]
    #[Assert\Valid]
    public ?ShippingAddressInput $shippingAddress = null;
}
