<?php

namespace Laf\UI\Page;
use Laf\Util\Settings;

class AdminPage extends GenericPage
{

	/**
	 * @return string
	 */
	public function draw(): string
	{
		if (!$this->isEnabled())
			return "";

		$settings = Settings::getInstance();
		$personClass = '\\'.$settings->getProperty('project.package_name').'\\Person';
		$user = $personClass::getLoggedUserInstance();

		if (!$this->hasLinks()) {
			$this->setHeader("<div>");
			$this->setFooter("</div>");
		}
		return parent::draw();
	}
}
