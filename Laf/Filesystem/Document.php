<?php

namespace Laf\Filesystem;

use claviska\SimpleImage;
use Laf\Util\Settings;

/**
 * Class Document
 * @package Laf\Filesystem
 */
class Document
{
	private $document_id = null;
	private $documentInstance = null;

	/**
	 * Instructors constructor.
	 * @param int $id
	 * @throws \Exception
	 */
	public function __construct($id = null)
	{
		$this->document_id = $id;
		$settings = Settings::getInstance();
		$documentClass = '\\'.$settings->getProperty('project.package_name').'\\Document';
		$this->documentInstance = new $documentClass($id);
	}

	/**
	 * Upload file and create a document entry
	 * @param $fieldName
	 * @return int
	 * @throws \Exception
	 */
	public static function upload($fieldName)
	{
		$settings = Settings::getInstance();
		$personClass = '\\'.$settings->getProperty('project.package_name').'\\Person';
		$user = $personClass::getLoggedUserInstance();
		$documentClass = $settings->getProperty('project.package_name').'\\Document';

		if (isset($_FILES[$fieldName])) {
			if (
				$_FILES[$fieldName]['size'] > 0
				&& $_FILES[$fieldName]['error'] == 0
				&& in_array($_FILES[$fieldName]['type'], $settings->getProperty('upload.allowed_mime_types'))
			) {
				$document = new $documentClass();
				$randomName = microtime(true);
				$document->setFileNameOriginalVal($_FILES[$fieldName]['name']);
				$document->setFileExtensionVal(strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION)));
				$document->setFileNameVal($randomName . '_full.' . $document->getFileExtensionVal());
				$document->setThumbnailNameVal($randomName . '_thumb.' . $document->getFileExtensionVal());
				$document->setFileSizeVal($_FILES[$fieldName]['size']);
				$document->setMimeTypeVal($_FILES[$fieldName]['type']);
				try {
					$i = new SimpleImage();
					$i->fromFile($_FILES[$fieldName]['tmp_name'])
						->bestFit(1200, 1200);
					$i->toFile($document->getFullPathFullSize());
					$i->bestFit('300', '300');
					$i->toFile($document->getFullPathThumbnail());
					$document->insert();
					return $document->getRecordId();
				} catch (\Exception $ex) {
					return null;
				}
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * Check if file exists in the filesystem
	 * @return bool
	 * @throws \Exception
	 */
	public function fileFullSizeExists()
	{
		if (!$this->documentInstance->recordExists())
			return false;

		return file_exists($this->getFullPathFullSize()) && is_readable($this->getFullPathFullSize());
	}

	/**
	 * Get file name including the full path
	 * @return string
	 * @throws \Exception
	 */
	public function getFullPathFullSize()
	{
		return $this->getDocumentsDirectory() . $this->documentInstance->getFileNameFld();
	}

	/**
	 * Path to documents directory
	 * Does not include a trailing /
	 * @return string
	 * @throws \Exception
	 */
	public function getDocumentsDirectory()
	{
		$settings = Settings::getInstance();
		return $settings->getProperty('upload.documents_directory');
	}

	/**
	 * Check if file exists in the filesystem
	 * @return bool
	 */
	public function fileThumbnailExists()
	{
		if (!$this->documentInstance->recordExists())
			return false;

		return file_exists($this->getFullPathThumbnail()) && is_readable($this->getFullPathThumbnail());
	}

	public function getFullPathThumbnail()
	{
		return $this->getDocumentsDirectory() . $this->documentInstance->getThumbnailNameVal();
	}

	/**
	 * @return bool
	 */
	public function isImage()
	{
		$settings = Settings::getInstance();
		if (in_array($this->documentInstance->getMimeTypeVal(), [
			'image/png',
			'image/jpeg',
			'image/jpg',
			'image/pjpeg',
			'image/gif',
		]))
			return true;
		return false;
	}

	/**
	 * @return bool
	 * @throws \Exception
	 */
	public function hardDelete()
	{
		$ok = $this->documentInstance->hardDelete();
		if ($ok) {
			if (file_exists($this->documentInstance->getFullPathFullSize()))
				unlink($this->getFullPathFullSize());
			if (file_exists($this->getFullPathThumbnail()))
				unlink($this->getFullPathThumbnail());
		}
		return $ok;
	}

	public function handleRequest($request = 'view')
	{
		header("Expires: Sun, 22 Jul 2030 19:07:47 GMT");
		header("Pragma: cache");
		header("Cache-Control: max-age=99999999, must-revalidate");
		header("Last-Modified: 22 Jul 2016 19:07:47 GMT");

		$doc = $this->documentInstance;
		$settings = Settings::getInstance();

		if ($doc->recordExists() && !$doc->getEncryptKeyVal()) {
			if ($request == 'view') {
				if (file_exists($doc->getFullPathFullSize())) {
					header('Content-Type:' . $doc->getMimeTypeVal());
					echo file_get_contents($doc->getFullPathFullSize());
					exit;
				} else {
					header('location:' . $settings->getProperty('404'));
					exit;
				}
			} else if ($request == 'download') {
				if (file_exists($doc->getFullPathFullSize())) {
					header('Content-Type:' . $doc->getMimeTypeVal());
					header("Content-Disposition:  attachment; filename=" . preg_replace("/[ ,]/", "_", $doc->getFileNameVal()) . "\r\n");
					echo file_get_contents($doc->getFullPathFullSize());
					exit;
				} else {
					$settings = Settings::getInstance();
					header('location:' . $settings->getProperty('404'));
					exit;
				}
			} else if ($request == 'thumbnail') {
				if (file_exists($doc->getFullPathThumbnail())) {
					header('Content-Type:' . $doc->getMimeTypeVal());
					echo file_get_contents($doc->getFullPathThumbnail());
					exit;
				} else {
					$settings = Settings::getInstance();
					header('location:' . $settings->getProperty('404'));
					exit;
				}
			} else {
				header('location:' . $settings->getProperty('404'));
				exit;
			}
		} else {
			header('location:' . $settings->getProperty('404'));
			exit;
		}

	}

}
