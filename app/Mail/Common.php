<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Common extends Mailable
{
	use Queueable, SerializesModels;

	public $view, $arEmailData;

	/**
	* Create a new message instance.
	*
	* @return void
	*/
	public function __construct($view, $arEmailData)
	{
		$this->view = $view;
		$this->arEmailData = $arEmailData;
	}

	/**
	* Build the message.
	*
	* @return $this
	*/
	public function build()
	{
		if(isset($this->arEmailData['replyTo']))
			$this->replyTo($this->arEmailData['replyTo']['email'], $this->arEmailData['replyTo']['name']);

		if(isset($this->arEmailData['packageFromEmail']) && isset($this->arEmailData['packageFromName']))
			$this->from(
				resolveTourPackageFromEmail($this->arEmailData['tourPackageId'] ?? null, $this->arEmailData['custEmail'] ?? null, $this->arEmailData['packageFromEmail']),
				$this->arEmailData['packageFromName']
			);

		$this->withSymfonyMessage(function ($message) {
			$message->getHeaders()->addTextHeader(
				'X-Model-ID',
				isset($this->arEmailData['bookingId']) ? $this->arEmailData['bookingId'] : 0
			);
			$message->getHeaders()->addTextHeader(
				'X-Model-TP',
				isset($this->arEmailData['emailType']) ? $this->arEmailData['emailType'] : 0
			);
			$message->getHeaders()->addTextHeader(
				'X-Model-SUB',
				isset($this->arEmailData['subject']) ? $this->arEmailData['subject'] : ''
			);
			$message->getHeaders()->addTextHeader(
				'X-Model-OB',
				isset($this->arEmailData['operationBy']) ? $this->arEmailData['operationBy'] : 1
			);
			$message->getHeaders()->addTextHeader(
				'X-Model-UT',
				isset($this->arEmailData['operationByUserType']) ? $this->arEmailData['operationByUserType'] : 'Admin'
			);
			$message->getHeaders()->addTextHeader(
				'X-Model-TID',
				isset($this->arEmailData['tenantId']) ? $this->arEmailData['tenantId'] : ''
			);
		});
		return $this
			->subject($this->arEmailData['subject'])
			->view('mail.' . $this->view, $this->arEmailData);
	}
}
