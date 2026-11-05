<?php

namespace App\Notifications;

use App\Models\GraduationRegistrationDocument;
use Illuminate\Notifications\Notification;

class DocumentRevisionNotification extends Notification
{
    public function __construct(
        public GraduationRegistrationDocument $document,
    ) {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        $registration = $this->document->registration;
        $documentLabel = $this->document->getDocumentTypeLabelAttribute();

        return [
            'type' => 'document_revision',
            'registration_id' => $registration?->id,
            'document_id' => $this->document->id,
            'document_type' => $this->document->document_type,
            'document_type_label' => $documentLabel,
            'review_notes' => $this->document->notes,
            'url' => $registration
                ? route('graduation-registrations.edit', $registration)
                : route('graduation-registrations.index'),
        ];
    }
}
