# API Facilyx Stock

La documentation du projet (architecture, démarrage, rôles et comptes) est dans le [README à la racine](../README.md).

Démarrage local de cette API :

```bash
cd backend
composer install
cp .env.example .env
php seed.php
php -S localhost:8888 -t api
```

Santé : `GET http://localhost:8888/health`
