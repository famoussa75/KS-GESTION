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
use Illuminate\Support\Str;

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

        // Comptes de démonstration : mots de passe connus en local/test uniquement.
        // Ailleurs, un mot de passe aléatoire est généré et affiché une seule fois.
        $demo = app()->environment(['local', 'testing']);
        $password = function (string $known) use ($demo, &$generated, &$currentEmail) {
            if ($demo) {
                return $known;
            }
            $plain = Str::password(20);
            $generated[$currentEmail] = $plain;
            return $plain;
        };
        $generated = [];

        $make = function (string $email, string $name, UserRole $role, string $known) use ($password, &$currentEmail) {
            $currentEmail = $email;
            $user = User::where('email', $email)->first();
            if ($user) {
                $user->update(['name' => $name, 'role' => $role->value, 'active' => true]);
                return $user;
            }
            return User::create(['email' => $email, 'name' => $name, 'password' => $password($known), 'role' => $role->value, 'active' => true]);
        };

        $admin = $make('admin@ks.local', 'ALMAMY OUSMANE KOBÉLÉ KEITA', UserRole::ADMIN, 'KSAdmin@2026!');
        $cashier = $make('caissier@ks.local', 'Caissier KS', UserRole::CASHIER, 'Caisse@2026!');
        $stockkeeper = $make('magasinier@ks.local', 'Magasinier KS', UserRole::STOCKKEEPER, 'Stock@2026!');

        foreach ($generated as $email => $plain) {
            $this->command?->warn("Compte créé : {$email} / {$plain} (à noter et changer)");
        }

        $ids = $subsidiaries->pluck('id')->all();
        $admin->subsidiaries()->sync($ids);
        $cashier->subsidiaries()->sync($ids);
        $stockkeeper->subsidiaries()->sync($ids);
    }
}
