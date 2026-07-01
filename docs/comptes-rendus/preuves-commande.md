# Service Commande — preuves & captures à intégrer au rapport

> Le service Commande est dans `J1/commande/`. Vérifié en **live** contre le vrai service Auth
> (`http://10.72.200.53`) et le Catalogue Django (`:8000`). Panier & Paiement en mode stub.

## 1. Preuves CLI déjà obtenues (à screenshoter ou coller)

### Sécurité JWT (S12.3 / S12.4) — vérification via JWKS

| Requête | Attendu | Obtenu |
|---|---|---|
| `GET /api/orders` sans token | 401 | **401** |
| `POST /api/auth/login` (Auth réel `10.72.200.53`) | JWT | **token RS256** |
| `GET /api/orders` avec token réel | 200 | **200** |
| `GET /api/orders` token bidon | 401 | **401** |
| `GET /api/orders` token expiré | 401 | **401** (test) |
| `GET /health` (public) | 200 | **200** |

La clé publique de l'Auth est récupérée **automatiquement** via `GET /api/auth/jwks`
(`JWT_JWKS_URL`), convertie JWK→PEM en interne, et utilisée pour vérifier la signature RS256.
Aucun fichier de clé à gérer.

### Chaîne complète authentifiée (login → token → commande)

```
POST /api/auth/login (10.72.200.53)  → { "token": "eyJ..." }
POST /api/orders  (Authorization: Bearer <token>)
  → 201  orderId=ORD-49C5AFE2  status=PAID  total=209.79 EUR
     - Casque sans fil XYZ x2 @ 89.90   (prix OFFICIEL du Catalogue Django)
     - Souris ergonomique  x1 @ 29.99
POST /api/orders sans token → 401
```

Orchestration : Panier (stub) → **Catalogue Django réel** (prix officiels) → Paiement (stub) →
`PAID` + événement async `OrderConfirmed`.

### Tests automatisés

```
php vendor/bin/phpunit   → OK (24 tests, 53 assertions)
```
Couvre : CRUD commande, machine à états (200/400/409), 422 (panier/produit/quantité/montant),
idempotence, refus paiement, **JWT (401 sans/bidon/expiré, 200 valide)**, conversion JWK→PEM.

### Contrat d'API publié

`commande/openapi.json` (OpenAPI 3.1) — 6 opérations : `POST/GET /api/orders`,
`GET /api/orders/{orderId}`, `GET /api/customers/{customerId}/orders`,
`PATCH /api/orders/{orderId}/status`, `DELETE /api/orders/{orderId}`.

## 2. Captures d'écran (déjà générées dans `docs/comptes-rendus/`)

Trois captures réelles, prises **en live contre l'Auth réel `10.72.200.53`**, intégrées au
rapport LaTeX (annexes A, B, C) :

1. **`commande-swagger.png`** — Swagger UI `http://localhost:8002/api/docs` : les 6 opérations
   + le bouton *Authorize* (JWT).
2. **`commande-parcours.png`** — parcours JWT bout-en-bout (console) : `GET /api/orders` sans
   token → **401** ; `POST 10.72.200.53/api/auth/login` → **JWT RS256** (payload `sub`,
   `roles`, `exp` décodés) ; `GET /api/orders` + `Bearer` valide → **200** (clé publique JWKS
   vérifiée) ; token bidon → **401** « Signature JWT invalide ».
3. **`commande-tests.png`** — `php vendor/bin/phpunit --testdox` : **24 tests OK**, dont les 4
   cas JWT (sans token 401, bidon 401, expiré 401, valide 200) + conversion JWK→PEM.

> Le `POST /api/orders` (→ 201 `PAID`) exige la stack complète (Catalogue Django `:8000` pour
> les prix) ; il est prouvé par le test *« Create order returns 201 and is paid »* de la suite,
> le Catalogue n'étant pas lancé hors Docker.
>
> Collection Postman prête : `commande/postman/Commande.postman_collection.json`
> (requête « Auth - Login » qui stocke `{{token}}`, puis toutes les requêtes commande avec Bearer).
