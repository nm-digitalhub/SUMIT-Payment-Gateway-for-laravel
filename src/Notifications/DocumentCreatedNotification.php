<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;

class DocumentCreatedNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public readonly OfficeGuyDocument $document,
        public readonly array $response
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => __('officeguy::notifications.document_created.title'),
            'message' => __('officeguy::notifications.document_created.message', [
                'document_type' => $this->document->document_type,
                'document_number' => $this->document->document_number,
            ]),
            'icon' => 'heroicon-o-document-text',
            'icon_color' => 'success',
            'data' => [
                'document_id' => $this->document->id,
                'document_type' => $this->document->document_type,
                'document_number' => $this->document->document_number,
                'total_amount' => $this->document->total_amount,
            ],
            'actions' => [
                [
                    'label' => __('officeguy::notifications.document_created.view_document'),
                    'url' => route('filament.admin.resources.documents.view', $this->document),
                ],
                [
                    'label' => __('officeguy::notifications.document_created.download_document'),
                    'url' => route('officeguy.document.download', $this->document),
                ],
            ],
        ];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
