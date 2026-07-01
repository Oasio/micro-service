# Commande — Service métier (FlexShop)

Service **Commande** du projet micro-services FlexShop. Il **orchestre** la création d'une
commande : il récupère le **Panier**, vérifie chaque produit et son **prix officiel** auprès
du **Catalogue** (Django), **débite** le client via le service **Paiement**, puis publie un
événement **`OrderConfirmed`** pour les services en aval (Stock, Notifications, Logistique).

Construit avec **Symfony 7.4** + **API Platform 4.2** + **Doctrine** + **Symfony Messenger**.

> Spec publiée aux autres équipes : [`openapi.json`](openapi.json) (OpenAPI 3.1) — Swagger UI sur `/api/docs`.

## Pile technique

| Élément                | Choix                                                        |
|------------------------|-------------------------------------------------------------|
| Framework              | Symfony 7.4 (skeleton, API only)                            |
| API REST + doc         | API Platform 4.2 (opérations + Swagger auto)                |
| ORM                    | Doctrine                                                    |
| Base de données        | **MariaDB** (config TP) / **SQLite** (dev zéro-install)     |
| Appels inter-services  | Symfony HttpClient (timeout + retry/backoff) + circuit breaker |
| Communication async    | **Symfony Messenger** (événement `OrderConfirmed`)          |
| Tests                  | PHPUnit (Web + unitaires)                                   |

## Démarrage rapide en local (SQLite)

Le projet est configuré pour **MariaDB** (`.env`). Pour le lancer sans serveur de base, un
fichier **`.env.local`** (non versionné) bascule sur **SQLite** :

```bash
composer install
php bin/console assets:install public              # CSS/JS de la Swagger UI (/api/docs)
php bin/console doctrine:schema:create            # crée var/data.db (SQLite)
php bin/console doctrine:fixtures:load -n          # 3 commandes de démo
# Lancer le Catalogue Django sur :8000 (pour la vérification des produits), puis :
php -S 127.0.0.1:8002 -t public public/router.php  # http://localhost:8002/api/docs
```

> Si la page `/api/docs` s'affiche sans style (images cassées), c'est que les assets ne sont pas
> installés : lancer `php bin/console assets:install public` puis rafraîchir (Ctrl+F5).

> Le service appelle le **Catalogue** réel sur `CATALOGUE_BASE_URL` (par défaut `http://localhost:8000`).
> **Panier** et **Paiement** n'existant pas encore, ils sont **simulés** (mode stub, voir plus bas).

## Version « officielle » MariaDB (TP / Docker)

Supprimer `.env.local`, renseigner `DATABASE_URL` (MariaDB) dans `.env`, puis :

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction   # migration MariaDB livrée
```

## Pré-requis

- PHP **8.2+** avec `pdo_mysql` (ou `pdo_sqlite`), `intl`, `ctype`, `iconv`
- [Composer](https://getcomposer.org/)
- Le service **Catalogue (Django)** joignable (local `:8000`, Docker `catalog:8000`)
- (Docker) Docker + docker compose

## Endpoints (contrat d'API)

Base API Platform : préfixe `/api`. La Swagger UI est sur **`/api/docs`**.

| Méthode | URI                                  | Rôle                                   | Codes |
|---------|--------------------------------------|----------------------------------------|-------|
| `POST`  | `/api/orders`                        | Créer (orchestration Panier→Catalogue→Paiement) | 201 / 400 / 422 / 502 |
| `GET`   | `/api/orders`                        | Lister les commandes (paginé)          | 200 |
| `GET`   | `/api/orders/{orderId}`              | Détail d'une commande                  | 200 / 404 |
| `GET`   | `/api/customers/{customerId}/orders` | Commandes d'un client                  | 200 |
| `PATCH` | `/api/orders/{orderId}/status`       | Changer le statut (machine à états)    | 200 / 400 / 404 / 409 |
| `DELETE`| `/api/orders/{orderId}`              | Annuler (soft cancel)                  | 200 / 404 / 409 |
| `GET`   | `/health`                            | Sonde de disponibilité                 | 200 / 503 |

### Exemple — créer une commande

```bash
curl -X POST http://localhost:8002/api/orders \
  -H "Content-Type: application/ld+json" \
  -H "Idempotency-Key: 9f8c1e2a-7b3d-4f6a-9c2e-1a2b3c4d5e6f" \
  -d '{
    "customerId": "CUST001",
    "cartId": "CART123",
    "shippingAddress": {"street":"10 rue de Paris","city":"Rennes","postalCode":"35000","country":"France"}
  }'
```

Réponse `201` : `orderId`, `status` (`PAID` si le paiement passe), `items` (prix **officiels**
du Catalogue), `totalAmount`, `shippingAddress`, `createdAt`/`updatedAt`.

### Changer le statut

```bash
curl -X PATCH http://localhost:8002/api/orders/ORD-XXXX/status \
  -H "Content-Type: application/merge-patch+json" \
  -d '{"status":"SHIPPED"}'
```

Statuts : `CREATED → PENDING_PAYMENT → PAID → PREPARING → SHIPPED → DELIVERED`
(+ `CANCELLED` tant que non expédiée, `FAILED` si paiement refusé). Une transition interdite → **409**.

## Communication inter-services (choix S6)

| Communication      | Cible(s)                          | Type            | Pourquoi                          |
|--------------------|-----------------------------------|-----------------|-----------------------------------|
| `GET /carts`       | Panier                            | **Sync**        | Besoin immédiat du contenu        |
| `GET /products`    | Catalogue                         | **Sync**        | Vérif. produit + prix officiel    |
| `POST /payments`   | Paiement                          | **Sync**        | Réponse client indispensable      |
| `OrderConfirmed`   | Stock, Notifications, Logistique  | **Async (broker)** | Découplage, résilience, pics   |

- **Idempotence** (`POST /orders`) : en-tête `Idempotency-Key` ; un rejeu renvoie la même
  commande sans réexécuter (pas de double débit).
- **Fiabilité sur chaque appel sortant** : `timeout`, **retry avec backoff** (1s, 2s, 4s,
  via `retry_failed` du HttpClient) et **circuit breaker** (`App\Service\CircuitBreaker`).
- **Persistance avant paiement** : la commande est enregistrée en `PENDING_PAYMENT` avant
  l'appel Paiement ; si le Paiement est injoignable (502), elle est conservée (réessai possible).
- **Async** : à la confirmation, l'événement `OrderConfirmed` est publié sur Messenger.
  En dev `MESSENGER_TRANSPORT_DSN=sync://` (handler en process) ; en prod pointer vers
  `amqp://` (RabbitMQ) et lancer `php bin/console messenger:consume async`. DLQ via le
  transport `failed`.

## Authentification JWT (S12.3 / S12.4)

Les endpoints `/api/orders` et `/api/customers/{id}/orders` sont **protégés par JWT RS256**
émis par le service **Auth**. Vérification décentralisée via **JWKS** — **100 % JWKS, aucun
fichier de clé à gérer**, aucun appel synchrone à l'Auth à chaque requête :

1. `App\Security\AuthPublicKeyProvider` fait `GET {JWT_JWKS_URL}` (l'endpoint `/api/auth/jwks`
   de l'Auth), et **met les clés en cache 1 h** (`CacheInterface`). Chaque clé JWK (`n`,`e`)
   est convertie en PEM en mémoire par `App\Security\JwkConverter` (aucune écriture disque).
2. `App\Security\JwtAuthenticator` (authenticator Symfony maison) vérifie chaque
   `Authorization: Bearer <jwt>` : `alg=RS256` imposé (refus `none`/`HS256`), signature
   vérifiée par `openssl_verify` avec la clé publique du JWKS, puis `exp` (401 si expiré,
   tolérance 30 s). Pas de JWT valide → **401**.
3. L'utilisateur est reconstruit (stateless) depuis le claim `username` (`App\Security\JwtUserProvider`).

> La clé de vérification provient **uniquement du JWKS de l'Auth**. Ni LexikJWT, ni
> `firebase/php-jwt`, ni fichier `public.pem` : vérification RS256 en PHP/OpenSSL pur.
> Les tests (PHPUnit) utilisent une paire RSA de test embarquée via `FakeAuthPublicKeyProvider`,
> sans réseau.

```bash
# 1. Obtenir un JWT auprès de l'Auth (service Platform)
curl -X POST http://10.72.200.53/api/auth/login -H 'Content-Type: application/json' \
  -d '{"username":"alice","password":"customer"}'        # → { "token": "..." }
# 2. Appeler Commande avec le jeton
curl http://localhost:8002/api/orders -H "Authorization: Bearer <token>"   # 200
curl http://localhost:8002/api/orders                                       # 401
```

Variable unique : `JWT_JWKS_URL` (local/gateway `http://10.72.200.53/api/auth/jwks` ; dans le
réseau Docker Platform : `http://auth-service:8000/api/auth/jwks` — **nom de service, pas
localhost**, S12.4 piège 1). `/health` et `/api/docs` restent publics.

> ⚠️ Dans le `docker-compose.yml` local de J1 (catalog + avis + commande), il n'y a pas d'Auth :
> `/api/orders` répondra **401**. Pour une démo complète, utiliser la stack gateway de l'équipe
> Platform (qui fournit `auth-service`) ou désactiver temporairement le firewall `api`.

## Mode stub (Panier & Paiement)

Tant que les services Panier et Paiement ne tournent pas, ils sont **simulés** :

| Variable               | Effet                                                              |
|------------------------|--------------------------------------------------------------------|
| `CART_STUB_ENABLED=true`    | Panier déterministe (produits 1 ×2 et 2 ×1) ; `cartId=CART_UNKNOWN` → 422 |
| `PAYMENT_STUB_ENABLED=true` | Paiement accepté (refusé au-delà de 10 000 € pour démontrer l'échec)     |

Passer ces variables à `false` pour appeler les vrais services (`CART_BASE_URL`,
`PAYMENT_BASE_URL`). Le **Catalogue est toujours réel**.

## Lancer avec Docker

Le service est intégré au `docker-compose.yml` racine du projet (services `commande` +
`commande-db`). Depuis la racine du repo :

```bash
docker compose up --build        # démarre catalog, avis, commande et leurs bases
```

- Commande : http://localhost:8002/api/docs
- Dans le réseau Docker, Commande appelle le Catalogue via `http://catalog:8000` (nom de service).

## Tests

```bash
php bin/console doctrine:schema:create --env=test   # crée la base de test (1ʳᵉ fois)
php vendor/bin/phpunit
```

Les 3 dépendances (Panier, Catalogue, Paiement) sont remplacées par des **doubles
déterministes** (`tests/Fake/`) : on teste l'orchestration et tous les codes HTTP du contrat
(201/200/400/404/409/422) sans dépendre des vrais services.

## Structure

```
src/
├── Controller/HealthController.php      # GET /health
├── Dto/                                 # entrées POST/PATCH (validation)
├── Entity/                              # Order, OrderItem, ShippingAddress (embeddable)
├── Enum/OrderStatus.php                 # machine à états + transitions
├── Message/OrderConfirmed.php           # événement async
├── MessageHandler/                      # consommateur de l'événement
├── Repository/
├── Service/                             # CartClient, CatalogClient, PaymentClient,
│                                        # CircuitBreaker, OrderOrchestrator
└── State/                               # processors (create/status/cancel) + provider
```
