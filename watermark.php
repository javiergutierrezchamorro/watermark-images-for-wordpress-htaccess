<?php
/**
---------------------------------------------------------------------------------------------------------------------------
Watermark images for Wordpress (.htaccess based) v1.03
 * @author Javier Gutiérrez Chamorro (Guti) - https://www.javiergutierrezchamorro.com
 * @link https://www.javiergutierrezchamorro.com
 * @copyright © Copyright 2021
 * @package watermark-images-for-wordpress-htaccess
 * @license LGPL
 * @version 1.03
---------------------------------------------------------------------------------------------------------------------------
*/


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
/*
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteRule (^.*\.(jpg|jpeg)$) ./watermark.php?src=$1 [L]
</IfModule>
*/


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
declare(strict_types = 1);


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
const KI_MIN_JPEG_DIMENSIONS =	100*1024;		//Minimum JPEG file size in order to be watermarked
const KI_MIN_JPEG_WIDTH = 1024;					//Minimum JPEG image width in order to be watermarked
const KI_MIN_JPEG_HEIGHT = 768;					//Minimum JPEG image width in order to be watermarked
const KI_SCALE_JPEG_WIDTH = 1600;				//JPEG image will be reduced to that width if it is wider


// -------------------------------------------------------------------------------------------------------------------------------------------------------------
$sSource = getcwd() . '/' . @$_GET['src'];


//We only support JPEG files
if ((!@empty($_GET['src'])) && ((strpos(strtolower($sSource), '.jpg') !== false) || (strpos(strtolower($sSource), '.jpeg') !== false)) && (strpos($_SERVER['REQUEST_URI'], 'nowatermark') === false))
{
	//Source image should exist
	if (file_exists($sSource))
	{
		//PHP getimagesize is slow because it reads the whole image. We will only watermark big images which initially is a faster check
		if (filesize($sSource) > KI_MIN_JPEG_DIMENSIONS)
		{
			$aSourceDim = @getjpegsize($sSource);
			//Now we know it is big enough we proceed checking the image dimensions
			if (($aSourceDim[0] >= KI_MIN_JPEG_WIDTH) || ($aSourceDim[1] >= KI_MIN_JPEG_HEIGHT))
			{
				$oImage = @imagecreatefromjpeg($sSource);
				//Rescale if too large
				if (($aSourceDim[0] >= KI_SCALE_JPEG_WIDTH) || ($aSourceDim[1] >= KI_SCALE_JPEG_WIDTH))
				{
					if ($aSourceDim[0] > $aSourceDim[1])
					{
						$aSourceDim[1] = (int) ($aSourceDim[1] * (KI_SCALE_JPEG_WIDTH / $aSourceDim[0]));
						$aSourceDim[0] = KI_SCALE_JPEG_WIDTH;
					}
					else
					{
						$aSourceDim[0] = (int) ($aSourceDim[0] * (1600 / $aSourceDim[1]));
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
				//Serve webp is supported
				if (@strpos($_SERVER['HTTP_ACCEPT'], 'image/webp') !== false)
				{
					header('content-type: image/webp');
					imagewebp($oImage, NULL, 85);
				}
				//Fallback to serving interlaced JPEG
				else
				{
					header('content-type: image/jpeg');
					imageinterlace($oImage);
					imagejpeg($oImage, NULL, 85);
				}
				@imagedestroy($oWatermark);
				@imagedestroy($oImage);
			}
			//Less tan 1024x768 JPEG so redirect to original file
			else
			{
				header('content-type: image/jpeg');
				readfile($sSource);
			}
		}
		//Less than 100 KB JPEG so redirect to original file
		else
		{
			header('content-type: image/jpeg');
			readfile($sSource);
		}
	}
	//Image not found
	else
	{
		header('HTTP/1.1 404 Not Found');
	}
}
//Not a JPEG so serve the original image
else
{
	header('content-type: image/jpeg');
	readfile($sSource);
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
							return array($width, $height);
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
