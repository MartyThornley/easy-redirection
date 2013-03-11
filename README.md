easy-redirection
================

Easy Redirection

Uses custom post types to create redirects within WordPress.

BETA!

Do not use on production sites!

Uses custom post types.

Meant to let you redirect mysite.com/SOMETHING to any url.

*Things to test...*

* It should not let you save duplicate redirects ( the /SOMETHING part ).
* It should not do anything if the url you want to redirect to does not work. It uses wp_remote_get() to test the page first.
