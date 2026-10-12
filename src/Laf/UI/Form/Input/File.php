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
 * (thumbnail for images, download link otherwise); update mode shows the same
 * link under the input (setShowCurrentFile(false) hides it).
 *
 * The document URLs default to "?module=document&submodule={view|thumbnail|
 * download}&id={id}"; an app with pretty URLs or its own document module sets
 * File::setDocumentUrlBuilder(fn(string $action, int $id): string => ...).
 */
class File extends Text implements FormElementInterface
{
    public const ACTION_VIEW = 'view';
    public const ACTION_THUMBNAIL = 'thumbnail';
    public const ACTION_DOWNLOAD = 'download';

    /** @var \Closure|null fn(string $action, int $documentId): string */
    private static ?\Closure $documentUrlBuilder = null;

    private bool $showCurrentFile = true;

    /**
     * How the links to a stored document are built, process-wide; null restores the default query-string URLs.
     * @param callable|null $builder fn(string $action, int $documentId): string
     */
    public static function setDocumentUrlBuilder(?callable $builder): void
    {
        self::$documentUrlBuilder = $builder === null ? null : \Closure::fromCallable($builder);
    }

    /**
     * The URL of a document action (ACTION_VIEW, ACTION_THUMBNAIL, ACTION_DOWNLOAD), raw (escape at output)
     */
    public static function documentUrl(string $action, int $documentId): string
    {
        if (self::$documentUrlBuilder !== null) {
            return (string)(self::$documentUrlBuilder)($action, $documentId);
        }
        return '?module=document&submodule=' . rawurlencode($action) . '&id=' . $documentId;
    }

    /**
     * Show the stored document under the input in edit modes (default true)
     */
    public function setShowCurrentFile(bool $show): static
    {
        $this->showCurrentFile = $show;
        return $this;
    }

    public function isShowCurrentFile(): bool
    {
        return $this->showCurrentFile;
    }

    public function drawViewMode(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $control = "<div class='bg-light rounded p-2' id='" . Util::e($this->getId()) . "'>\n                " . $this->renderStoredDocument(true) . "\n                </div>";
        return $this->renderWrapper($control, $this->viewWrapperOptions());
    }

    public function drawUpdateMode(): string
    {
        $this->setType(InputType::File);
        $this->addCssClass(static::getComponentCssControlClass());

        $attributes = $this->controlAttributes($this->inputOnlyAttributesExcept('type', 'size', 'multiple', 'accept'));
        if (empty($attributes['accept'])) {
            $accept = self::acceptFromSettings();
            if ($accept !== null) {
                $attributes['accept'] = $accept;
            }
        }

        $control = "<input class='" . Util::e(join(' ', $this->controlClasses('form-control'))) . "' style='" . Util::e($this->controlStyle()) . "'"
            . $this->renderAttributes($attributes)
            . $this->describedBy() . " />";

        if ($this->showCurrentFile && $this->storedDocumentId() !== null) {
            $current = $this->renderCurrentFile();
            if ($current !== '') {
                $control .= "\n\t\t\t\t<div id='" . Util::e($this->getId()) . "_current' class='form-text'>" . $current . "</div>";
            }
        }

        return $this->renderWrapper($control);
    }

    /**
     * The document id in the value, null when there is none
     */
    protected function storedDocumentId(): ?int
    {
        $value = $this->getValue();
        return (is_numeric($value) && (int)$value > 0) ? (int)$value : null;
    }

    /**
     * The stored document for the line under the input; '' when it cannot be loaded (no document
     * model in this process, no database), because the line is a convenience, not the value.
     */
    protected function renderCurrentFile(): string
    {
        try {
            return $this->renderStoredDocument(false);
        } catch (\Throwable $ex) {
            return '';
        }
    }

    /**
     * The stored document as a thumbnail link (images) or a download link; a note when there is none.
     * @param bool $withPlaceholder "(no file attached)" when nothing is stored (view mode), '' otherwise
     */
    protected function renderStoredDocument(bool $withPlaceholder): string
    {
        $id = $this->storedDocumentId();
        if ($id === null) {
            return $withPlaceholder ? '(no file attached)' : '';
        }
        $document = new Document($id);
        if (!$document->fileFullSizeExists()) {
            return '(file removed)';
        }
        if ($document->isImage()) {
            return "<a target='_blank' href='" . Util::e(self::documentUrl(self::ACTION_VIEW, $id)) . "'>"
                . "<img src='" . Util::e(self::documentUrl(self::ACTION_THUMBNAIL, $id)) . "' alt='image' style='height:150px;" . Util::e($this->controlStyle()) . "' class='" . Util::e($this->getCssClassesForHtml()) . "' />"
                . "</a>";
        }
        return "<a href='" . Util::e(self::documentUrl(self::ACTION_DOWNLOAD, $id)) . "'>Download File</a>";
    }

    /**
     * The allowed MIME types (Settings upload.allowed_mime_types) as an "accept" attribute, when configured
     */
    private static function acceptFromSettings(): ?string
    {
        if (!Settings::has('upload.allowed_mime_types')) {
            return null;
        }
        $types = Settings::get('upload.allowed_mime_types');
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
