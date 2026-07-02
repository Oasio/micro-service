# FlexShop — Orchestration Docker (S8)

Deux services communs du projet FlexShop, conteneurisés et orchestrés par
**docker compose** : ils démarrent ensemble et communiquent sur un réseau Docker privé.

| Service | Stack | Base de données | Port (hôte) |
|---------|-------|-----------------|-------------|
| **catalog** | Django + DRF | PostgreSQL 16 (`catalog-db`) | http://localhost:8000 |
| **avis** | Symfony + API Platform | MariaDB 10.11 (`avis-db`) | http://localhost:8001 |

```
                 réseau Docker : flexshop-net
   ┌────────────┐   http://catalog:8000   ┌────────────┐
   │   avis     │ ──────────────────────► │  catalog   │
   │ (Symfony)  │                          │  (Django)  │
   └─────┬──────┘                          └─────┬──────┘
         │ DATABASE_URL=avis-db                  │ POSTGRES_HOST=catalog-db
   ┌─────▼──────┐                          ┌─────▼──────┐
   │  avis-db   │                          │ catalog-db │
   │ (MariaDB)  │  volume avis_data         │ (Postgres) │  volume catalog_data
   └────────────┘                          └────────────┘
```

## Pré-requis

- **Docker Desktop** (Docker Engine + Compose v2)

## Démarrage (un seul `up` lance tout)

```bash
# 1. Préparer les secrets
cp .env.example .env        # puis ajuster les mots de passe si besoin

# 2. Construire et démarrer les 4 conteneurs
docker compose up --build -d

# 3. Vérifier l'état
docker compose ps
```

- Catalogue : http://localhost:8000/api/v1/products/ — doc http://localhost:8000/api/docs/
- Avis-Clients : http://localhost:8001/api/reviews — Swagger http://localhost:8001/api/docs

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

## Commandes utiles

```bash
docker compose logs -f avis            # logs d'un service
docker compose exec avis sh            # shell dans le conteneur Symfony
docker compose exec catalog sh         # shell dans le conteneur Django
docker compose restart avis            # redémarrer un service
docker compose down                    # arrêter (conserve les volumes/données)
docker compose down -v                 # tout supprimer, volumes inclus
```

## Détails d'implémentation

- **Réseau** : `flexshop-net` (bridge). Les services se joignent par leur **nom**
  (`catalog`, `avis-db`...) ; depuis `avis`, le Catalogue est sur `http://catalog:8000`
  (jamais `localhost`). Configuré via la variable `CATALOGUE_BASE_URL`.
- **Volumes nommés** : `catalog_data` (Postgres) et `avis_data` (MariaDB) — les données
  survivent à `docker compose down` (vérifié).
- **Healthchecks** : `catalog` et `avis` attendent que leur base soit `healthy`
  (`depends_on: condition: service_healthy`) avant de démarrer.
- **Migrations** : appliquées automatiquement au démarrage de chaque conteneur applicatif
  (Django `migrate`, Symfony `doctrine:migrations:migrate`).
- **Catalogue** : `settings.py` bascule sur PostgreSQL si `POSTGRES_HOST` est défini,
  sinon SQLite (dev local). `ALLOWED_HOSTS` inclut `catalog` (nom de service Docker).

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

Compte rendu : `rendu/J4_S14_CICD_DANILO_Elouan.pdf`.

## Structure du dépôt

```
J1/
├── README.md                   # ce fichier (orchestration Docker S8)
├── docker-compose.yml          # orchestration des 4 services
├── .env.example                # modèle des secrets (à commiter)
├── .env                        # secrets réels (NON commité)
├── .gitignore
│
├── catalog-service/            # service Catalogue (Django + DRF)
│   ├── Dockerfile              # image Django (python:3.12-slim)
│   └── .dockerignore
├── avis-clients/               # service Avis-Clients (Symfony + API Platform)
│   ├── Dockerfile              # image Symfony (php:8.3-cli)
│   ├── .dockerignore
│   └── docker/entrypoint.sh    # attente BD + migrations + serveur
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
