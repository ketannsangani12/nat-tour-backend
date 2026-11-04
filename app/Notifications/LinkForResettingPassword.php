<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LinkForResettingPassword extends Notification
{
	use Queueable;

	protected $token;

	/**
	 * Create a new notification instance.
	 *
	 * @return void
	 */
	public function __construct($token)
	{
		$this->token = $token;
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed  $notifiable
	 * @return array
	 */
	public function via($notifiable)
	{
		return [ 'mail' ];
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return \Illuminate\Notifications\Messages\MailMessage
	 */
	public function toMail($notifiable)
	{
		if($notifiable->getTable() == 'admins')
			$routePrefix = 'admin';
		elseif($notifiable->getTable() == 'tour_operator_staff')
			$routePrefix = 'tour-operator';
		else
			$routePrefix = 'customer';

		$linkParams = [
			'token' => $this->token,
			'email' => $notifiable->getEmailForPasswordReset(),
		];

		$url = route("auth.{$routePrefix}.reset-password-form", $linkParams, true);

		$arEmailData = [
			'subject' => 'Reset account password',
			'url' => $url,
			'receiverName' => $notifiable->getAttribute('name'),
		];

		return (new MailMessage)
			->view('mail.link-for-resetting-password', [ 'arEmailData' => $arEmailData ]);
	}
}
