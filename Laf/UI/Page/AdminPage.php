<?php

namespace Laf\UI\Page;


use Intrepicure\Person;

class AdminPage extends GenericPage
{

	/**
	 * @return string
	 */
	public function draw(): string
	{
		if (!$this->isEnabled())
			return "";

		$user = Person::getLoggedUserInstance();
		if ($user->getSchoolObject()->needsVerification()) {
			$this->setNotification($user->getSchoolObject()->getVerificationAlert()->draw());
		}

		if (!$this->hasLinks()) {
			$this->setHeader("<div>");
			$this->setFooter("</div>");
		}
		return parent::draw();
	}
}
