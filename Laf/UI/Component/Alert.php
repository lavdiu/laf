<?php

namespace Laf\UI\Component;

use Laf\UI\Traits\ComponentTrait;
use Laf\UI\ComponentInterface;

class Alert implements ComponentInterface
{
	use ComponentTrait;

	const Type_Primary = 'primary';
	const Type_Secondary = 'secondary';
	const Type_Success = 'success';
	const Type_Danger = 'danger';
	const Type_Warning = 'warning';
	const Type_Info = 'info';
	const Type_Light = 'light';
	const Type_Dark = 'dark';

	/**
	 * @var string
	 */
	protected $title = '';

	/**
	 * @var string
	 */
	protected $message = '';

	/**
	 * @var string
	 */
	protected $type = '';


	/**
	 * Alert constructor.
	 * @param string $title
	 * @param string $message
	 * @param string $type
	 */
	public function __construct(string $title = "", string $message = "", string $type = 'primary')
	{
		$this->title = $title;
		$this->message = $message;
		$this->type = $type;
	}

	/**
	 * Draw alert
	 * @param bool $wrap_in_container
	 * @return string
	 */
	public function draw($wrap_in_container = false): ?string
	{
		if ($this->getMessage() == "")
			return "";
		$html = "";
		$this->addCssClass(static::getComponentCssControlClass());

		if ($wrap_in_container) $html = "\n<div class='col-sm-12 offset-md-4 col-md-4'>";
		$html .= "
<div class='alert alert-{$this->getType()}' role='alert'>
  " . ($this->getTitle() != "" ? "<h4 class='alert-heading'>{$this->getTitle()}</h4>" : "") . "
  <p>{$this->getMessage()}</p>
</div>
";
		if ($wrap_in_container) $html .= "</div>\n";
		return $html;
	}

	/**
	 * @return string
	 */
	public function getMessage(): string
	{
		return $this->message;
	}

	/**
	 * @param string $message
	 * @return Alert
	 */
	public function setMessage(string $message): Alert
	{
		$this->message = $message;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 * @return Alert
	 */
	public function setType(string $type): Alert
	{
		$this->type = $type;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle(): string
	{
		return $this->title;
	}

	/**
	 * @param string $title
	 * @return Alert
	 */
	public function setTitle(string $title): Alert
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * Returns the CSS class unique to the UI component
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}

}
