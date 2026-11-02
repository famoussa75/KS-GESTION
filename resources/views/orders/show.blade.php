@extends('layouts.app')

@section('title', $order->number)
@section('page-title', 'Commande ' . $order->number)

@section('content')

@php
    /*
    |--------------------------------------------------------------------------
    | Préparation des statuts
    |--------------------------------------------------------------------------
    |
    | On calcule les statuts une seule fois afin d'éviter les instructions
    | PHP multiples dans @php(...) qui provoquaient le ParseError.
    |
    */

    $statusCases = \App\Enums\OrderStatus::cases();

    $statusValues = array_map(
        fn ($status) => $status->value,
        $statusCases
    );

    $currentIndex = array_search(
        $order->status->value,
        $statusValues,
        true
    );

    $nextStatus = $order->status->next();
@endphp


{{-- ============================================================
     BARRE D'ACTIONS
============================================================ --}}
<div class="toolbar">

    <a
        class="btn btn-secondary"
        href="{{ route('orders.index') }}"
    >
        ← Commandes
    </a>

    <span class="spacer"></span>

    @if($order->status->isCancellable())
        <form method="POST" action="{{ route('orders.cancel', $order) }}" onsubmit="return confirm('Annuler cette commande et libérer les réservations ?')">
            @csrf
            <button type="submit" class="btn btn-secondary">Annuler la commande</button>
        </form>
    @endif

    @if($nextStatus)

        <form
            method="POST"
            action="{{ route('orders.advance', $order) }}"
            onsubmit="return confirm('Passer la commande à l’étape suivante ?')"
        >
            @csrf

            <button
                type="submit"
                class="btn btn-primary"
            >
                Passer à : {{ $nextStatus->label() }}
            </button>
        </form>

    @else

        <span class="badge badge-success">
            Commande livrée
        </span>

    @endif

</div>


{{-- ============================================================
     PROGRESSION DE LA COMMANDE
============================================================ --}}
<div class="status-stepper">

    @foreach($statusCases as $step)

        @php
            $stepIndex = array_search(
                $step->value,
                $statusValues,
                true
            );

            $stepClass = '';

            if ($stepIndex < $currentIndex) {
                $stepClass = 'done';
            } elseif ($stepIndex === $currentIndex) {
                $stepClass = 'current';
            }
        @endphp

        <div class="status-step {{ $stepClass }}">
            {{ $step->label() }}
        </div>

    @endforeach

</div>


{{-- ============================================================
     CONTENU PRINCIPAL
============================================================ --}}
<div
    class="grid grid-3"
    style="grid-template-columns: 2fr 1fr;"
>

    {{-- ========================================================
         ARTICLES DE LA COMMANDE
    ========================================================= --}}
    <div class="card">

        <div class="card-header">

            <h3>Articles</h3>

            <span class="badge badge-blue">
                {{ $order->subsidiary->name }}
            </span>

        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>Produit</th>
                        <th class="text-right">Quantité</th>
                        <th class="text-right">Prix</th>
                        <th>Réservation</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($order->lines as $line)

                        <tr>

                            {{-- Produit --}}
                            <td>

                                <strong>
                                    {{ $line->product->name }}
                                </strong>

                                <div class="small muted">
                                    {{ $line->product->sku }}
                                </div>

                            </td>


                            {{-- Quantité --}}
                            <td class="text-right fw-bold">

                                {{ number_format(
                                    (float) $line->quantity,
                                    3,
                                    ',',
                                    ' '
                                ) }}

                            </td>


                            {{-- Prix unitaire --}}
                            <td class="text-right">

                                {{ number_format(
                                    (float) $line->unit_price,
                                    0,
                                    ',',
                                    ' '
                                ) }}

                            </td>


                            {{-- Réservations de stock --}}
                            <td>

                                @if($line->reservations->isNotEmpty())

                                    @foreach($line->reservations as $reservation)

                                        <div
                                            class="small"
                                            style="margin-bottom: 4px;"
                                        >

                                            <span
                                                class="badge {{
                                                    $reservation->status->value === 'active'
                                                        ? 'badge-blue'
                                                        : 'badge-success'
                                                }}"
                                            >
                                                {{ $reservation->status->value }}
                                            </span>

                                            {{ number_format(
                                                (float) $reservation->quantity,
                                                3,
                                                ',',
                                                ' '
                                            ) }}

                                            @if($reservation->batch)

                                                <span class="muted">
                                                    • lot {{ $reservation->batch->batch_number }}
                                                </span>

                                            @endif

                                        </div>

                                    @endforeach

                                @else

                                    <span class="muted small">
                                        Réservé automatiquement à la validation
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @empty

                        <tr>
                            <td
                                colspan="4"
                                class="text-center muted"
                                style="padding: 30px;"
                            >
                                Aucun article dans cette commande.
                            </td>
                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

    </div>


    {{-- ========================================================
         INFORMATIONS DE LA COMMANDE
    ========================================================= --}}
    <div class="card">

        <div class="card-header">
            <h3>Informations</h3>
        </div>

        <div class="card-body">

            {{-- Client --}}
            <div class="stat-line">

                <span>Client</span>

                <strong>
                    {{ $order->client?->name ?? 'Comptoir' }}
                </strong>

            </div>


            {{-- Date de commande --}}
            <div class="stat-line">

                <span>Date</span>

                <strong>
                    {{ $order->order_date?->format('d/m/Y') ?? '—' }}
                </strong>

            </div>


            {{-- Livraison souhaitée --}}
            <div class="stat-line">

                <span>Livraison souhaitée</span>

                <strong>
                    {{ $order->requested_delivery_date?->format('d/m/Y') ?? '—' }}
                </strong>

            </div>


            {{-- Créateur --}}
            <div class="stat-line">

                <span>Créée par</span>

                <strong>
                    {{ $order->creator?->name ?? '—' }}
                </strong>

            </div>


            {{-- Filiale --}}
            <div class="stat-line">

                <span>Filiale</span>

                <strong>
                    {{ $order->subsidiary?->name ?? '—' }}
                </strong>

            </div>


            {{-- Statut --}}
            <div class="stat-line">

                <span>Statut</span>

                <strong>
                    <span class="badge badge-blue">
                        {{ $order->status->label() }}
                    </span>
                </strong>

            </div>


            {{-- Notes --}}
            @if(!empty($order->notes))

                <div
                    class="doc-box"
                    style="margin-top: 14px;"
                >

                    <h4>Notes</h4>

                    <div>
                        {{ $order->notes }}
                    </div>

                </div>

            @endif

        </div>

    </div>

</div>


{{-- ============================================================
     RÈGLE DE GESTION DU STOCK
============================================================ --}}
<div
    class="card"
    style="margin-top: 18px;"
>

    <div class="card-body">

        <strong>
            Règle de stock :
        </strong>

        <span class="muted">

            la transition
            <strong>« En attente → Validée »</strong>
            réserve automatiquement les quantités correspondantes.

            La transition
            <strong>« En préparation → Livrée »</strong>
            déduit les quantités physiques du stock et consomme les
            réservations associées.

            Pour les produits périssables de
            <strong>Jus Sossoguinai</strong>,
            les lots dont la date de péremption est la plus proche
            sont réservés en priorité.

        </span>

    </div>

</div>

@endsection