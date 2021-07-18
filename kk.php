if (in_array('mod_xsendfile', apache_get_modules()))
{
	header('X-Sendfile: ' . $src);
}
else
{
	header('Content-Length: ' . $iSourceSize);
}