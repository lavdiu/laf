<?php

namespace LafShell;

use Laf\UI\Form\DrawMode;
use Laf\UI\Form\FormLayout;

/**
 * Class M2mPersonGroup
 * @package LafShell
 * Main Class for Table m2m_person_group
 * This class inherits functionality from BaseM2mPersonGroup.
 * It is generated only once, please include all logic and code here
 */
class M2mPersonGroup extends Base\BaseM2mPersonGroup
{
	/**
	 * M2mPersonGroup constructor.
	 * @param int|string|array|null $id
	 */
	public function __construct($id = null)
	{
		parent::__construct($id);
	}

	/**
	 * Find one row by using the first result
	 * @param array $keyValuePairs
	 * @return M2mPersonGroup|null
	 * @throws \Exception
	 */
	public static function findOne(array $keyValuePairs) : ?M2mPersonGroup
	{
		return parent::bOfindOne($keyValuePairs);
	}

	/**
	 * @param array $keyValuePairs
	 * @param array $orderBy field => ASC|DESC
	 * @return M2mPersonGroup[]
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
