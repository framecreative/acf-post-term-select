<?php

/*
 * Plugin Name: Advanced Custom Fields: Post or Term Select
 * Plugin URI: https://github.com/framedigital/acf-post-term-select
 * Description: SHORT_DESCRIPTION
 * Version: 1.0.0
 * Author: Daniel Bitzer
 * Author URI: https://framecreative.com.au
 * License: GPLv3
 *
*/


// 1. set text domain
// Reference: https://codex.wordpress.org/Function_Reference/load_plugin_textdomain
load_plugin_textdomain('acf-post-or-taxonomy-link', false, dirname(plugin_basename(__FILE__)) . '/lang/');

// 2. Include field type for ACF5
// $version = 5 and can be ignored until ACF6 exists
function include_field_types_post_or_taxonomy_link($version)
{
    include_once('acf-post-or-taxonomy-link-v5.php');
}

add_action('acf/include_field_types', 'include_field_types_post_or_taxonomy_link');
