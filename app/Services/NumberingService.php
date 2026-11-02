<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\DocumentSequence;
use App\Models\Subsidiary;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class NumberingService
{
    public function next(DocumentType $type, Subsidiary $subsidiary, CarbonInterface $date): string
    {
        return DB::transaction(function () use ($type, $subsidiary, $date) {
            $year = (int) $date->format('Y');
            $query = DocumentSequence::query()
                ->where('subsidiary_id', $subsidiary->id)
                ->where('document_type', $type->value)
                ->where('year', $year);

            $sequence = $query->lockForUpdate()->first();

            if (! $sequence) {
                try {
                    DocumentSequence::create([
                        'subsidiary_id' => $subsidiary->id,
                        'document_type' => $type->value,
                        'year' => $year,
                        'current_value' => 0,
                    ]);
                } catch (QueryException) {
                    // Another transaction may have inserted the unique sequence.
                }

                $sequence = $query->lockForUpdate()->firstOrFail();
            }

            $sequence->current_value++;
            $sequence->save();

            return sprintf(
                '%s-%s-%d-%06d',
                $type->prefix(),
                strtoupper($subsidiary->code),
                $year,
                $sequence->current_value
            );
        });
    }
}
