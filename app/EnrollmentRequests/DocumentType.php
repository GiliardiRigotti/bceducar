<?php

namespace App\EnrollmentRequests;

enum DocumentType: string
{
    case BirthCertificate = 'CERTIDAO_NASCIMENTO';
    case StudentCpf = 'CPF_ALUNO';
    case GuardianIdentity = 'DOCUMENTO_RESPONSAVEL';
    case GuardianCpf = 'CPF_RESPONSAVEL';
    case HealthCard = 'CARTAO_SUS';
    case Vaccination = 'DECLARACAO_VACINAL';
    case Residence = 'COMPROVANTE_RESIDENCIA';
    case Lease = 'CONTRATO_LOCACAO';
    case History = 'HISTORICO_ESCOLAR';
    case Transfer = 'DECLARACAO_TRANSFERENCIA';

    public static function required(string $kind): array
    {
        $types = [self::BirthCertificate, self::GuardianIdentity, self::Residence, self::Vaccination];

        return $kind === 'TRANSFERENCIA' ? [...$types, self::History, self::Transfer] : $types;
    }
}
