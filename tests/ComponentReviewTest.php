<?php

declare(strict_types=1);

use Laf\UI\Component\Alert;
use Laf\UI\Component\Carousel;
use Laf\UI\Component\Dropdown;
use Laf\UI\Component\Image;
use Laf\UI\Component\Link;
use PHPUnit\Framework\TestCase;

/**
 * Component review (2026-10-12): Link / Image getters for unset attributes, confirmation
 * messages with quotes, null attribute values, disabled links, Dropdown alignment, entries
 * and ids, Image sizes and lazy loading, Alert options, Carousel return value, indicators,
 * repeated draws and document URLs.
 */
class ComponentReviewTest extends TestCase
{
    protected function tearDown(): void
    {
        Carousel::setDocumentUrlBuilder(null);
    }

    /** @test */
    public function link_getters_are_empty_strings_for_unset_attributes_and_null_values_are_skipped(): void
    {
        $link = new Link('Go');
        $this->assertSame('', $link->getHref());
        $this->assertSame('', $link->getTarget());
        $this->assertSame('', $link->getRel());
        $this->assertSame('', $link->getId());
        $this->assertSame('', $link->getOnClick());
        $this->assertSame('', $link->getTitle());
        $this->assertNull($link->getField());

        $link->addAttribute('data-x', null)->addAttribute('data-y', '')->addAttribute('data-z', 'v');
        $html = $link->draw();
        $this->assertStringNotContainsString('data-x', $html);
        $this->assertStringNotContainsString('data-y', $html);
        $this->assertStringContainsString('data-z="v"', $html);
        $this->assertStringContainsString('>Go</a>', $html);

        $this->assertSame('', (new Link(null, null, null))->getHref());
    }

    /** @test */
    public function confirmation_message_survives_quotes_and_line_breaks(): void
    {
        $link = (new Link('Delete', '/d'))->setConfirmationMessage("A jeni t'i sigurt?\nReally");
        $this->assertSame("return confirm('A jeni t\\'i sigurt?\\nReally')", $link->getOnClick());
        $html = $link->draw();
        $this->assertStringContainsString("onclick=\"return confirm('A jeni t\\'i sigurt?\\nReally')\"", $html);
    }

    /** @test */
    public function a_disabled_link_gets_the_bootstrap_markup_and_escaped_icon_and_classes(): void
    {
        $link = (new Link('Go', '/x', "fa fa-go' x", [], ['btn']))->setDisabled(true);
        $this->assertTrue($link->isDisabled());
        $html = $link->draw();
        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertStringContainsString('tabindex="-1"', $html);
        $this->assertStringContainsString('class="btn disabled Laf-UI-Component-Link"', $html);
        $this->assertStringContainsString("<i class='fa fa-go&apos; x'></i> Go", $html);

        $link->setDisabled(false);
        $this->assertFalse($link->isDisabled());
        $this->assertStringNotContainsString('aria-disabled', $link->draw());
    }

    /** @test */
    public function dropdown_alignment_entries_and_ids(): void
    {
        $dropdown = (new Dropdown('<Options>', 'btn-primary', 'fa fa-cogs', true))->setId('dd1');
        $dropdown->addLink(new Link('Edit', '/e', '', [], ['btn', 'btn-sm']))
            ->addDivider()
            ->addHeader('<More>')
            ->addHtml("<button class='dropdown-item'>Raw</button>")
            ->addLink(new Link('Delete', '/d'))
            ->addMenuCssClass('dropdown-menu-dark');
        $html = (string)$dropdown->draw();

        $this->assertSame(1, substr_count($html, "id='dd1'"), 'the id attribute is drawn once');
        $this->assertStringContainsString("aria-labelledby='dd1'", $html);
        $this->assertStringContainsString('&lt;Options&gt;', $html);
        $this->assertStringContainsString("class='dropdown-menu dropdown-menu-right dropdown-menu-end dropdown-menu-dark'", $html);
        $this->assertStringContainsString("<div class='dropdown-divider'></div>", $html);
        $this->assertStringContainsString("<h6 class='dropdown-header'>&lt;More&gt;</h6>", $html);
        $this->assertStringContainsString("<button class='dropdown-item'>Raw</button>", $html);
        $this->assertStringContainsString('class="dropdown-item Laf-UI-Component-Link"', $html, 'btn classes removed, no trailing space');
        $this->assertCount(2, $dropdown->getLinks());
        $this->assertCount(5, $dropdown->getItems());
        $this->assertNull($dropdown->getField());

        $dropdown->leftAlign();
        $this->assertFalse($dropdown->isRightAligned());
        $this->assertStringNotContainsString('dropdown-menu-right', (string)$dropdown->draw());
        $dropdown->rightAlign();
        $this->assertTrue($dropdown->isRightAligned());

        $this->assertMatchesRegularExpression("/id='dd[0-9a-f]+'/", (string)(new Dropdown('x'))->draw());
    }

    /** @test */
    public function image_getters_sizes_lazy_loading_and_escaping(): void
    {
        $image = new Image();
        $this->assertSame('', $image->getSrc());
        $this->assertSame('', $image->getAlt());
        $this->assertSame('', $image->getTitle());
        $this->assertNull($image->getField());

        $image = (new Image("/a.png?x=1&y=2", 'Pic'))->setHeight(120)->setWidth(80)->setLazy(true)->addCssStyleItem('border', 'none');
        $html = $image->draw();
        $this->assertStringContainsString('src="/a.png?x=1&amp;y=2"', $html);
        $this->assertStringContainsString('alt="Pic"', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringNotContainsString('height="', $html);
        $this->assertStringContainsString("style='border:none;height:120px;width:80px'", $html);
        $this->assertSame([], array_diff_key($image->getCssStyles(), ['border' => 1]), 'drawing does not write the sizes into the style map');
        $this->assertSame($html, (string)$image);
        $this->assertStringContainsString('bg-light rounded p-2', $html);
    }

    /** @test */
    public function alert_is_dismissible_with_an_icon_and_escapes_its_type(): void
    {
        $alert = (new Alert('T', 'Saved', "success' x"))->setDismissible(true)->setIcon('fa fa-check');
        $html = (string)$alert->draw();
        $this->assertStringContainsString("class='alert alert-success&apos; x alert-dismissible fade show'", $html);
        $this->assertStringContainsString("<i class='fa fa-check'></i> Saved", $html);
        $this->assertStringContainsString("data-bs-dismiss='alert'", $html);
        $this->assertStringNotContainsString('btn-close', (string)(new Alert('', 'plain'))->draw());
    }

    /** @test */
    public function carousel_always_returns_a_string_and_does_not_change_on_repeated_draws(): void
    {
        $carousel = new Carousel([7], 'car', ['/a.jpg'], true, false);
        $carousel->addDocument(8)->addDocument(null);
        $this->assertSame(['/a.jpg', '/document/view/7/1.jpg', '/document/view/8/1.jpg'], $carousel->getSlides());

        $first = $carousel->draw();
        $this->assertSame($first, $carousel->draw());
        $this->assertSame(['/a.jpg'], $carousel->getImages(), 'documents are not copied into the image list');
        $this->assertSame(3, substr_count($first, "<li data-target='#car'"), 'one indicator per slide');
        $this->assertSame(3, substr_count($first, "class='carousel-item"));
        $this->assertStringNotContainsString('carousel-control-prev', $first);
        $this->assertStringContainsString('</div>' . "\n", $first);
        $this->assertStringContainsString("data-bs-ride='carousel'", $first);

        $carousel->setShowControls(true)->useBgImages();
        $html = $carousel->draw();
        $this->assertStringContainsString('carousel-control-next', $html);
        $this->assertStringContainsString("data-bs-slide='next'", $html);
        $this->assertStringContainsString("style=\"background-image:url('/a.jpg')\"", $html);

        Carousel::setDocumentUrlBuilder(fn(int $id): string => "/img/{$id}");
        $this->assertSame('/img/7', Carousel::documentUrl(7));
        $this->assertStringContainsString('/img/8', $carousel->draw());

        $this->assertSame('', (new Carousel())->draw());
        $this->assertNull((new Carousel())->getId());
        $this->assertMatchesRegularExpression("/id='carousel[0-9a-f]+'/", (new Carousel([], null, ['/x.jpg']))->draw());
    }
}
