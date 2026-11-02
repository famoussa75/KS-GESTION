<?php

namespace App\Http\Controllers;

use App\Enums\CashDirection;
use App\Models\AccountingAccount;
use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Services\CashService;
use App\Services\SubsidiaryAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashController extends Controller
{
    public function index(Request $request, SubsidiaryAccess $access): View
    {
        $subsidiaries = $access->list($request->user());
        $ids = $subsidiaries->pluck('id');
        $registers = CashRegister::with(['subsidiary', 'sessions' => fn ($q) => $q->latest('opened_at')->limit(5)])
            ->whereIn('subsidiary_id', $ids)->where('active', true)->orderBy('name')->get();
        $recentSessions = CashSession::with('register.subsidiary', 'opener', 'closer')
            ->whereHas('register', fn ($q) => $q->whereIn('subsidiary_id', $ids))
            ->latest('opened_at')->paginate(20);
        return view('cash.index', compact('subsidiaries', 'registers', 'recentSessions'));
    }

    public function open(Request $request, SubsidiaryAccess $access, CashService $cash): RedirectResponse
    {
        $data = $request->validate([
            'cash_register_id' => ['required', 'exists:cash_registers,id'],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);
        $register = CashRegister::with('subsidiary')->findOrFail($data['cash_register_id']);
        $access->assert($request->user(), $register->subsidiary_id);
        $session = $cash->open($register, (float) $data['opening_float'], $request->user());
        return redirect()->route('cash.show', $session)->with('success', 'Caisse ouverte.');
    }

    public function show(Request $request, CashSession $session, SubsidiaryAccess $access): View
    {
        $session->load('register.subsidiary.groupProfile', 'movements.invoice', 'movements.expenseAccount', 'movements.creator', 'opener', 'closer');
        $access->assert($request->user(), $session->register->subsidiary_id);
        $invoices = Invoice::with('payments')
            ->where('subsidiary_id', $session->register->subsidiary_id)
            ->whereRaw('total - COALESCE((SELECT SUM(amount) FROM invoice_payments WHERE invoice_payments.invoice_id = invoices.id), 0) > 0.01')
            ->latest('issue_date')->limit(200)->get();
        $expenseAccounts = AccountingAccount::where('type', 'expense')->where('active', true)->orderBy('code')->get();
        return view('cash.show', compact('session', 'invoices', 'expenseAccounts'));
    }

    public function movement(Request $request, CashSession $session, SubsidiaryAccess $access, CashService $cash): RedirectResponse
    {
        $session->load('register');
        $access->assert($request->user(), $session->register->subsidiary_id);
        $data = $request->validate([
            'direction' => ['required', Rule::in([CashDirection::IN->value, CashDirection::OUT->value])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'invoice_id' => ['nullable', 'exists:invoices,id'],
            'expense_account_id' => ['nullable', 'exists:accounting_accounts,id'],
            'justification' => ['nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $invoice = ! empty($data['invoice_id']) ? Invoice::findOrFail($data['invoice_id']) : null;
        $expense = ! empty($data['expense_account_id']) ? AccountingAccount::where('type', 'expense')->findOrFail($data['expense_account_id']) : null;
        $cash->addMovement(
            $session,
            CashDirection::from($data['direction']),
            (float) $data['amount'],
            $invoice,
            $expense,
            $data['justification'] ?? null,
            $data['reference'] ?? null,
            $request->user()
        );
        return back()->with('success', 'Mouvement de caisse enregistré.');
    }

    public function close(Request $request, CashSession $session, SubsidiaryAccess $access, CashService $cash): RedirectResponse
    {
        $session->load('register');
        $access->assert($request->user(), $session->register->subsidiary_id);
        $data = $request->validate([
            'actual_closing' => ['required', 'numeric', 'min:0'],
            'closing_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $cash->close($session, (float) $data['actual_closing'], $data['closing_note'] ?? null, $request->user());
        return back()->with('success', 'Caisse clôturée. L’écart a été calculé automatiquement.');
    }
}
