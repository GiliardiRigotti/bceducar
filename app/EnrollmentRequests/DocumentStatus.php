<?php

namespace App\EnrollmentRequests;

enum DocumentStatus: string
{
    case Pending = 'PENDENTE';
    case Sent = 'ENVIADO';
    case UnderReview = 'EM_ANALISE';
    case Approved = 'APROVADO';
    case Rejected = 'REJEITADO';
    case Correction = 'SOLICITAR_CORRECAO';
    case Replaced = 'SUBSTITUIDO';
}
