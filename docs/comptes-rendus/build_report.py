# -*- coding: utf-8 -*-
"""Génère le compte rendu (Devoir global de la Journée 1) en PDF.
Sections 1 à 3 du devoir. La Section 4 (Lien Git) est volontairement exclue.
Thème classique (Times New Roman, noir et blanc).
"""
from fpdf import FPDF

BLACK = (0, 0, 0)
GREY = (90, 90, 90)
CODEBG = (245, 245, 245)

F = "TimesNR"
FC = "Mono"


class PDF(FPDF):
    def header(self):
        if self.page_no() == 1:
            return
        self.set_font(F, "I", 8)
        self.set_text_color(*GREY)
        self.cell(0, 6, "Architecture Micro-services, Devoir Journée 1", align="L")
        self.cell(0, 6, "FlexShop / Catalog Service", align="R")
        self.ln(8)
        self.set_text_color(*BLACK)

    def footer(self):
        self.set_y(-12)
        self.set_font(F, "I", 8)
        self.set_text_color(*GREY)
        self.cell(0, 6, f"Page {self.page_no()}", align="C")
        self.set_text_color(*BLACK)


pdf = PDF(format="A4")
pdf.set_auto_page_break(True, margin=16)
pdf.add_font(F, "", r"C:/Windows/Fonts/times.ttf")
pdf.add_font(F, "B", r"C:/Windows/Fonts/timesbd.ttf")
pdf.add_font(F, "I", r"C:/Windows/Fonts/timesi.ttf")
pdf.add_font(FC, "", r"C:/Windows/Fonts/cour.ttf")
pdf.set_margins(18, 16, 18)
pdf.add_page()
EPW = pdf.epw  # effective page width


def h1(txt):
    pdf.ln(2)
    pdf.set_font(F, "B", 15)
    pdf.set_text_color(*BLACK)
    pdf.multi_cell(0, 7, txt, new_x="LMARGIN", new_y="NEXT")
    pdf.set_draw_color(*BLACK)
    pdf.set_line_width(0.4)
    y = pdf.get_y() + 1
    pdf.line(pdf.l_margin, y, pdf.l_margin + EPW, y)
    pdf.ln(3)


def h2(txt):
    pdf.ln(1.5)
    pdf.set_font(F, "B", 12)
    pdf.set_text_color(*BLACK)
    pdf.multi_cell(0, 6, txt, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(1)


def para(txt):
    pdf.set_font(F, "", 10.5)
    pdf.set_text_color(*BLACK)
    pdf.multi_cell(0, 5.2, txt, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(1.2)


def bullets(items):
    pdf.set_font(F, "", 10.5)
    pdf.set_text_color(*BLACK)
    for it in items:
        x = pdf.get_x()
        pdf.set_x(x + 3)
        pdf.cell(4, 5.2, "•")
        pdf.multi_cell(EPW - 7, 5.2, it, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(1.2)


def code(lines):
    pdf.ln(0.5)
    pdf.set_font(FC, "", 8.5)
    pdf.set_fill_color(*CODEBG)
    pdf.set_text_color(30, 30, 30)
    for ln in lines:
        pdf.cell(0, 4.6, "  " + ln, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.set_text_color(*BLACK)
    pdf.ln(1.5)


def table(headers, rows, widths):
    # scale widths to EPW
    total = sum(widths)
    widths = [w / total * EPW for w in widths]
    line_h = 5
    pdf.set_draw_color(*BLACK)
    pdf.set_line_width(0.4)
    y = pdf.get_y()
    pdf.line(pdf.l_margin, y, pdf.l_margin + EPW, y)
    pdf.set_font(F, "B", 9)
    pdf.set_text_color(*BLACK)
    for i, head in enumerate(headers):
        pdf.cell(widths[i], 6, head, border=0, align="L")
    pdf.ln(6)
    y = pdf.get_y()
    pdf.line(pdf.l_margin, y, pdf.l_margin + EPW, y)
    for row in rows:
        # compute needed height
        pdf.set_font(F, "", 9)
        heights = []
        for i, cellv in enumerate(row):
            n = pdf.multi_cell(widths[i], line_h, cellv, dry_run=True, output="LINES")
            heights.append(len(n))
        rh = max(heights) * line_h
        if pdf.get_y() + rh > pdf.page_break_trigger:
            pdf.add_page()
        x0 = pdf.get_x()
        y0 = pdf.get_y()
        for i, cellv in enumerate(row):
            x = pdf.get_x()
            y = pdf.get_y()
            pdf.multi_cell(widths[i], line_h, cellv, border=0,
                           align="L", max_line_height=line_h)
            pdf.set_xy(x + widths[i], y)
        pdf.set_xy(x0, y0 + rh)
        # thin separator
        pdf.set_draw_color(200, 200, 200)
        pdf.set_line_width(0.2)
        pdf.line(x0, pdf.get_y(), x0 + EPW, pdf.get_y())
    # bottom rule
    pdf.set_draw_color(*BLACK)
    pdf.set_line_width(0.4)
    pdf.line(pdf.l_margin, pdf.get_y(), pdf.l_margin + EPW, pdf.get_y())
    pdf.ln(2)


# ============================================================
# EN-TÊTE / TITRE
# ============================================================
pdf.set_font(F, "B", 19)
pdf.set_text_color(*BLACK)
pdf.multi_cell(0, 8, "Devoir global de la Journée 1", new_x="LMARGIN", new_y="NEXT", align="C")
pdf.set_font(F, "B", 13)
pdf.multi_cell(0, 7, "Architecture Micro-services", new_x="LMARGIN", new_y="NEXT", align="C")
pdf.ln(1)
pdf.set_font(F, "I", 10.5)
pdf.set_text_color(*GREY)
pdf.multi_cell(0, 5.2, "Découpage en services · Conception d'API REST · Retour sur le TP",
               new_x="LMARGIN", new_y="NEXT", align="C")
pdf.ln(2)
pdf.set_draw_color(*BLACK)
pdf.set_line_width(0.4)
pdf.line(pdf.l_margin, pdf.get_y(), pdf.l_margin + EPW, pdf.get_y())
pdf.ln(3)
pdf.set_font(F, "", 10)
pdf.set_text_color(*BLACK)
pdf.multi_cell(0, 5, "Nom / Prénom : ______________________________     "
                     "Classe : S6     Date : juin 2026", new_x="LMARGIN", new_y="NEXT")
pdf.ln(1)
pdf.set_font(F, "I", 9.5)
pdf.set_text_color(*GREY)
pdf.multi_cell(0, 4.6, "Le système étudié dans les Sections 1 et 2 est une plateforme de "
                      "livraison de repas (type Uber Eats / Deliveroo). Le service implémenté "
                      "au TP (Section 3) est le service Catalogue de FlexShop.",
               new_x="LMARGIN", new_y="NEXT")
pdf.set_text_color(*BLACK)
pdf.ln(2)

# ============================================================
# SECTION 1
# ============================================================
h1("Section 1. Découpage en services")
para("Le système retenu est une plateforme de livraison de repas à domicile. Le DOMAINE "
     "consiste à mettre en relation trois acteurs (clients, restaurants et livreurs) "
     "pour commander un repas, le payer, le faire préparer puis le livrer. Conformément "
     "à la règle d'or de la S2, on découpe par CAPACITÉ MÉTIER (ce que le métier fait), "
     "jamais par couche technique.")

h2("Proposition de découpage en 6 services")
table(
    ["Service", "Capacité métier", "Données possédées"],
    [
        ["1. Comptes & Identité", "Inscrire, authentifier et gérer les profils "
         "(clients, restaurateurs, livreurs) et les droits d'accès.",
         "Utilisateurs, rôles, adresses, jetons."],
        ["2. Restaurants & Menus", "Présenter l'offre : fiches restaurants, menus, "
         "plats, prix, disponibilités et horaires d'ouverture.",
         "Restaurants, menus, plats, catégories."],
        ["3. Commandes", "Gérer le cycle de vie d'une commande : panier, validation, "
         "suivi des états (créée → confirmée → préparée → livrée).",
         "Commandes, lignes de commande, états."],
        ["4. Paiement", "Encaisser, autoriser et rembourser les transactions. Isolé "
         "par criticité (audit, sécurité, conformité PCI-DSS).",
         "Transactions, remboursements (réfs)."],
        ["5. Livraison", "Affecter un livreur, suivre la position GPS, estimer le "
         "temps d'arrivée (ETA) et clôturer la course.",
         "Courses, affectations, positions, ETA."],
        ["6. Notifications", "Informer chaque acteur en temps réel : push, SMS, email "
         "transactionnels (commande confirmée, livreur en route...).",
         "Modèles de messages, journaux d'envoi."],
    ],
    [22, 46, 28],
)

h2("Bounded contexts (DDD)")
para("Chaque service correspond à un bounded context, c'est-à-dire une frontière à "
     "l'intérieur de laquelle un mot a UN sens précis. L'exemple le plus parlant ici est "
     "le mot « Commande », qui n'a pas le même modèle selon le contexte :")
bullets([
    "Dans le contexte COMMANDES (vente), une commande regroupe les plats choisis, le prix, "
    "le client et le mode de paiement. États : panier → confirmée → payée.",
    "Dans le contexte LIVRAISON (logistique), une « commande » est une course avec une "
    "adresse, une position de livreur, un ETA et un numéro de suivi. États : affectée → "
    "récupérée → livrée.",
    "MÊME MOT, deux modèles distincts. Le DDD valide d'avoir deux bounded contexts. "
    "Chaque équipe parle son ubiquitous language (ex. « course » côté Livraison).",
])
para("D'autres frontières sont nettes. Un « Restaurant » côté Restaurants & Menus "
     "(offre, plats) n'est pas le même objet que côté Paiement (un bénéficiaire de "
     "versement). Le mot « Client » appartient au contexte Comptes & Identité. "
     "Les autres services n'en gardent qu'une référence (l'identifiant).")

h2("Anti-patterns évités (contrôle S2)")
bullets([
    "Pas de découpage par couche technique. Aucun « service UI » ni « service base de "
    "données ». Chaque service est une tranche métier verticale.",
    "Pas de shared database. Chaque service possède sa propre base. Les autres passent "
    "par l'API, jamais par la table directement.",
    "Pas de distributed monolith ni de chatty service. La confirmation d'une commande "
    "n'appelle pas 6 services en synchrone. Le service Commandes publie un événement "
    "« CommandeConfirmée » que Livraison et Notifications consomment (cohérence à "
    "terme, eventual consistency).",
    "Pas de god service. Aucun service central ne pilote tous les autres, pas de hub unique.",
    "Pas d'entity service. On a un service « Commandes » (capacité), pas un service "
    "par table SQL.",
    "Paiement isolé par criticité, un choix volontaire pour la sécurité et l'audit.",
])

# ============================================================
# SECTION 2
# ============================================================
pdf.add_page()
h1("Section 2. Conception de l'API REST")
para("Le service retenu est le service Commandes, le cœur transactionnel de la "
     "plateforme. L'API suit le style REST (ressources au pluriel, verbes HTTP, codes "
     "de statut explicites) et est versionnée par URI (/api/v1/).")

h2("Endpoints REST (URI + verbe + code de retour)")
table(
    ["Verbe", "URI", "Action", "Code"],
    [
        ["GET", "/api/v1/orders/", "Liste paginée des commandes (filtrable).", "200"],
        ["POST", "/api/v1/orders/", "Créer une commande.", "201"],
        ["GET", "/api/v1/orders/{id}/", "Détail d'une commande.", "200 / 404"],
        ["PATCH", "/api/v1/orders/{id}/", "Modifier partiellement (ex. adresse).", "200"],
        ["GET", "/api/v1/orders/{id}/items/", "Lignes (plats) de la commande.", "200"],
        ["POST", "/api/v1/orders/{id}/cancellation/", "Annuler en créant une ressource "
         "d'annulation plutôt qu'un verbe /cancelOrder.", "201 / 409"],
        ["GET", "/api/v1/orders/{id}/tracking/", "État de suivi (délégué à Livraison).", "200"],
    ],
    [12, 40, 40, 14],
)
para("Par choix de modélisation, il n'y a pas de DELETE sur une commande. Une commande "
     "payée est un fait comptable. On ne la supprime pas, on l'ANNULE (changement d'état "
     "via la ressource cancellation). On pense ressources et états, pas actions.")

h2("Exemple de payload JSON (création, POST /api/v1/orders/)")
code([
    "{",
    '  "customer_id": 42,',
    '  "restaurant_id": 7,',
    '  "delivery_address": "12 rue des Lilas, 75011 Paris",',
    '  "items": [',
    '    { "dish_id": 101, "quantity": 2 },',
    '    { "dish_id": 205, "quantity": 1 }',
    '  ],',
    '  "payment_method": "card"',
    "}",
])
para("La réponse 201 Created contient la commande créée avec son id, son état initial "
     "(« created »), le total calculé côté serveur (jamais envoyé par le client) "
     "et les horodatages created_at / updated_at.")

h2("Pagination, filtrage, tri, versioning")
bullets([
    "Le versioning se fait par URI (/api/v1/...). C'est la stratégie la plus lisible et "
    "debuggable. L'ancienne version est maintenue 6 à 12 mois en cas de v2.",
    "La pagination utilise PageNumberPagination (?page=2&page_size=20) avec la réponse "
    "standard { count, next, previous, results }. Pour de gros volumes (historique), on "
    "basculerait sur une pagination cursor-based (performance constante).",
    "Filtrage par paramètres : ?status=confirmed, ?restaurant=7, ?customer=42.",
    "Le tri se fait avec ?ordering=-created_at (le préfixe - signifie décroissant).",
    "Les erreurs structurées suivent le format RFC 7807 (application/problem+json) avec "
    "title, detail et le détail des champs invalides. Jamais de stack trace en production.",
])
para("En cas d'erreur de validation, l'API renvoie par exemple un 422 avec un corps "
     "application/problem+json indiquant que le champ « items » ne peut pas être "
     "vide, avec un code de statut 4xx (et non 200).")

# ============================================================
# SECTION 3
# ============================================================
pdf.add_page()
h1("Section 3. Retour sur le TP (service Catalogue)")
para("Le TP de la S4 a consisté à coder le PREMIER micro-service de FlexShop, le service "
     "Catalogue, une API REST CRUD sur la ressource Product, avec Django + Django REST "
     "Framework, drf-spectacular (OpenAPI) et django-filter.")

h2("Choix techniques notables")
bullets([
    "Montants en DecimalField (max_digits=10, decimal_places=2) et JAMAIS FloatField "
    "(les arrondis binaires sont une faute éliminatoire). On stocke 89.90 directement, "
    "ce qui reste lisible dans le JSON. L'alternative fintech (Stripe/PayPal) serait un "
    "IntegerField en centimes (8990).",
    "Validation métier dans le serializer avec validate_price (prix >= 0) et "
    "validate_name (nom non vide). DRF renvoie alors un 400 précis sur le champ fautif.",
    "ModelViewSet + DefaultRouter donnent tout le CRUD (list, retrieve, create, update, "
    "partial_update, destroy) en moins de 20 lignes utiles. C'est la puissance de DRF.",
    "Filtrage, recherche et tri activés via les trois backends : DjangoFilterBackend "
    "(?category=), SearchFilter (?search= sur name + description), OrderingFilter "
    "(?ordering=-price).",
    "Pagination par défaut avec PageNumberPagination et PAGE_SIZE = 10, soit une réponse "
    "{ count, next, previous, results }.",
    "Versioning par URI (/api/v1/products/).",
    "Documentation OpenAPI 3 générée automatiquement par drf-spectacular (Swagger UI sur "
    "/api/docs/, schéma exporté dans openapi-schema.yml), avec une collection Postman "
    "fournie.",
    "En bonus, un endpoint custom @action GET /api/v1/products/low-stock/ liste les "
    "produits dont le stock est < 5 (réapprovisionnement à prévoir).",
])

h2("Difficultés rencontrées et solutions")
table(
    ["Difficulté", "Solution"],
    [
        ["ModuleNotFoundError au lancement.",
         "venv non activé. Réactiver (venv\\Scripts\\activate sous Windows) et vérifier "
         "le prompt (venv)."],
        ["Erreur « App 'products' isn't installed ».",
         "Ajouter 'products' dans INSTALLED_APPS."],
        ["Changements du modèle non pris en compte.",
         "Après toute modif de models.py, lancer makemigrations puis migrate."],
        ["POST renvoyait 415 Unsupported Media Type.",
         "Dans Postman, régler Body → raw → JSON (et non Text)."],
        ["POST renvoyait 400 sans raison claire.",
         "Lire le corps de la réponse. DRF indique précisément le champ en erreur."],
        ["Risque de committer db.sqlite3 et venv/.",
         "Écrire le .gitignore AVANT le premier ajout (venv/, __pycache__/, *.pyc, "
         "db.sqlite3, .env)."],
    ],
    [38, 62],
)

h2("Ce qui resterait à améliorer pour aller en production")
bullets([
    "Base de données. Remplacer SQLite par PostgreSQL, sortir SECRET_KEY et DEBUG dans "
    "des variables d'environnement (.env) et passer DEBUG=False.",
    "Sécurité. Authentification et autorisation (JWT), permissions par rôle, CORS "
    "restreint, rate limiting.",
    "Robustesse. Erreurs au format RFC 7807, gestion fine des conflits (409), "
    "healthcheck (/health) pour l'orchestrateur.",
    "Performance. Pagination cursor-based pour un très grand catalogue, index sur les "
    "champs filtrés et recherchés.",
    "Exploitation (DevOps). Conteneurisation Docker, pipeline CI/CD (tests + lint "
    "automatiques), observabilité (logs structurés, métriques, tracing distribué).",
    "Tests. Étendre la couverture (PUT/PATCH, filtres, pagination, cas limites) au-delà "
    "des 6 tests actuels.",
])

pdf.ln(1)
pdf.set_font(F, "I", 8.5)
pdf.set_text_color(*GREY)
pdf.multi_cell(0, 4.6, "La Section 4 (Lien Git) n'est pas incluse dans ce rendu, "
                      "conformément à la consigne.", new_x="LMARGIN", new_y="NEXT")

out = r"C:/Users/oasio/Desktop/S6/microservice/J1/Devoir_J1_compte_rendu.pdf"
pdf.output(out)
print("OK ->", out, "-", pdf.page_no(), "pages")
