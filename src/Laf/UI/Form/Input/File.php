<?php

namespace Laf\UI\Form\Input;

use Laf\Filesystem\Document;
use Laf\UI\Form\FormElementInterface;
use Laf\UI\Form\InputType;
use Laf\Util\Settings;
use Laf\Util\Util;

/**
 * Upload input for a foreign key to the document table.
 *
 * The file travels in $_FILES under the field's (rot13) name; Form uploads it
 * through the document handler only when a file was actually sent, otherwise
 * the stored document id is kept. VIEW mode links the stored document
 * (thumbnail for images, download link otherwise).
 */
class File extends Text implements FormElementInterface
{
    public function drawViewMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $content = '(no file attached)';
        $value = $this->getValue();
        if (is_numeric($value) && (int)$value > 0) {
            $id = Util::e((int)$value);
            $document = new Document((int)$value);
            if (!$document->fileFullSizeExists()) {
                $content = '(file removed)';
            } elseif ($document->isImage()) {
                $content = "<a target='_blank' href='?module=document&amp;submodule=view&amp;id={$id}'>"
                    . "<img src='?module=document&amp;submodule=thumbnail&amp;id={$id}' alt='image' style='height:150px;" . Util::e($this->controlStyle()) . "' class='" . Util::e($this->getCssClassesForHtml()) . "' />"
                    . "</a>";
            } else {
                $content = "<a href='?module=document&amp;submodule=download&amp;id={$id}'>Download File</a>";
            }
        }

        $control = "<div class='bg-light rounded p-2' id='" . Util::e($this->getId()) . "'>\n                {$content}\n                </div>";
        return $this->renderWrapper($control, $this->viewWrapperOptions());
    }

    public function drawUpdateMode(): string
    {
        $this->setType(InputType::File);
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->controlAttributes(['value', 'placeholder', 'autocomplete', 'maxlength', 'minlength', 'pattern', 'inputmode', 'step', 'min', 'max', 'checked']);
        if (empty($attributes['accept'])) {
            $accept = self::acceptFromSettings();
            if ($accept !== null) {
                $attributes['accept'] = $accept;
            }
        }

        $control = "<input class='" . Util::e(join(' ', $this->controlClasses('form-control'))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($attributes)
            . $this->describedBy() . " />";

        return $this->renderWrapper($control);
    }

    /**
     * The allowed MIME types (Settings upload.allowed_mime_types) as an "accept" attribute, when configured
     */
    private static function acceptFromSettings(): ?string
    {
        try {
            $types = Settings::get('upload.allowed_mime_types');
        } catch (\Throwable $ex) {
            return null;
        }
        if (!is_array($types) || count($types) === 0) {
            return null;
        }
        return join(',', array_map('strval', $types));
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
