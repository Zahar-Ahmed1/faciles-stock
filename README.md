# Facilyx Stock

Application de gestion de stock de consommables pour plusieurs sites clients. Chaque responsable suit le stock de son site. Facilyx (admin ou manager) propose une livraison. Le stock n’augmente que lorsque le responsable du site confirme l’ajout.

Les identifiants ci-dessous sont ceux du jeu de données local créé par `backend/seed.php`. Ils ne sont pas des comptes de production.

## Démarrage

Deux processus : l’API PHP et l’interface Angular.

```bash
# API — depuis backend/
cd backend
php -S localhost:8888 -t api
```

```bash
# Interface — depuis frontend/
cd frontend
npm install
npm start
```

- Interface : [http://localhost:4200](http://localhost:4200)
- API : [http://localhost:8888](http://localhost:8888)
- Santé de l’API : `GET http://localhost:8888/health`

Le frontend de développement appelle l’API sur le port **8888** (`frontend/src/environments/environment.development.ts`).

### Prérequis

- PHP 8.3+, extensions `pdo_mysql`, `mbstring`, `json`, `fileinfo`
- Composer
- MySQL 8
- Node.js (Angular 17)

### Base de données

1. Créer la base et les tables avec `backend/supabase/migrations/001_init_schema.sql`, puis `002`, `003` et `004`.
2. Copier `backend/.env.example` vers `backend/.env` et renseigner MySQL et `JWT_SECRET`.
3. Depuis `backend/` :

```bash
composer install
php seed.php
```

`seed.php` recharge clients, sites, utilisateurs, produits et stocks de démonstration. Le mot de passe de tous les comptes seed est `password`.

La table `delivery_proposals` est aussi créée au premier appel de l’API si la migration `004` n’a pas été jouée.

## Architecture

```text
Navigateur (Angular 17)
        │  HTTP + JWT
        ▼
API PHP 8.3  (backend/api/index.php)
        │
        ├─ Routes          backend/api/Routes/api.php
        ├─ Middleware      AuthMiddleware (JWT), RoleMiddleware
        ├─ Controllers     HTTP : validation, codes de réponse
        ├─ Services        règles métier (stock, alertes, auth)
        ├─ Repositories    SQL via PDO
        └─ Models          objets métier
                │
                ▼
            MySQL  facility_stock
```

Le point d’entrée `backend/api/index.php` charge Composer, le CORS et le routeur. Chaque route protégée passe par `$withAuth` : le JWT est vérifié, le rôle est contrôlé, et un responsable ne peut lire que son site.

### Frontend

| Dossier | Rôle |
|---|---|
| `frontend/src/app/pages/login` | Connexion |
| `frontend/src/app/pages/responsable` | Stock du site : recherche, filtres, retrait, CRUD produits, confirmation des livraisons |
| `frontend/src/app/pages/admin-deliveries` | Alertes de tous les sites et proposition de livraison |
| `frontend/src/app/pages/movements` | Historique des mouvements |
| `frontend/src/app/pages/admin-sites` | Liste des sites |
| `frontend/src/app/services` | Appels HTTP (`auth`, `stock`, `catalog`) |
| `frontend/src/app/guards` | Routes réservées à un rôle |

Routes :

| URL | Rôles |
|---|---|
| `/login` | visiteur |
| `/responsable` | `STOCK_MANAGER` |
| `/admin` | `ADMIN`, `MANAGER` |
| `/admin/historique` | `ADMIN`, `MANAGER` |
| `/admin/sites` | `ADMIN`, `MANAGER` |

### Backend

| Dossier | Rôle |
|---|---|
| `backend/api/Controllers` | Auth, clients, sites, catégories, produits, stocks, mouvements, alertes, propositions, utilisateurs |
| `backend/api/Services` | `AuthService`, `StockService`, `AlertService` |
| `backend/api/Repositories` | Accès MySQL |
| `backend/api/Middleware` | JWT et rôles |
| `backend/api/Models` | User, Client, Site, Product, Category, ClientStock, StockMovement, Alert, DeliveryProposal |
| `backend/supabase/migrations` | Schéma SQL |
| `backend/seed.php` | Données de démonstration |

### Données

```text
clients 1──* sites 1──* users
                 │
                 └──* stocks *──1 products *──1 product_categories
                        │
                        ├──* stock_movements
                        ├──* alerts
                        └──* delivery_proposals
```

Une ligne de `stocks` est unique par couple site + produit. La quantité ne peut pas être négative. Le seuil `minimum_quantity` déclenche une alerte `LOW_STOCK` ou `OUT_OF_STOCK`.

## Rôles

Trois rôles. L’écran d’accueil dépend du rôle après le login.

### Responsable — `STOCK_MANAGER`

Page `/responsable`, limitée à `users.site_id`.

- Voir, chercher et filtrer les produits du site (Vide, puis Stock bas, puis En stock ; plus grande quantité en haut).
- Créer, modifier et supprimer un produit de son site.
- Retirer une quantité. Si le stock passe sous le minimum, Facilyx reçoit une alerte.
- Confirmer ou refuser une proposition de livraison. Seule la confirmation augmente le stock.

### Admin Facilyx — `ADMIN`

Pages Livraisons, Historique et Sites, pour tous les sites.

- Voir les alertes et proposer une quantité. Cela n’écrit pas dans le stock.
- Consulter l’historique et les sites.
- Créer et modifier clients, sites et catégories.
- Supprimer un client, un site ou une catégorie.
- Créer, modifier et supprimer un utilisateur.

### Manager Facilyx — `MANAGER`

Mêmes écrans que l’admin, tous les sites.

- Proposer une livraison et consulter l’historique.
- Créer et modifier clients, sites et catégories.
- Ne peut pas supprimer un client, un site ou une catégorie.
- Ne peut pas créer, modifier ou supprimer un utilisateur.

Le CRUD produits n’est ouvert ni à l’admin ni au manager.

## Parcours de livraison

1. Le responsable retire du stock. Sous le seuil, une alerte `LOW_STOCK` ou `OUT_OF_STOCK` est ouverte.
2. L’admin ou le manager ouvre **Livraisons**, choisit une quantité et envoie une proposition (`POST /alerts/{id}/deliver`). Le stock ne change pas. Une proposition déjà en attente sur le même stock est mise à jour.
3. Le responsable du site voit **Livraisons à confirmer**.
4. **Confirmer l'ajout** incrémente le stock, écrit un mouvement `Livraison Facilyx confirmée…` et résout l’alerte. Si le stock reste bas, une nouvelle alerte est créée.
5. **Refuser** clôt la proposition. L’alerte reste ouverte.

## Comptes de démonstration

Mot de passe de tous les comptes : `password`.

### Responsables (un site chacun)

| Email | Nom | Site |
|---|---|---|
| stock.manager@example.com | Yassine Benali | Oracle Rabat HQ |
| sara.elamrani@example.com | Sara El Amrani | IAM Centre Ville |
| mourad.idrissi@awb.ma | Mourad El Idrissi | AWB Centre Technologique |

### Facilyx (tous les sites)

| Email | Nom | Rôle affiché | Accès en plus du manager |
|---|---|---|---|
| admin@example.com | Ahmed Admin | Admin Facilyx | Suppression clients / sites / catégories, gestion des utilisateurs |
| manager@example.com | Leila Manager | Manager Facilyx | — |

`manager@example.com` est rattaché en base à Oracle Rabat HQ, mais l’écran et l’API lui ouvrent tous les sites, comme à l’admin.

### Sites avec du stock, sans compte responsable

Ces sites apparaissent dans Livraisons et Sites. Personne ne peut confirmer une proposition ni gérer leurs produits tant qu’un `STOCK_MANAGER` ne leur est pas assigné.

| Site | Ville | Compte lié (pas un responsable) |
|---|---|---|
| Oracle Casablanca Office | Casablanca | aucun |
| IAM Agadir | Agadir | karim.tazi@example.com (`MANAGER`) |
| Attijariwafa Bank Siège | Casablanca | salma.bennani@awb.ma (`MANAGER`) |

Karim et Salma ont le rôle `MANAGER` : ils arrivent sur les écrans Facilyx, pas sur la page stock d’un site.

### Clients et sites seed

| Client | Site |
|---|---|
| Oracle | Oracle Rabat HQ, Oracle Casablanca Office |
| Maroc Telecom (IAM) | IAM Centre Ville, IAM Agadir |
| Attijariwafa Bank | Attijariwafa Bank Siège, AWB Centre Technologique |

Chaque site a les mêmes familles de consommables (eau, épicerie, papeterie, hygiène, nettoyage), avec des quantités et un seuil minimum différents.

## API principale

Toutes les réponses sont du JSON `{ "success": true, "data": ... }` ou `{ "success": false, "error": { "code", "message" } }`.

Le login `POST /auth/login` avec `{ "email", "password" }` renvoie un JWT. Les autres routes envoient `Authorization: Bearer <token>`.

| Méthode | Route | Qui |
|---|---|---|
| POST | `/auth/login` | public |
| GET | `/auth/me` | connecté |
| GET | `/alerts` | tous les rôles (le responsable est filtré sur son site) |
| POST | `/alerts/{id}/deliver` | admin, manager — crée une proposition |
| GET | `/delivery-proposals` | tous ; le responsable ne voit que son site |
| POST | `/delivery-proposals/{id}/confirm` | responsable de ce site |
| POST | `/delivery-proposals/{id}/reject` | responsable de ce site |
| POST / PUT / DELETE | `/products` | responsable, et seulement les produits de son site |
| GET | `/stocks/site/{siteId}` | tous, avec contrôle du site pour le responsable |
| POST | `/stocks/{id}/decrement` et `/decrement/{quantity}` | retrait |
| GET | `/stock-movements` | historique |
| DELETE | `/clients/{id}`, `/sites/{id}`, `/categories/{id}` | admin |
| POST / PUT / DELETE | `/users` | admin |

## Tri de la page responsable

Sans changer le filtre, l’ordre est :

1. Vide (`OUT_OF_STOCK`)
2. Stock bas (`LOW_STOCK`)
3. En stock (`NORMAL`)

Dans chaque groupe, la plus grande quantité est en haut. La recherche ignore les accents (`cafe` trouve `Café`).

## Hébergement Hostinger

Hostinger (Apache + PHP + MySQL) sert un seul dossier web, `public_html`. Le build Angular y va. Le PHP, `vendor` et `.env` restent **à côté** de `public_html`, sous le nom `facilty-api`, pour ne pas être téléchargeables.

```text
domains/mondomaine.com/
├── public_html/          contenu de frontend/dist + deploy/public_html
│   ├── index.html
│   ├── *.js, *.css
│   ├── .htaccess
│   └── api/
│       ├── index.php
│       ├── backend-path.php
│       └── .htaccess
└── facilty-api/          copie du dossier backend/
    ├── .env
    ├── vendor/
    ├── api/
    └── supabase/
```

Dans hPanel : PHP **8.3**, base MySQL créée, puis importer les migrations `001` à `004` et lancer le seed une fois (ou importer un dump).

`facilty-api/.env` :

```text
DB_HOST=localhost
DB_PORT=3306
DB_NAME=...        nom donné par Hostinger
DB_USERNAME=...
DB_PASSWORD=...
JWT_SECRET=une-longue-chaine-aleatoire
APP_ENV=production
APP_URL=https://mondomaine.com
CORS_ALLOWED_ORIGINS=https://mondomaine.com
```

Le build de production appelle l’API en `/api` (même domaine), pas `localhost:8888`.

```bash
cd frontend
npm run build
```

Copier le contenu de `frontend/dist/facility-stock-frontend/` dans `public_html/`, puis copier aussi `deploy/public_html/.htaccess` et `deploy/public_html/api/`.

### Test local du mode Hostinger

Ce serveur imite Apache : le site et `/api` sont sur le même port.

```bash
cd frontend && npm run build
cd ..
php -S localhost:8899 -t frontend/dist/facility-stock-frontend deploy/router.php
```

Ouvrir [http://localhost:8899](http://localhost:8899). Les comptes seed restent ceux du README. Ce test utilise le `.env` local du dossier `backend/`.

## CI/CD

Le dépôt est [github.com/Zahar-Ahmed1/faciles-stock](https://github.com/Zahar-Ahmed1/faciles-stock). Deux branches :

| Branche | Rôle |
|---|---|
| `develop` | Modifications du jour. Le contrôle CI vérifie Angular et le PHP. Le serveur ne change pas. |
| `prod` | Version en ligne. Un envoi sur cette branche lance le déploiement vers Hostinger. |

Les workflows sont dans `.github/workflows/`. Ils ne peuvent pas modifier le dépôt. Ils ne remplacent pas `facilty-api`, `.env`, `ONPITCH` ni `ONPITCHTEST`.

### Préparer l'accès FTP, une seule fois

1. Dans hPanel : **Fichiers → Comptes FTP → Créer un compte FTP**.
2. Répertoire : `public_html`. Notez le hostname FTP, l'utilisateur complet et le mot de passe. Le hostname n'est pas l'adresse du gestionnaire de fichiers.
3. Sur GitHub : **Settings → Secrets and variables → Actions → New repository secret**.

| Secret | Valeur |
|---|---|
| `FTP_SERVER` | hostname ou IP FTP, sans `ftp://` |
| `FTP_USERNAME` | utilisateur complet du compte FTP |
| `FTP_PASSWORD` | mot de passe choisi dans hPanel |
| `FTP_SERVER_DIR` | `./` si le compte s'ouvre déjà dans `public_html` |

### Mettre la dernière version sur le serveur

Depuis le dossier du projet, sur la branche `develop` :

```bash
git add .
git commit -m "Décrire la modification."
git push origin develop
```

Attendez que **CI** soit vert dans l'onglet [Actions](https://github.com/Zahar-Ahmed1/faciles-stock/actions). Puis envoyez cette version sur `prod` :

```bash
git push origin develop:prod
```

GitHub compile l'interface et copie `index.html`, les fichiers `.js` et `.css`, `assets`, `.htaccess` et le pont `api/` dans `public_html`.

Ouvrez **Actions**. La ligne **CD** doit être sur la branche `prod` et finir en vert. Rechargez le site.

Pour relancer le même déploiement sans nouveau commit : **Actions → CD → Run workflow**, branche `prod`.
