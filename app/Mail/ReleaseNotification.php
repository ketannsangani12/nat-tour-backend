<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use TijsVerkoyen\CssToInlineStyles\CssToInlineStyles;

class ReleaseNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $release;

    public $user;

    public $tenant;

    public $domain;

    public $routePrefix;

    public function __construct($release, $user, $tenant, $domain, $routePrefix)
    {
        $this->release = $release;
        $this->user = $user;
        $this->tenant = $tenant;
        $this->domain = $domain;
        $this->routePrefix = $routePrefix;
    }

    public function build()
    {
        $url = tenant_route($this->domain, "{$this->routePrefix}.release.view", $this->release->id);
        $body = str_replace('{release_url}', $url, $this->release->body);
        $body = (new CssToInlineStyles())->convert($body);

        return $this->from('info@nativeamericantours.com', 'Native American Tours')
            ->subject($this->release->title)
            ->html($body);
    }
}
