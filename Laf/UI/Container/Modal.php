<?php


namespace Laf\UI\Container;


use Laf\UI\Component\Link;
use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;

class Modal implements ComponentInterface
{
	use ComponentTrait;

	/**
	 * @var string
	 */
	protected $id = "";

	/**
	 * @var string
	 */
	protected $title = "";

	/**
	 * @var string
	 */
	protected $cancelLabel = "Cancel";

	/**
	 * @var string
	 */
	protected $size = "";

	/**
	 * @var ComponentInterface[]
	 */
	protected $footerButtons = [];

	/**
	 * @var bool
	 */
	protected $includeForm = false;

	/**
	 * @var string
	 */
	protected $formAction = "";

	/**
	 * @var string
	 */
	protected $formMethod = "";

	/**
	 * @var string
	 */
	protected $formId = "";


	const SISZE_NORMAL = "";
	const SIZE_LARGE = "modal-lg";


	public function __construct(string $id)
	{
		$this->setId($id);
	}

	/**
	 * @return string
	 */
	public function getId(): string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 * @return Modal
	 */
	public function setId(string $id): Modal
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @param Link $button
	 * @return Modal
	 */
	public function addFooterButton(Link $button): Modal
	{
		$this->footerButtons[] = $button;
		return $this;
	}

	/**
	 * @return array
	 */
	public function getFooterButtons(): array
	{
		return $this->footerButtons;
	}

	/**
	 * @param array $buttons
	 * @return Modal
	 */
	public function setFooterButtons(array $buttons = []): Modal
	{
		$this->footerButtons = $buttons;
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
	 * @return Modal
	 */
	public function setTitle(string $title): Modal
	{
		$this->title = $title;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCancelLabel(): string
	{
		return $this->cancelLabel;
	}

	/**
	 * @param string $cancelLabel
	 * @return Modal
	 */
	public function setCancelLabel(string $cancelLabel): Modal
	{
		$this->cancelLabel = $cancelLabel;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSize(): string
	{
		return $this->size;
	}

	/**
	 * @param string $size
	 * @return Modal
	 */
	public function setSize(string $size): Modal
	{
		$this->size = $size;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function isIncludeForm(): bool
	{
		return $this->includeForm;
	}

	/**
	 * @param bool $includeForm
	 * @return Modal
	 */
	public function setIncludeForm(bool $includeForm): Modal
	{
		$this->includeForm = $includeForm;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFormAction(): string
	{
		return $this->formAction;
	}

	/**
	 * @param string $formAction
	 * @return Modal
	 */
	public function setFormAction(string $formAction): Modal
	{
		$this->formAction = $formAction;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFormMethod(): string
	{
		return $this->formMethod;
	}

	/**
	 * @param string $formMethod
	 * @return Modal
	 */
	public function setFormMethod(string $formMethod): Modal
	{
		$this->formMethod = $formMethod;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFormId(): string
	{
		return $this->formId;
	}

	/**
	 * @param string $formId
	 * @return Modal
	 */
	public function setFormId(string $formId): Modal
	{
		$this->formId = $formId;
		return $this;
	}


	/**
	 * @inheritDoc
	 */
	public function draw(): ?string
	{
		$formStartTag = "<form role='form' action='{$this->getFormAction()}' method='{$this->getFormMethod()}'>";
		$formEndTag = "</form>";
		$components = $footerButtons = "";
		if (!$this->isIncludeForm()) {
			$formStartTag = $formEndTag = "";
		}
		foreach ($this->getComponents() as $c) {
			$components .= $c->draw();
		}

		foreach ($this->getFooterButtons() as $b) {
			$footerButtons .= $b->draw();
		}


		$html = "
	<div class='modal fade' id='{$this->getId()}' tabindex='-1' role='dialog' aria-labelledby='{$this->getId()}Label' aria-hidden='true'>
		<div class='modal-dialog {$this->getSize()}' role='document'>
			<div class='modal-content'>
				{$formStartTag}
				<div class='modal-header'>
					<h5 class='modal-title' id='{$this->getId()}Label}'>{$this->getTitle()}</h5>
					<button type='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true'>&times;</span></button>
				</div>
				<div class='modal-body'>
				{$components}	
				</div>
				<div class='modal-footer'>
					<button type='button' class='btn btn-outline-secondary' data-dismiss='modal'>{$this->getCancelLabel()}</button>
					{$footerButtons}
				</div>
				{$formEndTag}
			</div>
		</div>
	</div>
";
		return $html;

	}

	/**
	 * Returns a button ready to open the modal
	 * @param string $label
	 * @param $cssClasses string classes to o assign to the button
	 * @return Link
	 */
	public function getModalOpenbutton(string $label = "Open Modal", string $cssClasses = 'btn btn-outline-secondary btn-ms'): Link
	{
		$btn = new Link();
		$btn->setValue('Open Modal')
			->addAttribute('data-toggle', 'modal')
			->addAttribute('data-target', '#' . $this->getId())
			->addCssClass(explode(' ', $cssClasses))
			->setCssClasses('')
			->addAttribute('href', 'javascript:;');
		return $btn;
	}

	/**
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}
}