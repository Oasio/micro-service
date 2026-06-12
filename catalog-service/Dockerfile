# Service Catalogue (Django) — S8 étape 1
# Django 6.0 nécessite Python 3.12+
FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1

WORKDIR /app

# Dépendances Python (couche cachée tant que requirements.txt ne change pas).
# psycopg[binary] embarque libpq : pas besoin de paquets système supplémentaires.
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Code applicatif
COPY . .

EXPOSE 8000

# Applique les migrations puis lance le serveur de dev Django.
CMD ["sh", "-c", "python manage.py migrate --noinput && python manage.py runserver 0.0.0.0:8000"]
