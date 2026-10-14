<?php

namespace MyApp;

use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;

/**
 * Class Project
 * @package MyApp
 * Main Class for Table project
 * This class inherits functionality from BaseProject.
 * It is generated only once, please include all logic and code here
 */
class Project extends Base\BaseProject
{
	/**
	 * Project constructor.
	 * @param int|string|array|null $id
	 */
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	/**
	 * Find one row by using the first result
	 * @param array $keyValuePairs
	 * @return Project|null
	 * @throws \Exception
	 */
	public static function findOne(array $keyValuePairs) : ?Project
	{
		return parent::bOfindOne($keyValuePairs);
	}

	/**
	 * @param array $keyValuePairs
	 * @param array $orderBy field => ASC|DESC
	 * @return Project[]
	 * @throws \Exception
	 */
	public static function find(array $keyValuePairs = [], array $orderBy = [], ?int $limit = null, int $offset = 0) : array
	{
		return parent::bOfind($keyValuePairs, $orderBy, $limit, $offset);
	}

	/**
	 * The layout of this model's forms (null = FormLayout::auto() from the table and the
	 * JSON column comments). Uncomment and adjust to control columns, sections, tabs and
	 * which fields are excluded, read-only or hidden in each draw mode:
	 *
	 * public function getFormLayout(): ?FormLayout
	 * {
	 *     return FormLayout::auto($this->getTable())
	 *         ->readOnly(['code'], [DrawMode::UPDATE])
	 *         ->fieldset('Address', ['street', 'city'], 2);
	 * }
	 */
}
