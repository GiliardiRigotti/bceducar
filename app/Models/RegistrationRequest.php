<?php

namespace App\Models;

use App\EnrollmentRequests\AttendanceMode;
use App\EnrollmentRequests\RequestAccess;
use App\EnrollmentRequests\RequestStatus;
use App\User;
use iEducar\Packages\PreMatricula\Models\PreRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RegistrationRequest extends Model
{
    protected $table = 'bc_registration_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'attendance_mode' => AttendanceMode::class,
            'document_deadline' => 'datetime',
            'submitted_at' => 'datetime',
            'requested_at' => 'datetime',
            'released_at' => 'datetime',
            'approved_at' => 'datetime',
            'workflow_version' => 'integer',
            'integrated_at' => 'datetime',
            'physical_deadline' => 'datetime',
            'physical_confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function preregistration()
    {
        return $this->belongsTo(PreRegistration::class, 'pmd_preregistration_id');
    }

    public function scopeDocumentaryStage(Builder $query): Builder
    {
        return $query->where(fn ($query) => $query->whereNull('pmd_preregistration_id')
            ->orWhereHas('preregistration', fn ($pmd) => $pmd->whereIn('preregistrations.status', [
                PreRegistration::STATUS_SUMMONED, PreRegistration::STATUS_IN_CONFIRMATION,
                PreRegistration::STATUS_ACCEPTED, PreRegistration::STATUS_REJECTED,
            ])));
    }

    public function documentationLabel(): string
    {
        if ($this->workflow_version === 2 && in_array($this->status, [RequestStatus::Approved, RequestStatus::VacancyConfirmed])) {
            return $this->integration_status === 'INTEGRATED' ? 'Matrícula em confirmação — aguardando conferência física' : 'Documentação aprovada — integração pendente';
        }

        return match ($this->status) {
            RequestStatus::Registered => 'Matrícula efetivada',
            RequestStatus::Approved, RequestStatus::VacancyConfirmed => 'Documentação aprovada — aguardando efetivação',
            RequestStatus::Expired, RequestStatus::NoShow => 'Prazo vencido',
            RequestStatus::Rejected => 'Indeferida',
            RequestStatus::Cancelled => 'Cancelada',
            default => !$this->attendance_mode ? 'Aguardando escolha da modalidade' : match ($this->status) {
                RequestStatus::UnderReview => 'Em análise',
                RequestStatus::Pending => 'Correção solicitada',
                RequestStatus::AwaitingReview, RequestStatus::DocumentsSent, RequestStatus::Corrected => 'Documentação enviada — aguardando análise',
                default => ($this->required_received ?? 0) > 0 ? 'Entrega parcial' : 'Aguardando documentos',
            },
        };
    }

    public function studentName(): string
    {
        return $this->preregistration?->student?->name ?? $this->student?->person?->nome ?? 'Não informado';
    }

    public function guardianName(): string
    {
        return $this->preregistration?->responsible?->name ?? $this->guardian?->person?->nome ?? 'Não informado';
    }

    public function student()
    {
        return $this->belongsTo(LegacyStudent::class, 'student_id');
    }

    public function guardian()
    {
        return $this->belongsTo(LegacyIndividual::class, 'guardian_id');
    }

    public function school()
    {
        return $this->belongsTo(LegacySchool::class, 'school_id');
    }

    public function grade()
    {
        return $this->belongsTo(LegacyGrade::class, 'grade_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(LegacySchoolClass::class, 'school_class_id');
    }

    public function registration()
    {
        return $this->belongsTo(LegacyRegistration::class, 'registration_id');
    }

    public function documents()
    {
        return $this->hasMany(RegistrationDocument::class);
    }

    public function events()
    {
        return $this->hasMany(RegistrationEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return app(RequestAccess::class)->scope($query, $user);
    }
}
