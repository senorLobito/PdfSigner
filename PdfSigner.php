<?php
/**
 * Created by PhpStorm.
 * User: Radovan Vlček (Wolfie)
 * Date: 01.05.2018
 * Time: 8:36
 */

namespace PdfSigner;
use setasign\Fpdi\TcpdfFpdi;
use Signer\Signer;
require_once 'vendor/autoload.php';
require_once 'Signer.php';

class PdfSigner implements Signer
{

	private $pdf;

	private $sourceFile = null;
	private $certificate = null;
	private $certificatePass = null;
	private $signImage = null;

	private $signCoordinates = [
		'x' => 180,
		'y' => 260,
		'w' => 15,
		'h' => 15
	];

	private $pageCount = 0;

	const DEFAULT_SIGN_IMAGE = 'images/default_signature.png';

	public function __construct(string $sourceFile = null, string $certificatePath = null, string $certificatePass = null, string $signImagePath = null)
	{
		$this->pdf = new TcpdfFpdi();

		if (!empty($sourceFile)) {
			$this->setSourceFile($sourceFile);
			$this->pageCount = $this->pdf->setSourceFile($this->sourceFile);
		}

		if (!empty($certificatePath)) {
			$this->setCertificate($certificatePath);
		}

		if (!empty($certificatePass)) {
			$this->certificatePass = $certificatePass;
		}

		if (!empty($signImagePath) && file_exists($signImagePath)) {
			$this->signImage = $signImagePath;
		} else {
			$this->signImage = self::DEFAULT_SIGN_IMAGE;
		}
	}

	/**
	 * Creates PDF object from given pdf file and tries to add digital signature
	 * @param array $signOptions
	 * @return bool
	 */
	public function signDocument(array $signOptions = [], array $signCoords = []):bool {
		$result = true;

		if ($this->pageCount === 0 || empty($this->certificate) || empty($this->certificatePass)) {
			$this->log("Document cannot be signed, missing one of parameters: \npageCount: " . $this->pageCount .
				"\ncertPath: " . $this->certificate . "\ncertPass: " . strlen($this->certificatePass)
			);
			$result = false;
		}

		try {

			$this->preparePdf();
			$this->setSignature($signOptions);
			$this->applySignatureWithImage($signCoords);

		} catch (\Exception $e) {
			$this->log($e->getMessage() . "\n" . $e->getTraceAsString());
			$result = false;
		}

		return $result;
	}

	/**
	 * Generates pdf and outputs it according to passed parameter
	 * @param string $outputType
	 * I: send the file inline to the browser (default). The plug-in is used if available. The name given by name is used when one selects the "Save as" option on the link generating the PDF.</li><li>
	 * D: send to the browser and force a file download with the name given by name.</li><li>
	 * F: save to a local server file with the name given by name.</li><li>
	 * S: return the document as a string (name is ignored).</li><li>
	 * FI: equivalent to F + I option</li><li>
	 * FD: equivalent to F + D option</li><li>
	 * E: return the document as base64 mime multi-part email attachment (RFC 2045)</li></ul>
	 */
	public function getSignedDocument(string $outputType)
	{
		$docName = $this->getDocumentName();

		//Close and output PDF document - must be ABSOLUTE PATH
		$this->pdf->Output(__DIR__ . '/out/' . $docName . '.pdf', $outputType);
	}

	/**
	 * @param array $signOptions
	 * @param int $certType
	 * 1 = No changes to the document shall be permitted; any change to the document shall invalidate the signature;
	 * 2 = Permitted changes shall be filling in forms, instantiating page templates, and signing; other changes shall invalidate the signature; 3 = Permitted changes shall be the same as for 2, as well as annotation creation, deletion, and modification; other changes shall invalidate the signature.
	 */
	private function setSignature(array $signOptions = [], $certType = 1) {
		foreach ($signOptions as &$option) {
			if (empty($option)) {
				$option = '';
			}
		}

		// set document signature
		$this->pdf->setSignature($this->certificate, $this->certificate, $this->certificatePass, '', $certType, $signOptions);
	}

	/**
	 * Fetches the pages of passed PDF and adds it to PDF object
	 * @return bool
	 */
	private function preparePdf() {

		try {
			for ($currentPage = 1; $currentPage <= $this->pageCount; $currentPage++) {
				$pageId = $this->pdf->importPage($currentPage);
				$this->pdf->addPage();
				$this->pdf->useTemplate($pageId);
			}
		} catch (\Exception $e) {
			$this->log($e->getMessage());
		}

	}

	/**
	 * This function puts signature link to PDF and covers it with image, so it's visible
	 * @param null|string $signImage
	 */
	private function applySignatureWithImage($coords)
	{
		if (empty($signCoords)) {
			$coords = $this->signCoordinates;
		}

		$this->pdf->Image($this->signImage, $coords['x'], $coords['y'], $coords['w'], $coords['h'], 'PNG');
		$this->pdf->setSignatureAppearance($coords['x'], $coords['y'], $coords['w'], $coords['h']);
	}

	/**
	 * @return null
	 */
	public function getSourceFile()
	{
		return $this->sourceFile;
	}

	/**
	 * @param null $sourceFile
	 */
	public function setSourceFile($sourceFile)
	{
		if (!file_exists($sourceFile)) {
			$this->log('Source file could not be found in the following path: ' . $sourceFile);
			return false;
		}
		$this->sourceFile = $sourceFile;
	}

	/**
	 * @return mixed
	 */
	public function getCertificate()
	{
		return $this->certificate;
	}

	/**
	 * @param mixed $certificate
	 */
	public function setCertificate($certificate)
	{
		if (!file_exists($certificate)) {
			$this->log('File with certificate could not be found in the following path: ' . $certificate);
		}
		$this->certificate = 'file://' . realpath($certificate);
	}

	/**
	 * @return mixed
	 */
	public function getCertificatePass()
	{
		return $this->certificatePass;
	}

	/**
	 * @param mixed $certificatePass
	 */
	public function setCertificatePass($certificatePass)
	{
		$this->certificatePass = $certificatePass;
	}

	/**
	 * @return null|string
	 */
	public function getSignImage()
	{
		return $this->signImage;
	}

	/**
	 * @param $x coordinate
	 * @param $y coordinate
	 * @param $w width
	 * @param $h height
	 */
	public function setSignCoordinates($x, $y, $w, $h)
	{
		$this->signCoordinates = [
			'x' => $x,
			'y' => $y,
			'w' => $w,
			'h' => $h
		];
	}

	/**
	 * Retrieves file name from the filepath given
	 * @return string
	 */
	private function getDocumentName():string {
		return pathinfo($this->sourceFile, PATHINFO_FILENAME);
	}

	/**
	 * Serves to log errors to log file
	 * @param $message
	 */
	private function log($message) {
		$handle = fopen('log.txt', 'a');
		fwrite($handle, date('d.m.Y H:i:s') . ' ' . $message . "\n");
		fclose($handle);
	}
}