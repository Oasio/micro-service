# Avis-Clients — Service Symfony (FlexShop)

2ᵉ service commun du projet micro-services FlexShop. Gère les **avis** (`Review`) sur les
produits du service **Catalogue** (Django). Construit avec **Symfony 7** + **API Platform**.

Une `Review` = note (1-5) + commentaire + `productId` + auteur + date de création.
Avant d'enregistrer un avis, le service **vérifie via HTTP que le produit existe**
dans le Catalogue (communication inter-services).

## Pile technique

| Élément        | Choix                                    |
|----------------|------------------------------------------|
| Framework      | Symfony 7.1 (skeleton, API only)         |
| API REST + doc | API Platform 3 (CRUD + Swagger auto)     |
| ORM            | Doctrine                                 |
| Base de données| **MariaDB** (polyglottisme vs PostgreSQL côté Django) |
| Appels inter-services | Symfony HttpClient                |
| Tests          | PHPUnit (Web + Kernel TestCase)          |

## Démarrage rapide en local (SQLite)

Le projet est configuré pour **MariaDB** (config du TP, dans `.env`). Pour le lancer
sans installer de serveur de base, un fichier **`.env.local`** (non versionné) bascule
sur **SQLite** :

```bash
composer install
php bin/console doctrine:schema:create           # crée var/data.db (SQLite)
php bin/console doctrine:fixtures:load -n         # 3 avis de démo
php -S 127.0.0.1:8001 -t public public/router.php  # http://localhost:8001/api/docs
```

Pour la version « officielle » MariaDB du TP : supprimer `.env.local`, renseigner
`DATABASE_URL` (MariaDB) dans `.env`, puis utiliser les migrations
(`doctrine:migrations:migrate`).

## Pré-requis

- PHP **8.2+** avec les extensions `pdo_mysql`, `mbstring`, `intl`, `ctype`, `iconv`
- [Composer](https://getcomposer.org/)
- MariaDB (ou MySQL) en écoute sur `127.0.0.1:3306`
- (optionnel) le [Symfony CLI](https://symfony.com/download) pour `symfony serve`
- Le service **Catalogue (Django)** lancé sur `http://localhost:8000`

## Installation (≈ 30 secondes)

```bash
# 1. Récupérer le projet
git clone <url-du-depot> avis-clients
cd avis-clients

# 2. Installer les dépendances PHP
composer install

# 3. Configurer la base (ajuster l'identifiant/mot de passe si besoin)
#    -> éditer DATABASE_URL dans .env  (ou créer un .env.local)

# 4. Créer la base et appliquer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction

# 5. (optionnel) Charger des avis de démo (produits 1 et 2 doivent exister dans le Catalogue)
php bin/console doctrine:fixtures:load --no-interaction
```

## Lancer le service

Le Catalogue (Django) occupe déjà le port `:8000`. On lance Avis-Clients sur **`:8001`** :

```bash
# Avec le Symfony CLI
symfony serve -d --port=8001

# …ou avec le serveur PHP intégré (router.php = sert les assets CSS/JS de la Swagger UI)
php -S 127.0.0.1:8001 -t public public/router.php
```

- **API** : http://localhost:8001/api/reviews
- **Swagger UI** : http://localhost:8001/api/docs
- **Schéma OpenAPI (JSON)** : http://localhost:8001/api/docs.json

## Endpoints (auto-générés par API Platform)

| Méthode | URL                  | Rôle                              |
|---------|----------------------|-----------------------------------|
| GET     | `/api/reviews`       | Liste paginée des avis            |
| POST    | `/api/reviews`       | Créer un avis (201)               |
| GET     | `/api/reviews/{id}`  | Détail d'un avis                  |
| PATCH   | `/api/reviews/{id}`  | Modifier un avis                  |
| DELETE  | `/api/reviews/{id}`  | Supprimer un avis                 |

### Exemple de création

```bash
curl -X POST http://localhost:8001/api/reviews \
  -H 'Content-Type: application/ld+json' \
  -d '{"rating": 5, "comment": "Excellent produit !", "productId": 1, "author": "Alice"}'
```

### Règles de validation (réponse `422` sinon)

- `rating` : entier **entre 1 et 5**
- `comment` : non vide, entre **5 et 1000** caractères
- `productId` : entier positif **existant dans le Catalogue** (vérifié par HttpClient)
- `author` : non vide

Si le produit n'existe pas dans le Catalogue → **422** (« Produit inexistant »).
Si le Catalogue est injoignable → **502**.

## Communication avec le Catalogue

Avant chaque écriture, `App\State\ReviewPersistProcessor` interroge :

```
GET {CATALOGUE_BASE_URL}/api/v1/products/{productId}/
```

L'URL de base se configure via `CATALOGUE_BASE_URL` dans `.env`
(`http://localhost:8000` en local, `http://catalogue:8000` en Docker à la séance S8).

## Tests

```bash
# Préparer la base de test
php bin/console --env=test doctrine:database:create
php bin/console --env=test doctrine:migrations:migrate --no-interaction

# Lancer la suite
php bin/phpunit
```

Couverture : liste, création (201), produit inexistant (422), note hors bornes (422),
commentaire vide (422), plus des tests unitaires de validation. Le Catalogue est
remplacé par un double (`FakeCatalogueClient`) : les tests ne dépendent pas du Django réel.

## Postman

Collection prête à l'emploi dans [`postman/Avis-Clients.postman_collection.json`](postman/Avis-Clients.postman_collection.json)
(list, create, get-one, patch, delete + cas d'erreur 422).
Astuce : on peut aussi importer le schéma depuis `http://localhost:8001/api/docs.json`.

## Structure

```
avis-clients/
├── config/                 # configuration Symfony + API Platform + Doctrine
├── migrations/             # migration de la table review
├── postman/                # collection Postman exportée
├── public/index.php        # point d'entrée HTTP
├── src/
│   ├── Entity/Review.php            # entité + #[ApiResource] + validation
│   ├── Repository/ReviewRepository.php
│   ├── Service/CatalogueClient.php  # appel HTTP vers le Catalogue
│   ├── State/ReviewPersistProcessor.php  # vérif produit avant persist
│   └── DataFixtures/ReviewFixtures.php
└── tests/                  # PHPUnit (Api/ + Entity/)
```

## Notes

- `.gitignore` exclut `vendor/`, `var/`, `.env.local`, `public/bundles/`.
- MariaDB est choisie volontairement (vs PostgreSQL pour le Catalogue Django) pour
  illustrer le **polyglottisme de persistance** d'une architecture micro-services.
