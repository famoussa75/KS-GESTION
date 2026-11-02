<?php

namespace App\Http\Controllers;

use App\Models\AccountingAccount;
use App\Models\AccountingJournal;
use App\Models\JournalEntry;
use App\Models\Subsidiary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingController extends Controller
{
    public function index(Request $request): View
    {
        $query = JournalEntry::with(['subsidiary', 'journal', 'lines.account'])->latest('entry_date')->latest('id');
        if ($request->filled('subsidiary_id')) $query->where('subsidiary_id', $request->subsidiary_id);
        if ($request->filled('journal_id')) $query->where('accounting_journal_id', $request->journal_id);
        if ($request->filled('from')) $query->whereDate('entry_date', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('entry_date', '<=', $request->to);

        return view('accounting.index', [
            'entries' => $query->paginate(25)->withQueryString(),
            'accounts' => AccountingAccount::orderBy('code')->get(),
            'journals' => AccountingJournal::orderBy('code')->get(),
            'subsidiaries' => Subsidiary::where('active', true)->orderBy('name')->get(),
        ]);
    }
}
