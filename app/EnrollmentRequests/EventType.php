<?php

namespace App\EnrollmentRequests;

enum EventType: string
{
    case PreRegistrationApproved = 'PREREGISTRATION_APPROVED';
    case DocumentsReleased = 'DOCUMENT_STAGE_RELEASED';
    case Created = 'REQUEST_CREATED';
    case SchoolAssigned = 'REQUEST_SCHOOL_ASSIGNED';
    case ModeSelected = 'ATTENDANCE_MODE_SELECTED';
    case ModeChanged = 'ATTENDANCE_MODE_CHANGED';
    case Uploaded = 'DOCUMENT_UPLOADED';
    case Received = 'DOCUMENT_RECEIVED_IN_PERSON';
    case ReviewStarted = 'DOCUMENT_REVIEW_STARTED';
    case DocumentApproved = 'DOCUMENT_APPROVED';
    case DocumentRejected = 'DOCUMENT_REJECTED';
    case CorrectionRequested = 'DOCUMENT_REPLACEMENT_REQUESTED';
    case Replaced = 'DOCUMENT_REPLACED';
    case Approved = 'REQUEST_APPROVED';
    case Rejected = 'REQUEST_REJECTED';
    case Expired = 'DEADLINE_EXPIRED';
    case DeadlineReminder = 'DEADLINE_REMINDER';
    case NativeIntegrated = 'NATIVE_PREREGISTRATION_INTEGRATED';
    case IntegrationFailed = 'NATIVE_INTEGRATION_FAILED';
    case PhysicalReminder = 'PHYSICAL_DEADLINE_REMINDER';
    case PhysicalExpired = 'PHYSICAL_DEADLINE_EXPIRED';
    case PhysicalReviewed = 'PHYSICAL_DOCUMENT_REVIEWED';
    case PhysicalPending = 'PHYSICAL_CORRECTION_REQUESTED';
    case PhysicalConfirmed = 'PHYSICAL_DOCUMENTATION_CONFIRMED';
    case DataConsolidated = 'DECLARED_DATA_CONSOLIDATED';
    case Registered = 'REGISTRATION_CREATED';
    case Enrolled = 'STUDENT_ENROLLED';
    case Cancelled = 'REQUEST_CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Solicitação registrada',
            self::PreRegistrationApproved => 'Pré-matrícula deferida',
            self::DocumentsReleased => 'Documentação liberada',
            self::SchoolAssigned => 'Unidade escolar definida',
            self::ModeSelected => 'Forma de entrega escolhida',
            self::ModeChanged => 'Forma de entrega alterada',
            self::Uploaded => 'Documento enviado',
            self::Received => 'Documento recebido na escola',
            self::ReviewStarted => 'Documento em análise',
            self::DocumentApproved => 'Documento aprovado',
            self::DocumentRejected => 'Documento recusado: consulte o motivo',
            self::CorrectionRequested => 'Correção documental solicitada',
            self::Replaced => 'Nova versão do documento recebida',
            self::Approved => 'Documentação aprovada',
            self::NativeIntegrated => 'Pré-matrícula integrada: matrícula em confirmação',
            self::IntegrationFailed => 'Integração pendente de verificação pela escola',
            self::PhysicalReminder => 'Lembrete de apresentação ou regularização física',
            self::PhysicalExpired => 'Prazo físico vencido: procure a escola',
            self::PhysicalReviewed => 'Documentação física conferida',
            self::PhysicalPending => 'Regularização física solicitada: consulte o motivo e prazo',
            self::PhysicalConfirmed => 'Conferência física concluída',
            self::DataConsolidated => 'Cadastro integrado ao i-Educar',
            self::Registered => 'Matrícula confirmada',
            self::Enrolled => 'Aluno enturmado',
            self::Expired => 'Prazo documental encerrado',
            self::DeadlineReminder => 'Lembrete de prazo',
            self::Rejected => 'Solicitação indeferida',
            self::Cancelled => 'Solicitação cancelada',
        };
    }
}
