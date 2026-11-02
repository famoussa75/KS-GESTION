# Document de cadrage — KS Gestion

## 1. Périmètre organisationnel

### KS Groupe
- RCCM : GN.TCC.2026.B.04080
- NIF : 811840461
- RIB : 004.000.1095512502.18
- Adresse : Jardin du 02 Octobre à Kaloum

### Filiales
1. KS Négoce — commerce général
2. KS Prestations — prestations diverses
3. Jus Sossoguinai — vente de jus naturels

### Utilisateurs
- ALMAMY OUSMANE KOBÉLÉ KEITA : accès total et vue consolidée.
- Caissier : caisse uniquement.
- Magasinier : stock uniquement (consultation, entrées par lot, ajustements et suivi des mouvements). Le catalogue et les paramètres de prix restent réservés à l’administrateur.

## 2. Facturation

### Proforma
- Numérotation automatique distincte.
- Création et modification tant qu’elle n’est pas convertie.
- Envoi au client par e-mail avec lien temporaire signé.
- Conversion en facture définitive en transaction, sans ressaisie.

### Facture définitive
- Numéro automatique, séquentiel par filiale et année.
- Aucun écran ni route de modification/suppression.
- Protection Eloquent + triggers MySQL.
- Conservation d’un snapshot de l’identité de la filiale, du groupe, du client et de chaque ligne.
- Affichage du logo du groupe et, s’il existe, du logo spécifique de la filiale.

## 3. Caisse

- Une caisse est créée par filiale.
- Une session ouverte par caisse au maximum.
- Fond initial saisi manuellement à l’ouverture.
- Chaque mouvement : facture liée **ou** motif obligatoire.
- Pour une sortie, sélection d’un compte de charge de la grille comptable.
- Une entrée liée à une facture crée un règlement séparé de la facture.
- Clôture : théorique = fond initial + entrées − sorties.
- Écart = comptage réel − théorique.
- Rapport de clôture imprimable.

## 4. Stock

- Stock isolé par filiale grâce au rattachement de chaque produit.
- Vue consolidée pour l’administrateur.
- Quantités : physique, réservée, disponible.
- Alerte automatique si disponible ≤ seuil.
- Historique intégral des mouvements.

### Jus Sossoguinai
- Entrée obligatoire par lot.
- Date de production obligatoire.
- Date de péremption calculée à +6 mois.
- Alerte péremption à 30 jours et après dépassement.
- Réservation FEFO : priorité aux lots expirant le plus tôt.

## 5. Commandes

Workflow strict :

`En attente → Validée → En préparation → Livrée`

- Création : aucun stock réservé.
- Validation : contrôle de disponibilité + réservation atomique.
- Préparation : stock toujours réservé.
- Livraison : déduction du physique + consommation de la réservation.
- Une insuffisance de stock annule la validation dans la transaction.

## 6. Comptabilité

- Référentiel initial structuré par comptes et journaux.
- Facture définitive : débit client / crédit ventes / crédit taxe le cas échéant.
- Encaissement facture : débit caisse / crédit client.
- Décaissement : débit compte de charge sélectionné / crédit caisse.
- Contrôle systématique de l’équilibre débit = crédit.

Le paramétrage comptable livré est une base technique ; le plan détaillé, les taux et règles de valorisation doivent être validés par le professionnel comptable du groupe.

## 7. Exigences non fonctionnelles

- Laravel 13 / PHP 8.3+.
- MySQL, encodage utf8mb4.
- Sessions et CSRF Laravel.
- Mots de passe hachés.
- Absence d’inscription publique.
- Contrôle d’accès par rôle et filiales autorisées.
- Journal d’audit.
- Interface responsive.
- Palette logo : bleu #2177B3, bleu profond #1C578D, graphite #404143.
