<?php

namespace Laf\UI\Component;

use Laf\UI\ComponentInterface;
use Laf\UI\Traits\ComponentTrait;

class Carousel implements ComponentInterface
{
	use ComponentTrait;
	/**
	 * @var array
	 */
	protected $documents = [];
	/**
	 * @var array
	 */
	protected $images = [];
	/**
	 * @var bool
	 */
	protected $indicators = true;
	/**
	 * @var bool
	 */
	protected $controls = true;
	/**
	 * @var string
	 */
	protected $topContent = "";
	/**
	 * @var string
	 */
	protected $id = "";
	/**
	 * @var bool
	 */
	protected $useBgImages = false;

	/**
	 * Carousel constructor.
	 * @param array $documents
	 * @param string $id
	 * @param array $images
	 * @param bool $indicators
	 * @param bool $controls
	 */
	public function __construct(array $documents, $id = null, $images = [], bool $indicators = true, bool $controls = true)
	{
		$this->documents = $documents;
		$this->indicators = $indicators;
		$this->controls = $controls;
		$this->setId($id);
		$this->setImages($images);
	}


	/**
	 * Use Background images instead of embedded <img /> images
	 * @return Carousel
	 */
	public function useBgImages()
	{
		$this->useBgImages = true;
		return $this;
	}

	/**
	 * @param $id
	 * @return Carousel
	 */
	public function addDocument(?int $id)
	{
		$this->documents[] = $id;
		return $this;
	}

	/**
	 * @param bool $indicators
	 * @return Carousel
	 */
	public function setShowIndicators(bool $indicators): Carousel
	{
		$this->indicators = $indicators;
		return $this;
	}

	/**
	 * @param bool $controls
	 * @return Carousel
	 */
	public function setShowControls(bool $controls): Carousel
	{
		$this->controls = $controls;
		return $this;
	}

	public function draw(): string
	{
		$this->addCssClass(static::getComponentCssControlClass());

		if ($this->getDocumentCount() < 1 && $this->getImagesCount() < 1)
			return "";

		foreach ($this->getDocuments() as $document) {
			$this->addImage("/document/view/{$document}/1.jpg");
		}

		$carouselId = Util::coalesce($this->getId(), ('a' . uniqid()));

		$html = "\n<div id='{$carouselId}' class='carousel slide' data-ride='carousel'>";

		/**
		 * Indicators
		 */
		if ($this->getShowIndicators()) {
			$html .= "\n\t<ol class='carousel-indicators'>";
			for ($i = 0; $i < $this->getDocumentCount(); $i++) {
				$html .= "\n\t\t<li data-target='#{$carouselId}' data-slide-to='{$i}' class='" . ($i == 0 ? 'active' : '') . "'></li>";
			}
			$html .= "\n\t</ol>";
		}


		/**
		 * Draw Images
		 */
		$html .= "\n\t<div class = 'carousel-inner'>";
		$counter = 1;
		foreach ($this->getImages() as $image) {
			if ($this->useBgImages) {

				$html .= "\n\t\t<div class='carousel-item " . ($counter == 1 ? 'active' : '') . "' style='background-image:url({$image})'></div>";
			} else {
				$html .= "
\t\t<div class='carousel-item " . ($counter == 1 ? 'active' : '') . "'>
    \t\t<img class='d-block w-100' src='{$image}' alt='image'>
\t\t</div>";
			}
			$counter++;
		}
		$html .= "\n\t</div>";

		if ($this->getTopContent()) {
			$html .= $this->getTopContent();
		}

		if ($this->getShowControls()) {
			$html .= "\n\t<a class = 'carousel-control-prev' href='#{$carouselId}' role='button' data-slide='prev'>
        <i class='fa fa-chevron-circle-left fa-2x'></i>
        <span class='sr-only'>Previous</span>
    </a>
    <a class = 'carousel-control-next' href='#{$carouselId}' role='button' data-slide='next'>
        <i class='fa fa-chevron-circle-right fa-2x'></i>
        <span class='sr-only'>Next</span>
    </a>
</div>
";
			return $html;
		}
	}

	/**
	 * Returns the CSS class unique to the UI component
	 * @return string
	 */
	public function getComponentCssControlClass(): string
	{
		return str_replace('\\', '-', static::class);
	}

	/**
	 * @return int
	 */
	public function getDocumentCount()
	{
		return count($this->documents);
	}

	/**
	 * @return int
	 */
	public function getImagesCount(): int
	{
		return count($this->images);
	}

	/**
	 * @return array
	 */
	public function getDocuments(): array
	{
		return $this->documents;
	}

	/**
	 * @param array $documents
	 * @return Carousel
	 */
	public function setDocuments(array $documents): Carousel
	{
		$this->documents = $documents;
		return $this;
	}

	/**
	 * @param string $image
	 * @return Carousel
	 */
	public function addImage(string $image)
	{
		$this->images[] = $image;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getId(): ?string
	{
		return $this->id;
	}

	/**
	 * @param string $id
	 * @return Carousel
	 */
	public function setId(?string $id): Carousel
	{
		$this->id = $id;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getShowIndicators(): bool
	{
		return $this->indicators;
	}

	/**
	 * @return array
	 */
	public function getImages(): array
	{
		return $this->images;
	}

	/**
	 * @param array $images
	 * @return Carousel
	 */
	public function setImages(array $images): Carousel
	{
		$this->images = $images;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getTopContent(): string
	{
		return $this->topContent;
	}

	/**
	 * @param string $topContent
	 * @return Carousel
	 */
	public function setTopContent(string $topContent): Carousel
	{
		$this->topContent = $topContent;
		return $this;
	}

	/**
	 * @return bool
	 */
	public function getShowControls(): bool
	{
		return $this->controls;
	}
}
