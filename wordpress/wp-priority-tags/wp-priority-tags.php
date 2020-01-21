<?php

/**
 * @package WP Priority Tags
 */

/*

Plugin Name: WP Priority Tags
Plugin URI: https://lukejournal.co.uk/wp-priority-tags-plugin/
Description: Enables addition of a priority 'flag' on a post-tag relationship. For example you may want to be able to tell Wordpress that a certain post in a tag has priority over the rest, for whatever reason. More info available in the Github README.
Version: 1.0
Author: Luke Cohen
Author URI: https://github.com/lukenicohen
License: GPLv2 Copyright (c) 2016 Luke Cohen
Text Domain: wp-priority-tags
Domain Path: /language

*/


/* Returns a 'safe' postID for the current post in admin->edit (safe, i.e it's type-checked to be an integer, or returns 0) */
function getPostID() {

  $postID = 0;

  if ($_POST && $_POST['post_ID'] && is_numeric($_POST['post_ID'])) {
    $postID = intval($_POST['post_ID']);
  }

  if ($_GET && $_GET['post'] && is_numeric($_GET['post'])) {
    $postID = intval($_GET['post']);
  }

  return $postID;

}


function loadAdminJS($hook) {

  if ($hook == 'post.php' || $hook == 'post-new.php') {

    wp_register_script('taxrel_js', plugin_dir_url( __FILE__ ) . 'immediate-taxrel-meta.dev.js', array('jquery'), rand(1,1000), true);

    $curPriorityFlags = array();

    if ($hook == 'post-new.php') {
      $adminAction = 'new';
    } else {

      $adminAction = 'edit';

      $postID = getPostID();
      $curPriorityFlags = wp_get_post_terms($postID, 'taxonomy_priority');

    }

    wp_localize_script('taxrel_js', 'actionFlags', array(
      'action'  => $adminAction,
      'flags'   => $curPriorityFlags
    ));

    wp_enqueue_script('taxrel_js');

  }

}

function loadAdminCSS() {

    wp_enqueue_style('immediate-taxrel-meta', plugins_url('immediate-taxrel-meta.css', __FILE__));

}


/* This function is called when a post is saved, which is when we want to apply our shadow priority taxonomies */
function doApplyFlags() {

  if ($_POST && $_POST['immFlagTags']) {

    $postID = getPostID();

    $flagTags = json_decode(stripslashes($_POST['immFlagTags']));

    $newTagFlags = array();

    foreach ($flagTags as $key => $value) {

      $tag = $key;
      $tagFlagged = $value;

      if ($tagFlagged == 'true') {
        array_push($newTagFlags, $tag);
      }

    }

    /* apply flag tag where appropriate */
    wp_set_object_terms($postID, $newTagFlags, 'taxonomy_priority', false);

  }

}


/* Initialisation function, create our custom taxonomies for internal management, etc. */
function immSetup() {

  /* Register our private custom taxonomy */
  register_taxonomy(
    'taxonomy_priority',
    array(
      'post'
    ),
    array(
      'label'         => 'Priority Flag',
      'public'        => false,
      'rewrite'       => false,
      'hierarchical'  => true,
    )
  );

}

add_action('admin_enqueue_scripts',     'loadAdminCSS');
add_action('login_enqueue_scripts',     'loadAdminCSS');
add_action('admin_enqueue_scripts',     'loadAdminJS');

add_action('init',                      'immSetup');

add_action('save_post',                 'doApplyFlags');

?>
