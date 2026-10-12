<?php

namespace Laf\Util;

/**
 * The route of the current request: /module/submodule/action/id.
 *
 * With pretty URLs (Settings 'settings.use_pretty_url', default true) the rewrite rule hands the
 * path in $_GET['uriRewrite']; without them the parts come from ?module=&submodule=&action=&id=.
 * The id is an int when numeric, a lower-cased UUID when it looks like one, null otherwise.
 *
 * One instance per request (getInstance()); the static getters and link builders are what pages
 * and the generated code call. reset() forgets the instance so the next call re-reads $_GET
 * (tests, CLI scripts that handle several routes). Link builders URL-encode the module and
 * submodule they put into a path or query string.
 */
class UrlParser
{
    private const UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    private static ?UrlParser $instance = null;

    private ?string $fullUrl = null;

    /** @var string[] the path split on "/" */
    private array $pieces = [];

    private string $module = 'home';

    private ?string $submodule = null;

    private ?string $action = null;

    /** the id segment as written in the URL */
    private ?string $id = null;

    /**
     * true: host/module/submodule/action/id, false: host/?module=&submodule=&action=&id=
     */
    private bool $usePrettyUrl = true;

    private function __construct()
    {
        if (Settings::has('settings.use_pretty_url')) {
            $this->usePrettyUrl = (bool)Settings::get('settings.use_pretty_url');
        }
        $this->buildFromUri();
    }

    public static function getInstance(): UrlParser
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Forget the parsed request; the next call re-reads Settings and $_GET.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    private function buildFromUri(): void
    {
        $this->module = 'home';
        $this->submodule = null;
        $this->action = null;
        $this->id = null;
        $this->pieces = [];

        if (isset($_GET['uriRewrite'])) {
            $this->fullUrl = self::scalar($_GET['uriRewrite']);
        }

        if (!$this->isUsePrettyUrl()) {
            $this->module = self::scalar($_GET['module'] ?? ($_GET['mod'] ?? '')) ?? '';
            $this->submodule = self::scalar($_GET['submodule'] ?? '');
            $this->action = self::scalar($_GET['action'] ?? '');
            $this->id = self::scalar($_GET['id'] ?? '');
            return;
        }

        $this->fullUrl = str_replace('?', '', (string)$this->fullUrl);
        if (str_contains($this->fullUrl, '/')) {
            $this->pieces = explode('/', $this->fullUrl);
            $this->module = $this->pieces[0];
            $this->submodule = $this->pieces[1] ?? null;
            $this->action = $this->pieces[2] ?? null;
            $this->id = $this->pieces[3] ?? null;
        } elseif (trim($this->fullUrl) !== '') {
            $this->module = $this->fullUrl;
        }
    }

    /** A request value as a string; arrays (?id[]=1) count as absent. */
    private static function scalar(mixed $value): ?string
    {
        return is_scalar($value) ? (string)$value : null;
    }

    public function isUsePrettyUrl(): bool
    {
        return $this->usePrettyUrl;
    }

    public function setUsePrettyUrl(bool $usePrettyUrl): static
    {
        $this->usePrettyUrl = $usePrettyUrl;
        return $this;
    }

    /**
     * The current route as a link: "/module/submodule/action/id/" or the query-string form.
     */
    public static function getFullUri(): string
    {
        $instance = self::getInstance();
        if ($instance->isUsePrettyUrl()) {
            return '/' . $instance->getModule() . '/' . $instance->getSubmodule() . '/' . $instance->_getAction() . '/' . $instance->getId() . '/';
        }
        return sprintf('?module=%s&submodule=%s&action=%s&id=%s', $instance->getModule(), $instance->getSubmodule(), $instance->_getAction(), $instance->getId());
    }

    public static function getModule(): string
    {
        return self::getInstance()->_getModule();
    }

    public function _getModule(): string
    {
        return $this->module;
    }

    public static function getSubmodule(): ?string
    {
        return self::getInstance()->_getSubmodule();
    }

    public function _getSubmodule(): ?string
    {
        return $this->submodule;
    }

    public static function getAction(): ?string
    {
        return self::getInstance()->_getAction();
    }

    public function _getAction(): ?string
    {
        return $this->action;
    }

    /**
     * The record id of the route: an int for a numeric segment, the lower-cased UUID for a UUID
     * segment, null for anything else.
     * @return int|string|null
     */
    public static function getId(): int|string|null
    {
        return self::getInstance()->_getId();
    }

    /** @return int|string|null */
    public function _getId(): int|string|null
    {
        return self::normaliseId($this->id);
    }

    /**
     * The id segment exactly as written in the URL (null when the route has none).
     */
    public static function getRawId(): ?string
    {
        return self::getInstance()->id;
    }

    /** @return int|string|null */
    private static function normaliseId(?string $id): int|string|null
    {
        if ($id === null) {
            return null;
        }
        if (is_numeric($id)) {
            return (int)$id;
        }
        if (self::isUuid($id)) {
            return strtolower($id);
        }
        return null;
    }

    public static function isUuid(string $value): bool
    {
        return preg_match(self::UUID_PATTERN, $value) === 1;
    }

    /**
     * Redirects to the view page of the current module and submodule, for $id or the route's id.
     * @param int|string|null $id
     */
    public static function redirectToViewPage(int|string|null $id = null): void
    {
        self::redirect(static::getViewLink($id));
    }

    /** @param int|string|null $id a number or a UUID; anything else means the route's own id */
    public static function getViewLink(int|string|null $id = null): string
    {
        return self::getLink('view', $id);
    }

    /**
     * Redirects to the list page of the current module and submodule.
     */
    public static function redirectToListPage(): void
    {
        self::redirect(static::getListLink());
    }

    public static function getListLink(): string
    {
        return self::getLink('list', 'all');
    }

    public static function getNewLink(): string
    {
        return self::getLink('new', null, false);
    }

    /** Same as getNewLink(). */
    public static function getInsertLink(): string
    {
        return self::getNewLink();
    }

    /** @param int|string|null $id a number or a UUID; anything else means the route's own id */
    public static function getUpdateLink(int|string|null $id = null): string
    {
        return self::getLink('update', $id);
    }

    /** @param int|string|null $id a number or a UUID; anything else means the route's own id */
    public static function getDeleteLink(int|string|null $id = null): string
    {
        return self::getLink('delete', $id);
    }

    /**
     * A link to $action of the current module and submodule: "/module/submodule/action/id" or
     * "?module=…&submodule=…&action=…&id=…". $id is used when it is a number, a UUID or "all";
     * otherwise the link carries the route's own id (an empty id part when the route has none,
     * as the original builders did). $withId false leaves the id part out altogether (getNewLink()).
     * @param int|string|null $id
     */
    public static function getLink(string $action, int|string|null $id = null, bool $withId = true): string
    {
        $instance = self::getInstance();
        $module = rawurlencode($instance->_getModule());
        $submodule = rawurlencode((string)$instance->_getSubmodule());
        $action = rawurlencode($action);
        $idPart = $withId ? rawurlencode((string)(self::linkId($id) ?? $instance->_getId())) : null;

        if ($instance->isUsePrettyUrl()) {
            $link = '/' . $module . '/' . $submodule . '/' . $action;
            return $idPart === null ? $link : $link . '/' . $idPart;
        }
        $link = '?module=' . $module . '&submodule=' . $submodule . '&action=' . $action;
        return $idPart === null ? $link : $link . '&id=' . $idPart;
    }

    /** @param int|string|null $id */
    private static function linkId(int|string|null $id): int|string|null
    {
        if ($id === null || $id === '') {
            return null;
        }
        if ($id === 'all' || is_numeric($id) || self::isUuid((string)$id)) {
            return $id;
        }
        return null;
    }

    private static function redirect(string $location): void
    {
        header('Location: ' . $location);
        exit;
    }
}
