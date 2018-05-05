<?php
/**
 * Created by PhpStorm.
 * User: Radovan Vlček (Wolfie)
 * Date: 01.05.2018
 * Time: 13:28
 */

namespace Signer;

interface Signer
{
	public function signDocument(array $options);

	public function getSignedDocument(string $outputType);
}