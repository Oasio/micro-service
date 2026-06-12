# Catalog Service

Premier micro-service du projet **FlexShop** (architecture micro-services).
Il expose une **API REST CRUD** sur la ressource `Product` (catalogue de produits).

Stack : **Python · Django · Django REST Framework · drf-spectacular · django-filter**.

## Démarrer

```bash
# Windows (PowerShell)
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
python manage.py migrate
python manage.py runserver

# Linux / macOS
python3 -m venv venv && source venv/bin/activate
pip install -r requirements.txt
python manage.py migrate
python manage.py runserver
```

## Endpoints

Base : `http://localhost:8000/api/v1/products/`

| Méthode | URI | Description | Code |
|---|---|---|---|
| GET | `/api/v1/products/` | Liste paginée | 200 |
| GET | `/api/v1/products/{id}/` | Détail | 200 |
| POST | `/api/v1/products/` | Créer | 201 |
| PUT | `/api/v1/products/{id}/` | Remplacer | 200 |
| PATCH | `/api/v1/products/{id}/` | Modifier partiellement | 200 |
| DELETE | `/api/v1/products/{id}/` | Supprimer | 204 |
| GET | `/api/v1/products/low-stock/` | **Bonus** : produits avec stock < 5 | 200 |

### Filtrage / recherche / tri
- `?category=electronics` — filtre par catégorie
- `?search=casque` — recherche sur `name` + `description`
- `?ordering=-price` — tri (préfixe `-` = décroissant), champs : `price`, `created_at`, `stock`

### Documentation
- **Swagger UI** : http://localhost:8000/api/docs/
- **Schéma OpenAPI** : http://localhost:8000/api/schema/ (aussi exporté dans `openapi-schema.yml`)

## Exemple de payload (création)

```json
{
  "name": "Casque sans fil XYZ",
  "description": "Casque bluetooth à réduction de bruit",
  "price": "89.90",
  "stock": 12,
  "category": "electronics"
}
```

## Tests

```bash
python manage.py test products
```

6 tests couvrent : liste, création, 404, prix négatif rejeté, suppression (204), recherche.

## Choix techniques

- **Prix en `DecimalField`** (`max_digits=10, decimal_places=2`) — jamais `FloatField`
  (arrondis binaires). L'alternative fintech serait un `IntegerField` en centimes.
- **Pagination** par défaut (`PageNumberPagination`, `PAGE_SIZE=10`) → `{count, next, previous, results}`.
- **Versioning** par URI (`/api/v1/`).
- Validation métier : prix ≥ 0 et nom non vide dans le serializer.
