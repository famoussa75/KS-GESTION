# KS Gestion — Laravel / MySQL

Application de gestion multi-filiales pour **KS Groupe**, développée sur **Laravel 13** avec **MySQL** et une interface Blade/CSS sans dépendance frontend externe.

## Filiales intégrées

- **KS Négoce (KSN)** — commerce général
- **KS Prestations (KSP)** — prestations diverses
- **Jus Sossoguinai (JSS)** — vente de jus naturels, avec lots, date de production et péremption automatique à +6 mois

Identité légale préchargée : RCCM `GN.TCC.2026.B.04080`, NIF `811840461`, RIB `004.000.1095512502.18`, adresse `Jardin du 02 Octobre à Kaloum`.

## Fonctionnalités livrées

- Authentification sans inscription publique.
- Gestion des droits : administrateur total, caissier limité à la caisse, magasinier limité strictement au stock.
- Vue consolidée groupe pour la direction.
- Clients partagés ou rattachés à une filiale.
- Factures proforma : création, modification, envoi par e-mail via lien signé, conversion en facture définitive sans ressaisie.
- Factures définitives : numérotation automatique `FAC-{FILIALE}-{ANNEE}-{SEQUENCE}`, snapshots des identités, impossibilité de mise à jour/suppression au niveau Eloquent **et** via triggers MySQL.
- Logo groupe systématique et logo propre à la filiale lorsqu’il existe.
- Caisse : fond initial, entrées/sorties, lien facture ou justification obligatoire, compte de charge pour les décaissements, clôture avec calcul automatique du théorique, du réel et de l’écart.
- Stock : physique, réservé, disponible, historique, seuil d’alerte automatique.
- Jus Sossoguinai : gestion des lots, péremption à 6 mois, alerte à 30 jours, allocation FEFO des lots lors des réservations/livraisons.
- Commandes : `En attente → Validée → En préparation → Livrée`; validation = réservation automatique; livraison = sortie physique.
- Pré-comptabilité : plan de comptes initial, journaux Ventes/Caisse/OD/Stock et journalisation automatique des factures et mouvements de caisse.
- Journal d’audit des actions structurantes.
- Liens clients signés et temporaires pour les documents.
- Interface responsive aux couleurs du logo KS : `#2177B3`, `#1C578D`, `#404143`.

## Prérequis

- PHP 8.3 ou supérieur
- Composer
- MySQL
- Extensions PHP usuelles Laravel : PDO, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON, Fileinfo

## Installation

```bash
cp .env.example .env
composer install
php artisan key:generate
```

Créez la base :

```sql
CREATE DATABASE ks_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Modifiez ensuite les variables `DB_*` du fichier `.env`, puis :

```bash
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Accès local : `http://127.0.0.1:8000`.

## Comptes initiaux

> À changer immédiatement après la première connexion en environnement réel.

| Profil | E-mail | Mot de passe initial |
|---|---|---|
| ALMAMY OUSMANE KOBÉLÉ KEITA — accès total | `admin@ks.local` | `KSAdmin@2026!` |
| Caissier | `caissier@ks.local` | `Caisse@2026!` |
| Magasinier | `magasinier@ks.local` | `Stock@2026!` |

Les trois comptes sont initialement autorisés sur les trois filiales, mais les modules restent filtrés par rôle.

## Envoi réel des proformas

Par défaut, `MAIL_MAILER=log`, donc les e-mails sont écrits dans `storage/logs/laravel.log`. Pour un envoi réel, configurez un serveur SMTP dans `.env`, par exemple :

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=facturation@example.com
MAIL_FROM_NAME="KS Groupe"
```

## Alertes automatiques

Les alertes sont recalculées à chaque mouvement de stock. Un contrôle périodique est également planifié toutes les heures :

```bash
php artisan schedule:run
```

En production, ajoutez au cron une exécution toutes les minutes du scheduler Laravel :

```cron
* * * * * cd /chemin/ks_gestion && php artisan schedule:run >> /dev/null 2>&1
```

## Règle d’immuabilité des factures

Une facture définitive n’a **aucune route de modification ni de suppression**. Elle est protégée par :

1. événements Eloquent bloquant `update` / `delete` ;
2. triggers MySQL bloquant toute modification/suppression directe des tables `invoices` et `invoice_lines` ;
3. snapshots de l’émetteur, du client, des identifiants légaux et des lignes au moment de la conversion.

Les paiements sont conservés dans une table séparée : le suivi du règlement ne modifie donc jamais la facture elle-même.

## SYSCOHADA

Le module fourni est une **base de pré-comptabilité SYSCOHADA-ready** : comptes, journaux et écritures en partie double. Les mappings fiscaux, taux de taxes, comptes auxiliaires, règles de valorisation de stock et états financiers doivent être validés et complétés avec l’expert-comptable du groupe avant mise en production comptable.

Consultez `docs/SYSCOHADA.md`.

## Arborescence utile

- `app/Services/InvoiceService.php` — conversion proforma → facture
- `app/Services/NumberingService.php` — séquences documentaires
- `app/Services/StockService.php` — stock, réservations, lots FEFO
- `app/Services/CashService.php` — ouverture, mouvements, clôture
- `app/Services/AccountingService.php` — écritures comptables automatiques
- `database/migrations/` — schéma MySQL + triggers d’immuabilité
- `database/seeders/DatabaseSeeder.php` — structure KS, utilisateurs, comptes
- `resources/views/` — template professionnel Blade
- `public/css/app.css` — charte issue du logo KS

## Tests de recette automatisés

Une suite PHPUnit couvre les règles structurantes : droits caissier/magasinier, immuabilité des factures, réservation de stock et calcul d’écart de caisse. Après `composer install` :

```bash
php artisan test
```

La matrice détaillée des exigences est disponible dans `docs/MATRICE_EXIGENCES.md`.

## Mise en production

Voir `docs/DEPLOIEMENT.md`. Le document root du serveur web doit pointer vers le dossier `public/` de Laravel.
