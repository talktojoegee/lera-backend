<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillingExportCompleted extends Notification
{
    use Queueable;

    protected $fileName;

    /**
     * Create a new notification instance.
     */
    public function __construct($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Billing Export is Ready')
            ->line('Your requested billing export has been generated.')
            ->action('Download File', url('/storage/'.$this->fileName))
            ->line('Thank you for using our application!');
    }

    /**
     * Store in database notifications.
     */
    public function toArray($notifiable)
    {
        return [
            'message' => 'Your billing export is ready for download.',
            'file_url' => url('/storage/'.$this->fileName),
        ];
    }
}
