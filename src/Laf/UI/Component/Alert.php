<?php

namespace Laf\UI\Component;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * A Bootstrap alert: an optional heading, the message, one of the Type_* colours.
 * Title and message are raw HTML (pages pass links and line breaks); an empty
 * message draws nothing. setDismissible(true) adds the close button,
 * setIcon() an icon before the message.
 */
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

    protected string $title = '';

    protected string $message = '';

    protected string $type = '';

    protected bool $dismissible = false;

    protected string $icon = '';

    /**
     * @param string $title heading, raw HTML
     * @param string $message raw HTML
     * @param string $type one of the Type_* constants
     */
    public function __construct(string $title = '', string $message = '', string $type = self::Type_Primary)
    {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Draw alert
     * @param bool $wrap_in_container centre it in a narrow column (col-md-4 offset-md-4)
     */
    public function draw($wrap_in_container = false): ?string
    {
        if ($this->getMessage() === '') {
            return '';
        }
        $this->addCssClass(static::getComponentCssControlClass());

        $classes = 'alert alert-' . Util::e($this->getType()) . ($this->dismissible ? ' alert-dismissible fade show' : '');
        $icon = $this->icon !== '' ? "<i class='" . Util::e($this->icon) . "'></i> " : '';
        $close = $this->dismissible ? "\n  <button type='button' class='btn-close' data-dismiss='alert' data-bs-dismiss='alert' aria-label='Close'></button>" : '';

        $html = '';
        if ($wrap_in_container) {
            $html = "\n<div class='col-sm-12 offset-md-4 col-md-4'>";
        }
        $html .= "
<div class='{$classes}' role='alert'>
  " . ($this->getTitle() !== '' ? "<h4 class='alert-heading'>{$this->getTitle()}</h4>" : '') . "
  <p>{$icon}{$this->getMessage()}</p>{$close}
</div>
";
        if ($wrap_in_container) {
            $html .= "</div>\n";
        }
        return $html;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message raw HTML
     */
    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type one of the Type_* constants
     */
    public function setType(string $type): static
    {
        $this->type = $type;
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

    /**
     * Draw a close button that hides the alert
     */
    public function setDismissible(bool $dismissible): static
    {
        $this->dismissible = $dismissible;
        return $this;
    }

    public function isDismissible(): bool
    {
        return $this->dismissible;
    }

    /**
     * Css class(es) of an icon drawn before the message, '' for none
     */
    public function setIcon(string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }
}
