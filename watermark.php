<?php
/**
 * ---------------------------------------------------------------------------------------------------------------------------
 * Watermark images for WordPress (.htaccess based) v3.00
 *
 * Original script v2.16 by Javier Gutiérrez Chamorro (Guti) - https://www.javiergutierrezchamorro.com
 * @copyright © Copyright 2021-2026 Javier Gutiérrez Chamorro
 * @package watermark-images-for-wordpress-htaccess
 * @license LGPL
 *
 * Refactor: bugs fixed, code simplified and optimized.
 * Requires PHP 8.2+ with the GD extension (WebP/AVIF optional depending on the build).
 *
 * Installation: place this file and watermark.png in the images directory
 * (typically wp-content/uploads) and add the following to the directory .htaccess:
 *
 * <IfModule mod_rewrite.c>
 *     RewriteEngine On
 *     RewriteCond expr "filesize('%{REQUEST_FILENAME}') -gt 102400"
 *     RewriteRule (^.*\.(jpg|jpeg|png)$) ./watermark.php?src=$1 [L]
 * </IfModule>
 *
 * Add "nowatermark" to any URL to get the original image unprocessed.
 * ---------------------------------------------------------------------------------------------------------------------------
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------------------------------------------------------
// Configuration
// ---------------------------------------------------------------------------------------------------------------------------

const KI_MIN_WIDTH  = 800;                        // Minimum image width (px) to apply the watermark
const KI_MIN_HEIGHT = 600;                        // Minimum image height (px) to apply the watermark
const KI_SCALE_MAX  = 1200;                       // Image gets downscaled if its larger side exceeds this value
const KS_EXCLUDE    = 'nowatermark';              // Substring in the URL that disables processing
const KS_WATERMARK  = __DIR__ . '/watermark.png'; // PNG image used as the watermark

const KI_WM_OPACITY = 60;                         // Watermark opacity (0-100)
const KI_WM_MARGIN  = 5;                          // Watermark margin from the border (px)

const KI_Q_AVIF     = 80;                         // AVIF encoding quality (0-100)
const KI_Q_WEBP     = 85;                         // WebP encoding quality (0-100)
const KI_Q_JPEG     = 85;                         // JPEG encoding quality (0-100)

// GD constants missing when PHP is compiled without WebP/AVIF support
defined('IMG_WEBP') || define('IMG_WEBP', 32);
defined('IMG_AVIF') || define('IMG_AVIF', 256);

// ---------------------------------------------------------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------------------------------------------------------

/** Returns a 404 and ends the request. */
function notFound(): never
{
	http_response_code(404);
	exit;
}

/** Serves the original file as-is, using X-Sendfile if the server supports it. */
function serveFile(string $sFile, int $iType): never
{
	header('Content-Type: ' . image_type_to_mime_type($iType));

	if (function_exists('apache_get_modules') && in_array('mod_xsendfile', apache_get_modules(), true))
	{
		header('X-Sendfile: ' . $sFile);
	}
	else
	{
		$iSize = @filesize($sFile);
		if ($iSize !== false && $iSize > 0)
		{
			header('Content-Length: ' . $iSize);
		}
		readfile($sFile);
	}
	exit;
}

// ---------------------------------------------------------------------------------------------------------------------------
// Main program
// ---------------------------------------------------------------------------------------------------------------------------

// 1) The src parameter is mandatory: non-empty string without null bytes
$sSrc = $_GET['src'] ?? '';
if (!is_string($sSrc) || $sSrc === '' || str_contains($sSrc, "\0"))
{
	notFound();
}

// 2) Only jpg / jpeg / png extensions are allowed
if (!in_array(strtolower(pathinfo($sSrc, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png'], true))
{
	notFound();
}

// 3) Canonical path: the file must stay INSIDE the script directory
//    (closes the path traversal: src=../../../etc/secret.png)
$sBase   = (string)realpath(__DIR__);
$sSource = realpath($sBase . DIRECTORY_SEPARATOR . $sSrc);
if ($sSource === false || !str_starts_with($sSource, $sBase . DIRECTORY_SEPARATOR) || !is_file($sSource))
{
	notFound();
}

// 4) Dimensions and real type of the file
//    (getimagesize only reads the file headers: it does not decode pixels)
$aInfo = @getimagesize($sSource);
if ($aInfo === false)
{
	notFound();
}
$iW    = (int)$aInfo[0];
$iH    = (int)$aInfo[1];
$iType = (int)$aInfo[2];

// 5) The original file is served unprocessed when the URL excludes it, the image
//    is smaller than the configured minimum, or its real content is not JPEG/PNG
if (
	str_contains($_SERVER['REQUEST_URI'] ?? '', KS_EXCLUDE)
	|| ($iW < KI_MIN_WIDTH && $iH < KI_MIN_HEIGHT)
	|| !in_array($iType, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
)
{
	serveFile($sSource, $iType);
}

// 6) Image is loaded into GD
$oImage = match ($iType)
{
	IMAGETYPE_JPEG => @imagecreatefromjpeg($sSource),
	default        => @imagecreatefrompng($sSource),
};
if ($oImage === false)
{
	notFound(); // Corrupted file or not readable by GD
}

// 7) Indexed (palette) PNGs are converted to truecolor, required for WebP/AVIF
//    encoding and for a correct merge
if ($iType === IMAGETYPE_PNG)
{
	imagepalettetotruecolor($oImage);
}

// 8) Proportional downscale when the larger side exceeds the maximum
if (max($iW, $iH) > KI_SCALE_MAX)
{
	$fRatio  = KI_SCALE_MAX / max($iW, $iH);
	$iNewW   = max(1, (int)round($iW * $fRatio));
	$iNewH   = max(1, (int)round($iH * $fRatio));
	$oScaled = imagescale($oImage, $iNewW, $iNewH);

	if ($oScaled !== false)
	{
		imagedestroy($oImage);
		[$oImage, $iW, $iH] = [$oScaled, $iNewW, $iNewH];
	}
}

// 9) Watermark merge in the bottom right corner
//    (if watermark.png is missing, the image is still served without the mark)
$oWatermark = @imagecreatefrompng(KS_WATERMARK);
if ($oWatermark !== false)
{
	$iWmW = imagesx($oWatermark);
	$iWmH = imagesy($oWatermark);

	imagecopymerge(
		$oImage,
		$oWatermark,
		max(0, $iW - $iWmW - KI_WM_MARGIN),
		max(0, $iH - $iWmH - KI_WM_MARGIN),
		0,
		0,
		$iWmW,
		$iWmH,
		KI_WM_OPACITY
	);
	imagedestroy($oWatermark);
}

// 10) Delivery with content negotiation: AVIF > WebP > original format
$sAccept = $_SERVER['HTTP_ACCEPT'] ?? '';

// Vary: Accept prevents caches/CDNs from storing the AVIF/WebP version and
// serving it later to browsers that do not support it (cache bug in the original script)
header('Vary: Accept');

$bAvif = str_contains($sAccept, 'image/avif') && function_exists('imageavif') && (imagetypes() & IMG_AVIF);
$bWebp = str_contains($sAccept, 'image/webp') && function_exists('imagewebp') && (imagetypes() & IMG_WEBP);

if ($bAvif)
{
	header('Content-Type: image/avif');
	imageavif($oImage, null, KI_Q_AVIF, 9);
}
elseif ($bWebp)
{
	header('Content-Type: image/webp');
	imagealphablending($oImage, true);
	imagesavealpha($oImage, true);
	imagewebp($oImage, null, KI_Q_WEBP);
}
elseif ($iType === IMAGETYPE_JPEG)
{
	header('Content-Type: image/jpeg');
	imageinterlace($oImage, true);
	imagejpeg($oImage, null, KI_Q_JPEG);
}
else
{
	header('Content-Type: image/png');
	imagesavealpha($oImage, true);
	imagepng($oImage, null, 6);
}

imagedestroy($oImage);
exit;
