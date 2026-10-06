<?php

namespace App\EnrollmentRequests;

use Illuminate\Validation\ValidationException;

class DocumentReviewReasons
{
    public const OPTIONS = [
        'ILLEGIBLE' => 'Arquivo ilegível. Envie uma foto nítida ou um PDF em que todos os dados possam ser lidos.',
        'INCOMPLETE' => 'Documento incompleto. Envie todas as páginas e, quando necessário, frente e verso.',
        'WRONG_DOCUMENT' => 'Tipo de documento incorreto. Envie o documento solicitado para este item.',
        'WRONG_PERSON' => 'Documento de outra pessoa. Envie o documento correspondente ao aluno ou responsável solicitado.',
        'EXPIRED' => 'Documento fora da validade. Envie uma versão válida do documento solicitado.',
        'MISMATCH' => 'Dados divergentes. Confira os dados do cadastro e envie um documento que comprove as informações corretas.',
        'OTHER' => 'Outro motivo',
    ];

    public static function describe(?string $option, ?string $details): ?string
    {
        $details = trim($details ?? '');
        if ($option === 'OTHER' && $details === '') {
            throw ValidationException::withMessages(['reason' => 'Descreva o motivo para orientar o responsável.']);
        }
        $reason = $option && $option !== 'OTHER' ? self::OPTIONS[$option] : '';
        $reason = trim($reason . ($reason && $details ? ' ' : '') . $details);
        if (mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'O motivo completo deve ter até 1000 caracteres.']);
        }

        return $reason !== '' ? $reason : null;
    }
}
