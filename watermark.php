<?php
/**
---------------------------------------------------------------------------------------------------------------------------
Watermark images for WordPress (.htaccess based) v2.15
 * @author Javier Gutiérrez Chamorro (Guti) - https://www.javiergutierrezchamorro.com
 * @link https://www.javiergutierrezchamorro.com
 * @copyright © Copyright 2021-2022
 * @package watermark-images-for-wordpress-htaccess
 * @license LGPL
 * @version 2.16
---------------------------------------------------------------------------------------------------------------------------
*/


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
/*
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond expr "filesize('%{REQUEST_FILENAME}') -gt 102400"
	RewriteRule (^.*\.(jpg|jpeg|png)$) ./watermark.php?src=$1 [L]
</IfModule>
*/


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
declare(strict_types = 1);


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
if (!defined('IMG_WEPB'))
{
	define('IMG_WEPB', 32);
}
if (!defined('IMG_AVIF'))
{
	define('IMG_AVIF', 256);
}

// -------------------------------------------------------------------------------------------------------------------------------------------------------------
const KI_MIN_JPEG_WIDTH = 800;					//Minimum JPEG image width in order to be watermarked
const KI_MIN_JPEG_HEIGHT = 600;					//Minimum JPEG image width in order to be watermarked
const KI_SCALE_JPEG_WIDTH = 1200;				//JPEG image will be reduced to that width if it is wider
const KI_MIN_PNG_WIDTH = 800;					//Minimum PNG image width in order to be watermarked
const KI_MIN_PNG_HEIGHT = 600;					//Minimum JPEG image width in order to be watermarked
const KI_SCALE_PNG_WIDTH = 1200;				//PNG image will be reduced to that width if it is wider
const KS_EXCLUDE_PROCESSING = 'nowatermark';	//Substring in URL to exclude processing


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
$sGetSrc = @filter_var($_GET['src'], FILTER_SANITIZE_STRING);
$sGetRequestUrl = @filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_STRING);
$sSource = getcwd() . '/' . $sGetSrc;


//We only support JPEG AND PNG files
if ((isset($sGetSrc)) && ((strpos(strtolower($sSource), '.jpg') !== false) || (strpos(strtolower($sSource), '.jpeg') !== false) || (strpos(strtolower($sSource), '.png') !== false)) && (strpos($sGetRequestUrl, KS_EXCLUDE_PROCESSING) === false))
{
	//Source image should exist
	if (file_exists($sSource))
	{
		//JPEG processing
		if ((strpos(strtolower($sSource), '.jpg') !== false) || (strpos(strtolower($sSource), '.jpeg')))
		{
			//PHP getimagesize is slow because it reads the whole image. We will only watermark big images which initially is a faster check
			$aSourceDim = @getjpegsize($sSource);
			//If fast getimagesize fails, try to use system version
			if (!$aSourceDim)
			{
				$aSourceDim = @getimagesize($sSource);
			}
			//Now we know it is big enough we proceed checking the image dimensions
			if (($aSourceDim) && (($aSourceDim[0] >= KI_MIN_JPEG_WIDTH) || ($aSourceDim[1] >= KI_MIN_JPEG_HEIGHT)) && ($aSourceDim[2] === IMAGETYPE_JPEG))
			{
				$oImage = @imagecreatefromjpeg($sSource);
				//Rescale if too large
				if (($aSourceDim[0] > KI_SCALE_JPEG_WIDTH) || ($aSourceDim[1] > KI_SCALE_JPEG_WIDTH))
				{
					if ($aSourceDim[0] > $aSourceDim[1])
					{
						$aSourceDim[1] = (int)($aSourceDim[1] * (KI_SCALE_JPEG_WIDTH / $aSourceDim[0]));
						$aSourceDim[0] = KI_SCALE_JPEG_WIDTH;
					}
					else
					{
						$aSourceDim[0] = (int)($aSourceDim[0] * (KI_SCALE_JPEG_WIDTH / $aSourceDim[1]));
						$aSourceDim[1] = KI_SCALE_JPEG_WIDTH;
					}
					$oScaled = imagescale($oImage, $aSourceDim[0], $aSourceDim[1]);
					@imagedestroy($oImage);
					$oImage = $oScaled;
				}
				//Watermark the image
				$oWatermark = imagecreatefrompng('watermark.png');
				$iWatermarkWidth = imagesx($oWatermark);
				$iWatermarkHeight = imagesy($oWatermark);
				$iDestX = $aSourceDim[0] - $iWatermarkWidth;
				$iDestY = $aSourceDim[1] - $iWatermarkHeight;
				imagecopymerge($oImage, $oWatermark, $iDestX - 5, $iDestY - 5, 0, 0, $iWatermarkWidth, $iWatermarkHeight, 60);
				//Serve avif if supported by PHP and client browser
				if ((imagetypes() & IMG_AVIF) && (isset($sServerHttpAccept)) && (strpos($sServerHttpAccept, 'image/avif') !== false))
				{
					header('Content-Type: image/avif');
					imageavif($oImage, NULL, 70, 9);
				}
				//Serve webp if supported by PHP and client browser
				else if ((imagetypes() & IMG_WEBP) && (isset($sServerHttpAccept)) && (strpos($sServerHttpAccept, 'image/webp') !== false))
				{
					header('Content-Type: image/webp');
					imagewebp($oImage, NULL, 80);
				}
				//Fallback to serving interlaced JPEG
				else
				{
					header('Content-Type: image/jpeg');
					imageinterlace($oImage);
					imagejpeg($oImage, NULL, 85);
				}
				@imagedestroy($oWatermark);
				@imagedestroy($oImage);
			}
			//Less than 1024x768 JPEG so redirect to original file
			else
			{
				ServeFile($sSource);
			}
		}

		//PNG processing
		if (strpos(strtolower($sSource), '.png') !== false)
		{
			//PHP getimagesize is slow because it reads the whole image. We will only watermark big images which initially is a faster check
			$aSourceDim = @getpngsize($sSource);
			//If fast getimagesize fails, try to use system version
			if (!$aSourceDim)
			{
				$aSourceDim = @getimagesize($sSource);
			}
			//Now we know it is big enough we proceed checking the image dimensions
			if (($aSourceDim) && (($aSourceDim[0] >= KI_MIN_PNG_WIDTH) || ($aSourceDim[1] >= KI_MIN_PNG_HEIGHT)) && ($aSourceDim[2] === IMAGETYPE_PNG))
			{
				$oImage = @imagecreatefrompng($sSource);
				//Rescale if too large
				if (($aSourceDim[0] > KI_SCALE_PNG_WIDTH) || ($aSourceDim[1] > KI_SCALE_PNG_WIDTH))
				{
					if ($aSourceDim[0] > $aSourceDim[1])
					{
						$aSourceDim[1] = (int)($aSourceDim[1] * (KI_SCALE_PNG_WIDTH / $aSourceDim[0]));
						$aSourceDim[0] = KI_SCALE_PNG_WIDTH;
					}
					else
					{
						$aSourceDim[0] = (int)($aSourceDim[0] * (KI_SCALE_PNG_WIDTH / $aSourceDim[1]));
						$aSourceDim[1] = KI_SCALE_PNG_WIDTH;
					}
					$oScaled = imagescale($oImage, $aSourceDim[0], $aSourceDim[1]);
					@imagedestroy($oImage);
					$oImage = $oScaled;
				}
				//Watermark the image
				$oWatermark = imagecreatefrompng('watermark.png');
				$iWatermarkWidth = imagesx($oWatermark);
				$iWatermarkHeight = imagesy($oWatermark);
				$iDestX = $aSourceDim[0] - $iWatermarkWidth;
				$iDestY = $aSourceDim[1] - $iWatermarkHeight;
				imagecopymerge($oImage, $oWatermark, $iDestX - 5, $iDestY - 5, 0, 0, $iWatermarkWidth, $iWatermarkHeight, 60);
				//Serve avif if supported by PHP and client browser
				$sServerHttpAccept = @filter_var($_SERVER['HTTP_ACCEPT'], FILTER_SANITIZE_STRING);
				if ((imagetypes() & IMG_AVIF) && (isset($sServerHttpAccept)) && (strpos($sServerHttpAccept, 'image/avif') !== false))
				{
					header('Content-Type: image/avif');
					imageavif($oImage, NULL, 85);
				}
				//Serve webp if supported by PHP and client browser
				else if ((imagetypes() & IMG_WEBP) && (isset($sServerHttpAccept)) && (strpos($sServerHttpAccept, 'image/webp') !== false))
				{
					header('Content-Type: image/webp');
					imagepalettetotruecolor($oImage);
					imagealphablending($oImage, true);
					imagesavealpha($oImage, true);
					imagewebp($oImage, NULL, 85);
				}
				//Fallback to serving PNG
				else
				{
					header('Content-Type: image/png');
					imagepng($oImage, NULL, 9, PNG_ALL_FILTERS);
				}
				@imagedestroy($oWatermark);
				@imagedestroy($oImage);
			}
			//Less than 1024x768 PNG so redirect to original file
			else
			{
				ServeFile($sSource, 'png');
			}
		}
	}
	//Image not found
	else
	{
		header('HTTP/1.1 404 Not Found');
	}
}
//Not a JPEG, or no image specified  so serve the original image
else
{
	ServeFile($sSource);
}


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
function ServeFile($psFile, $psType = 'jpeg')
{
	header('Content-Type: image/' . $psType);
	header('Content-Length: ' . @filesize($psFile));
	
	//Use faster X-Sendfile if available
	if ((function_exists('apache_get_modules')) && (in_array('mod_xsendfile', apache_get_modules())))
	{
		header('X-Sendfile: ' . $psFile);
	}
	else
	{
		@readfile($psFile);
	}
}


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
//Retrieve JPEG width and height without downloading/reading entire image.
//https://www.php.net/manual/en/function.getimagesize.php#88793
function getjpegsize($img_loc)
{
	$handle = fopen($img_loc, 'rb') or die('Invalid file stream.');
	if (!feof($handle))
	{
		$new_block = fread($handle, 32);
		$i = 0;
		if ($new_block[$i] === "\xFF" && $new_block[$i + 1] === "\xD8" && $new_block[$i + 2] === "\xFF" && $new_block[$i + 3] === "\xE0")
		{
			$i += 4;
			if ($new_block[$i + 2] === "\x4A" && $new_block[$i + 3] === "\x46" && $new_block[$i + 4] === "\x49" && $new_block[$i + 5] === "\x46" && $new_block[$i + 6] === "\x00")
			{
				// Read block size and skip ahead to begin cycling through blocks in search of SOF marker
				$block_size = unpack('H*', $new_block[$i] . $new_block[$i + 1]);
				$block_size = hexdec($block_size[1]);
				while (!feof($handle))
				{
					$i += $block_size;
					$new_block .= fread($handle, $block_size);
					if ($new_block[$i] === "\xFF")
					{
						// New block detected, check for SOF marker
						$sof_marker = array("\xC0", "\xC1", "\xC2", "\xC3", "\xC5", "\xC6", "\xC7", "\xC8", "\xC9", "\xCA", "\xCB", "\xCD", "\xCE", "\xCF");
						if (in_array($new_block[$i + 1], $sof_marker))
						{
							// SOF marker detected. Width and height information is contained in bytes 4-7 after this byte.
							$size_data = $new_block[$i + 2] . $new_block[$i + 3] . $new_block[$i + 4] . $new_block[$i + 5] . $new_block[$i + 6] . $new_block[$i + 7] . $new_block[$i + 8];
							$unpacked = unpack('H*', $size_data);
							$unpacked = $unpacked[1];
							$height = hexdec($unpacked[6] . $unpacked[7] . $unpacked[8] . $unpacked[9]);
							$width = hexdec($unpacked[10] . $unpacked[11] . $unpacked[12] . $unpacked[13]);
							return(array($width, $height, IMAGETYPE_JPEG));
						}
						else
						{
							// Skip block marker and read block size
							$i += 2;
							$block_size = unpack('H*', $new_block[$i] . $new_block[$i + 1]);
							$block_size = hexdec($block_size[1]);
						}
					}
					else
					{
						return FALSE;
					}
				}
			}
		}
	}
	return FALSE;
}


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
//Retrieve PNG width and height without downloading/reading entire image.
//https://www.php.net/manual/en/function.getimagesize.php
function getpngsize($img_loc)
{
	$handle = fopen($img_loc, 'rb') or die('Invalid file stream.');
	if (!feof( $handle))
	{
		$new_block = fread($handle, 24);
		if (strncmp($new_block, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A", 8) === 0)
		{
			if ($new_block[12] . $new_block[13] . $new_block[14] . $new_block[15] === "\x49\x48\x44\x52")
			{
				$width  = unpack('H*', $new_block[16] . $new_block[17] . $new_block[18] . $new_block[19]);
				$width  = hexdec($width[1]);
				$height = unpack('H*', $new_block[20] . $new_block[21] . $new_block[22] . $new_block[23]);
				$height  = hexdec($height[1]);
				return(array($width, $height, IMAGETYPE_PNG));
			}
		}
	}
	return FALSE;
}