<?php

namespace App\Tests\Fake;

use App\Security\AuthPublicKeyProvider;

/**
 * Double du provider de clé publique : renvoie la clé publique de TEST (sans appeler
 * le service Auth ni le JWKS réseau), indexée par le kid utilisé pour signer les jetons.
 */
class FakeAuthPublicKeyProvider extends AuthPublicKeyProvider
{
    public function __construct()
    {
        // Pas d'appel à parent::__construct() : aucun HttpClient ni cache requis en test.
    }

    public function getPublicKeys(): array
    {
        return [TestKeys::KID => TestKeys::PUBLIC_PEM];
    }
}
