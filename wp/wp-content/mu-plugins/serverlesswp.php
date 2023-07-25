<?php

/*
Plugin Name: ServerlessWP
Plugin URI: https://github.com/mitchmac/serverlesswp
Description: Plugin that makes sure your site runs smoothly on Vercel or Netlify.
Author: Mitch MacKenzie
Version: 1.0.0
*/

add_filter( 'got_url_rewrite', '__return_true' );