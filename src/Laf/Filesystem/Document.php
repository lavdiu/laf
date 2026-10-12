<?php

namespace Laf\Filesystem;

use claviska\SimpleImage;
use Laf\Database\BaseObject;
use Laf\Util\Settings;

/**
 * A stored file: the wrapper around the application's Document model (`<package>\Document`, generated from
 * a table with file_name, file_name_original, file_extension, thumbnail_name, file_size, mime_type and
 * optionally encrypt_key).
 *
 * `Document::upload('field')` stores the file posted in a form field, `Document::storeFile($path)` a file
 * that is already on disk; both read the MIME type from the file itself, resize images to
 * `upload.max_image_size` with a `upload.thumbnail_size` thumbnail, store other files as they are, insert
 * the record and return its id. `new Document($id)` locates the stored files (`fileFullSizeExists()`,
 * `getFullPathFullSize()`, `isImage()`), `handleRequest('view'|'download'|'thumbnail')` streams one with
 * caching headers, `hardDelete()` removes record and files.
 *
 * Settings: `upload.documents_directory` (required), `upload.allowed_mime_types` (required by upload();
 * `image/*` wildcards allowed), `upload.max_image_size` (1200), `upload.thumbnail_size` (300),
 * `upload.image_mime_types` (what isImage() accepts), `upload.not_found_url` (fallback: the legacy `404`
 * key, else a 404 response), `upload.cache_max_age` (seconds, default one year).
 */
class Document
{
    public const ACTION_VIEW = 'view';
    public const ACTION_DOWNLOAD = 'download';
    public const ACTION_THUMBNAIL = 'thumbnail';

    public const DEFAULT_MAX_IMAGE_SIZE = 1200;
    public const DEFAULT_THUMBNAIL_SIZE = 300;
    public const DEFAULT_CACHE_MAX_AGE = 31536000;

    private const DEFAULT_IMAGE_MIME_TYPES = [
        'image/png', 'image/jpeg', 'image/jpg', 'image/pjpeg', 'image/gif', 'image/webp', 'image/bmp',
        'image/avif', 'image/svg+xml',
    ];

    /** MIME type => file extension for the images that are resized */
    private const RESIZABLE_IMAGES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/bmp' => 'bmp',
        'image/avif' => 'avif',
    ];

    /** types the browser may render inline; anything else is sent as an attachment (SVG, HTML, XML can carry scripts) */
    private const INLINE_SAFE_MIME_TYPES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/bmp', 'image/avif', 'application/pdf', 'text/plain',
    ];

    private const EXTENSION_BY_MIME = [
        'application/pdf' => 'pdf',
        'image/svg+xml' => 'svg',
        'text/plain' => 'txt',
    ];

    private static ?string $modelClass = null;
    private static ?string $notFoundUrl = null;

    private BaseObject $model;

    /**
     * @param int|string|null $id the document id; null for a wrapper without a record
     * @throws \LogicException when the application has no Document model
     */
    public function __construct($id = null)
    {
        $class = self::modelClass();
        $this->model = new $class($id === '' ? null : $id);
    }

    /**
     * The application's Document model class; null restores the default `<package>\Document`
     */
    public static function setModelClass(?string $class): void
    {
        self::$modelClass = $class === null ? null : '\\' . ltrim($class, '\\');
    }

    /**
     * Where handleRequest() redirects when there is nothing to serve; null restores the Settings lookup
     * (`upload.not_found_url`, then the legacy `404` key)
     */
    public static function setNotFoundUrl(?string $url): void
    {
        self::$notFoundUrl = $url;
    }

    /**
     * The model class name with a leading backslash
     * @throws \LogicException
     */
    private static function modelClass(): string
    {
        $class = self::$modelClass ?? '\\' . Settings::getString('project.package_name') . '\\Document';
        if (!class_exists($class) || !is_subclass_of($class, BaseObject::class)) {
            throw new \LogicException(sprintf(
                'Document model %s not found: generate the document table or call %s::setModelClass()',
                $class,
                self::class
            ));
        }
        return $class;
    }

    /**
     * Stores the file posted in $fieldName and creates the document record.
     *
     * The MIME type is read from the file, not from the request, and checked against
     * `upload.allowed_mime_types`. Images are resized and get a thumbnail, other files are stored as they are.
     *
     * @param array|null $file one `$_FILES` entry (name, tmp_name, error, size) to use instead of `$_FILES[$fieldName]`
     * @return int|null the new document id; null when nothing usable was posted (field absent, upload error,
     *                  empty file, or a MIME type that is not allowed)
     * @throws \RuntimeException when the file cannot be stored (directory, image processing, database)
     */
    public static function upload(string $fieldName, ?array $file = null): ?int
    {
        $file = $file ?? ($_FILES[$fieldName] ?? null);
        if (!is_array($file) || !isset($file['tmp_name']) || is_array($file['tmp_name'])) {
            return null;
        }
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        $path = (string)$file['tmp_name'];
        if ($path === '' || !is_file($path) || (int)filesize($path) <= 0) {
            return null;
        }
        $mimeType = self::detectMimeType($path) ?? (string)($file['type'] ?? '');
        if (!self::isAllowedMimeType($mimeType)) {
            return null;
        }
        return self::storeFile($path, (string)($file['name'] ?? ''), $mimeType);
    }

    /**
     * Stores a file that is already on disk (a download, a generated report) as a document and returns the
     * new id. An uploaded file is moved, any other file is copied. Images (`image/png`, `jpeg`, `gif`,
     * `webp`, `bmp`, `avif`) are resized to `upload.max_image_size` and get a `upload.thumbnail_size`
     * thumbnail; other files are stored unchanged. `upload.allowed_mime_types` is not consulted here.
     *
     * @param string $originalName the name to record (default: the file's base name)
     * @param string|null $mimeType the type to record (default: read from the file)
     * @throws \InvalidArgumentException when $path is not a readable file
     * @throws \RuntimeException when the file cannot be stored (directory, image processing, database);
     *                           files written so far are removed
     */
    public static function storeFile(string $path, string $originalName = '', ?string $mimeType = null): ?int
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('Not a readable file: ' . $path);
        }
        $mimeType = $mimeType ?? self::detectMimeType($path) ?? 'application/octet-stream';
        $originalName = $originalName !== '' ? $originalName : basename($path);
        $directory = self::writableDocumentsDirectory();
        $resize = isset(self::RESIZABLE_IMAGES[$mimeType]) && class_exists(SimpleImage::class);
        $extension = self::extensionFor($originalName, $mimeType, $resize);
        $baseName = static::generateBaseName();
        $fullName = $baseName . '_full' . ($extension !== '' ? '.' . $extension : '');
        $thumbnailName = $resize ? $baseName . '_thumb.' . $extension : null;

        $written = [];
        try {
            if ($resize) {
                $image = (new SimpleImage())->fromFile($path);
                $image->bestFit(self::maxImageSize(), self::maxImageSize())->toFile($directory . $fullName, $mimeType);
                $written[] = $directory . $fullName;
                $image->bestFit(self::thumbnailSize(), self::thumbnailSize())->toFile($directory . $thumbnailName, $mimeType);
                $written[] = $directory . $thumbnailName;
            } else {
                $stored = is_uploaded_file($path)
                    ? move_uploaded_file($path, $directory . $fullName)
                    : copy($path, $directory . $fullName);
                if (!$stored) {
                    throw new \RuntimeException('Could not write ' . $directory . $fullName);
                }
                $written[] = $directory . $fullName;
            }

            $class = self::modelClass();
            /** @var BaseObject $model */
            $model = new $class();
            $model->setFieldValues([
                'file_name_original' => $originalName,
                'file_extension' => $extension,
                'file_name' => $fullName,
                'thumbnail_name' => $thumbnailName,
                'file_size' => (int)filesize($directory . $fullName),
                'mime_type' => $mimeType,
            ]);
            $model->insert();
            $id = $model->getRecordId();
            return is_numeric($id) ? (int)$id : null;
        } catch (\Throwable $ex) {
            foreach ($written as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            if ($ex instanceof \RuntimeException) {
                throw $ex;
            }
            throw new \RuntimeException('Could not store ' . $originalName . ': ' . $ex->getMessage(), 0, $ex);
        }
    }

    /**
     * The MIME type read from the file's content (finfo); null when it cannot be determined
     */
    public static function detectMimeType(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $mimeType = false;
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($path);
        } elseif (function_exists('mime_content_type')) {
            $mimeType = mime_content_type($path);
        }
        return is_string($mimeType) && $mimeType !== '' ? $mimeType : null;
    }

    /**
     * Whether `upload.allowed_mime_types` accepts the type (exact entries or `type/*` wildcards)
     * @throws \Laf\Exception\MissingConfigParamException when the list is not configured
     */
    public static function isAllowedMimeType(string $mimeType): bool
    {
        $allowed = Settings::get('upload.allowed_mime_types');
        if (!is_array($allowed)) {
            $allowed = [(string)$allowed];
        }
        foreach ($allowed as $entry) {
            $entry = strtolower(trim((string)$entry));
            if ($entry === $mimeType || $entry === '*/*') {
                return true;
            }
            if (substr($entry, -2) === '/*' && strncmp($mimeType, substr($entry, 0, -1), strlen($entry) - 1) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * The stored file's base name (the `_full` / `_thumb` suffix and extension are added): the time and
     * six random bytes
     */
    protected static function generateBaseName(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(6));
    }

    /**
     * The extension to store: the original name's, lower-cased and reduced to letters and digits (at most
     * ten), or one derived from the MIME type; a resized image always gets the extension of its type
     */
    private static function extensionFor(string $originalName, string $mimeType, bool $resize): string
    {
        if ($resize) {
            return self::RESIZABLE_IMAGES[$mimeType];
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $extension = substr((string)preg_replace('/[^a-z0-9]/', '', $extension), 0, 10);
        if ($extension !== '') {
            return $extension;
        }
        return self::EXTENSION_BY_MIME[$mimeType] ?? self::RESIZABLE_IMAGES[$mimeType] ?? '';
    }

    private static function maxImageSize(): int
    {
        return max(1, Settings::getInt('upload.max_image_size', self::DEFAULT_MAX_IMAGE_SIZE));
    }

    private static function thumbnailSize(): int
    {
        return max(1, Settings::getInt('upload.thumbnail_size', self::DEFAULT_THUMBNAIL_SIZE));
    }

    /**
     * The documents directory with a trailing slash
     * @throws \Laf\Exception\MissingConfigParamException when `upload.documents_directory` is not configured
     */
    private static function documentsDirectory(): string
    {
        return rtrim(Settings::getString('upload.documents_directory'), '/\\') . '/';
    }

    /**
     * @throws \RuntimeException when the directory does not exist or is not writable
     */
    private static function writableDocumentsDirectory(): string
    {
        $directory = self::documentsDirectory();
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \RuntimeException('The documents directory is missing or not writable: ' . $directory);
        }
        return $directory;
    }

    /**
     * The application's Document model behind this wrapper
     */
    public function getModel(): BaseObject
    {
        return $this->model;
    }

    /**
     * The document id, null for a wrapper without a record
     */
    public function getId(): ?int
    {
        $key = $this->model->getTable()->getPrimaryKey();
        $id = $key !== null && $key->getFirstField() !== null ? $key->getFirstField()->getValue() : null;
        if ($id === null || $id === '') {
            $id = $this->model->getRecordId();
        }
        return is_numeric($id) ? (int)$id : null;
    }

    /**
     * Whether the wrapper holds a record
     */
    public function recordExists(): bool
    {
        return $this->model->recordExists();
    }

    /**
     * Path to the documents directory, with a trailing slash (`upload.documents_directory`)
     * @throws \Laf\Exception\MissingConfigParamException
     */
    public function getDocumentsDirectory(): string
    {
        return self::documentsDirectory();
    }

    /**
     * The stored file's path; '' when the record has no file name
     */
    public function getFullPathFullSize(): string
    {
        $name = (string)($this->model->getFieldValue('file_name') ?? '');
        return $name === '' ? '' : $this->getDocumentsDirectory() . $name;
    }

    /**
     * The thumbnail's path; '' when the record has no thumbnail
     */
    public function getFullPathThumbnail(): string
    {
        $name = (string)($this->model->getFieldValue('thumbnail_name') ?? '');
        return $name === '' ? '' : $this->getDocumentsDirectory() . $name;
    }

    /**
     * Whether the record exists and its file is on disk and readable
     */
    public function fileFullSizeExists(): bool
    {
        return $this->recordExists() && self::isReadableFile($this->getFullPathFullSize());
    }

    /**
     * Whether the record exists and its thumbnail is on disk and readable
     */
    public function fileThumbnailExists(): bool
    {
        return $this->recordExists() && self::isReadableFile($this->getFullPathThumbnail());
    }

    private static function isReadableFile(string $path): bool
    {
        return $path !== '' && is_file($path) && is_readable($path);
    }

    /**
     * The stored MIME type, null when the record has none
     */
    public function getMimeType(): ?string
    {
        $mimeType = $this->model->getFieldValue('mime_type');
        return $mimeType === null || $mimeType === '' ? null : (string)$mimeType;
    }

    /**
     * Whether the stored MIME type is an image (`upload.image_mime_types`, default the common web formats)
     */
    public function isImage(): bool
    {
        $mimeType = $this->getMimeType();
        if ($mimeType === null) {
            return false;
        }
        $types = Settings::has('upload.image_mime_types') ? Settings::get('upload.image_mime_types') : self::DEFAULT_IMAGE_MIME_TYPES;
        return in_array(strtolower($mimeType), array_map('strtolower', (array)$types), true);
    }

    /**
     * Deletes the record and, when that succeeded, the stored file and thumbnail
     */
    public function hardDelete(): bool
    {
        $fullPath = $this->getFullPathFullSize();
        $thumbnailPath = $this->getFullPathThumbnail();
        if (!$this->model->hardDelete()) {
            return false;
        }
        foreach ([$fullPath, $thumbnailPath] as $path) {
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        return true;
    }

    /**
     * Sends the stored file and ends the request: `view` inline, `download` as an attachment named after the
     * original file, `thumbnail` the thumbnail (or the file itself when there is none). Only raster images,
     * PDF and plain text are sent inline; every other type (SVG, HTML, XML, Office files) goes out as an
     * `application/octet-stream` attachment, and every response carries a sandboxing Content-Security-Policy,
     * so an uploaded file can never run as a page of the site. A missing record, a record with an encrypt key
     * or a missing file redirects to the not-found URL, or answers 404 when none is configured. Conditional
     * requests get 304. $exit = false returns instead of ending the script.
     */
    public function handleRequest(string $request = self::ACTION_VIEW, bool $exit = true): void
    {
        $this->serve($request);
        if ($exit) {
            exit;
        }
    }

    /**
     * The not-found URL: setNotFoundUrl(), `upload.not_found_url`, the legacy `404` key, or null
     */
    public static function notFoundUrl(): ?string
    {
        if (self::$notFoundUrl !== null) {
            return self::$notFoundUrl;
        }
        foreach (['upload.not_found_url', '404'] as $key) {
            $url = Settings::getString($key, '');
            if ($url !== '') {
                return $url;
            }
        }
        return null;
    }

    private function serve(string $request): void
    {
        if (!$this->recordExists() || $this->isEncrypted()) {
            $this->sendNotFound();
            return;
        }
        switch ($request) {
            case self::ACTION_VIEW:
            case self::ACTION_DOWNLOAD:
                $path = $this->getFullPathFullSize();
                break;
            case self::ACTION_THUMBNAIL:
                $path = $this->fileThumbnailExists() ? $this->getFullPathThumbnail() : $this->getFullPathFullSize();
                break;
            default:
                $this->sendNotFound();
                return;
        }
        if (!self::isReadableFile($path)) {
            $this->sendNotFound();
            return;
        }

        $modified = (int)filemtime($path);
        $size = (int)filesize($path);
        $etag = '"' . md5(basename($path) . '|' . $modified . '|' . $size) . '"';
        $this->sendHeader('Cache-Control: private, max-age=' . max(0, Settings::getInt('upload.cache_max_age', self::DEFAULT_CACHE_MAX_AGE)));
        $this->sendHeader('Last-Modified: ' . gmdate('D, d M Y H:i:s', $modified) . ' GMT');
        $this->sendHeader('ETag: ' . $etag);

        if ($this->isNotModified($etag, $modified)) {
            $this->sendHeader('HTTP/1.1 304 Not Modified', 304);
            return;
        }

        $type = $this->contentType();
        $safe = in_array($type, self::INLINE_SAFE_MIME_TYPES, true);
        $inline = $safe && $request !== self::ACTION_DOWNLOAD;
        $this->sendHeader('Content-Type: ' . ($safe ? $type : 'application/octet-stream'));
        $this->sendHeader('Content-Length: ' . $size);
        $this->sendHeader('X-Content-Type-Options: nosniff');
        $this->sendHeader("Content-Security-Policy: sandbox; default-src 'none'");
        $this->sendHeader('Content-Disposition: ' . self::contentDisposition(
            $inline ? 'inline' : 'attachment',
            $this->downloadName($request === self::ACTION_THUMBNAIL ? basename($path) : null)
        ));
        $this->sendBody($path);
    }

    private function isEncrypted(): bool
    {
        if (!$this->model->getTable()->hasField('encrypt_key')) {
            return false;
        }
        $key = $this->model->getFieldValue('encrypt_key');
        return $key !== null && $key !== '';
    }

    private function isNotModified(string $etag, int $modified): bool
    {
        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;
        if (is_string($ifNoneMatch) && $ifNoneMatch !== '') {
            return in_array($etag, array_map('trim', explode(',', $ifNoneMatch)), true);
        }
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
        if (is_string($ifModifiedSince) && $ifModifiedSince !== '') {
            $since = strtotime($ifModifiedSince);
            return $since !== false && $since >= $modified;
        }
        return false;
    }

    /**
     * The stored MIME type when it looks like one, else application/octet-stream
     */
    private function contentType(): string
    {
        $mimeType = $this->getMimeType();
        return $mimeType !== null && preg_match('#^[\w.+-]+/[\w.+-]+$#', $mimeType) === 1 ? $mimeType : 'application/octet-stream';
    }

    /**
     * The name the browser sees: the original name, else the stored name
     */
    private function downloadName(?string $fallback): string
    {
        $name = (string)($this->model->getFieldValue('file_name_original') ?? '');
        if ($name === '') {
            $name = (string)($this->model->getFieldValue('file_name') ?? '');
        }
        if ($name === '' && $fallback !== null) {
            $name = $fallback;
        }
        $name = (string)preg_replace('/[\x00-\x1F\x7F\\\\\/:*?"<>|]+/', '_', $name);
        return $name === '' ? 'document' : $name;
    }

    /**
     * A Content-Disposition value with an ASCII filename and the UTF-8 name (RFC 6266)
     */
    private static function contentDisposition(string $type, string $name): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/u', '_', $name);
        if ($ascii === null) {
            $ascii = (string)preg_replace('/[^\x20-\x7E]/', '_', $name);
        }
        $value = $type . '; filename="' . str_replace('"', '_', $ascii) . '"';
        if ($ascii !== $name) {
            $value .= "; filename*=UTF-8''" . rawurlencode($name);
        }
        return $value;
    }

    private function sendNotFound(): void
    {
        $url = self::notFoundUrl();
        if ($url !== null) {
            $this->sendHeader('Location: ' . $url, 302);
            return;
        }
        $this->sendHeader('HTTP/1.1 404 Not Found', 404);
        $this->sendHeader('Content-Type: text/plain; charset=utf-8');
        echo 'Document not found';
    }

    /**
     * One response header; overridable (tests capture them)
     */
    protected function sendHeader(string $header, int $responseCode = 0): void
    {
        if (!headers_sent()) {
            header($header, true, $responseCode);
        }
    }

    /**
     * The file's content, streamed; overridable
     */
    protected function sendBody(string $path): void
    {
        readfile($path);
    }
}
