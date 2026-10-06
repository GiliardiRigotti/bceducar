<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuardianAccessCode extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public string $protocol) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Acesso à documentação da pré-matrícula');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.guardian-access-code');
    }
}
