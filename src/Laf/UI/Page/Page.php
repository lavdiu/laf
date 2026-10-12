<?php

namespace Laf\UI\Page;

use Laf\Util\Util;

/**
 * Public-site variant of GenericPage: a full-width banner section (background image from
 * setBannerImage(), the title as its heading) followed by a centred container that holds the card.
 *
 * When no banner image is set and the application defines a global getBannerImage() function,
 * its result is used (that is what the original template expected).
 */
class Page extends GenericPage
{
    protected string $bannerImage = '';

    public function getBannerImage(): string
    {
        if ($this->bannerImage !== '') {
            return $this->bannerImage;
        }
        if (function_exists('getBannerImage')) {
            $image = \getBannerImage();
            return is_string($image) ? $image : '';
        }
        return '';
    }

    /**
     * URL of the banner background image.
     */
    public function setBannerImage(?string $url): static
    {
        $this->bannerImage = $url ?? '';
        return $this;
    }

    public function draw(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $header = $this->getHeader();
        $footer = $this->getFooter();
        $this->header = $this->drawBanner() . "
    <section class='contact-us'>
        <div class='container animated fadeIn'>
            <div class='row'>
                <div class='col-sm-10 offset-sm-1 col-md-8 offset-md-2'>
        " . $header;
        $this->footer = $footer . "
                </div>
            </div>
        </div>
    </section>
        ";
        try {
            return parent::draw();
        } finally {
            $this->header = $header;
            $this->footer = $footer;
        }
    }

    protected function drawBanner(): string
    {
        $image = $this->getBannerImage();
        $style = $image !== '' ? " style=\"background-image:url('" . Util::attr($image) . "');\"" : '';
        return "
    <section class=\"innerbanner\"" . $style . ">
        <div class='container'>
            <div class='row'>
                <div class='col-12 text-center innerbanner--wrapper'>
                    <h1 class='innerbanner-heading'>" . $this->getTitle() . "</h1>
                </div>
            </div>
        </div>
    </section>";
    }
}
