<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GuardianRegistrationNotice extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $kind, public string $protocol, public ?string $deadline = null, public ?string $openedAt = null) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.$this->protocol.'] '.match ($this->kind) {
            'PREREGISTRATION_REGISTERED' => 'Pré-matrícula registrada: aguarde a análise',
            'MODE_SELECTED' => 'Forma de entrega documental escolhida',
            'DOCUMENT_RECEIVED' => 'Documento recebido para análise',
            'PHYSICAL_REMINDER' => 'Prazo de apresentação ou regularização física próximo do fim',
            'PHYSICAL_EXPIRED' => 'Prazo físico vencido: procure a escola',
            'PHYSICAL_REQUIRED' => 'Matrícula em confirmação: apresente os documentos físicos',
            'DOCUMENTATION_APPROVED' => 'Documentação aprovada: aguardando efetivação',
            'DOCUMENTS_OPEN' => 'Pré-matrícula deferida: escolha matrícula online ou presencial',
            'CORRECTION' => 'Pendência documental da matrícula',
            'EXPIRED' => 'Prazo documental encerrado',
            'REGISTERED' => 'Matrícula efetivada',
            'REMINDER' => 'Prazo documental próximo do fim',
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.guardian-registration-notice-html', text: 'mail.guardian-registration-notice');
    }
}
