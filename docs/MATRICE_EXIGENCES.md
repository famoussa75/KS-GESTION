# Matrice de couverture fonctionnelle

| Exigence | Implémentation | Fichiers principaux |
|---|---|---|
| 3 filiales séparées | Filiales préchargées avec code, activité, paramètres de stock et logo optionnel | `DatabaseSeeder.php`, `Subsidiary.php` |
| Identité RCCM / NIF / RIB / adresse | Profil groupe préchargé et administrable | `group_profiles`, `SettingsController.php` |
| Administrateur total | Rôle `admin`, navigation et routes complètes | `UserRole.php`, `RoleMiddleware.php`, `routes/web.php` |
| Caissier — caisse seulement | Middleware de rôle + filtrage des filiales autorisées | `CashController.php`, `SubsidiaryAccess.php` |
| Magasinier — stock seulement | Accès stock/lots/mouvements, sans prix/catalogue/ventes/caisse | `StockController.php`, `routes/web.php` |
| Proforma → facture sans ressaisie | Copie transactionnelle des données/lignes vers un snapshot de facture | `InvoiceService.php` |
| Envoi proforma au client | E-mail + URL signée temporaire | `ProformaController.php`, `ProformaMail.php` |
| Facture séquentielle automatique | Compteur par filiale / type / année sous verrou DB | `NumberingService.php`, `document_sequences` |
| Facture non modifiable / non supprimable | Absence de routes d’édition + événements Eloquent + triggers MySQL | `Invoice.php`, `InvoiceLine.php`, migration `000002` |
| Logo groupe + logo filiale | Logo groupe systématique ; logo filiale optionnel chargé par l’admin | vues `_document.blade.php`, `SubsidiaryController.php` |
| Ouverture caisse avec fond manuel | Session de caisse avec `opening_float` obligatoire | `CashService.php` |
| Clôture / écart automatique | Théorique = fond + entrées − sorties ; écart = réel − théorique | `CashService.php` |
| Mouvement de caisse justifié | Facture liée ou justification ; sortie rattachée à un compte de charge | `CashService.php`, `CashController.php` |
| Stock par filiale | Chaque produit appartient à une filiale, stock associé | `products`, `stock_items` |
| Stock consolidé direction | Vue groupe de l’administrateur | `DashboardController.php`, `StockController.php` |
| Alerte stock bas | Seuil configurable par produit, alerte recalculée après mouvement et périodiquement | `AlertService.php`, `stock:check-alerts` |
| Sossoguinai : production / péremption 6 mois | Lots avec calcul automatique `date production + 6 mois` | `StockService.php`, `stock_batches` |
| Commande en 4 statuts | `pending → validated → preparation → delivered` | `OrderStatus.php`, `OrderController.php` |
| Validation réserve le stock | Réservation transactionnelle ; stock physique inchangé jusqu’à livraison | `StockService.php`, `stock_reservations` |
| Livraison consomme la réservation | Déduction du physique et passage des réservations à `consumed` | `StockService.php` |
| Pré-comptabilité SYSCOHADA | Comptes paramétrables, journaux, partie double, génération facture/caisse | `AccountingService.php`, tables `accounting_*` / `journal_*` |
| Audit | Journal des actions sensibles | `AuditService.php`, `activity_logs` |
| Style KS | Palette extraite du logo, sidebar responsive, cartes, documents A4 | `public/css/app.css` |
