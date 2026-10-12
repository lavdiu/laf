<?php

namespace Laf\UI\Container;

use Laf\UI\Component\Link;
use Laf\UI\ComponentInterface;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * A Bootstrap modal dialog: title, the components as the body, a cancel button
 * and the footer buttons. With setIncludeForm(true) the content is wrapped in a
 * <form> (action, method, files); addHiddenInput() adds hidden fields to it, e.g.
 * a CSRF token: addHiddenInput($form->getCsrfSessionKey(), $form->getCsrfToken()).
 *
 * The title is raw HTML (pages pass icons); the cancel label, ids and form
 * attributes are escaped. getModalOpenButton() gives a link that opens it.
 */
class Modal implements ComponentInterface
{
    use ComponentTrait;

    const SIZE_NORMAL = '';
    /** @deprecated misspelt; use SIZE_NORMAL */
    const SISZE_NORMAL = '';
    const SIZE_SMALL = 'modal-sm';
    const SIZE_LARGE = 'modal-lg';
    const SIZE_XLARGE = 'modal-xl';
    const SIZE_FULLSCREEN = 'modal-fullscreen';

    protected string $id = '';

    protected bool $formHasFiles = false;

    protected string $title = '';

    protected string $cancelLabel = 'Cancel';

    protected string $size = '';

    /** @var ComponentInterface[] */
    protected array $footerButtons = [];

    protected bool $includeForm = false;

    protected string $formAction = '';

    protected string $formMethod = 'GET';

    protected string $formId = '';

    protected bool $scrollable = true;

    protected bool $centered = false;

    protected bool $staticBackdrop = false;

    protected bool $showCancelButton = true;

    /** @var array<string, string> name => value, hidden inputs drawn inside the form */
    protected array $hiddenInputs = [];

    public function __construct(string $id)
    {
        $this->setId($id);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;
        return $this;
    }

    public function addFooterButton(FormElementInterface $button): static
    {
        $this->footerButtons[] = $button;
        return $this;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getFooterButtons(): array
    {
        return $this->footerButtons;
    }

    /**
     * @param ComponentInterface[] $buttons
     */
    public function setFooterButtons(array $buttons = []): static
    {
        $this->footerButtons = $buttons;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title raw HTML
     */
    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getCancelLabel(): string
    {
        return $this->cancelLabel;
    }

    /**
     * Caption of the cancel button (escaped); see setShowCancelButton() to drop the button
     */
    public function setCancelLabel(string $cancelLabel): static
    {
        $this->cancelLabel = $cancelLabel;
        return $this;
    }

    public function setShowCancelButton(bool $show): static
    {
        $this->showCancelButton = $show;
        return $this;
    }

    public function isShowCancelButton(): bool
    {
        return $this->showCancelButton;
    }

    public function getSize(): string
    {
        return $this->size;
    }

    /**
     * @param string $size one of the SIZE_* constants
     */
    public function setSize(string $size): static
    {
        $this->size = $size;
        return $this;
    }

    public function isIncludeForm(): bool
    {
        return $this->includeForm;
    }

    /**
     * Wrap the body and footer in a <form> (see setFormAction(), setFormMethod(), setFormHasFiles(), addHiddenInput())
     */
    public function setIncludeForm(bool $includeForm): static
    {
        $this->includeForm = $includeForm;
        return $this;
    }

    public function getFormAction(): string
    {
        return $this->formAction;
    }

    public function setFormAction(string $formAction): static
    {
        $this->formAction = $formAction;
        return $this;
    }

    public function getFormMethod(): string
    {
        return $this->formMethod;
    }

    public function setFormMethod(string $formMethod): static
    {
        $this->formMethod = strtoupper($formMethod) === 'POST' ? 'POST' : 'GET';
        return $this;
    }

    public function getFormId(): string
    {
        return $this->formId;
    }

    public function setFormId(string $formId): static
    {
        $this->formId = $formId;
        return $this;
    }

    public function getFormHasFiles(): bool
    {
        return $this->formHasFiles;
    }

    public function setFormHasFiles(bool $formHasFiles): static
    {
        $this->formHasFiles = $formHasFiles;
        return $this;
    }

    /**
     * A hidden input drawn inside the form (with setIncludeForm(true)), e.g. the CSRF token of a Form
     */
    public function addHiddenInput(string $name, string $value): static
    {
        $this->hiddenInputs[$name] = $value;
        return $this;
    }

    /**
     * @return array<string, string>
     */
    public function getHiddenInputs(): array
    {
        return $this->hiddenInputs;
    }

    public function isScrollable(): bool
    {
        return $this->scrollable;
    }

    public function setScrollable(bool $scrollable): static
    {
        $this->scrollable = $scrollable;
        return $this;
    }

    public function isCentered(): bool
    {
        return $this->centered;
    }

    /**
     * Vertically centre the dialog (modal-dialog-centered)
     */
    public function setCentered(bool $centered): static
    {
        $this->centered = $centered;
        return $this;
    }

    public function isStaticBackdrop(): bool
    {
        return $this->staticBackdrop;
    }

    /**
     * A click outside the dialog does not close it (data-bs-backdrop="static")
     */
    public function setStaticBackdrop(bool $static): static
    {
        $this->staticBackdrop = $static;
        return $this;
    }

    public function draw(): ?string
    {
        $id = Util::e($this->getId());
        $formStartTag = '';
        $formEndTag = '';
        if ($this->isIncludeForm()) {
            $formStartTag = "<form role='form' action='" . Util::e($this->getFormAction()) . "' method='" . Util::e($this->getFormMethod()) . "'"
                . ($this->getFormId() !== '' ? " id='" . Util::e($this->getFormId()) . "'" : '')
                . ($this->getFormHasFiles() ? " enctype='multipart/form-data'" : '') . ">";
            foreach ($this->hiddenInputs as $name => $value) {
                $formStartTag .= "\n\t\t\t\t<input type='hidden' name='" . Util::e($name) . "' value='" . Util::e($value) . "' />";
            }
            $formEndTag = "</form>";
        }

        $components = '';
        foreach ($this->getComponents() as $component) {
            if ((string)$component->getDrawMode() === '') {
                $component->setDrawMode($this->getDrawMode());
            }
            $components .= $component->draw();
        }

        $footerButtons = '';
        foreach ($this->getFooterButtons() as $button) {
            $footerButtons .= $button->draw();
        }
        $cancel = $this->showCancelButton
            ? "<button type='button' class='btn btn-outline-secondary' data-dismiss='modal' data-bs-dismiss='modal'>" . Util::e($this->getCancelLabel()) . "</button>"
            : '';

        $dialogClasses = Util::e(join(' ', array_filter([
            'modal-dialog',
            $this->getSize(),
            $this->isScrollable() ? 'modal-dialog-scrollable' : '',
            $this->isCentered() ? 'modal-dialog-centered' : '',
        ], fn($c) => $c !== '')));
        $backdrop = $this->isStaticBackdrop() ? " data-bs-backdrop='static' data-backdrop='static'" : '';

        return "
	<div class='modal fade' id='{$id}' tabindex='-1' role='dialog' aria-labelledby='{$id}Label' aria-hidden='true' data-bs-keyboard='true'{$backdrop}>
		<div class='{$dialogClasses}' role='document'>
			<div class='modal-content'>
				{$formStartTag}
				<div class='modal-header'>
					<h5 class='modal-title' id='{$id}Label'>{$this->getTitle()}</h5>
					<button type='button' class='btn-close' data-dismiss='modal' data-bs-dismiss='modal' aria-label='Close'></button>
				</div>
				<div class='modal-body'>
				{$components}
				</div>
				<div class='modal-footer'>
					{$cancel}
					{$footerButtons}
				</div>
				{$formEndTag}
			</div>
		</div>
	</div>
";
    }

    /**
     * A link that opens the modal (Bootstrap 4 and 5 data attributes)
     * @param string $cssClasses space separated classes of the link
     */
    public function getModalOpenButton(string $label = 'Open Modal', string $cssClasses = 'btn btn-outline-secondary btn-sm'): Link
    {
        $btn = new Link();
        $btn->setValue($label)
            ->addAttribute('data-toggle', 'modal')
            ->addAttribute('data-bs-toggle', 'modal')
            ->addAttribute('data-target', '#' . $this->getId())
            ->addAttribute('data-bs-target', '#' . $this->getId())
            ->setCssClasses(array_values(array_filter(explode(' ', $cssClasses), fn($c) => $c !== '')))
            ->addAttribute('href', 'javascript:;');
        return $btn;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
