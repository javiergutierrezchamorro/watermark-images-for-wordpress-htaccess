# Watermark images for Wordpress (.htaccess based)
By Javier Gutiérrez Chamorro (Guti) - https://www.javiergutierrezchamorro.com


This is a simple PHP script with a .htaccess that enables watermarking images on the fly. Simply copy all files to your Wordpress wp-content/uploads folder:
* .htaccess: Apache redirection rule for watermarking.
* watermark.php: PHP code to apply watermark
* watermark.png: Image of the watermark to be added to the images. You can replace it by any arbitrary PNG image you would like to use.

There is no related plugin nor admin panel. To uninstall simply remove the supplied 3 files from your folder. If you want to change any option you can take a look at the constants defined in watermark.php.


## How does it work?
The .htaccess file intercepts all .jpg/.jpeg files that are requested. Those files will be passed to watermark.php, which will do the following:

1. If image is more KI_MIN_JPEG_DIMENSIONS (default to 100 KB.) and it is larger than KI_MIN_JPEG_WIDTH, KI_MIN_JPEG_HEIGHT (default 1024x768), the watermark will be applied.
1. Additionally if the image is larger that KI_SCALE_JPEG_WIDTH (default to 1600), it will be downscaled to KI_SCALE_JPEG_WIDTH in order to save bandwidth.
1. A new on-the-fly image with the watermark will be served to the browser, in WEBP format if supported or in JPEG if not.

Original images are never modified so you will always have them on your wp-content/uploads/ folder. The conversion is done on-demand, so lots of care has been put in making it working fast.