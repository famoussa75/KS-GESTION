<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\CashRegister;
use App\Models\GroupProfile;
use App\Models\Subsidiary;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $group = GroupProfile::updateOrCreate(
            ['name' => 'KS Groupe'],
            [
                'rccm' => 'GN.TCC.2026.B.04080',
                'nif' => '811840461',
                'rib' => '004.000.1095512502.18',
                'address' => 'Jardin du 02 Octobre à Kaloum',
                'logo_path' => 'images/ks-logo.png',
                'icon_path' => 'images/ks-icon.png',
                'currency' => 'GNF',
            ]
        );

        $subsidiaries = collect([
            ['name' => 'KS Négoce', 'code' => 'KSN', 'slug' => 'ks-negoce', 'activity' => 'Commerce général', 'tracks_expiry' => false, 'default_shelf_life_months' => null],
            ['name' => 'KS Prestations', 'code' => 'KSP', 'slug' => 'ks-prestations', 'activity' => 'Prestations diverses', 'tracks_expiry' => false, 'default_shelf_life_months' => null],
            ['name' => 'Jus Sossoguinai', 'code' => 'JSS', 'slug' => 'jus-sossoguinai', 'activity' => 'Vente de jus naturels', 'tracks_expiry' => true, 'default_shelf_life_months' => 6],
        ])->map(fn ($data) => Subsidiary::updateOrCreate(
            ['code' => $data['code']],
            array_merge($data, ['group_profile_id' => $group->id, 'active' => true])
        ));

        $accounts = [
            ['code' => '411', 'label' => 'Clients', 'class' => 4, 'type' => 'asset'],
            ['code' => '4431', 'label' => 'État — TVA facturée / taxe collectée', 'class' => 4, 'type' => 'liability'],
            ['code' => '521', 'label' => 'Banques', 'class' => 5, 'type' => 'asset'],
            ['code' => '571', 'label' => 'Caisse', 'class' => 5, 'type' => 'asset'],
            ['code' => '601', 'label' => 'Achats de marchandises', 'class' => 6, 'type' => 'expense'],
            ['code' => '602', 'label' => 'Achats de matières premières et fournitures liées', 'class' => 6, 'type' => 'expense'],
            ['code' => '624', 'label' => 'Entretien, réparations et maintenance', 'class' => 6, 'type' => 'expense'],
            ['code' => '625', 'label' => 'Primes d’assurance', 'class' => 6, 'type' => 'expense'],
            ['code' => '628', 'label' => 'Frais de télécommunications', 'class' => 6, 'type' => 'expense'],
            ['code' => '6588', 'label' => 'Autres charges diverses', 'class' => 6, 'type' => 'expense'],
            ['code' => '701', 'label' => 'Ventes de marchandises', 'class' => 7, 'type' => 'revenue'],
            ['code' => '702', 'label' => 'Ventes de produits finis', 'class' => 7, 'type' => 'revenue'],
            ['code' => '706', 'label' => 'Services vendus', 'class' => 7, 'type' => 'revenue'],
            ['code' => '758', 'label' => 'Produits divers', 'class' => 7, 'type' => 'revenue'],
        ];
        foreach ($accounts as $account) {
            AccountingAccount::updateOrCreate(['code' => $account['code']], $account + ['active' => true]);
        }

        foreach ([
            ['code' => 'VE', 'label' => 'Journal des ventes'],
            ['code' => 'CA', 'label' => 'Journal de caisse'],
            ['code' => 'OD', 'label' => 'Opérations diverses'],
            ['code' => 'ST', 'label' => 'Journal de stock'],
        ] as $journal) {
            AccountingJournal::updateOrCreate(['code' => $journal['code']], $journal);
        }

        foreach ($subsidiaries as $subsidiary) {
            CashRegister::updateOrCreate(
                ['code' => 'CAI-'.$subsidiary->code],
                ['subsidiary_id' => $subsidiary->id, 'name' => 'Caisse '.$subsidiary->name, 'active' => true]
            );
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@ks.local'],
            ['name' => 'ALMAMY OUSMANE KOBÉLÉ KEITA', 'password' => Hash::make('KSAdmin@2026!'), 'role' => UserRole::ADMIN->value, 'active' => true]
        );
        $cashier = User::updateOrCreate(
            ['email' => 'caissier@ks.local'],
            ['name' => 'Caissier KS', 'password' => Hash::make('Caisse@2026!'), 'role' => UserRole::CASHIER->value, 'active' => true]
        );
        $stockkeeper = User::updateOrCreate(
            ['email' => 'magasinier@ks.local'],
            ['name' => 'Magasinier KS', 'password' => Hash::make('Stock@2026!'), 'role' => UserRole::STOCKKEEPER->value, 'active' => true]
        );

        $ids = $subsidiaries->pluck('id')->all();
        $admin->subsidiaries()->sync($ids);
        $cashier->subsidiaries()->sync($ids);
        $stockkeeper->subsidiaries()->sync($ids);
    }
}
