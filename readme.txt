=== Simple Thumbs ===
Contributors: eskapism, MarsApril
Donate link: http://eskapism.se/sida/donate/
Tags: image, gd, thumbs, thumbnails, photos, images, resize, attachments, gallery, function
Requires at least: 3.0
Tested up to: 3.0
Stable tag: trunk


Generate image thumbs from WP attachments, with options to crop or fit to the wanted size.
Also create IMG-tags with correct width & height attributes set after resize.

== Description ==

(Nice of you to find this plugin. I'm still working on the readme and on a example/tutorial. Stay tuned!)

This plugin does two things:

1. It creates rewrite rules that let you create nice urls for your images, with the option to resize and crop the image.
2. It adds a function, simple_thumbs_img(), that works as a wrapper to the rewrite rules. 
With this function you can create ready-to-go IMG-tags, that even if you choose to resize the 
image it will output the correct width and height.


== Rewrite Rules/Nice Image URLs example ==

Instead of this URL:
http://eskapism.se/wordpress/wp-content/uploads/2010/02/DSC_0003.jpg

Your image can have this URL, where 55 is the Attachment ID:
http://eskapism.se/image/55/DSC_0003.jpg

Shorter and sweeter.
But there's more! You can also send in some arguments:

Resize the image to be 150px in width:
'http://eskapism.se/image/55:w150/DSC_0003.jpg'

Resize the image to be 150px in width, and give it another name (you can name the image to whatever you want)
http://eskapism.se/image/55:w150/my-cool-image.jpg

Resize the image to 150px in height:
http://eskapism.se/image/55:h150/DSC_0003.jpg

Resize the image to stay within 150px in height and width:
http://eskapism.se/image/55:w150:h150/DSC_0003.jpg

Crop the image to exactly 150px in width and height:
http://eskapism.se/image/55:w150:h150/DSC_0003.jpg

Make a small thumb, and also add an unsharp filter:
http://eskapism.se/image/55:w175:h75:c1:u1/DSC_0003.jpg

Make a small thumb, and also add an unsharp filter, and output as png:
http://eskapism.se/image/55:w175:h75:c1:u1:fp/DSC_0003.jpg

Same as above, but with pipe as the delimeter (you can choose between ,._- and |):
http://eskapism.se/image/55|w175|h75|c1|u1|fp/DSC_0003.jpg

Please note that all the resize stuff where made without adding any querystring to the URL.
This is good for Search Engine Optimization (SEO) reasones, but also for caching reasons. 
Google Page Speed will for example give you a higher score because of this! :)

All generated images will be cached, so only the first call to each URL restults in an actual resize of the image.
Images are also sent with far future expires headers, so if a user returns to your page all images should load blazingly fast.


== simple_thumbs_img(): The Magic Function ==

simple_thumbs_img() generates IMG tags for you, with the correct width & height attributes set, even after resize.

With no width and height values set, the page may be redrawn several times, resulting in a very "jumpy" page.
Using Simple Thumb to create your image tag will solve this problem.

Lack of width and height atributes in img-tags can also lead to 
errors when JavaScript ondomready calculations are made while images are still loading, since
it can't determine the size of the image.


<code>
    <?php
	
	// get img tag with nice url for image with id 55, with the correct width and height attributes set.
	// do whatever you wan't with it
	$img_src = simple_thumbs_img("id=55&tag=1");

	// print img tag with nice url for image with id 55, with the correct width and height attributes set.
	echo simple_thumbs_img("id=55&tag=1");
	
	// print img tag with nice url for image with id 55, and resize it to be a thumb that has the max size 75x75,
	// with the correct width and height attributes set.
	echo simple_thumbs_img("id=55&tag=1&w=75&h=75");
	
	// print img tag with nice url for image with id 55, and crop it to be a thumb that has the excact size 75x75,
	//with the correct width and height attributes set, and add an alt text
	echo simple_thumbs_img("id=55&tag=1&w=75&h=75&mc&alt=My alternative text");

    ?>
</code>


== Resize modes ==

* within
* crop
* portrait
* landscape
* auto

Filter (well.. one so far!)

* unsharp mask - good for making small thumbnails appear to have more detail. Once you've gone unsharp, you don't want to go back! :)


#### Donation and more plugins
* If you like this plugin don't forget to [donate to support further development](http://eskapism.se/sida/donate/).
* More [WordPress CMS plugins](http://wordpress.org/extend/plugins/profile/eskapism) by the same author.

== Installation ==

1. Upload the folder "simple-thumbs" to "/wp-content/plugins/"
1. Activate the plugin through the "Plugins" menu in WordPress
1. Done! Now start editing your template files. See usage for more info.


== Screenshots ==

1. No screenshots yet.

== Changelog ==

= 0.1 =
- First version. Works fine for me. Let me know how your thumbnail-experience is going!
