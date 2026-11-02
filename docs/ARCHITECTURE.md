# Architecture technique

## Modules

- **Auth & RBAC** : `UserRole`, `RoleMiddleware`, pivot `subsidiary_user`.
- **Référentiel groupe** : `GroupProfile`, `Subsidiary`.
- **Ventes** : `Client`, `Proforma`, `Invoice`.
- **Numérotation** : `DocumentSequence` + verrouillage transactionnel.
- **Caisse** : `CashRegister`, `CashSession`, `CashMovement`, `InvoicePayment`.
- **Stock** : `Product`, `StockItem`, `StockBatch`, `StockMovement`, `StockReservation`.
- **Commandes** : `Order`, `OrderLine`.
- **Alertes** : table `alerts` + `AlertService` + commande horaire.
- **Pré-comptabilité** : `AccountingAccount`, `AccountingJournal`, `JournalEntry`, `JournalEntryLine`.
- **Audit** : `ActivityLog`.

## Principes de cohérence

### Transactions
Les opérations critiques utilisent `DB::transaction()` et `lockForUpdate()` : séquences, conversion facture, réservations, livraisons, caisse.

### Documents définitifs
Les factures définitives sont append-only. Les paiements et écritures comptables sont des objets séparés.

### Multi-filiales
Un produit appartient à une seule filiale. Les documents opérationnels portent un `subsidiary_id`. Les utilisateurs non administrateurs reçoivent une liste explicite de filiales autorisées.

### Stocks périssables
Les réservations de Jus Sossoguinai sont enregistrées au niveau des lots via `stock_reservations`, ce qui permet de savoir quel lot est réservé par quelle ligne de commande.
