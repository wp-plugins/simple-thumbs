<?php
/*
Plugin Name: Simple Thumbs
Plugin URI: http://eskapism.se/code-playground/simple-thumbs/
Description: Generates image thumbs, with options to crop or fit to the wanted size. Using custom rewrite rules the urls are also pretty nice and SEO-friendly. You can also generate img tags with the correct width & height attributes set, even after resize.
Version: 0.3
Author: Pär Thernström
Author URI: http://eskapism.se/
License: GPL2
*/

/*  Copyright 2010  Pär Thernström (email: par.thernstrom@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



/**
 * Create img-src or full img-tags with the correct width and height set
 * 
 * Some examples
 * 
 * // print img tag for image with id 1511
 * echo simple_thumbs_img("id=1511&tag=1");
 *
 * // print img tag for image with id 1511, and resize it to be a thumb that has the max size 75x75
 * echo simple_thumbs_img("id=1511&tag=1&w=75&h=75");
 * 
 * // print img tag for image with id 1511, and crop it to be a thumb that has the excact size 75x75
 * echo simple_thumbs_img("id=1511&tag=1&w=75&h=75&mc");
 *
 * Available args:
 * id = file id
 * w = width
 * h = height
 * m = mode = c for crop | e for excact, a for auto, w for within (default), p for portrait, l for landscape
 * u = unsharp mask
 * f = output image format, j = jpeg (default) | g = gif | p = png
 * q = output image quality, integer, default is 85
 *
 */
if (!function_exists("simple_thumbs_img")) {
function simple_thumbs_img($args = "") {

    $defaults = array(
    	"w" => "0",
		"h" => "0",
		"q" => 85,
		"f" => "j",
		"m" => "w", // within
		"u" => "0", // unsharp
		"alt" => "", // alt-attribute, always set
		"title" => null, // title-attribute, only set if not null
		"tag" => false, // return scr wrapped in tag?
		"id" => null, // get src from file with this id
		"name" => null // use attachment name by default
    );
    $args = wp_parse_args($args, $defaults);

	$out = "";

	$file_id = (int) $args["id"];
	if ($file_id) {
		if (!$file_id) {
			return "";
		}
		$src = wp_get_attachment_url($file_id);
		$image_info = wp_get_attachment_metadata($file_id);
		$post_image = get_post($file_id);
		$post_name = $post_image->post_name;
		if (!$args["name"]) {
			$args["name"] = $post_name;
		}
		 
	} else {
		return "";
	}

	$args["w"] = (int) $args["w"];
	$args["h"] = (int) $args["h"];
	$args["q"] = (int) $args["q"];

	// Dont add q if 85, default in class
	if ($args["q"] == 85) {
		$q = "";
	} else {
		$q = ":q{$args[q]}";
	}
	$w = ""; if ($args["w"]) { $w = ":w{$args[w]}"; }
	$h = ""; if ($args["h"]) { $h = ":h{$args[h]}"; }	
	$m = ""; if ($args["m"] && $args["m"] != "w") { $m = ":m{$args[m]}"; }
	$f = ""; if ($args["f"] && $args["f"] != "j") { $f = ":f{$args[f]}"; }
	$u = ""; if ($args["u"]) { $u = ":u{$args[u]}"; }

	$file_ext = "jpg";
	if ($args["f"] == "p") { $file_ext = "png"; } elseif ($args["f"] == "g") { $file_ext = "gif"; }
	
	$image_name = sanitize_title_with_dashes($args["name"]) . ".$file_ext";
	
	$thumb_src = "/image/{$args[id]}{$w}{$h}{$q}{$m}{$f}{$u}/$image_name";

	if ($args["tag"]) {

		// Get original width and height
		$org_width = $image_info["width"];
		$org_height = $image_info["height"];
		$new_width = $args["w"];
		$new_height = $args["h"];
		$objResize = new simple_thumbs_resize();
		$objResize->width = $org_width;
		$objResize->height = $org_height;
		$optionArray = $objResize->getDimensions($new_width, $new_height, $args["m"]);
		
		// crop, actually just use w and h
		if ($args["m"] == "c") {
			$optionArray = array("optimalWidth" => $args["w"], "optimalHeight" => $args["h"]);
		}
		
		$new_width = $optionArray["optimalWidth"];
		$new_height = $optionArray["optimalHeight"];
		$alt = $args["alt"];
		$title = "";
		if (isset($args["title"])) {
			$title = " title='{$args[title]}' ";
		}
		$out = sprintf("<img src='%s' alt='%s' $title width='%d' height='%d' />", $thumb_src, $alt, $new_width, $new_height);
		
	} else {
		$out = $thumb_src;
	}
	
	return $out;

}
}




/**
 * The main class
 */
if( !class_exists( 'wp_plugin_simple_thumbs' ) ) {
class wp_plugin_simple_thumbs {

	var $version = 0.1;
	var $regexp_dividers = "/[|,\-_:;]+/"; // type of dividers to allow
	var $cache_dir;
	var $args;
	var $cache_max_files = 10;
	var $cache_max_files_to_delete = 5;
	
	function __construct() {
		$this->add_hooks();
	}
	
	function add_hooks() {

		register_activation_hook( __FILE__, array($this, 'activation_hook') );
		register_deactivation_hook( __FILE__, array($this, 'deactivation_hook'));
		add_action("init", array($this, 'action_init'));
		add_filter('wp', array($this, 'wp'));
		add_filter('generate_rewrite_rules', array($this, 'generate_rewrite_rules'));
		add_filter('template_redirect', array($this, 'template_redirect'));
		add_action('query_vars', array($this, 'query_vars'));
		add_filter("redirect_canonical", array($this, "redirect_canonical"), 10, 2);
		add_filter("nocache_headers", array($this, "filter_nocache_headers"));
		
		// new image being saved, clear cache		
		add_filter("image_save_pre", array($this, "filter_image_save_pre"), 10, 2);
		add_filter('edit_attachment', array($this, "filter_edit_attachment"));
				
	}

	function is_simple_thumbs_request() {
	    // if run early (like in init) no usable info in wp_query at this time
	    // so check REQUEST_URI directly, if it begins with /image/
	    return (preg_match("/^\/image\//", $_SERVER["REQUEST_URI"]));
	}

	function action_init() {

		// remove filters for wp_minify and autoptimize
		// because they breaks the image. other than that they are really nice plugins!
		if ($this->is_simple_thumbs_request()) {
			remove_action("template_redirect", "autoptimize_start_buffering", 2);
			global $wp_filter;
			if (isset($wp_filter["init"][99999])) {
				foreach ($wp_filter["init"][99999] as $key => $val) {
					// look for something like 00000000280de24000000000590d4c56pre_content] => Array
					if (strpos($key, "pre_content") !== false) {
						unset($wp_filter["init"][99999][$key]);
					}
					
				}
			}
		}
		
	}


	function filter_edit_attachment($post_id) {
		$this->clear_cache_for_specific_post_id($post_id);
		return $post_id;
	}
	function filter_image_save_pre($image, $post_id) {
		$this->clear_cache_for_specific_post_id($post_id);
		return $image;
		exit;
	}

	// clear all cache files for a specific file
	function clear_cache_for_specific_post_id($post_id) {
		$post_id = (int) $post_id;
		$glob = $this->get_cache_dir() . "{$post_id}-*";
		$files = glob($glob, GLOB_BRACE);
		foreach ($files as $one_file) {
			unlink ($one_file);
		}
	}

	function clear_cache_completely() {
		$glob = $this->get_cache_dir() . "*-*";
		$files = glob($glob, GLOB_BRACE);
		foreach ($files as $one_file) {
			unlink ($one_file);
		}
	}


	// lets cache for admins too
	function filter_nocache_headers($headers) {
		return array();
	}

	/**
	 * Deactivation
	 * Flush rewrite rules (probably pointless, since we at the same time set up rules.. or..?)
	 * Remove all cache files
	 */
	function deactivation_hook() {
		flush_rewrite_rules(false);
		$this->clear_cache_completely();
	}

	function activation_hook() {
		// check that cache dir exists and is writable
		// printf("<p>Cache directory is '%s'.</p>", $this->get_cache_dir());
		$went_ok = true;
		if (!is_dir($this->get_cache_dir())) {
			printf("<p>Please create cache directory '%s'</p>", $this->get_cache_dir());
			$went_ok = false;
		} else {
			if (!is_writable($this->get_cache_dir())) {
				printf("<p>Please make sure cache directory '%s' is writable.</p>", $this->get_cache_dir());
				$went_ok = false;
			}
		}
		flush_rewrite_rules(false); // do a soft flush of the rules
		if ($went_ok == false) {
			// not ok, so don't activate
			deactivate_plugins(__FILE__, $silent = false);
			exit;
		}
	}

	function wp() {

		global $wp_query;
		$this->query_args = $wp_query->get("simple_thumbs_attachment_args");
		$this->args = $this->parse_parameters($this->query_args);

		// prefix cache file with attachment id so we can do stuff to that (like clear the cache)
		$this->cache_dir = $this->get_cache_dir();
		$this->cache_file = $this->args["attachment_id"] . "-" . md5($this->query_args);
		
		// Get from cache, if cache file exists
		$cache_file = $this->cache_dir . $this->cache_file;
		if (file_exists($cache_file)) {
			header ('Simple-Thumbs: get-cached-file');
			$this->show_cache_file();
		}

	}

	// register our rewrite rule
	// will be like: /image/100/myimage.jpg
	function generate_rewrite_rules($wp_rewrite) {
		$rule = array(
			'image/(.+)/(.+)' => 'simple_thumbs.php?simple_thumbs_attachment_args=' . $wp_rewrite->preg_index(1) . "&simple_thumbs_attachment_name=" . $wp_rewrite->preg_index(2)
		);
		$wp_rewrite->rules = $rule + $wp_rewrite->rules;
	}

	// must add the variables setup above so WordPress recognize them
	function query_vars($qvars) {
		$qvars[] = 'simple_thumbs_attachment_args';
		$qvars[] = 'simple_thumbs_attachment_name';
		return $qvars;
	}

	// redirect_canonical wants to add a slash to our request
	// but we really want it to end with for example /MyImage.jpg, not /MyImage.jpg/
	function redirect_canonical($redirect_url, $requested_url) {
	
		global $wp_query;
		if ($wp_query->get("simple_thumbs_attachment_args")) {
			return $requested_url;
		}

	}
	
	// Here the magic begins!
	function template_redirect() {
		
	    global $wp_query;
		if ($this->query_args) {

			$upload_dir = wp_upload_dir();

			// we have the args from the query
			// now fetch the attachment from WordPress
			$attachment_id = $this->args["attachment_id"];
			if (!$this->args["attachment_id"]) {
				wp_die("Image ID missing.", "Simple Thumbs Error");
			}

			$attachment_meta = wp_get_attachment_metadata($attachment_id); // width, height, file
			if (!$attachment_meta) {
				wp_die("Image not found.", "Simple Thumbs Error");
			}
			$attachment_local_file = $upload_dir["basedir"] . "/" . $attachment_meta["file"];
			

			// now we have all info to start resizing
			$resizeObj = new simple_thumbs_resize($attachment_local_file);

			$doTheResize = true;
			// check if resize args is equal to real size or no size set
			// in that case we should not try to resize it, because
			// we want to keep everything as it is
			// ... but only if we don't have any args like quality or unsharp
			if (
				(($resizeObj->width == $this->args["w"] && $resizeObj->height == $this->args["h"])
				|| (!$this->args["w"] && !$this->args["h"]))
				&&
				!$this->args["qInitiallySet"] && !$this->args["u"] && !$this->args["fInitiallySet"]
			) {
				$doTheResize = false;
			}
			
			if ($doTheResize) {
				$resizeObj->resizeImage($this->args["w"], $this->args["h"], $this->args["m"]);

				// if u = true then unsharp
				// it's really really nice for small images/thumbnails
				if ($this->args["u"]) {
					// $amount = 80, $radius = 0.5, $threshold = 3
					switch ($this->args["u"]) {
						case "2":
							$unsharp_amount = 60;
							$unsharp_radius = 0.4;
							$unsharp_threshold = 3;
							break;
						case "3":
							$unsharp_amount = 80;
							$unsharp_radius = 0.5;
							$unsharp_threshold = 3;
							break;
						case "1":
						default:
							$unsharp_amount = 40;
							$unsharp_radius = 0.3;
							$unsharp_threshold = 2;
							break;
	
					}
					$resizeObj->unsharpImage($unsharp_amount, $unsharp_radius, $unsharp_threshold);
				}
			}
					
			// save to cache folder
			if (is_dir($this->cache_dir) && (is_writable($this->cache_dir))) {

				// ok
				$this->clean_cache();
				header ('Simple-Thumbs: save-new-cached-file');
				
				if ($doTheResize) {
					$resizeObj->saveImage($this->cache_dir . $this->cache_file, $this->args["q"], $this->args["f"]);
				} else {
					// if no resize, we must save the file anyway..
					// bascially just copy original file to cache file. yeah.
					copy($attachment_local_file, $this->cache_dir . $this->cache_file);
				}
				
				// image written, now serve it
				$this->show_cache_file();
				
			} else {
				wp_die("Cache directory must exist and be writable.", "Simple Thumbs Error");
			}
			
			exit;
		}
		
	
	}

	// from timthumb
	function show_cache_file() {
	
		$cache_file = $this->cache_dir . $this->cache_file;

	    if (file_exists($cache_file)) {

			// use browser cache if available to speed up page load
	        if (isset ($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
	            if (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) < strtotime('now')) {
	              	header ('HTTP/1.1 304 Not Modified');
	              	die();
				}
			}
			clearstatcache ();
			$fileSize = filesize ($cache_file);

			// change the modified headers
			$gmdate_expires = gmdate('D, d M Y H:i:s', strtotime('now +10 days')) . ' GMT';
			$gmdate_modified = gmdate('D, d M Y H:i:s') . ' GMT';
	
			// send content headers then display image
			switch ($this->args["f"]) {
				case "jpg":
					$mime_type = "image/jpeg";
					break;
				case "png":
					$mime_type = "image/png";
					break;
				case "gif":
					$mime_type = "image/png";
					break;
			}
			header ('Content-Type: ' . $mime_type);
			header ('Accept-Ranges: bytes');
			header ('Last-Modified: ' . $gmdate_modified);
			header ('Content-Length: ' . $fileSize);
			header ('Cache-Control: max-age=864000, must-revalidate');
			header ('Expires: ' . $gmdate_expires);
	
			if (!@readfile ($cache_file)) {
				$content = file_get_contents ($cache_file);
				if ($content != FALSE) {
					echo $content;
				} else {
					wp_die("Cache file could not be loaded.", "Simple Thumbs Error");
				}
			}

			// we've shown the image so stop processing
	        die();
	
	    }
	
	}

	function get_cache_dir() {

		// use wp-content/cache/simple-thumbs/
		if (defined("WP_CONTENT_DIR")) {
			$cache_dir = WP_CONTENT_DIR . "/cache/simple-thumbs/";
		}
		// cache dir in subfolder of plugin
		#$cache_dir = dirname(__FILE__) . "/cache/";
		
		return $cache_dir;
	}
	
	/**
	 * get/parse all our paramters
	 * Format
	 * /1100,w100,h65,q64/myimage.jpg
	 * /image/1100|w100|h65|q64/myimage.jpg
	 * =
	 * image id 1100
	 * size 100x65
	 * quality 75
	 * filter 1 (negative.. or something)
	 * @return array
	 */
	function parse_parameters($args) {
	
		$arr_args = preg_split($this->regexp_dividers, $args);
		
		// first args id always id
		// all others have a prefix of one letter
		$arr_real_args = array(
			"attachment_id" => (int) $arr_args[0]
		);
		for ($i=1; $i<sizeof($arr_args); $i++) {
			$this_arg = $arr_args[$i];
			$key = substr($this_arg, 0, 1);
			$val = substr($this_arg, 1);
			$arr_real_args[$key] = $val;
		}
		
		/*
		make sure method/mode is one of
		case 'exact':
		case 'portrait':
		case 'landscape':
		case 'auto':
		case 'crop':
		case 'within';
		*/
		$method = "within"; // default method
		if (isset($arr_real_args["m"])) {
			$arg_method = $arr_real_args["m"];
			if ($arg_method == "exact" || $arg_method == "e") {
				$method = "exact";
			} elseif ($arg_method == "portrait" || $arg_method == "p") {
				$method = "portrait";
			} elseif ($arg_method == "landscape" || $arg_method == "l") {
				$method = "landscape";
			} elseif ($arg_method == "auto" || $arg_method == "a") {
				$method = "auto";
			} elseif ($arg_method == "crop" || $arg_method == "c") {
				$method = "crop";
			} elseif ($arg_method == "within" || $arg_method == "w") {
				$method = "within";
			}
		}
		$arr_real_args["m"] = $method;
		
		// make sure quality is an int
		if (isset($arr_real_args["q"])) {
			$arr_real_args["qInitiallySet"] = true;
		} else {
			$arr_real_args["qInitiallySet"] = false;
		}
		$arr_real_args["q"] = (int) $arr_real_args["q"];
		// default 85
		if (!$arr_real_args["q"]) { $arr_real_args["q"] = 85; }
		
		// output format. default to jpg
		if (isset($arr_real_args["f"])) {
			$arr_real_args["fInitiallySet"] = true;
		} else {
			$arr_real_args["fInitiallySet"] = false;
		}
		if ($arr_real_args["f"] == "j") {
			$arr_real_args["f"] = "jpg";
		} elseif ($arr_real_args["f"] == "p") {
			$arr_real_args["f"] = "png";
		} elseif ($arr_real_args["f"] == "g") {
			$arr_real_args["f"] = "gif";
		} else {
			$arr_real_args["f"] = "jpg";
		}
		
		return $arr_real_args;
	
	}
	
	function d($arg) {
		echo "<pre>";
		print_r($arg);
		echo "</pre>";
	}

	/**
	 * clean out old files from the cache
	 * you can change the number of files to store and to delete per loop in the defines at the top of the code
	 *
	 * @return <type>
	 */
	function clean_cache() {
	
		// Reduces the amount of cache clearing to save some processor speed
		if (rand (1, 100) > 10) {
			return true;
		}
	
		$files = glob($this->cache_dir . '*', GLOB_BRACE);
		if (count($files) > $this->cache_max_files) {
			
	        $yesterday = time () - (24 * 60 * 60);
	        usort ($files, 'simple_thumbs_filemtime_compare');
	        $i = 0;
			foreach ($files as $file) {
				$i ++;
	
				if ($i >= $this->cache_max_files_to_delete) {
					return;
				}
	
				if (@filemtime ($file) > $yesterday) {
					return;
				}
	
				if (file_exists ($file)) {
					unlink ($file);
				}
	
			}
	
	    }
	
	}

}
}

if( class_exists( 'wp_plugin_simple_thumbs' ) ) {

}

// Instantiate
$wp_plugin_simple_thumbs = new wp_plugin_simple_thumbs;


/**
 * compare the file time of two files
 *
 * @param <type> $a
 * @param <type> $b
 * @return <type>
 */
if (!function_exists("simple_thumbs_filemtime_compare")) {
function simple_thumbs_filemtime_compare($a, $b) {

	#$break = explode ('/', $_SERVER['SCRIPT_FILENAME']);
	#$filename = $break[count($break) - 1];
	#$filepath = str_replace ($filename, '', $_SERVER['SCRIPT_FILENAME']);

	#$file_a = realpath ($filepath . $a);
	#$file_b = realpath ($filepath . $b);
    return filemtime ($file_a) - filemtime ($file_b);

}
}





# ========================================================================#
#
#  Author:    Jarrod Oberto
#  Version:	 1.0
#  Date:      17-Jan-10
#  Purpose:   Resizes and saves image
#  Requires : Requires PHP5, GD library.
#  Usage Example:
#                     include("classes/resize_class.php");
#                     $resizeObj = new resize('images/cars/large/input.jpg');
#                     $resizeObj -> resizeImage(150, 100, 0);
#                     $resizeObj -> saveImage('images/cars/large/output.jpg', 100);
#
#
# ========================================================================#


if (!class_exists("simple_thumbs_resize")) {
Class simple_thumbs_resize
{
	// *** Class variables
	private $image;
    public $width;
    public $height;
	private $imageResized;

	function __construct($fileName = null)
	{
	
		if ($fileName) {
			// *** Open up the file
			$this->image = $this->openImage($fileName);
	
		    // *** Get width and height
		    $this->width  = imagesx($this->image);
		    $this->height = imagesy($this->image);
	    }
	}

	## --------------------------------------------------------

	private function openImage($file)
	{
		// *** Get extension
		$extension = strtolower(strrchr($file, '.'));

		switch($extension)
		{
			case '.jpg':
			case '.jpeg':
				$img = @imagecreatefromjpeg($file);
				break;
			case '.gif':
				$img = @imagecreatefromgif($file);
				break;
			case '.png':
				$img = @imagecreatefrompng($file);
				break;
			default:
				$img = false;
				break;
		}
		return $img;
	}

	## --------------------------------------------------------

	public function resizeImage($newWidth, $newHeight, $option="auto")
	{

		// if dimensions are empty, use original size
		// so we don't get any division by zero-errors
		$newWidth = (int) $newWidth;
		$newHeight = (int) $newHeight;
		if (!$newWidth) { $newWidth = $this->width; }
		if (!$newHeight) { $newHeight = $this->height; }

		// *** Get optimal width and height - based on $option
		$optionArray = $this->getDimensions($newWidth, $newHeight, $option);

		$optimalWidth  = $optionArray['optimalWidth'];
		$optimalHeight = $optionArray['optimalHeight'];

		// *** Resample - create image canvas of x, y size
		$this->imageResized = imagecreatetruecolor($optimalWidth, $optimalHeight);
		if (imagetypes() & IMG_PNG) {
			// added by Pär
			// http://net.tutsplus.com/tutorials/php/image-resizing-made-easy-with-php/comment-page-2/#comment-292270
			imagesavealpha($this->imageResized, true);
			imagealphablending($this->imageResized, false);
		}
		imagecopyresampled($this->imageResized, $this->image, 0, 0, 0, 0, $optimalWidth, $optimalHeight, $this->width, $this->height);

		// *** if option is 'crop', then crop too
		if ($option == 'crop') {
			$this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight);
		}
	}

	## --------------------------------------------------------
	
	public function getDimensions($newWidth, $newHeight, $option)
	{
		$newWidth = (int) $newWidth;
		$newHeight = (int) $newHeight;
		if (!$newWidth) { $newWidth = $this->width; }
		if (!$newHeight) { $newHeight = $this->height; }

		#echo "<br><br>option: $option<br>newWidth: $newWidth<br>thiswidth: $this->width<br>newHeight: $newHeight<br>thisheight: $this->height";
	   switch ($option)
		{
			case 'exact':
			case "e":
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
				break;
			case 'portrait':
			case "p":
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
				break;
			case 'landscape':
			case "l":
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
				break;
			case 'auto':
			case "a":
				$optionArray = $this->getSizeByAuto($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			case 'crop':
			case "c":
				$optionArray = $this->getOptimalCrop($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			case 'within':
			case "w":
				// added by Pär Thernström
				// stay within newWidth & newHeight, but make it as large as possible
				// if $max_width is det but not $max_height, don't freak out
				#if (!empty($max_width) && empty($max_height)) {
				#	$max_height = $max_width*99;
				#} elseif (empty($max_width) && !empty($max_height)) {
				#	$max_width = $max_height*99;
				#}
			    $optionArray = $this->getSizeByWithin($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
				
				
		}

		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	## --------------------------------------------------------

	private function getSizeByWithin($newWidth, $newHeight) {
	    $x_ratio = $newWidth / $this->width;
	    $y_ratio = $newHeight / $this->height;
	    if( ($this->width <= $newWidth) && ($this->height <= $newHeight) ){
	        $tn_width = $this->width;
	        $tn_height = $this->height;
        } elseif (($x_ratio * $this->height) < $newHeight){

            $tn_height = ceil($x_ratio * $this->height);
            $tn_width = $newWidth;
        } else {

            $tn_width = ceil($y_ratio * $this->width);
            $tn_height = $newHeight;
	    }
	    $optimalWidth = $tn_width;
	    $optimalHeight = $tn_height;
	    return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	private function getSizeByFixedHeight($newHeight)
	{
		$ratio = $this->width / $this->height;
		$newWidth = $newHeight * $ratio;
		return $newWidth;
	}

	private function getSizeByFixedWidth($newWidth)
	{
		$ratio = $this->height / $this->width;
		$newHeight = $newWidth * $ratio;
		return $newHeight;
	}

	private function getSizeByAuto($newWidth, $newHeight)
	{
		if ($this->height < $this->width)
		// *** Image to be resized is wider (landscape)
		{
			$optimalWidth = $newWidth;
			$optimalHeight= $this->getSizeByFixedWidth($newWidth);
		}
		elseif ($this->height > $this->width)
		// *** Image to be resized is taller (portrait)
		{
			$optimalWidth = $this->getSizeByFixedHeight($newHeight);
			$optimalHeight= $newHeight;
		}
		else
		// *** Image to be resizerd is a square
		{
			if ($newHeight < $newWidth) {
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
			} else if ($newHeight > $newWidth) {
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
			} else {
				// *** Sqaure being resized to a square
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
			}
		}

		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	## --------------------------------------------------------

	private function getOptimalCrop($newWidth, $newHeight)
	{

		$heightRatio = $this->height / $newHeight;
		$widthRatio  = $this->width /  $newWidth;

		if ($heightRatio < $widthRatio) {
			$optimalRatio = $heightRatio;
		} else {
			$optimalRatio = $widthRatio;
		}

		$optimalHeight = $this->height / $optimalRatio;
		$optimalWidth  = $this->width  / $optimalRatio;

		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	## --------------------------------------------------------

	private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight)
	{
		// *** Find center - this will be used for the crop
		$cropStartX = ( $optimalWidth / 2) - ( $newWidth /2 );
		$cropStartY = ( $optimalHeight/ 2) - ( $newHeight/2 );

		$crop = $this->imageResized;
		//imagedestroy($this->imageResized);

		// *** Now crop from center to exact requested size
		if (imagetypes() & IMG_PNG) {
			// added by Pär
			// http://net.tutsplus.com/tutorials/php/image-resizing-made-easy-with-php/comment-page-2/#comment-292270
			imagesavealpha($this->imageResized, true);
			imagealphablending($this->imageResized, false);
		}
		$this->imageResized = imagecreatetruecolor($newWidth , $newHeight);
		imagecopyresampled($this->imageResized, $crop , 0, 0, $cropStartX, $cropStartY, $newWidth, $newHeight , $newWidth, $newHeight);
	}

	## --------------------------------------------------------

	public function saveImage($savePath, $imageQuality="100", $extension)
	{
		// *** Get extension
		#$extension = strrchr($savePath, '.');
		#$extension = strtolower($extension);

		switch($extension)
		{
			case 'jpg':
			case 'jpeg':
				if (imagetypes() & IMG_JPG) {
					imagejpeg($this->imageResized, $savePath, $imageQuality);
				}
				break;

			case 'gif':
				if (imagetypes() & IMG_GIF) {
					imagegif($this->imageResized, $savePath);
				}
				break;

			case 'png':
				// *** Scale quality from 0-100 to 0-9
				$scaleQuality = round(($imageQuality/100) * 9);

				// *** Invert quality setting as 0 is best, not 9
				$invertScaleQuality = 9 - $scaleQuality;

				if (imagetypes() & IMG_PNG) {
					 imagepng($this->imageResized, $savePath, $invertScaleQuality);
				}
				break;

			// ... etc

			default:
				// *** No extension - No save.
				break;
		}

		imagedestroy($this->imageResized);
	}
	
	public function outputToBrowser() {
		header("Content-type: image/jpeg");
		imagejpeg($this->imageResized, null, 75);
	}


	## --------------------------------------------------------

	public function unsharpImage($amount = 80, $radius = 0.5, $threshold = 3) {
		$this->imageResized = $this->UnsharpMask($this->imageResized, $amount, $radius, $threshold);
	}

	/*
	Unsharp filter for PHp
	from: http://vikjavev.no/computing/ump.php?id=306
	
	New: 
	- In version 2.1 (February 26 2007) Tom Bishop has done some important speed enhancements.
	- From version 2 (July 17 2006) the script uses the imageconvolution function in PHP 
	version >= 5.1, which improves the performance considerably.
	
	
	Unsharp masking is a traditional darkroom technique that has proven very suitable for 
	digital imaging. The principle of unsharp masking is to create a blurred copy of the image
	and compare it to the underlying original. The difference in colour values
	between the two images is greatest for the pixels near sharp edges. When this 
	difference is subtracted from the original image, the edges will be
	accentuated. 
	
	The Amount parameter simply says how much of the effect you want. 100 is 'normal'.
	Radius is the radius of the blurring circle of the mask. 'Threshold' is the least
	difference in colour values that is allowed between the original and the mask. In practice
	this means that low-contrast areas of the picture are left unrendered whereas edges
	are treated normally. This is good for pictures of e.g. skin or blue skies.
	
	Any suggenstions for improvement of the algorithm, expecially regarding the speed
	and the roundoff errors in the Gaussian blur process, are welcome.
	
	*/
	
	public function UnsharpMask($img, $amount, $radius, $threshold) { 
	
	////////////////////////////////////////////////////////////////////////////////////////////////  
	////  
	////                  Unsharp Mask for PHP - version 2.1.1  
	////  
	////    Unsharp mask algorithm by Torstein Hønsi 2003-07.  
	////             thoensi_at_netcom_dot_no.  
	////               Please leave this notice.  
	////  
	///////////////////////////////////////////////////////////////////////////////////////////////  
	
	
	
	    // $img is an image that is already created within php using 
	    // imgcreatetruecolor. No url! $img must be a truecolor image. 
	
	    // Attempt to calibrate the parameters to Photoshop: 
	    if ($amount > 500)    $amount = 500; 
	    $amount = $amount * 0.016; 
	    if ($radius > 50)    $radius = 50; 
	    $radius = $radius * 2; 
	    if ($threshold > 255)    $threshold = 255; 
	     
	    $radius = abs(round($radius));     // Only integers make sense. 
	    if ($radius == 0) { 
	        return $img; imagedestroy($img); break;        } 
	    $w = imagesx($img); $h = imagesy($img); 
	    $imgCanvas = imagecreatetruecolor($w, $h); 
	    $imgBlur = imagecreatetruecolor($w, $h); 
	     
	
	    // Gaussian blur matrix: 
	    //                         
	    //    1    2    1         
	    //    2    4    2         
	    //    1    2    1         
	    //                         
	    ////////////////////////////////////////////////// 
	         
	
	    if (function_exists('imageconvolution')) { // PHP >= 5.1  
	            $matrix = array(  
	            array( 1, 2, 1 ),  
	            array( 2, 4, 2 ),  
	            array( 1, 2, 1 )  
	        );  
	        imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h); 
	        imageconvolution($imgBlur, $matrix, 16, 0);  
	    }  
	    else {  
	
	    // Move copies of the image around one pixel at the time and merge them with weight 
	    // according to the matrix. The same matrix is simply repeated for higher radii. 
	        for ($i = 0; $i < $radius; $i++)    { 
	            imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left 
	            imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right 
	            imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center 
	            imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h); 
	
	            imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up 
	            imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down 
	        } 
	    } 
	
	    if($threshold>0){ 
	        // Calculate the difference between the blurred pixels and the original 
	        // and set the pixels 
	        for ($x = 0; $x < $w-1; $x++)    { // each row
	            for ($y = 0; $y < $h; $y++)    { // each pixel 
	                     
	                $rgbOrig = ImageColorAt($img, $x, $y); 
	                $rOrig = (($rgbOrig >> 16) & 0xFF); 
	                $gOrig = (($rgbOrig >> 8) & 0xFF); 
	                $bOrig = ($rgbOrig & 0xFF); 
	                 
	                $rgbBlur = ImageColorAt($imgBlur, $x, $y); 
	                 
	                $rBlur = (($rgbBlur >> 16) & 0xFF); 
	                $gBlur = (($rgbBlur >> 8) & 0xFF); 
	                $bBlur = ($rgbBlur & 0xFF); 
	                 
	                // When the masked pixels differ less from the original 
	                // than the threshold specifies, they are set to their original value. 
	                $rNew = (abs($rOrig - $rBlur) >= $threshold)  
	                    ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))  
	                    : $rOrig; 
	                $gNew = (abs($gOrig - $gBlur) >= $threshold)  
	                    ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))  
	                    : $gOrig; 
	                $bNew = (abs($bOrig - $bBlur) >= $threshold)  
	                    ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))  
	                    : $bOrig; 
	                 
	                 
	                             
	                if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) { 
	                        $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew); 
	                        ImageSetPixel($img, $x, $y, $pixCol); 
	                    } 
	            } 
	        } 
	    } 
	    else{ 
	        for ($x = 0; $x < $w; $x++)    { // each row 
	            for ($y = 0; $y < $h; $y++)    { // each pixel 
	                $rgbOrig = ImageColorAt($img, $x, $y); 
	                $rOrig = (($rgbOrig >> 16) & 0xFF); 
	                $gOrig = (($rgbOrig >> 8) & 0xFF); 
	                $bOrig = ($rgbOrig & 0xFF); 
	                 
	                $rgbBlur = ImageColorAt($imgBlur, $x, $y); 
	                 
	                $rBlur = (($rgbBlur >> 16) & 0xFF); 
	                $gBlur = (($rgbBlur >> 8) & 0xFF); 
	                $bBlur = ($rgbBlur & 0xFF); 
	                 
	                $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig; 
	                    if($rNew>255){$rNew=255;} 
	                    elseif($rNew<0){$rNew=0;} 
	                $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig; 
	                    if($gNew>255){$gNew=255;} 
	                    elseif($gNew<0){$gNew=0;} 
	                $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig; 
	                    if($bNew>255){$bNew=255;} 
	                    elseif($bNew<0){$bNew=0;} 
	                $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew; 
	                    ImageSetPixel($img, $x, $y, $rgbNew); 
	            } 
	        } 
	    } 
	    imagedestroy($imgCanvas); 
	    imagedestroy($imgBlur); 
	     
	    return $img; 
	
	}


}
}

