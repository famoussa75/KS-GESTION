<?php

namespace App\Services;

use App\Enums\CashDirection;
use App\Enums\DocumentType;
use App\Models\AccountingAccount;
use App\Models\CashMovement;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashService
{
    public function __construct(
        private NumberingService $numbering,
        private AccountingService $accounting,
        private AuditService $audit,
    ) {}

    public function open(CashRegister $register, float $openingFloat, User $user): CashSession
    {
        return DB::transaction(function () use ($register, $openingFloat, $user) {
            $register = CashRegister::query()->whereKey($register->id)->lockForUpdate()->with('subsidiary')->firstOrFail();
            $alreadyOpen = CashSession::where('cash_register_id', $register->id)->whereNull('closed_at')->exists();
            if ($alreadyOpen) {
                throw ValidationException::withMessages(['cash_register_id' => 'Cette caisse possède déjà une session ouverte.']);
            }

            $number = $this->numbering->next(DocumentType::CASH_SESSION, $register->subsidiary, now());
            $session = CashSession::create([
                'cash_register_id' => $register->id,
                'opened_by' => $user->id,
                'number' => $number,
                'opening_float' => $openingFloat,
                'opened_at' => now(),
            ]);
            $this->audit->log('cash.opened', $session, $register->subsidiary, ['opening_float' => $openingFloat]);
            return $session;
        });
    }

    public function addMovement(CashSession $session, CashDirection $direction, float $amount, ?AccountingAccount $expenseAccount, string $justification, ?string $reference, User $user): CashMovement
    {
        return DB::transaction(function () use ($session, $direction, $amount, $expenseAccount, $justification, $reference, $user) {
            $session = CashSession::query()->whereKey($session->id)->lockForUpdate()->with('register.subsidiary')->firstOrFail();
            if (! $session->isOpen()) {
                throw ValidationException::withMessages(['session' => 'Cette caisse est déjà clôturée.']);
            }
            if ($direction === CashDirection::OUT && ! $expenseAccount) {
                throw ValidationException::withMessages(['expense_account_id' => 'Sélectionnez un compte SYSCOHADA de charge pour une sortie.']);
            }

            $movement = CashMovement::create([
                'cash_session_id' => $session->id,
                'expense_account_id' => $expenseAccount?->id,
                'direction' => $direction->value,
                'amount' => $amount,
                'reference' => $reference,
                'justification' => $justification,
                'created_by' => $user->id,
                'occurred_at' => now(),
            ]);

            $this->accounting->postCashMovement($movement);
            $this->audit->log('cash.movement', $movement, $session->register->subsidiary, ['direction' => $direction->value, 'amount' => $amount]);
            return $movement;
        });
    }

    public function close(CashSession $session, float $actualClosing, ?string $note, User $user): CashSession
    {
        return DB::transaction(function () use ($session, $actualClosing, $note, $user) {
            $session = CashSession::query()->whereKey($session->id)->lockForUpdate()->with('register.subsidiary')->firstOrFail();
            if (! $session->isOpen()) {
                throw ValidationException::withMessages(['session' => 'Cette session est déjà clôturée.']);
            }

            $in = (float) $session->movements()->where('direction', CashDirection::IN->value)->sum('amount');
            $out = (float) $session->movements()->where('direction', CashDirection::OUT->value)->sum('amount');
            $theoretical = (float) $session->opening_float + $in - $out;
            $variance = $actualClosing - $theoretical;

            $session->update([
                'closed_by' => $user->id,
                'closed_at' => now(),
                'theoretical_closing' => round($theoretical, 2),
                'actual_closing' => round($actualClosing, 2),
                'variance' => round($variance, 2),
                'closing_note' => $note,
            ]);
            $this->audit->log('cash.closed', $session, $session->register->subsidiary, ['theoretical' => $theoretical, 'actual' => $actualClosing, 'variance' => $variance]);
            return $session;
        });
    }
}
