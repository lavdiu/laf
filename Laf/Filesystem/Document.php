<?php

namespace Laf\Filesystem;

use claviska\SimpleImage;
use Laf\Util\Settings;

/**
 * Class Document
 * @package Intrepicure
 * Main Class for Table document
 * This class inherits functionality from BaseDocument.
 * It is generated only once, please include all logic and code here
 */
class File
{
	private $document_id = null;
	private $documentInstance = null;

	/**
	 * Instructors constructor.
	 * @param int $id
	 */
	public function __construct($id = null)
	{
		$this->document_id = $id;
		$this->documentInstance = new \Intrepicure\Document($id);
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
		$user = \Intrepicure\Person::getLoggedUserInstance();

		if (isset($_FILES[$fieldName])) {
			if (
				$_FILES[$fieldName]['size'] > 0
				&& $_FILES[$fieldName]['error'] == 0
				&& in_array($_FILES[$fieldName]['type'], $settings->getProperty('allowed_upload_images'))
			) {
				$document = new Document();
				$randomName = microtime(true);
				$document->setFileNameOriginalVal($_FILES[$fieldName]['name']);
				$document->setFileExtensionVal(strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION)));
				$document->setFileNameVal($randomName . '_full.' . $document->getFileExtensionVal());
				$document->setThumbnailNameVal($randomName . '_thumb.' . $document->getFileExtensionVal());
				$document->setFileSizeVal($_FILES[$fieldName]['size']);
				$document->setMimeTypeVal($_FILES[$fieldName]['type']);
				if ($user->hasSchool()) {
					$document->setSchoolIdVal($user->getSchoolObject()->getIdVal());
				}
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
	 * @return Document
	 */
	public function getDocumentInstance()
	{
		return $this->documentInstance;
	}

	/**
	 * Check if file exists in the filesystem
	 * @return bool
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
	 */
	public function getFullPathFullSize()
	{
		return $this->getDocumentsDirectory() . $this->documentInstance->getFileNameFld();
	}

	/**
	 * Path to documents directory
	 * Does not include a trailing /
	 * @return string
	 */
	public function getDocumentsDirectory()
	{
		return __DIR__ . '/../../../document/';
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
		if (in_array($this->documentInstance->getMimeTypeVal(), $settings->getProperty('allowed_upload_images')))
			return true;
		return false;
	}

	/**
	 * @return bool
	 */
	public function hardDelete()
	{
		$ok = parent::hardDelete();
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

	/**
	 * Returns the lowest level class in the inheritance tree
	 * Used with late static binding to get the lowest level class
	 */
	protected function returnLeafClass()
	{
		return $this;
	}
}
