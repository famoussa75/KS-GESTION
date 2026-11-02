# Déploiement production

## 1. Variables d’environnement

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://...`
- compte MySQL dédié, avec le privilège `TRIGGER` pendant les migrations afin d’installer la protection DB des factures définitives
- SMTP réel
- sauvegarde quotidienne de la base et de `storage/app/public`

## 2. Installation

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize
```

Ne relancez pas le seeder en production après avoir changé les mots de passe initiaux sans l’avoir préalablement adapté : le seeder est idempotent mais contient les mots de passe de bootstrap.

## 3. Serveur web

Le DocumentRoot doit viser :

`/chemin/ks_gestion/public`

Les fichiers `.env`, `composer.json`, `app/` et `storage/` ne doivent jamais être exposés directement par le serveur web.

## 4. Permissions

Le compte du serveur web doit pouvoir écrire dans :

- `storage/`
- `bootstrap/cache/`

## 5. Scheduler

```cron
* * * * * cd /chemin/ks_gestion && php artisan schedule:run >> /dev/null 2>&1
```

## 6. Première mise en service

1. Se connecter avec le compte administrateur initial.
2. Changer immédiatement les trois mots de passe initiaux.
3. Vérifier les informations RCCM/NIF/RIB/adresse.
4. Charger les logos spécifiques des filiales si nécessaire.
5. Faire valider le plan SYSCOHADA par l’expert-comptable.
6. Configurer les taux de taxe des produits.
7. Créer les clients et produits réels.
8. Faire un test complet : proforma → facture → encaissement ; commande → réservation → livraison ; caisse → clôture.
