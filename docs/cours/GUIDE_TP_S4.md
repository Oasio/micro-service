# Guide du TP S4 — Catalog Service (FlexShop)

> Cadre complet de ce que tu dois faire pour le TP S4 et comment le faire.
> Source : `journee1_doc_eleve.pdf`, séance S4 (pages 42 → 52) + Devoir global (page 53).

---

## 1. C'est quoi ce TP ?

Tu joues un **développeur dans l'équipe FlexShop** (un e-commerce découpé en micro-services).
On te confie le **PREMIER service : le Catalogue de produits**.

Tu dois livrer une **API REST CRUD** complète sur une ressource `Product`, avec :
pagination, filtrage, recherche, documentation OpenAPI/Swagger, tests, et un dépôt Git propre.

**Stack imposée :** Python · Django · Django REST Framework (DRF) · drf-spectacular · django-filter.

---

## 2. La ressource `Product` à modéliser

| Champ | Type | Contraintes |
|---|---|---|
| `id` | auto | auto-généré |
| `name` | chaîne | **obligatoire**, 1 à 200 caractères |
| `description` | texte long | optionnel |
| `price` | décimal | **positif ou nul** (jamais `FloatField` !) |
| `stock` | entier | positif ou nul, défaut `0` |
| `category` | chaîne courte | optionnel |
| `created_at` | datetime | auto (`auto_now_add`) |
| `updated_at` | datetime | auto (`auto_now`) |

⚠️ **Règle éliminatoire** : le prix se stocke en `DecimalField` (ou `IntegerField` en centimes pour le bonus fintech). **JAMAIS `FloatField`** (arrondis binaires) → −2 pts.

---

## 3. Les endpoints attendus

Base : `/api/v1/products/`

| Méthode | URI | Rôle | Code succès |
|---|---|---|---|
| GET | `/api/v1/products/` | liste paginée | 200 |
| GET | `/api/v1/products/{id}/` | détail | 200 |
| POST | `/api/v1/products/` | créer | **201** |
| PUT | `/api/v1/products/{id}/` | remplacer | 200 |
| PATCH | `/api/v1/products/{id}/` | modifier partiellement | 200 |
| DELETE | `/api/v1/products/{id}/` | supprimer | **204** |

Filtres / recherche / tri :
- `GET /api/v1/products/?category=electronics` (filtre)
- `GET /api/v1/products/?search=casque` (recherche sur name + description)
- `GET /api/v1/products/?ordering=-price` (tri)

---

## 4. Les 10 étapes (ordre imposé)

| # | Étape | Fichier(s) clé |
|---|---|---|
| 1 | Init projet + venv + dépendances | `requirements.txt` |
| 2 | Configurer `settings.py` (apps, DRF, pagination, filtres) | `catalog_project/settings.py` |
| 3 | Modèle `Product` + `makemigrations` + `migrate` | `products/models.py` |
| 4 | Serializer `ProductSerializer` (+ validation prix) | `products/serializers.py` |
| 5 | `ProductViewSet` (ModelViewSet + filtres) | `products/views.py` |
| 6 | Router + URLs + routes Swagger | `products/urls.py`, `catalog_project/urls.py` |
| 7 | Lancer le serveur | `python manage.py runserver` |
| 8 | 3 tests unitaires minimum | `products/tests.py` |
| 9 | Collection Postman | `*.postman_collection.json` |
| 10 | README + `.gitignore` + Git | `README.md`, `.gitignore` |

> Mantra DRF : **modèle → makemigrations → migrate → serializer → view → URL.**
> `ModelViewSet` + `Router` = CRUD complet en <20 lignes utiles.

---

## 5. Conformité REST (3 pts) — à ne pas rater

- URIs **au pluriel** : `/products`, **jamais** `/product/42` → −2 pts.
- **Pas de verbes dans l'URI** : `POST /products`, jamais `/createProduct` → −2 pts.
- Bons **codes de statut** : 201 à la création, 204 au DELETE, 404 si absent, 400/422 si invalide.
- Toute collection **paginée dès le jour 1** (structure `{count, next, previous, results}`).
- Versioning par URI : `/api/v1/`.

---

## 6. Pièges classiques (cf. S4.3)

1. `ModuleNotFoundError` → venv pas activé.
2. `App 'products' isn't installed` → oubli dans `INSTALLED_APPS`.
3. Modèle changé sans `makemigrations` → toujours migrer après modif.
4. POST → 415 → dans Postman : Body → raw → **JSON**.
5. POST → 400 sans raison → **lire le body** de la réponse (DRF dit quel champ).
6. Filtrage KO → vérifier `django_filters` + `DjangoFilterBackend` + `filterset_fields`.
7. `db.sqlite3`/`venv/` poussés sur Git → vérifier `.gitignore` **avant** le premier `git add`.
8. `FloatField` pour le prix → faute éliminatoire.

---

## 7. Livrables (à rendre dans 7 jours)

- [ ] Lien vers le **dépôt Git**.
- [ ] **Capture d'écran** de Swagger UI fonctionnel (`/api/docs/`).
- [ ] **Collection Postman** exportée (`.json`).
- [ ] **Compte rendu PDF 2 pages**.

### Barème (sur 15)
| Critère | Pts |
|---|---|
| Code fonctionnel (tous les CRUD + pagination + filtrage) | 6 |
| Conformité REST (URIs, codes de statut) | 3 |
| Documentation OpenAPI (Swagger UI complet) | 3 |
| Tests unitaires (≥ 3 qui passent) | 2 |
| Qualité projet (README, .gitignore, requirements.txt) | 1 |

**Bonus (+2 max)** : endpoint custom `@action`, validation métier custom, prix en centimes (fintech).
**Malus** : `db.sqlite3`/`venv` committé −1 · URI verbe/singulier −2 · `FloatField` −2 · pas de README −1.

---

## 8. Devoir global de la journée (compte rendu 4-5 pages, séparé du code)

1. **Découpage en services** (S1+S2) : choisir un système (livraison de repas / banque en ligne / MOOC), proposer 5-8 services, justifier par capacité métier, identifier les bounded contexts, éviter les anti-patterns.
2. **Conception API REST** (S3) : sur UN service → endpoints (URI + verbe + code), payload JSON de création, stratégie pagination/filtrage/versioning.
3. **Retour sur le TP** (S4) : choix techniques, difficultés, pistes d'amélioration prod.
4. **Lien Git** du service Catalogue.

---

## 9. Comment je (Claude) vais procéder pour toi

1. ✅ Python installé + ajouté au PATH.
2. Créer le dossier `catalog-service/` avec un **venv**.
3. Installer Django + DRF + drf-spectacular + django-filter, geler `requirements.txt`.
4. Générer le projet `catalog_project` + l'app `products`.
5. Écrire : `settings.py`, `models.py`, `serializers.py`, `views.py`, les URLs.
6. `makemigrations` + `migrate`.
7. Écrire les **tests** et les faire passer (`python manage.py test`).
8. Lancer le serveur, vérifier `/api/v1/products/` et `/api/docs/`.
9. Créer la **collection Postman**, le **README**, le **.gitignore**.
10. `git init` + premier commit (push laissé à toi avec ton URL de repo).

> Le **compte rendu PDF** (section 8) reste à rédiger par toi — c'est une réflexion personnelle évaluée. Je peux t'en générer un brouillon si tu veux.
