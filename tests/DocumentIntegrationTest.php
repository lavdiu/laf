<?php

declare(strict_types=1);

use Laf\Database\AuditLog;
use Laf\Database\Db;
use Laf\Filesystem\Document;
use Laf\Util\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Laf\Filesystem\Document against a database (MariaDB or PostgreSQL, see tests/bootstrap.php): storing an
 * upload and a file from disk, the record that is written, image resizing and thumbnails, loading by id,
 * serving and deleting.
 */
class DocumentIntegrationTest extends TestCase
{
    private static bool $dbAvailable = false;
    private string $dir = '';

    public static function setUpBeforeClass(): void
    {
        try {
            Db::getInstance();
            self::$dbAvailable = true;
        } catch (\Exception $e) {
            self::$dbAvailable = false;
            return;
        }
        if ((Settings::get('database.engine') ?: 'mysql') === 'postgres') {
            Db::run("DROP TABLE IF EXISTS document;
                CREATE TABLE document (
                  id SERIAL PRIMARY KEY,
                  file_name_original VARCHAR(255) DEFAULT NULL,
                  file_name VARCHAR(255) DEFAULT NULL,
                  file_extension VARCHAR(10) DEFAULT NULL,
                  thumbnail_name VARCHAR(255) DEFAULT NULL,
                  file_size INTEGER DEFAULT NULL,
                  mime_type VARCHAR(255) DEFAULT NULL,
                  encrypt_key VARCHAR(255) DEFAULT NULL,
                  created_on TIMESTAMP DEFAULT NULL,
                  created_by INTEGER DEFAULT NULL,
                  updated_on TIMESTAMP DEFAULT NULL,
                  updated_by INTEGER DEFAULT NULL
                );");
        } else {
            Db::run("DROP TABLE IF EXISTS `document`;
                CREATE TABLE `document` (
                  `id` INT NOT NULL AUTO_INCREMENT,
                  `file_name_original` VARCHAR(255) DEFAULT NULL,
                  `file_name` VARCHAR(255) DEFAULT NULL,
                  `file_extension` VARCHAR(10) DEFAULT NULL,
                  `thumbnail_name` VARCHAR(255) DEFAULT NULL,
                  `file_size` INT DEFAULT NULL,
                  `mime_type` VARCHAR(255) DEFAULT NULL,
                  `encrypt_key` VARCHAR(255) DEFAULT NULL,
                  `created_on` DATETIME DEFAULT NULL,
                  `created_by` INT DEFAULT NULL,
                  `updated_on` DATETIME DEFAULT NULL,
                  `updated_by` INT DEFAULT NULL,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
    }

    protected function setUp(): void
    {
        if (!self::$dbAvailable) {
            $this->markTestSkipped('No database connection (set LAF_DB_* environment variables)');
        }
        AuditLog::disable();
        $this->dir = sys_get_temp_dir() . '/laf_document_it_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Settings::set('upload.documents_directory', $this->dir . '/');
        Settings::set('upload.allowed_mime_types', ['image/png', 'text/plain']);
        Settings::set('upload.max_image_size', 400);
        Settings::set('upload.thumbnail_size', 100);
        Document::setNotFoundUrl('/missing');
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            foreach (glob($this->dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
        foreach (['upload.documents_directory', 'upload.allowed_mime_types', 'upload.max_image_size', 'upload.thumbnail_size'] as $key) {
            Settings::remove($key);
        }
        Document::setNotFoundUrl(null);
        AuditLog::enable();
    }

    private function sourcePng(int $width, int $height): string
    {
        $path = $this->dir . '/source.png';
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int)imagecolorallocate($image, 200, 30, 30));
        imagepng($image, $path);
        return $path;
    }

    /** @test */
    public function an_uploaded_image_is_resized_stored_with_a_thumbnail_and_recorded(): void
    {
        $source = $this->sourcePng(800, 400);
        $id = Document::upload('photo', ['name' => 'Holiday Photo.PNG', 'type' => 'text/plain', 'tmp_name' => $source, 'error' => UPLOAD_ERR_OK, 'size' => filesize($source)]);
        $this->assertIsInt($id);

        $document = new Document($id);
        $this->assertSame($id, $document->getId());
        $this->assertTrue($document->recordExists());
        $this->assertTrue($document->isImage());
        $this->assertTrue($document->fileFullSizeExists());
        $this->assertTrue($document->fileThumbnailExists());
        $model = $document->getModel();
        $this->assertSame('Holiday Photo.PNG', $model->getFieldValue('file_name_original'));
        $this->assertSame('png', $model->getFieldValue('file_extension'));
        $this->assertSame('image/png', $model->getFieldValue('mime_type'), 'the type read from the file, not the posted one');
        $this->assertMatchesRegularExpression('/^\d{14}_[0-9a-f]{12}_full\.png$/', (string)$model->getFieldValue('file_name'));
        $this->assertMatchesRegularExpression('/^\d{14}_[0-9a-f]{12}_thumb\.png$/', (string)$model->getFieldValue('thumbnail_name'));
        $this->assertSame((int)filesize($document->getFullPathFullSize()), (int)$model->getFieldValue('file_size'));

        [$width, $height] = getimagesize($document->getFullPathFullSize());
        $this->assertSame([400, 200], [$width, $height], 'bestFit into upload.max_image_size');
        [$width, $height] = getimagesize($document->getFullPathThumbnail());
        $this->assertSame([100, 50], [$width, $height], 'thumbnail into upload.thumbnail_size');
        $this->assertFileExists($source, 'a file that is not an upload is copied, not moved');
    }

    /** @test */
    public function a_file_from_disk_that_is_not_an_image_is_stored_unchanged_without_a_thumbnail(): void
    {
        $source = $this->dir . '/report.txt';
        file_put_contents($source, "line 1\nline 2\n");
        $id = Document::storeFile($source, 'Report (final).txt');
        $this->assertIsInt($id);

        $document = new Document($id);
        $this->assertFalse($document->isImage());
        $this->assertTrue($document->fileFullSizeExists());
        $this->assertFalse($document->fileThumbnailExists());
        $this->assertNull($document->getModel()->getFieldValue('thumbnail_name'));
        $this->assertSame('text/plain', $document->getMimeType());
        $this->assertSame('txt', $document->getModel()->getFieldValue('file_extension'));
        $this->assertSame("line 1\nline 2\n", file_get_contents($document->getFullPathFullSize()));

        $served = new CapturingIntegrationDocument($id);
        ob_start();
        $served->handleRequest(Document::ACTION_DOWNLOAD, false);
        $body = ob_get_clean();
        $this->assertSame("line 1\nline 2\n", $body);
        $this->assertContains('Content-Disposition: attachment; filename="Report (final).txt"', array_keys($served->headers));
        $this->assertContains('Content-Type: text/plain', array_keys($served->headers));

        $this->assertTrue($document->hardDelete());
        $this->assertFileDoesNotExist($document->getFullPathFullSize());
        $this->assertFalse((new Document($id))->recordExists());
        $this->assertFalse((new Document())->hardDelete(), 'nothing loaded');
    }

    /** @test */
    public function upload_many_stores_each_usable_file_of_a_multi_file_field(): void
    {
        $first = $this->dir . '/first.txt';
        $second = $this->dir . '/second.txt';
        file_put_contents($first, 'first');
        file_put_contents($second, 'second');
        $ids = Document::uploadMany('files', [
            'name' => ['First.txt', 'empty.txt', 'Second.txt'],
            'type' => ['text/plain', 'text/plain', 'text/plain'],
            'tmp_name' => [$first, '', $second],
            'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            'size' => [5, 0, 6],
        ]);
        $this->assertCount(2, $ids);
        $this->assertSame('First.txt', (new Document($ids[0]))->getModel()->getFieldValue('file_name_original'));
        $this->assertSame('Second.txt', (new Document($ids[1]))->getModel()->getFieldValue('file_name_original'));
        $this->assertSame('second', file_get_contents((new Document($ids[1]))->getFullPathFullSize()));
    }

    /** @test */
    public function a_failed_store_leaves_no_files_behind(): void
    {
        $source = $this->dir . '/report.txt';
        file_put_contents($source, 'x');
        $before = count(glob($this->dir . '/*') ?: []);
        try {
            Document::storeFile($source, str_repeat('n', 300) . '.txt');
            $this->fail('expected a RuntimeException for a name longer than the column');
        } catch (\RuntimeException $ex) {
            $this->assertStringContainsString('Could not store', $ex->getMessage());
            $this->assertNotNull($ex->getPrevious());
        }
        $this->assertCount($before, glob($this->dir . '/*') ?: []);
    }
}

class CapturingIntegrationDocument extends Document
{
    /** @var array<string, int> */
    public array $headers = [];

    protected function sendHeader(string $header, int $responseCode = 0): void
    {
        $this->headers[$header] = $responseCode;
    }
}
