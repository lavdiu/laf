<?php

namespace Laf\UI\Page;

use Laf\Util\Util;

/**
 * One tag of the document head (or of the end of the body): script, link, meta, style, base, ...
 *
 * Attribute values are escaped when drawn; a null (or true) value draws a bare attribute
 * (`<script src="..." defer>`), false drops the attribute. The content (inline script / style)
 * is written as is. meta, link and base are void elements and never get a closing tag.
 *
 * Factories: HeaderComponent::script(), inlineScript(), stylesheet(), inlineStyle(), meta(), link().
 */
class HeaderComponent
{
    /** Tags that never have a closing tag, whatever the selfClosingTag flag says. */
    public const VOID_ELEMENTS = ['meta', 'link', 'base'];

    protected string $tagName = '';
    /** @var array<string, string|bool|null> */
    protected array $attributes = [];
    protected ?string $content = null;
    protected bool $selfClosingTag = false;

    /**
     * @param string $tagName
     * @param array<string, string|bool|null> $attributes
     * @param bool $selfClosingTag draw `<tag ... />` instead of `<tag ...>content</tag>`
     * @param string|null $content raw content between the tags (inline script / style)
     */
    public function __construct(string $tagName, array $attributes = [], bool $selfClosingTag = false, ?string $content = null)
    {
        $this->setTagName($tagName);
        $this->attributes = $attributes;
        $this->content = $content;
        $this->selfClosingTag = $selfClosingTag;
    }

    /**
     * `<script src="...">` (add ['defer' => null] or ['type' => 'module'] through $attributes)
     * @param array<string, string|bool|null> $attributes
     */
    public static function script(string $src, array $attributes = []): self
    {
        return new self('script', ['src' => $src] + $attributes);
    }

    /**
     * `<script>code</script>`
     * @param array<string, string|bool|null> $attributes
     */
    public static function inlineScript(string $code, array $attributes = []): self
    {
        return new self('script', $attributes, false, $code);
    }

    /**
     * `<link rel="stylesheet" href="...">`
     * @param array<string, string|bool|null> $attributes
     */
    public static function stylesheet(string $href, array $attributes = []): self
    {
        return new self('link', ['rel' => 'stylesheet', 'href' => $href] + $attributes, true);
    }

    /**
     * `<style>css</style>`
     */
    public static function inlineStyle(string $css): self
    {
        return new self('style', [], false, $css);
    }

    /**
     * `<meta name="..." content="...">`
     */
    public static function meta(string $name, string $content): self
    {
        return new self('meta', ['name' => $name, 'content' => $content], true);
    }

    /**
     * `<link rel="..." href="...">` (icons, canonical, preload, ...)
     * @param array<string, string|bool|null> $attributes
     */
    public static function link(string $rel, string $href, array $attributes = []): self
    {
        return new self('link', ['rel' => $rel, 'href' => $href] + $attributes, true);
    }

    /**
     * The tag as HTML, without a trailing newline.
     */
    public function draw(): string
    {
        $html = '<' . $this->tagName;
        foreach ($this->attributes as $key => $value) {
            if ($value === false) {
                continue;
            }
            if ($value === null || $value === true) {
                $html .= ' ' . $key;
                continue;
            }
            $html .= ' ' . $key . '="' . Util::attr($value) . '"';
        }
        if ($this->selfClosingTag || $this->isVoidElement()) {
            return $html . ' />';
        }
        return $html . '>' . (string)$this->content . '</' . $this->tagName . '>';
    }

    public function isVoidElement(): bool
    {
        return in_array(strtolower($this->tagName), self::VOID_ELEMENTS, true);
    }

    public function getTagName(): string
    {
        return $this->tagName;
    }

    /**
     * @throws \InvalidArgumentException when the name is not a plain tag name
     */
    public function setTagName(string $tagName): static
    {
        if (!preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $tagName)) {
            throw new \InvalidArgumentException("'{$tagName}' is not a valid tag name");
        }
        $this->tagName = $tagName;
        return $this;
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param array<string, string|bool|null> $attributes
     */
    public function setAttributes(array $attributes): static
    {
        $this->attributes = $attributes;
        return $this;
    }

    /**
     * @param string|bool|null $value null or true = bare attribute, false = not drawn
     */
    public function addAttribute(string $key, $value): static
    {
        $this->attributes[$key] = $value;
        return $this;
    }

    public function removeAttribute(string $key): static
    {
        unset($this->attributes[$key]);
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function isSelfClosingTag(): bool
    {
        return $this->selfClosingTag;
    }

    public function setSelfClosingTag(bool $selfClosingTag): static
    {
        $this->selfClosingTag = $selfClosingTag;
        return $this;
    }
}
