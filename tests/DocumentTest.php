<?php

declare(strict_types=1);

use Laf\Filesystem\Document;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Laf\Filesystem\Document without a database (2026-10-12 review): request checks in upload(), MIME
 * detection from the file, paths, isImage(), the not-found response and the streamed response.
 * Storing and loading records is covered by DocumentIntegrationTest.
 */
class DocumentTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/laf_document_test_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Settings::set('upload.documents_directory', $this->dir);
        Settings::set('upload.allowed_mime_types', ['image/png', 'image/jpeg', 'application/pdf']);
        Document::setModelClass(null);
        Document::setNotFoundUrl(null);
        unset($_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        Settings::remove('upload.documents_directory');
        Settings::remove('upload.allowed_mime_types');
        Settings::remove('upload.not_found_url');
        Settings::remove('upload.image_mime_types');
        Settings::remove('404');
        Document::setModelClass(null);
        Document::setNotFoundUrl(null);
        unset($_FILES['doc'], $_SERVER['HTTP_IF_NONE_MATCH'], $_SERVER['HTTP_IF_MODIFIED_SINCE']);
    }

    private function textFile(string $content = "hello\n"): string
    {
        $path = $this->dir . '/source_' . bin2hex(random_bytes(3)) . '.txt';
        file_put_contents($path, $content);
        return $path;
    }

    private function pngFile(int $width = 10, int $height = 10): string
    {
        $path = $this->dir . '/source_' . bin2hex(random_bytes(3)) . '.png';
        $image = imagecreatetruecolor($width, $height);
        imagepng($image, $path);
        return $path;
    }

    /** @test */
    public function upload_returns_null_when_nothing_usable_was_posted(): void
    {
        $this->assertNull(Document::upload('missing'));
        $this->assertNull(Document::upload('doc', ['name' => 'a.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]));
        $this->assertNull(Document::upload('doc', ['name' => 'a.png', 'tmp_name' => $this->pngFile(), 'error' => UPLOAD_ERR_PARTIAL, 'size' => 10]));
        $this->assertNull(Document::upload('doc', ['name' => 'a.txt', 'tmp_name' => $this->textFile(''), 'error' => UPLOAD_ERR_OK, 'size' => 0]), 'empty file');
        $this->assertNull(Document::upload('doc', ['name' => ['a.png'], 'tmp_name' => [$this->pngFile()], 'error' => [0], 'size' => [10]]), 'multi-file arrays belong to uploadMany()');
        $_FILES['doc'] = ['name' => 'a.png', 'tmp_name' => $this->dir . '/does-not-exist', 'error' => UPLOAD_ERR_OK, 'size' => 10];
        $this->assertNull(Document::upload('doc'));
        $this->assertSame([], glob($this->dir . '/*_full.*') ?: [], 'nothing stored');
    }

    /** @test */
    public function upload_many_skips_unusable_slots_and_accepts_a_single_file_entry(): void
    {
        $this->assertSame([], Document::uploadMany('missing'));
        $this->assertSame([], Document::uploadMany('doc', ['name' => 'a.png', 'tmp_name' => '', 'error' => UPLOAD_ERR_NO_FILE, 'size' => 0]), 'single-file entry, nothing posted');
        $_FILES['doc'] = [
            'name' => ['a.png', 'b.txt', 'c.png'],
            'type' => ['image/png', 'text/plain', 'image/png'],
            'tmp_name' => ['', $this->textFile(), $this->dir . '/does-not-exist'],
            'error' => [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK, UPLOAD_ERR_OK],
            'size' => [0, 6, 10],
        ];
        $this->assertSame([], Document::uploadMany('doc'), 'no file, a refused type, a missing file');
        $this->assertSame([], glob($this->dir . '/*_full.*') ?: [], 'nothing stored');
    }

    /** @test */
    public function the_mime_type_comes_from_the_file_not_from_the_request(): void
    {
        $text = $this->textFile();
        $this->assertSame('text/plain', Document::detectMimeType($text));
        $this->assertSame('image/png', Document::detectMimeType($this->pngFile()));
        $this->assertNull(Document::detectMimeType($this->dir . '/nope'));
        // a text file posted as image/png is refused: text/plain is not in the allowed list
        $this->assertNull(Document::upload('doc', ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $text, 'error' => UPLOAD_ERR_OK, 'size' => 6]));
    }

    /** @test */
    public function allowed_mime_types_accept_exact_entries_and_wildcards(): void
    {
        $this->assertTrue(Document::isAllowedMimeType('image/png'));
        $this->assertFalse(Document::isAllowedMimeType('image/gif'));
        $this->assertFalse(Document::isAllowedMimeType('text/plain'));
        Settings::set('upload.allowed_mime_types', ['image/*', 'Application/PDF ']);
        $this->assertTrue(Document::isAllowedMimeType('image/gif'));
        $this->assertTrue(Document::isAllowedMimeType('application/pdf'));
        $this->assertFalse(Document::isAllowedMimeType('text/plain'));
        Settings::set('upload.allowed_mime_types', '*/*');
        $this->assertTrue(Document::isAllowedMimeType('text/plain'));
    }

    /** @test */
    public function store_file_refuses_a_missing_source_and_an_unwritable_directory(): void
    {
        try {
            Document::storeFile($this->dir . '/nope.txt');
            $this->fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $ex) {
            $this->assertStringContainsString('nope.txt', $ex->getMessage());
        }
        Settings::set('upload.documents_directory', $this->dir . '/missing/');
        try {
            Document::storeFile($this->textFile());
            $this->fail('expected RuntimeException');
        } catch (\RuntimeException $ex) {
            $this->assertStringContainsString('documents directory', $ex->getMessage());
        }
    }

    /** @test */
    public function the_wrapper_needs_a_document_model(): void
    {
        Document::setModelClass('Nope\\Document');
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Document::setModelClass()');
        new Document();
    }

    /** @test */
    public function paths_are_built_from_the_record_values_and_the_directory_gets_one_slash(): void
    {
        $document = new Document();
        $this->assertNull($document->getId());
        $this->assertFalse($document->recordExists());
        $this->assertSame($this->dir . '/', $document->getDocumentsDirectory());
        $this->assertSame('', $document->getFullPathFullSize());
        $this->assertSame('', $document->getFullPathThumbnail());
        $this->assertFalse($document->fileFullSizeExists());
        $this->assertFalse($document->fileThumbnailExists(), 'no thumbnail name must not match the directory itself');

        Settings::set('upload.documents_directory', $this->dir . '/');
        $document->getModel()->setFieldValues(['id' => 7, 'file_name' => 'a_full.txt', 'thumbnail_name' => 'a_thumb.txt']);
        $this->assertSame(7, $document->getId());
        $this->assertTrue($document->recordExists());
        $this->assertSame($this->dir . '/a_full.txt', $document->getFullPathFullSize());
        $this->assertSame($this->dir . '/a_thumb.txt', $document->getFullPathThumbnail());
        $this->assertFalse($document->fileFullSizeExists());
        file_put_contents($this->dir . '/a_full.txt', 'x');
        $this->assertTrue($document->fileFullSizeExists());
        $this->assertFalse($document->fileThumbnailExists());
    }

    /** @test */
    public function is_image_follows_the_stored_mime_type(): void
    {
        $document = new Document();
        $this->assertNull($document->getMimeType());
        $this->assertFalse($document->isImage());
        $document->getModel()->setFieldValue('mime_type', 'image/WebP');
        $this->assertSame('image/WebP', $document->getMimeType());
        $this->assertTrue($document->isImage());
        $document->getModel()->setFieldValue('mime_type', 'application/pdf');
        $this->assertFalse($document->isImage());
        Settings::set('upload.image_mime_types', ['application/pdf']);
        $this->assertTrue($document->isImage());
    }

    /** @test */
    public function nothing_to_serve_redirects_to_the_not_found_url_or_answers_404(): void
    {
        $document = new CapturingDocument();
        ob_start();
        $document->handleRequest('view', false);
        $body = ob_get_clean();
        $this->assertSame(['HTTP/1.1 404 Not Found' => 404, 'Content-Type: text/plain; charset=utf-8' => 0], $document->headers);
        $this->assertSame('Document not found', $body);

        Settings::set('404', '/legacy-404');
        $document = new CapturingDocument();
        ob_start();
        $document->handleRequest('view', false);
        ob_end_clean();
        $this->assertSame(['Location: /legacy-404' => 302], $document->headers);

        Settings::set('upload.not_found_url', '/missing');
        $this->assertSame('/missing', Document::notFoundUrl());
        Document::setNotFoundUrl('/explicit');
        $this->assertSame('/explicit', Document::notFoundUrl());

        // a record with an encrypt key, an unknown action and a missing file are not served either
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt', 'encrypt_key' => 'k']);
        $document->handleRequest('view', false);
        $this->assertSame(['Location: /explicit' => 302], $document->headers);
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt']);
        $document->handleRequest('view', false);
        $this->assertSame(['Location: /explicit' => 302], $document->headers, 'file missing');
        file_put_contents($this->dir . '/a_full.txt', 'x');
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt']);
        $document->handleRequest('edit', false);
        $this->assertSame(['Location: /explicit' => 302], $document->headers, 'unknown action');
    }

    /** @test */
    public function the_file_is_streamed_with_type_length_caching_and_disposition_headers(): void
    {
        file_put_contents($this->dir . '/a_full.txt', 'hello');
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt', 'file_name_original' => 'Jänner "Bericht".txt', 'mime_type' => 'text/plain']);

        ob_start();
        $document->handleRequest('view', false);
        $body = ob_get_clean();
        $this->assertSame('hello', $body);
        $headers = array_keys($document->headers);
        $this->assertContains('Content-Type: text/plain', $headers);
        $this->assertContains('Content-Length: 5', $headers);
        $this->assertContains('Cache-Control: private, max-age=' . Document::DEFAULT_CACHE_MAX_AGE, $headers);
        $this->assertContains("Content-Disposition: inline; filename=\"J_nner _Bericht_.txt\"; filename*=UTF-8''J%C3%A4nner%20_Bericht_.txt", $headers);
        $etag = null;
        foreach ($headers as $header) {
            if (strncmp($header, 'ETag: ', 6) === 0) {
                $etag = substr($header, 6);
            }
            if (strncmp($header, 'Last-Modified: ', 15) === 0) {
                $this->assertStringEndsWith(' GMT', $header);
            }
        }
        $this->assertNotNull($etag);

        // download = attachment; a bogus stored type becomes octet-stream
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt', 'mime_type' => 'not a type']);
        ob_start();
        $document->handleRequest('download', false);
        ob_end_clean();
        $headers = array_keys($document->headers);
        $this->assertContains('Content-Type: application/octet-stream', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="a_full.txt"', $headers, 'no original name: the stored name');

        // active content is never rendered inline: an SVG (or HTML) is forced to an octet-stream attachment
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt', 'file_name_original' => 'logo.svg', 'mime_type' => 'image/svg+xml']);
        ob_start();
        $document->handleRequest('view', false);
        ob_end_clean();
        $headers = array_keys($document->headers);
        $this->assertContains('Content-Type: application/octet-stream', $headers);
        $this->assertContains('Content-Disposition: attachment; filename="logo.svg"', $headers);
        $this->assertContains("Content-Security-Policy: sandbox; default-src 'none'", $headers);
        $this->assertNotContains('Content-Type: image/svg+xml', $headers);

        // thumbnail without a thumbnail file = the file itself
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt', 'thumbnail_name' => 'a_thumb.txt', 'mime_type' => 'text/plain']);
        ob_start();
        $document->handleRequest('thumbnail', false);
        $this->assertSame('hello', ob_get_clean());

        // conditional request
        $_SERVER['HTTP_IF_NONE_MATCH'] = $etag;
        $document = new CapturingDocument();
        $document->getModel()->setFieldValues(['id' => 1, 'file_name' => 'a_full.txt', 'mime_type' => 'text/plain']);
        ob_start();
        $document->handleRequest('view', false);
        $this->assertSame('', ob_get_clean());
        $this->assertSame(304, $document->headers['HTTP/1.1 304 Not Modified']);
        $this->assertArrayNotHasKey('Content-Length: 5', $document->headers);
    }
}

class CapturingDocument extends Document
{
    /** @var array<string, int> header => response code */
    public array $headers = [];

    protected function sendHeader(string $header, int $responseCode = 0): void
    {
        $this->headers[$header] = $responseCode;
    }
}
