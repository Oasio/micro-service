# FlexShop — Architecture micro-services

Projet **FlexShop** : une boutique en ligne découpée en micro-services indépendants,
chacun avec sa propre base de données, communiquant par HTTP sur un réseau Docker privé.

Ce dépôt regroupe les services **développés** au fil des journées (Catalogue, Avis-Clients,
Commande) et, à titre de référence, les services de l'équipe **Platform** (gateway + Auth)
et le service **Paiement**.

Auteur : **DANILO Elouan**.

## Services

### Développés dans ce dépôt

| Service | Rôle | Stack | Base de données | Port (hôte) |
|---------|------|-------|-----------------|-------------|
| **catalog** | CRUD produits (`/api/v1/products`) | Django + DRF | PostgreSQL 16 (`catalog-db`) | http://localhost:8000 |
| **avis** | Avis clients (`/api/reviews`) | Symfony 7 + API Platform | MariaDB 10.11 (`avis-db`) | http://localhost:8001 |
| **commande** | Orchestration des commandes (`/api/orders`) | Symfony 7 + API Platform + Messenger | MariaDB 10.11 (`commande-db`) | http://localhost:8002 |

### Services de référence (équipe Platform / autres équipes)

Présents dans le dépôt comme clones/références (org `3il-flexshop`), **hors** du
`docker-compose.yml` racine — chacun a sa propre stack Docker autonome.

| Service | Rôle | Stack |
|---------|------|-------|
| **platform-gateway-service** | API Gateway, point d'entrée unique (`:80`, dashboard `:8080`) | Traefik v3.1 |
| **platform-auth-service** | Auth : émet des JWT **RS256**, expose la clé publique en **JWKS** (`/api/auth/login`, `/api/auth/jwks`) | Symfony 7.4 + LexikJWT + PostgreSQL |
| **payment-service** | Intentions de paiement (`/api/v1/payments`) | Symfony (components) + SQLite |
| **auth-service** | Squelette Symfony/JWT local (hors orchestration) | Symfony + LexikJWT |

## Architecture (stack orchestrée par le `docker-compose.yml` racine)

```
                        réseau Docker : flexshop-net

   ┌──────────────┐                              ┌──────────────┐
   │   commande   │ ── http://catalog:8000 ───►  │   catalog    │
   │  (Symfony)   │      (prix officiel)         │  (Django)    │
   │  :8002       │                              │  :8000       │
   └──────┬───────┘                              └──────┬───────┘
          │  commande-db (MariaDB)                      │  catalog-db (Postgres)
          │                                             ▲
   ┌──────▼───────┐                                     │
   │    avis      │ ── http://catalog:8000 ─────────────┘
   │  (Symfony)   │      (le produit existe ?)
   │  :8001       │   avis-db (MariaDB)
   └──────────────┘

   commande vérifie les JWT (RS256) via le service Auth :
   JWT_JWKS_URL = http://10.72.200.53/api/auth/jwks   (Auth réel, équipe Platform)
   Panier & Paiement : mode stub déterministe (services pas encore intégrés).
```

## Pré-requis

- **Docker Desktop** (Docker Engine + Compose v2)

## Démarrage (un seul `up` lance tout)

```bash
# 1. Préparer les secrets
cp .env.example .env        # puis ajuster les mots de passe si besoin

# 2. Construire et démarrer les 6 conteneurs (3 services + 3 bases)
docker compose up --build -d

# 3. Vérifier l'état
docker compose ps
```

- Catalogue : http://localhost:8000/api/v1/products/ — doc http://localhost:8000/api/docs/
- Avis-Clients : http://localhost:8001/api/reviews — Swagger http://localhost:8001/api/docs
- Commande : http://localhost:8002/api/orders — Swagger http://localhost:8002/api/docs

## Tester la communication inter-services

```bash
# 1) Créer un produit dans le Catalogue
curl -X POST http://localhost:8000/api/v1/products/ \
  -H "Content-Type: application/json" \
  -d '{"name":"Souris","price":"19.90","stock":5}'
# -> note l'id renvoyé (ex: 1)

# 2) Créer un avis qui référence ce produit
curl -X POST http://localhost:8001/api/reviews \
  -H "Content-Type: application/ld+json" \
  -d '{"rating":5,"comment":"Souris parfaite !","productId":1,"author":"Yanis"}'
# -> 201 : l'avis n'est créé QUE parce que avis a pu appeler catalog:8000

# 3) Avec un produit inexistant -> 422
curl -X POST http://localhost:8001/api/reviews \
  -H "Content-Type: application/ld+json" \
  -d '{"rating":4,"comment":"Produit fantome","productId":999999,"author":"Test"}'
```

Le service **Commande** protège `/api/orders` par un **JWT RS256** : il faut un jeton valide
(obtenu via l'Auth) dans l'en-tête `Authorization: Bearer <jwt>` (voir le compte rendu J3).

## Commandes utiles

```bash
docker compose logs -f commande        # logs d'un service (catalog / avis / commande)
docker compose exec avis sh            # shell dans un conteneur Symfony
docker compose exec catalog sh         # shell dans le conteneur Django
docker compose restart commande        # redémarrer un service
docker compose down                    # arrêter (conserve les volumes/données)
docker compose down -v                 # tout supprimer, volumes inclus
```

## Détails d'implémentation

- **Réseau** : `flexshop-net` (bridge). Les services se joignent par leur **nom**
  (`catalog`, `avis-db`, `commande-db`...) ; depuis `avis`/`commande`, le Catalogue est sur
  `http://catalog:8000` (jamais `localhost`). Configuré via `CATALOGUE_BASE_URL`.
- **Volumes nommés** : `catalog_data` (Postgres), `avis_data` et `commande_data` (MariaDB) —
  les données survivent à `docker compose down`.
- **Healthchecks** : chaque service applicatif attend que sa base soit `healthy`
  (`depends_on: condition: service_healthy`) avant de démarrer.
- **Migrations** : appliquées automatiquement au démarrage de chaque conteneur applicatif
  (Django `migrate`, Symfony `doctrine:migrations:migrate`).
- **Sécurité (Commande)** : vérification JWT **RS256** par récupération de la clé publique
  **JWKS** de l'Auth (aucun appel synchrone à l'Auth après le login).
- **Résilience (Commande)** : appels inter-services en HttpClient avec timeout + retry, et un
  **circuit breaker** ; Panier et Paiement sont stubés (`CART_STUB_ENABLED`,
  `PAYMENT_STUB_ENABLED`) tant que ces services ne sont pas intégrés.
- **Événementiel (Commande)** : publication d'un événement `OrderConfirmed` via **Symfony
  Messenger** (transport `sync://` en démo, pas de broker requis).

## CI/CD (J4 — S14)

Le service **Avis-Clients** dispose d'un pipeline CI/CD sur un dépôt GitHub dédié :
**https://github.com/Oasio/avis-clients-cicd** (GitHub Actions, `.github/workflows/ci.yml`).

À chaque `push` sur `main`, le pipeline enchaîne 4 stages :

- **test** : PHPUnit sur une base SQLite jetable (`doctrine:schema:create`)
- **build** : construction de l'image Docker
- **package** : push sur le GitHub Container Registry → `ghcr.io/oasio/avis-clients-cicd:latest` (+ tag par hash de commit)
- **deploy** (bonus) : déploiement simulé, manuel via un environnement `production` protégé

Récupérer l'image publiée :

```bash
docker pull ghcr.io/oasio/avis-clients-cicd:latest
```

## Progression par journée

| Journée | Sujet | Compte rendu |
|---------|-------|--------------|
| **J1** | Architecture micro-services (conception) | `rendu/J1_archi_micro-service_DANILO_Elouan.pdf` |
| **J2 · S7** | Service Avis-Clients (Symfony + API Platform) | `rendu/J2_S7_Symfony_DANILO_Elouan.pdf` |
| **J2 · S8** | Conteneurisation & orchestration Docker | `rendu/J2_S8_Docker_DANILO_Elouan.pdf` |
| **J3 · S12** | JWT RS256 (JWKS) dans le service Commande | `rendu/J3_S12_JWT_DANILO_Elouan.pdf` |
| **J4 · S14** | Pipeline CI/CD (GitHub Actions + ghcr.io) | `rendu/J4_S14_CICD_DANILO_Elouan.pdf` |

## Structure du dépôt

```
J1/
├── README.md                   # ce fichier
├── docker-compose.yml          # orchestration : catalog + avis + commande (+ 3 bases)
├── .env.example                # modèle des secrets (à commiter)
├── .env                        # secrets réels (NON commité)
├── .gitignore
│
├── catalog-service/            # service Catalogue (Django + DRF, Postgres)
├── avis-clients/               # service Avis-Clients (Symfony + API Platform, MariaDB)
├── commande/                   # service Commande (Symfony + API Platform + Messenger)
│
├── platform-gateway-service/   # (référence Platform) API Gateway Traefik + compose
├── platform-auth-service/      # (référence Platform) Auth JWT RS256 / JWKS
├── payment-service/            # (référence) service Paiement
├── auth-service/               # squelette Symfony/JWT local (hors orchestration)
│
├── rendu/                      # LIVRABLES FINAUX à rendre
│   ├── J1_archi_micro-service_DANILO_Elouan.pdf
│   ├── J2_S7_Symfony_DANILO_Elouan.pdf
│   ├── J2_S8_Docker_DANILO_Elouan.pdf
│   ├── J3_S12_JWT_DANILO_Elouan.pdf
│   ├── J4_S14_CICD_DANILO_Elouan.pdf
│   ├── CatalogService.postman_collection.json
│   └── AvisClients.postman_collection.json
│
└── docs/                       # sources & supports (non rendus tels quels)
    ├── comptes-rendus/         # sources LaTeX + figures + PDF compilés
    │   ├── Devoir_J1_compte_rendu.tex / .pdf
    │   ├── Devoir_J2_S7_Symfony.tex / .pdf
    │   ├── Devoir_J2_S8_Docker.tex / .pdf
    │   ├── Devoir_J3_S12_JWT_Commande.tex / .pdf
    │   ├── Devoir_J4_S14_CICD.tex / .pdf
    │   └── *.png               # figures (swagger, docker ps, tests, pipeline)
    └── cours/                  # supports de cours (pdf / pptx / docx) + guides
```

> Recompiler un compte rendu : `cd docs/comptes-rendus && tectonic Devoir_J4_S14_CICD.tex`
