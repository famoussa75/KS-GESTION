# Paramétrage SYSCOHADA — note d’implémentation

L’application ne prétend pas remplacer le travail de validation d’un expert-comptable. Elle fournit une couche de pré-comptabilité conçue pour être paramétrée selon le SYSCOHADA révisé.

## Comptes initiaux fournis

- 411 — Clients
- 4431 — État, taxe facturée / collectée
- 521 — Banques
- 571 — Caisse
- 601 — Achats de marchandises
- 602 — Achats de matières premières et fournitures liées
- 624 — Entretien, réparations et maintenance
- 625 — Primes d’assurance
- 628 — Frais de télécommunications
- 6588 — Autres charges diverses
- 701 — Ventes de marchandises
- 702 — Ventes de produits finis
- 706 — Services vendus
- 758 — Produits divers

## Journaux initiaux

- VE — Ventes
- CA — Caisse
- OD — Opérations diverses
- ST — Stock

## Écritures automatiques

### Facture
- Débit 411 : total TTC
- Crédit 701 / 702 / 706 : base HT selon l’activité ou le compte du produit
- Crédit 4431 : taxe, si applicable

### Encaissement de facture en caisse
- Débit 571
- Crédit 411

### Décaissement justifié
- Débit du compte de charge choisi par l’utilisateur
- Crédit 571

## À compléter avant usage comptable légal

1. Validation du plan auxiliaire clients/fournisseurs.
2. Validation des taux et comptes fiscaux applicables en Guinée.
3. Méthode de valorisation des stocks et écritures de variation.
4. Gestion fournisseurs, achats, banques et rapprochements.
5. Immobilisations et amortissements.
6. Paie et charges sociales/fiscales.
7. Clôtures périodiques, lettrage et états financiers.
8. Politique de numérotation en cas d’avoirs et annulations.

La facture définitive n’est jamais modifiée : toute correction future doit être modélisée par un document correctif/avoir, à ajouter selon la politique comptable retenue.
