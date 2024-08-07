<?php

namespace Laf\UI\Traits;

use Laf\UI\ComponentInterface;

trait FormElementTrait
{

    /**
     * @var bool
     */
    protected $excluded = false;


    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setType(?string $value): ComponentInterface
    {
        $this->addAttribute('type', $value);
        return $this;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->getAttribute('type');
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->getAttribute('name');
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setName(?string $value): ComponentInterface
    {
        $this->addAttribute('name', $value);
        return $this;
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setId(?string $value): ComponentInterface
    {
        $this->addAttribute('id', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setMin(?int $value): ComponentInterface
    {
        $this->addAttribute('min', $value);
        return $this;
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setLabel(?string $value): ComponentInterface
    {
        $this->addWrapperAttribute('label', $value);
        return $this;
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setHint(?string $value): ComponentInterface
    {
        $this->addWrapperAttribute('hint', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setHeight(?int $value): ComponentInterface
    {
        $this->addAttribute('height', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setWidth(?int $value): ComponentInterface
    {
        $this->addAttribute('width', $value);
        return $this;
    }

    /**
     * @return bool
     */
    public function isDisabled(): bool
    {
        return $this->getAttribute('disabled') == 'disabled';
    }

    /**
     * @param bool $value
     * @return ComponentInterface
     */
    public function setDisabled(bool $value): ComponentInterface
    {
        $this->addAttribute('disabled', ($value ? 'disabled' : ''));
        return $this;
    }

    /**
     * @return bool
     */
    public function isReadonly(): bool
    {
        return $this->getAttribute('readonly') == 'readonly';
    }

    /**
     * @param bool $readonly
     * @return ComponentInterface
     */
    public function setReadonly(bool $readonly): ComponentInterface
    {
        $this->addAttribute('readonly', ($readonly ? 'readonly' : ''));
        return $this;
    }

    /**
     * @param bool $value
     * @return ComponentInterface
     */
    public function setHidden(bool $value): ComponentInterface
    {
        $this->addAttribute('hidden', ($value ? 'hidden' : ''));
        return $this;
    }


    /**
     * @return int
     */
    public function getHeight(): ?int
    {
        return $this->getAttribute('height');
    }


    /**
     * @return int
     */
    public function getWidth(): ?int
    {
        return $this->getAttribute('width');
    }

    /**
     * @return string
     */
    public function getId(): ?string
    {
        return $this->getAttribute('id');
    }

    /**
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->getAttribute('hidden') == 'hidden';
    }

    /**
     * @return string
     */
    public function getLabel(): ?string
    {
        return $this->getWrapperAttribute('label');
    }


    /**
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->getAttribute('required') == 'required';
    }

    /**
     * @return string
     */
    public function getHint(): ?string
    {
        return $this->getWrapperAttribute('hint');
    }

    /**
     * @return string
     */
    public function getValueForHtml(): ?string
    {
        if ($this->getField()->allowHtml()) {
            return nl2br($this->getAttribute('value') ?? '');
        } else {
            return nl2br(htmlentities($this->getAttribute('value') ?? ''));
        }
    }

    /**
     * @return bool
     */
    public function isExcluded(): bool
    {
        return $this->excluded;
    }

    /**
     * @param bool $value
     * @return ComponentInterface
     */
    public function setExcluded(bool $value): ComponentInterface
    {
        $this->excluded = $value;
        return $this;
    }

    /**
     * @return int
     */
    public function getSize(): ?int
    {
        return $this->getAttribute('size');
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setSize(?int $value): ComponentInterface
    {
        $this->addAttribute('size', $value);
        return $this;
    }

    /**
     * @return bool
     */
    public function isMultiple(): bool
    {
        return $this->getAttribute('multiple') == 'multiple';
    }

    /**
     * @param bool $multiple
     * @return ComponentInterface
     */
    public function setMultiple(bool $multiple): ComponentInterface
    {
        $this->addAttribute('multiple', ($multiple ? 'multiple' : ''));
        return $this;
    }

    /**
     * @return int
     */
    public function getCols(): ?int
    {
        return $this->getAttribute('cols');
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setCols(?int $value): ComponentInterface
    {
        $this->addAttribute('cols', $value);
        return $this;
    }

    /**
     * @return int
     */
    public function getRows(): ?int
    {
        return $this->getAttribute('rows');
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setRows(?int $value): ComponentInterface
    {
        $this->addAttribute('rows', $value);
        return $this;
    }

    /**
     * @return bool
     */
    public function isAutofocus(): ?bool
    {
        return $this->getAttribute('autofocus') == 'autofocus';
    }

    /**
     * @param bool $value
     * @return ComponentInterface
     */
    public function setAutofocus(bool $value): ComponentInterface
    {
        $this->addAttribute('autofocus', ($value ? 'autofocus' : ''));
        return $this;
    }

    /**
     * @return bool
     */
    public function isAutocomplete(): bool
    {
        return $this->getAttribute('autocomplete') == 'autocomplete';
    }

    /**
     * @param bool $value
     * @return ComponentInterface
     */
    public function setAutocomplete(bool $value): ComponentInterface
    {
        $this->addAttribute('attribute', ($value ? 'autocomplete' : ''));
        return $this;
    }

    /**
     * @return string
     */
    public function getPlaceholder(): ?string
    {
        return $this->getAttribute('placeholder');
    }

    /**
     * @return string
     */
    public function getPattern(): ?string
    {
        return $this->getAttribute('pattern');
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setPattern(?string $value): ComponentInterface
    {
        $this->addAttribute('pattern', $value);
        return $this;
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setPlaceholder(?string $value): ComponentInterface
    {
        $this->addAttribute('placeholder', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setMaxLength(?int $value): ComponentInterface
    {
        $this->addAttribute('maxlength', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setMinLength(?int $value): ComponentInterface
    {
        $this->addAttribute('minlength', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setMax(?int $value): ComponentInterface
    {
        $this->addAttribute('max', $value);
        return $this;
    }

    /**
     * @param int $value
     * @return ComponentInterface
     */
    public function setStep(?int $value): ComponentInterface
    {
        $this->addAttribute('step', $value);
        return $this;
    }

    /**
     * @param bool $value
     * @return ComponentInterface
     */
    public function setRequired(bool $value): ComponentInterface
    {
        $this->addAttribute('required', ($value ? 'required' : ''));
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxLength(): ?int
    {
        return $this->getAttribute('maxlength');
    }

    /**
     * @return int
     */
    public function getMinLength(): ?int
    {
        return $this->getAttribute('minlength');
    }

    /**
     * @return int
     */
    public function getMax(): ?int
    {
        return $this->getAttribute('max');
    }

    /**
     * @return int
     */
    public function getMin(): ?int
    {
        return $this->getAttribute('min');
    }

    /**
     * @return int
     */
    public function getStep(): ?int
    {
        return $this->getAttribute('step');
    }

    /**
     * @return string
     */
    public function getValue(): ?string
    {
        return $this->getAttribute('value');
    }

    /**
     * @param string $value
     * @return ComponentInterface
     */
    public function setValue(?string $value): ComponentInterface
    {
        $this->addAttribute('value', $value);
        return $this;
    }

}
