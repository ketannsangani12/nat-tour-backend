<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReleaseNoteNotification extends Notification
{
    use Queueable;

    protected $release;

    protected $user;

    protected $tenant;

    protected $domain;

    protected $routePrefix;

    /**
     * Create a new notification instance.
     */
    public function __construct($release, $user, $tenant, $domain, $routePrefix)
    {
        $this->release = $release;
        $this->user = $user;
        $this->tenant = $tenant;
        $this->domain = $domain;
        $this->routePrefix = $routePrefix;
    }

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
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable)
    {
        return [
            'id' => $this->release->id,
            'title' => 'New Release Available!',
            'message' => $this->release->title,
            'route' => route("{$this->routePrefix}.release.view", $this->release->id),
            'tenant_id' => $this->release->tenant_id
        ];
    }
}
