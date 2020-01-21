<?php

/**
 * @package WordPress Tag Priority Flag
 */

/*

Plugin Name: WordPress Tag Priority Flag
Plugin URI: https://...
Description: Enables addition of meta fields on a relationship between a post and a taxonomy. Useful when there's a requirement to go beyond just a simple post->taxonomy relationship, but to add depth to that relationship (i.e to give one tag of several a priority flag, or to mark a single post as having priority over other posts with that tag).
Version: 1.0
Author: Luke Cohen
Author URI: https://github.com/lukenicohen
Text Domain: wp-tag-priority
Domain Path: /language

*/

class ImmediateTaxrelMeta {

  /* Returns a 'safe' postID for the current post in admin->edit (safe, i.e it's type-checked to be an integer, or returns 0) */
  public function getPostID() {

    $postID = 0;

    if ($_POST && $_POST['post_ID'] && is_numeric($_POST['post_ID'])) {
      $postID = intval($_POST['post_ID']);
    }

    if ($_GET && $_GET['post'] && is_numeric($_GET['post'])) {
      $postID = intval($_GET['post']);
    }

    return $postID;

  }


  public function loadAdminJS($hook) {

    if ($hook == 'post.php' || $hook == 'post-new.php') {

      wp_register_script('taxrel_js', plugin_dir_url( __FILE__ ) . 'taxrel-meta.dev.js', array('jquery'), rand(1,1000), true);

      $curPriorityFlags = array();

      if ($hook == 'post-new.php') {
        $adminAction = 'new';
      } else {

        $adminAction = 'edit';

        $postID = self::getPostID();
        $curPriorityFlags = wp_get_post_terms($postID, 'taxonomy_priority');

      }

      wp_localize_script('taxrel_js', 'actionFlags', array(
        'action'  => $adminAction,
        'flags'   => $curPriorityFlags
      ));

      wp_enqueue_script('taxrel_js');

      return true;

    } else {

      return false;

    }

  }

  public function loadAdminCSS() {

      wp_enqueue_style('taxrel-meta', plugins_url('taxrel-meta.css', __FILE__));

  }


  /* This function is called when a post is saved, which is when we want to apply our shadow priority taxonomies */
  public function doApplyFlags($postID, $flagTags) {

    if ($postID && $flagTags) {

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

      return true;

    } else {
      return false;
    }

  }

  public function tryApplyFlags() {

    if ($_POST && $_POST['immFlagTags']) {

      $postID = self::getPostID();
      $flagTags = json_decode(stripslashes($_POST['immFlagTags']));

      self::doApplyFlags($postID, $flagTags);

    } else {
      return false;
    }

  }


  /* Check if our custom taxonomy exists */
  public function immTaxExists() {

    return taxonomy_exists('taxonomy_priority');

  }


  /* Admin-side initialisation */
  public function adminInit($hook) {

    wp_enqueue_style('taxrel-meta', plugins_url('taxrel-meta.css', __FILE__));

    if ($hook && $hook == 'post.php' || $hook == 'post-new.php') {

      wp_register_script('taxrel_js', plugin_dir_url( __FILE__ ) . 'taxrel-meta.dev.js', array('jquery'), rand(1,1000), true);

      $curPriorityFlags = array();

      if ($hook == 'post-new.php') {
        $adminAction = 'new';
      } else {

        $adminAction = 'edit';

        $postID = self::getPostID();
        $curPriorityFlags = wp_get_post_terms($postID, 'taxonomy_priority');

      }

      wp_localize_script('taxrel_js', 'actionFlags', array(
        'action'  => $adminAction,
        'flags'   => $curPriorityFlags
      ));

      wp_enqueue_script('taxrel_js');

    }    

  }


  /* Initialisation function, create our custom taxonomies for internal management, etc. */
  public function immSetup() {

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

    return 'immSetup';

  }

}


add_action('admin_enqueue_scripts',     array('ImmediateTaxrelMeta', 'adminInit'));
add_action('login_enqueue_scripts',     array('ImmediateTaxrelMeta', 'adminInit'));
add_action('admin_enqueue_scripts',     array('ImmediateTaxrelMeta', 'adminInit'));

add_action('init',                      array('ImmediateTaxrelMeta', 'immSetup'));

add_action('save_post',                 array('ImmediateTaxrelMeta', 'tryApplyFlags'));

?>
