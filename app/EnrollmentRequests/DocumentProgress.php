<?php

namespace App\EnrollmentRequests;

use App\Models\RegistrationRequest;

class DocumentProgress
{
    public function status(RegistrationRequest $request): RequestStatus
    {
        $required = $request->documents()->where('required', true)
            ->where('status', '!=', DocumentStatus::Replaced->value)->get();
        if ($required->contains(fn ($document) => in_array($document->status,
            [DocumentStatus::Rejected, DocumentStatus::Correction]))) {
            return RequestStatus::Pending;
        }
        if ($required->contains(fn ($document) => $document->status === DocumentStatus::Pending
            || !$document->received_at || !DocumentDeadlines::timely($document, $request))) {
            return RequestStatus::AwaitingDocuments;
        }
        if ($required->contains(fn ($document) => $document->status === DocumentStatus::UnderReview)) {
            return RequestStatus::UnderReview;
        }

        // General approval remains an explicit, authorized action.
        return RequestStatus::AwaitingReview;
    }
}
