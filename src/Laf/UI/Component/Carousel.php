<?php

namespace Laf\UI\Component;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;
use Laf\Util\Util;

/**
 * A Bootstrap carousel of images: image URLs (setImages()/addImage()) and/or
 * document ids (addDocument(), turned into URLs by the document URL builder,
 * default "/document/view/{id}/1.jpg"). Optional indicators and previous/next
 * controls, images as <img> or as background images (useBgImages()).
 * Bootstrap 4 and 5 data attributes are both rendered. Drawing never changes
 * the component.
 */
class Carousel implements ComponentInterface
{
    use ComponentTrait;

    /** @var \Closure|null fn(int $documentId): string */
    private static ?\Closure $documentUrlBuilder = null;

    /** @var int[] */
    protected array $documents = [];

    /** @var string[] */
    protected array $images = [];

    protected bool $indicators = true;

    protected bool $controls = true;

    protected string $topContent = '';

    protected string $id = '';

    protected bool $useBgImages = false;

    /**
     * @param array<int, int|string> $documents document ids
     * @param string|null $id DOM id; empty = generated
     * @param string[] $images image URLs
     */
    public function __construct(array $documents = [], ?string $id = null, array $images = [], bool $indicators = true, bool $controls = true)
    {
        $this->setDocuments($documents);
        $this->indicators = $indicators;
        $this->controls = $controls;
        $this->setId($id);
        $this->setImages($images);
    }

    /**
     * How a document id becomes an image URL, process-wide; null restores "/document/view/{id}/1.jpg"
     * @param callable|null $builder fn(int $documentId): string
     */
    public static function setDocumentUrlBuilder(?callable $builder): void
    {
        self::$documentUrlBuilder = $builder === null ? null : \Closure::fromCallable($builder);
    }

    public static function documentUrl(int $documentId): string
    {
        if (self::$documentUrlBuilder !== null) {
            return (string)(self::$documentUrlBuilder)($documentId);
        }
        return '/document/view/' . $documentId . '/1.jpg';
    }

    /**
     * Use background images instead of embedded <img /> images
     */
    public function useBgImages(): static
    {
        $this->useBgImages = true;
        return $this;
    }

    public function setUseBgImages(bool $useBgImages): static
    {
        $this->useBgImages = $useBgImages;
        return $this;
    }

    public function isUseBgImages(): bool
    {
        return $this->useBgImages;
    }

    public function addDocument(?int $id): static
    {
        if ($id !== null) {
            $this->documents[] = $id;
        }
        return $this;
    }

    public function setShowIndicators(bool $indicators): static
    {
        $this->indicators = $indicators;
        return $this;
    }

    public function setShowControls(bool $controls): static
    {
        $this->controls = $controls;
        return $this;
    }

    /**
     * Every slide URL: the images, then the documents' URLs
     * @return string[]
     */
    public function getSlides(): array
    {
        $slides = $this->images;
        foreach ($this->documents as $document) {
            $slides[] = self::documentUrl($document);
        }
        return $slides;
    }

    public function draw(): string
    {
        $this->addCssClass(static::getComponentCssControlClass());

        $slides = $this->getSlides();
        if ($slides === []) {
            return '';
        }

        $carouselId = Util::e($this->id !== '' ? $this->id : 'carousel' . uniqid());

        $html = "\n<div id='{$carouselId}' class='carousel slide' data-ride='carousel' data-bs-ride='carousel'>";

        if ($this->getShowIndicators()) {
            $html .= "\n\t<ol class='carousel-indicators'>";
            foreach (array_keys($slides) as $i) {
                $html .= "\n\t\t<li data-target='#{$carouselId}' data-bs-target='#{$carouselId}' data-slide-to='{$i}' data-bs-slide-to='{$i}' class='" . ($i === 0 ? 'active' : '') . "'" . ($i === 0 ? " aria-current='true'" : '') . "></li>";
            }
            $html .= "\n\t</ol>";
        }

        $html .= "\n\t<div class='carousel-inner'>";
        foreach ($slides as $i => $image) {
            $active = $i === 0 ? ' active' : '';
            $url = Util::attr($image);
            if ($this->useBgImages) {
                $html .= "\n\t\t<div class='carousel-item{$active}' style=\"background-image:url('" . Util::e($image) . "')\"></div>";
            } else {
                $html .= "\n\t\t<div class='carousel-item{$active}'>\n\t\t\t<img class='d-block w-100' src=\"{$url}\" alt='image'>\n\t\t</div>";
            }
        }
        $html .= "\n\t</div>";

        if ($this->topContent !== '') {
            $html .= $this->topContent;
        }

        if ($this->getShowControls()) {
            $html .= "\n\t<a class='carousel-control-prev' href='#{$carouselId}' role='button' data-slide='prev' data-bs-slide='prev'>
        <i class='fa fa-chevron-circle-left fa-2x'></i>
        <span class='sr-only visually-hidden'>Previous</span>
    </a>
    <a class='carousel-control-next' href='#{$carouselId}' role='button' data-slide='next' data-bs-slide='next'>
        <i class='fa fa-chevron-circle-right fa-2x'></i>
        <span class='sr-only visually-hidden'>Next</span>
    </a>";
        }
        $html .= "\n</div>\n";
        return $html;
    }

    /**
     * Returns the CSS class unique to the UI component
     */
    public function getComponentCssControlClass(): string
    {
        return str_replace('\\', '-', static::class);
    }

    public function getDocumentCount(): int
    {
        return count($this->documents);
    }

    public function getImagesCount(): int
    {
        return count($this->images);
    }

    /**
     * @return int[]
     */
    public function getDocuments(): array
    {
        return $this->documents;
    }

    /**
     * @param array<int, int|string> $documents document ids
     */
    public function setDocuments(array $documents): static
    {
        $this->documents = [];
        foreach ($documents as $document) {
            if (is_numeric($document)) {
                $this->documents[] = (int)$document;
            }
        }
        return $this;
    }

    public function addImage(string $image): static
    {
        $this->images[] = $image;
        return $this;
    }

    /**
     * @return string|null the id, null when none was set (one is generated per draw)
     */
    public function getId(): ?string
    {
        return $this->id !== '' ? $this->id : null;
    }

    public function setId(?string $id): static
    {
        $this->id = $id ?? '';
        return $this;
    }

    public function getShowIndicators(): bool
    {
        return $this->indicators;
    }

    /**
     * @return string[]
     */
    public function getImages(): array
    {
        return $this->images;
    }

    /**
     * @param string[] $images image URLs
     */
    public function setImages(array $images): static
    {
        $this->images = array_values(array_map('strval', $images));
        return $this;
    }

    /**
     * Raw HTML drawn after the slides (captions, overlays)
     */
    public function getTopContent(): string
    {
        return $this->topContent;
    }

    public function setTopContent(string $topContent): static
    {
        $this->topContent = $topContent;
        return $this;
    }

    public function getShowControls(): bool
    {
        return $this->controls;
    }
}
