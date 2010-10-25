=== Simple Thumbs ===
Contributors: eskapism, MarsApril
Donate link: http://eskapism.se/sida/donate/
Tags: image, gd, thumbs, thumbnails, photos, images, resize, attachments, gallery
Requires at least: 3.0
Tested up to: 3.0
Stable tag: trunk

Generates image thumbs from WP attachments, with options to crop or fit to the wanted size.
Can also create IMG-tags with the correct width & height attributes set, even after resize.

== Description ==

(Nice of you to find this plugin. I'm still working on the readme and on a example/tutorial)

Generates image thumbs, with options to crop or fit to the wanted size.
Using custom rewrite rules the urls are also pretty nice and SEO-friendly.
You can also generate img tags with the correct width & height attributes set, even after resize.

With no width and height values set, the page may be redrawn several times, resulting in a very "jumpy" page.
Using Simple Thumb to create your image tag will solve this problem.

Lack of width and height atributes in img-tags can also lead to 
errors when JavaScript ondomready calculations are made while images are still loading, since
it can't determine the size of the image.

Several resize modes

* within
* crop
* portrait
* landscape
* auto

And some filter (well.. one!)

* unsharp mask - good for making small thumbnails appear to have more detail. Once you've gone unsharp, you don't want to go back! :)


#### Donation and more plugins
* If you like this plugin don't forget to [donate to support further development](http://eskapism.se/sida/donate/).
* More [WordPress CMS plugins](http://wordpress.org/extend/plugins/profile/eskapism) by the same author.

== Installation ==

1. Upload the folder "simple-thumbs" to "/wp-content/plugins/"
1. Activate the plugin through the "Plugins" menu in WordPress
1. Done! Now start editing your template files. See usage for more info.


== Screenshots ==

1. Well...

== Changelog ==

= 0.1 =
- First version. Works fine for me. Let me know how your thumbnail-experience is going!
