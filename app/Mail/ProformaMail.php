<?php

namespace App\Mail;

use App\Models\Proforma;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProformaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Proforma $proforma, public string $documentUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Facture proforma '.$this->proforma->number.' — '.$this->proforma->subsidiary->name);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.proforma');
    }

    public function attachments(): array
    {
        return [];
    }
}
