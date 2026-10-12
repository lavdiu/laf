<?php

namespace Laf\UI\Page;

use Laf\UI\ComponentInterface;
use Laf\Util\Locale;
use Laf\Util\Util;

/**
 * The HTML document: doctype, head (charset, viewport, title, the header components, raw head
 * HTML), body (attributes from setBodyAttributes(), the menu HTML, the components, the footer
 * components and footer HTML, the body-end components such as scripts).
 *
 * draw(true) stops after the components without closing the body, so a page can append raw HTML.
 * The language comes from setLanguage(), else Settings `locale.language`, else "en".
 */
class Html
{
    protected string $htmlHeader = '';
    /** @var HeaderComponent[] */
    protected array $headerComponents = [];
    /** @var HeaderComponent[] */
    protected array $bodyEndComponents = [];
    protected string $pageTitle = '';
    protected ?string $language = null;
    /** @var ComponentInterface[] */
    protected array $components = [];
    /** @var ComponentInterface[] */
    protected array $footerComponents = [];
    protected string $footerHtml = '';
    protected string $menu = '';
    /** @var array<string, string|null> */
    protected array $bodyAttributes = ['style' => 'background-color:#EEEEEE'];

    /**
     * @return HeaderComponent[]
     */
    public function getHeaderComponents(): array
    {
        return $this->headerComponents;
    }

    /**
     * @param HeaderComponent[] $headerComponents
     */
    public function setHeaderComponents(array $headerComponents): static
    {
        $this->headerComponents = $headerComponents;
        return $this;
    }

    /**
     * A tag for the head (after the title).
     */
    public function addHeaderComponent(HeaderComponent $h): static
    {
        $this->headerComponents[] = $h;
        return $this;
    }

    /**
     * @return HeaderComponent[]
     */
    public function getBodyEndComponents(): array
    {
        return $this->bodyEndComponents;
    }

    /**
     * A tag drawn right before `</body>` (scripts that need the DOM).
     */
    public function addBodyEndComponent(HeaderComponent $h): static
    {
        $this->bodyEndComponents[] = $h;
        return $this;
    }

    /**
     * `<script src="...">` in the head, or at the end of the body with $atBodyEnd.
     * @param array<string, string|bool|null> $attributes e.g. ['defer' => null], ['type' => 'module']
     */
    public function addScript(string $src, array $attributes = [], bool $atBodyEnd = false): static
    {
        return $this->placeHeaderComponent(HeaderComponent::script($src, $attributes), $atBodyEnd);
    }

    /**
     * `<script>code</script>` in the head, or at the end of the body with $atBodyEnd.
     */
    public function addInlineScript(string $code, bool $atBodyEnd = false): static
    {
        return $this->placeHeaderComponent(HeaderComponent::inlineScript($code), $atBodyEnd);
    }

    /**
     * `<link rel="stylesheet" href="...">`
     * @param array<string, string|bool|null> $attributes
     */
    public function addStylesheet(string $href, array $attributes = []): static
    {
        return $this->addHeaderComponent(HeaderComponent::stylesheet($href, $attributes));
    }

    /**
     * `<style>css</style>`
     */
    public function addInlineStyle(string $css): static
    {
        return $this->addHeaderComponent(HeaderComponent::inlineStyle($css));
    }

    /**
     * `<meta name="..." content="...">`
     */
    public function addMeta(string $name, string $content): static
    {
        return $this->addHeaderComponent(HeaderComponent::meta($name, $content));
    }

    /**
     * `<link rel="..." href="...">`
     * @param array<string, string|bool|null> $attributes
     */
    public function addLinkTag(string $rel, string $href, array $attributes = []): static
    {
        return $this->addHeaderComponent(HeaderComponent::link($rel, $href, $attributes));
    }

    /**
     * The icon, shortcut icon and apple-touch-icon links for one image.
     */
    public function setFavicon(string $href): static
    {
        return $this->addLinkTag('icon', $href)
            ->addLinkTag('shortcut icon', $href)
            ->addLinkTag('apple-touch-icon', $href);
    }

    protected function placeHeaderComponent(HeaderComponent $h, bool $atBodyEnd): static
    {
        return $atBodyEnd ? $this->addBodyEndComponent($h) : $this->addHeaderComponent($h);
    }

    public function getHtmlHeader(): string
    {
        return $this->htmlHeader;
    }

    /**
     * Raw HTML appended to the head after the header components.
     */
    public function setHtmlHeader(string $htmlHeader): static
    {
        $this->htmlHeader = $htmlHeader;
        return $this;
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    /**
     * Text of the `<title>` (escaped when drawn).
     */
    public function setPageTitle(string $pageTitle): static
    {
        $this->pageTitle = $pageTitle;
        return $this;
    }

    /**
     * The `lang` attribute of `<html>`: setLanguage(), else Settings `locale.language`, else "en".
     */
    public function getLanguage(): string
    {
        return $this->language ?? Locale::language();
    }

    public function setLanguage(?string $language): static
    {
        $this->language = ($language === null || $language === '') ? null : $language;
        return $this;
    }

    /**
     * @return array<string, string|null>
     */
    public function getBodyAttributes(): array
    {
        return $this->bodyAttributes;
    }

    /**
     * Attributes of `<body>` (default `['style' => 'background-color:#EEEEEE']`); replaces the whole set.
     * @param array<string, string|null> $attributes
     */
    public function setBodyAttributes(array $attributes): static
    {
        $this->bodyAttributes = $attributes;
        return $this;
    }

    public function addBodyAttribute(string $key, ?string $value): static
    {
        $this->bodyAttributes[$key] = $value;
        return $this;
    }

    public function addBodyCssClass(string $class): static
    {
        $classes = preg_split('/\s+/', (string)($this->bodyAttributes['class'] ?? ''), -1, PREG_SPLIT_NO_EMPTY);
        if (!in_array($class, $classes, true)) {
            $classes[] = $class;
        }
        $this->bodyAttributes['class'] = join(' ', $classes);
        return $this;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * @param ComponentInterface[] $components
     */
    public function setComponents(array $components): static
    {
        $this->components = $components;
        return $this;
    }

    public function addComponent(ComponentInterface $component): static
    {
        $this->components[] = $component;
        return $this;
    }

    /**
     * @return ComponentInterface[]
     */
    public function getFooterComponents(): array
    {
        return $this->footerComponents;
    }

    /**
     * A component drawn after the page components, before the body-end scripts.
     */
    public function addFooterComponent(ComponentInterface $component): static
    {
        $this->footerComponents[] = $component;
        return $this;
    }

    public function getFooterHtml(): string
    {
        return $this->footerHtml;
    }

    /**
     * Raw HTML drawn after the footer components (a site footer).
     */
    public function setFooterHtml(string $footerHtml): static
    {
        $this->footerHtml = $footerHtml;
        return $this;
    }

    /**
     * @param bool $no_closing_tags true stops after the components: no footer, no body-end scripts,
     *                              no `</body></html>`, so the caller can append raw HTML
     */
    public function draw(bool $no_closing_tags = false): string
    {
        $html = $this->drawHead() . $this->drawBodyOpen();
        $html .= $this->getMenu();
        foreach ($this->getComponents() as $component) {
            $html .= (string)$component->draw();
        }
        if (!$no_closing_tags) {
            $html .= $this->drawBodyClose();
        }
        return $html;
    }

    /**
     * Doctype, `<html>`, the whole `<head>`.
     */
    protected function drawHead(): string
    {
        $html = "<!DOCTYPE html>\n<html lang=\"" . Util::attr($this->getLanguage()) . "\">\n<head>\n"
            . "\t<meta charset='utf-8'>\n"
            . "\t<meta http-equiv='X-UA-Compatible' content='IE=edge'>\n"
            . "\t<meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>\n"
            . "\t<title>" . Util::e($this->getPageTitle()) . "</title>\n";
        foreach ($this->getHeaderComponents() as $hc) {
            $html .= $hc->draw() . "\n";
        }
        $html .= $this->getHtmlHeader();
        $html .= "\n</head>";
        return $html;
    }

    /**
     * The opening `<body>` tag with its attributes.
     */
    protected function drawBodyOpen(): string
    {
        $html = "\n\n<body";
        foreach ($this->getBodyAttributes() as $key => $value) {
            if ($value === null || $value === '') {
                $html .= ' ' . $key;
                continue;
            }
            $html .= ' ' . $key . '="' . Util::attr($value) . '"';
        }
        return $html . '>';
    }

    /**
     * Footer components, footer HTML, body-end components, `</body></html>`.
     */
    protected function drawBodyClose(): string
    {
        $html = '';
        foreach ($this->getFooterComponents() as $component) {
            $html .= (string)$component->draw();
        }
        $html .= $this->getFooterHtml();
        foreach ($this->getBodyEndComponents() as $hc) {
            $html .= "\n" . $hc->draw();
        }
        return $html . "\n</body>\n</html>";
    }

    public function getMenu(): string
    {
        return $this->menu;
    }

    /**
     * Raw HTML drawn right after `<body>`, before the components.
     */
    public function setMenu(string $menu): static
    {
        $this->menu = $menu;
        return $this;
    }
}
