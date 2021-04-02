<?php

namespace Laf\UI\Form;

use Laf\Database\Field\Field;
use Laf\UI\ComponentInterface;

interface FormElementInterface
{

    /**
     * @param Field $field
     * @return mixed
     */
    public function setField(Field $field): ComponentInterface;

    /**
     * @return mixed
     */
    public function getField(): ?Field;

    /**
     * Returns the CSS class unique to the UI component
     * @return string
     */
    public function getComponentCssControlClass();

    public function getHint(): ?string;

    public function setHint(?string $value);
}
