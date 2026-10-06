<?php

namespace App\EnrollmentRequests;

enum RequestStatus: string
{
    case Started = 'INICIADA';
    case AwaitingDocuments = 'AGUARDANDO_DOCUMENTOS';
    case DocumentsSent = 'DOCUMENTOS_ENVIADOS';
    case AwaitingReview = 'AGUARDANDO_ANALISE';
    case UnderReview = 'EM_ANALISE';
    case Pending = 'PENDENCIA_DOCUMENTAL';
    case Corrected = 'DOCUMENTOS_CORRIGIDOS';
    case Approved = 'APROVADA';
    case VacancyConfirmed = 'VAGA_CONFIRMADA';
    case Registered = 'MATRICULA_EFETIVADA';
    case Scheduled = 'PRESENCIAL_AGENDADO';
    case AwaitingAttendance = 'AGUARDANDO_COMPARECIMENTO';
    case NoShow = 'NAO_COMPARECEU';
    case Expired = 'PRAZO_EXPIRADO';
    case Rejected = 'INDEFERIDA';
    case Cancelled = 'CANCELADA';

    public function terminal(): bool
    {
        return in_array($this, [self::Registered, self::NoShow, self::Expired, self::Rejected, self::Cancelled]);
    }
}
