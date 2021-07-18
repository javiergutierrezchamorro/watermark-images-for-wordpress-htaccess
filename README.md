# Watermark images for Wordpress (.htaccess based)
By Javier Gutiérrez Chamorro (Guti) - https://www.javiergutierrezchamorro.com

watermark-images-for-wordpress-htaccess is a simple PHP script with a .htaccess file that enables watermarking Wordpress images on the fly. No plugin needed!

Simply copy all files to your Wordpress wp-content/uploads folder:
* .htaccess: Apache redirection rule for watermarking.
* watermark.php: PHP code to apply watermark
* watermark.png: Image of the watermark to be added to the images. You can replace it by any arbitrary PNG image you would like to use.

There is no related wp-plugin to install nor any admin panel. To uninstall simply remove the supplied 3 files from your folder. If you want to change any option you can take a look at the constants defined in watermark.php. Watermark image (watermark.png) can be replaced for any PNG you would like to use as watermark, using any arbitrary dimensions and color depth.


## How does it work?
The .htaccess file intercepts all calls to .jpg/.jpeg files that are requested from inside your wp-content/uploads/* folder (images on outer locations will be not treated). Those images will be passed to watermark.php, which will do the following:

1. If image is more KI_MIN_JPEG_SIZE (default to 100 KB.) and if it is larger than KI_MIN_JPEG_WIDTH or KI_MIN_JPEG_HEIGHT (default 1024x768), the watermark will be applied.
1- Appending KS_EXCLUDE_PROCESSING (by default "nowatermark"), processing will be deactivated, and so the original image with no watermark will be served.
1. Additionally if the image is larger that KI_SCALE_JPEG_WIDTH pixels (default to 1600), it will be downscaled to KI_SCALE_JPEG_WIDTH in order to save bandwidth.
1. A new on-the-fly image with the watermark added will be served to the browser. If client webbrowser supports WEBP it will used that format, while fallback to regular JPEG if not supported.

Original images are never modified so you will always have them on your wp-content/uploads/ folder. The conversion is done on-demand, so lots of care has been put in making it working fast. It includes its own implementation of getimagesize and uses mod_xsendfile/X-Sendfile if available.


## Requirements
* .htaccess support Apache (tested) or LiteSpeed/OpenLiteSpeed (untested)
* PHP 7.0 or later
* A wp-content/uploads Wordpress folder available


## Demonstration
To see it in action, the post at https://www.javiergutierrezchamorro.com/un-modelo-y-un-bell-ross/ contains https://i0.wp.com/www.javiergutierrezchamorro.com/wp-content/uploads/2020/08/un_modelo_bell_and_ross_a_contrarreloj_01.jpg where the watermarked image is shown.