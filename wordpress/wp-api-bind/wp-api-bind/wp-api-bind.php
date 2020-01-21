<?php

/**
 * @package Wordpress API Bind
 */

/*

Plugin Name: Wordpress API Bind
Plugin URI: https://...
Description: General purpose plugin which binds the results of API calls to various posts and pages, exposing the API dataset returns directly to WordPress views. Only works with APIs that return valid JSON.
Version: 1.0
Author: Luke Cohen
Author URI: https://github.com/lukex4
License: GPLv2 Copyright (c) 2016 Luke Cohen
Text Domain: wp-api-bind
Domain Path: /language

*/

include_once 'helper.php';

class WPAPIBind {

  public $debug = 0;
  public $installed = false;


  /* Constructor */
  public function __construct() {

    if (is_admin()) {

      /* Start */
      add_action('admin_init', array($this, 'adminInit'));
      add_action('admin_menu', array($this, 'adminMenu'));
      add_action('admin_footer', array($this, 'enqueueAssets'));

      /* Functional callbacks */
      add_action('admin_post_saveBinding', array($this, 'doSaveBinding'));
      add_action('admin_post_removeBinding', array($this, 'doRemoveBinding'));

      /* Interface-relevant */
      add_action('add_meta_boxes', array($this, 'apibind_add_meta_box'));
      add_action('save_post', array($this, 'apibind_post_save'), 10, 2);

      /* At the end */
      add_action('shutdown', array($this, 'onShutdown'));

    }

  }


  public function adminInit() {

    /* Add our bindings option */
    add_option('im-api-bind-bindings', '');

  }


  /* Create admin site menu link */
  public function adminMenu() {

    add_management_page('API Bindings', 'API Bindings', 'manage_options', 'imapibind-admin-options',
		array($this, 'createAdminPanel'));

  }


  /* Create our main admin page */
  public function createAdminPanel() {

    /* Pull in the panel include */
    include('inc/panel.php');

  }


  /* Enqueue our CSS and Javascript */
  public function enqueueAssets() {

    /* Pure CSS */
    wp_enqueue_style('pure_css', plugin_dir_url( __FILE__ ) . 'pure-min.css', array(), time());

    /* Our custom styles and Javascript for the admin panel */
    wp_enqueue_style('apibind_css', plugin_dir_url( __FILE__ ) . 'wp-api-bind.css', array(), time());
    wp_register_script('apibind_js', plugin_dir_url( __FILE__ ) . 'wp-api-bind.js', array('jquery'), rand(1,1000), true);

    wp_enqueue_script('apibind_js');

  }


  /* Deletes an existing API binding */
  public function doRemoveBinding() {

    if (!empty($_POST) && check_admin_referer('removeBinding', 'removeBinding_nonce')) {

      $idToRemove = $_POST['removeID'];

      if ($idToRemove) {
        $bindings = get_option('im-api-bind-bindings');
        $bindings = unserialize($bindings);

        if (!is_array($bindings)) {
          /* There are no existing bindings, so nothing to remove */
          return;
        } else {

          if ($bindings[$idToRemove]) {
            $bindings[$idToRemove]['deleted'] = true;
          }

          $bindings = serialize($bindings);
          update_option('im-api-bind-bindings', $bindings);

          /* Redirect out */
          wp_redirect(admin_url('tools.php?page=imapibind-admin-options'));

        }
      }

    }

  }


  /* Saves a new binding */
  public function doSaveBinding() {

    if (!empty($_POST) && check_admin_referer('saveBinding', 'saveBinding_nonce')) {

      $formFail = false;

      $guid = $_POST['bindingGUID'];

      $apiname = $_POST['apiname'];
      $baseuri = $_POST['baseuri'];
      $reqtype = $_POST['reqtype'];

      $fields = $_POST['addedFields'];

      $apiname = escapeString($apiname);

      if (filter_var($baseuri, FILTER_VALIDATE_URL)===false) {
        $formFail = true;
      }

      if ($reqtype !== 'GET' && $reqtype !== 'POST') {
        $reqtype = 'GET';
      }


      /* Loop through fields for this binding */
      $fields = stripslashes($fields);
      $fields = json_decode($fields);


      /* Prepare new binding object to add to our option */
      $newBinding = Array(
        'name'        => $apiname,
        'baseuri'     => $baseuri,
        'reqtype'     => $reqtype,
        'reqfields'   => $fields,
        'respfields'  => Array()
      );


      /* Save new binding to our option */
      $bindings = get_option('im-api-bind-bindings');
      $bindings = unserialize($bindings);

      if (!is_array($bindings)) {

        /* This is the first binding so we start with an empty array */
        $bindings = Array();

      }

      $bindings[$guid] = $newBinding;

      $bindings = serialize($bindings);
      update_option('im-api-bind-bindings', $bindings);


      /* Redirect out */
      wp_redirect(admin_url('tools.php?page=imapibind-admin-options'));


    }

  }


  /* Fetches existing bindings as an array */
  public function fetchExistingBindings() {

    $bindings = get_option('im-api-bind-bindings');
    $bindings = unserialize($bindings);

    if (!is_array($bindings)) {
      return Array();
    } else {
      return $bindings;
    }

  }


  /* Checks if a post/page binding is valid */
  public function checkValidBinding($id) {

    if (!$id) {
      return false;
    }

    $bindings = self::fetchExistingBindings();

    if (count($bindings)>0) {

      $b = $bindings[$id];

      if (!$b) {
        return false;
      } else {

        $burl = $b['baseuri'];
        $breqtype = $b['reqtype'];

        if (filter_var($burl, FILTER_VALIDATE_URL) === false) {
          return false;
        }

        if ($breqtype !== 'GET' && $breqtype !== 'POST') {
          return false;
        }

        return true;

      }

    } else {
      return false;
    }

  }


  /* Returns a set of binding params */
  public function getBindingParams($id) {

    if (!$id) {
      return false;
    }

    $bindings = self::fetchExistingBindings();

    if (count($bindings)===0) {
      return false;
    } else {

      $b = $bindings[$id];
      return $b;

    }

  }


  /* Make the API call */
  public function retrieveAPIResponseObject($post_id, $post_meta, $debug) {

    /*

    - check if there is an API binding for this view
    - if there is, load the binding info
    - if appropriate (i.e no cache/cache expired), prepare the API call
    - make the API call
    - save the API response in a custom field
    - return the API response object

    */

    $apiDebug = false;

    if ($debug && $debug===true) {
      $apiDebug = true;
    }

    if ($apiDebug) {
      print_r($meta);
    }

    $hasApiBinding = false;

    $bind_id = 0;
    $bind_field_mappings = Array();
    $bind_cache_minutes = 0;
    $bind_last_call = 0;
    $bind_last_result = '';

    if ($post_meta['bind_status'][0] && $post_meta['bind_status'][0] == 'enabled') {

      /* There's an existing binding so let's load that info */
      $hasApiBinding = true;

      $bind_id = $post_meta['bind_id'][0];
      $bind_field_mappings = unserialize($post_meta['bind_field_mappings'][0]);
      $bind_cache_minutes = $post_meta['bind_cache_minutes'][0];
      $bind_last_call = $post_meta['bind_last_call'][0];
      $bind_last_result = $post_meta['bind_last_result'][0];

      if (!$bind_id) {
        $bind_id = 0;
        $hasApiBinding = false;
      }

      if (!is_numeric($bind_cache_minutes)) {
        $bind_cache_minutes = 0;
      }

      if (!is_numeric($bind_last_call)) {
        $bind_last_call = 0;
      }

    } else {

      /* No API bindings for this page/post, return */
      return false;

    }


    /* There's a binding, so continue */
    if ($hasApiBinding === true && self::checkValidBinding($bind_id) === true) {

      if ($apiDebug) {
        echo "\r\nhas valid binding";
      }


      $b = unserialize(get_option('im-api-bind-bindings'));
      $b = $b[$bind_id];


      /* Has this binding been deleted? If so we can't process the request, and we remove binding info from the post/page */
      if ($b['deleted'] && $b['deleted']===true) {

        update_post_meta($post_id, 'bind_status', '');
        update_post_meta($post_id, 'bind_id', '');
        update_post_meta($post_id, 'bind_field_mappings', Array());
        update_post_meta($post_id, 'bind_cache_minutes', 0);
        update_post_meta($post_id, 'bind_last_call', 0);
        update_post_meta($post_id, 'bind_last_result', '');

        return false;

      }


      $liveRequest = false;
      $apiResponseObject = Array();

      $reqURI = $b['baseuri'];


      /*

      if the API binding has fields, we may have to cache more than just a generic request-response

      i.e if there's a dynamic field, we would have to cache the results of the same query X times, where X is the maximum possible number of values the dynamic field could equal

      if there are no fields on the binding, it's a straight forward cache

      */
      if (count($b['reqfields'])===0) {

        /* simple cache strategy */

        if ($apiDebug) {
          echo "\r\nno fields, simple cache strategy (URI) only";
        }

        /* where no caching is set for this call */
        if ($bind_cache_minutes == 0) {

          if ($apiDebug) {
            echo "\r\ncache_minutes=0, liveRequest true";
          }

          $liveRequest = true;

        }

        /* where caching is set but it hasn't been cached yet */
        if ($liveRequest === true && $bind_last_call == 0) {

          if ($apiDebug) {
            echo "\r\ncache set but no cache last call, liveRequest true";
          }

          $liveRequest = true;

        }

        /* where caching has been set, the first call has been made, now we check to see if X minutes have passed since the last call */
        if ($liveRequest===false) {

          if ($apiDebug) {
            echo "\r\ncache enabled, first call has been made, checking time of last cache";
          }

          $diff = (time()-$bind_last_call)/60;

          if ($apiDebug) {
            echo "\r\nlast cache diff: " . $diff;
          }

          if ($diff > $bind_cache_minutes) {

            if ($apiDebug) {
              echo "\r\nhas cache but it's old, liveRequest true";
            }

            $liveRequest = true;

          }

        }

        /* if liveRequest is still false, load the cached response here */
        if ($liveRequest===false) {

          if ($apiDebug) {
            echo "\r\nall other conditions passed, now we load cached version (if it exists)";
          }

          $uriHash = hash('md5', $reqURI);

          if (get_option('cache_' . $uriHash)) {

            if ($apiDebug) {
              echo "\r\ngot option cache_" . $uriHash;
            }

            if (get_option('cachetime_' . $uriHash)) {

              if ($apiDebug) {
                echo "\r\ngot option cachetime_" . $uriHash;
              }

              $cachedTime = get_option('cachetime_' . $uriHash);
              $diff = (time()-$cachedTime)/60;

              if ($apiDebug) {
                echo "\r\ndiff between now and time of cache: " . $diff;
              }

              if ($diff > $bind_cache_minutes) {

                if ($apiDebug) {
                  echo "\r\ncache has expired, liveRequest true";
                }

                $liveRequest = true;

              } else {
                $apiResponseObject = json_decode(get_option('cache_' . $uriHash));
              }

            }
          } else {

            if ($apiDebug) {
              echo "\r\nfor some reason, no cache exists for this key as a site option, liveRequest true";
            }

            $liveRequest = true;

          }


        }

      } else {

        /* complex cache strategy */

        if ($apiDebug) {
          echo "\r\nhas fields, complex cache strategy";
        }


        /*

        Sets up the request query vars (i.e GET or POST fields):

        */

        /* where the API call is via HTTP POST, we don't cache the request */
        if ($b['reqtype']=='POST') {

          if ($apiDebug) {
            echo "\r\nthis is a POST request, liveRequest true";
          }

          $liveRequest = true;

          $postVars = Array();

          foreach($bind_field_mappings as $map) {

            $reqKey = $b['reqfields'][$x]->fieldName;
            $reqVal = '';

            switch($map['bind_to']) {

              case "":
              break;

              case "Get":
                $reqVal = $_GET[$map['bind_to_opt']];
              break;

              case "Default":
                $reqVal = $b['reqfields'][$x]->defaultValue;
              break;

              case "Explicit":
                $reqVal = $map['bind_to_opt'];
              break;

            }

            $postVar = Array();
            $postVar[$reqKey] = $reqVal;

            array_push($postVars, $postVar);

          }


        }

        /* where the API call is via HTTP GET */
        if ($b['reqtype']=='GET') {

          if ($apiDebug) {
            echo "\r\nthis is a GET request";
          }

          $x = 0;
          $reqURI .= "?";

          foreach($bind_field_mappings as $map) {

            $reqKey = $b['reqfields'][$x]->fieldName;
            $reqVal = "";

            switch($map['bind_to']) {

              case "":
              break;

              case "Get":
                $reqVal = $_GET[$map['bind_to_opt']];
              break;

              case "Default":
                $reqVal = $b['reqfields'][$x]->defaultValue;
              break;

              case "Explicit":
                $reqVal = $map['bind_to_opt'];
              break;

            }

            $reqURI .= $reqKey . "=" . $reqVal;
            $x++;

            if (count($bind_field_mappings) > $x) {
              $reqURI .= "&";
            }

          }


          if ($apiDebug) {
            echo "\r\nGET request req uri: " . $reqURI;
          }

          /* Create an MD5 hash of this unique URI, as our cache key */
          $uriHash = hash('md5', $reqURI);

          if ($apiDebug) {
            echo "\r\nGET request uri hash: " . $uriHash;
          }

          /* Check to see if there is a cached version of this URI available */
          if (get_option('cache_' . $uriHash)) {

            if ($apiDebug) {
              echo "\r\nhas cache_ of GET request - cache_" . $uriHash;
            }

            if (get_option('cachetime_' . $uriHash)) {

              if ($apiDebug) {
                echo "\r\nhas cachetime_ of GET request: " . get_option('cachetime_' . $uriHash);
              }

              $cachedTime = get_option('cachetime_' . $uriHash);
              $diff = (time()-$cachedTime)/60;


              if ($apiDebug) {
                echo "\r\ndiff of time of cached GET request and now: " . $diff;
              }

              if ($diff > $bind_cache_minutes) {

                if ($apiDebug) {
                  echo "\r\ncache has expired, liveRequest true";
                }

                $liveRequest = true;

              } else {

                /* We have a valid, non-expired cached version of the API request, return it as the responseObject */
                $apiResponseObject = json_decode(get_option('cache_' . $uriHash));

              }

            } else {

              if ($apiDebug) {
                echo "\r\nno cachetime_ available, liveRequest true";
              }

              $liveRequest = true;
            }


          } else {

            if ($apiDebug) {
              echo "\r\nno cache_ available, liveRequest true";
            }

            $liveRequest = true;

          }

        }


      }


      /* Make the request, if applicable */
      if ($liveRequest===true) {

        if ($apiDebug) {
          echo "\r\n\r\nMaking a new request";
        }

        switch($b['reqtype']) {

          case "GET":

            if ($apiDebug) {
              echo "\r\nmaking GET API call to: " . $reqURI . " hash of URI: " . $uriHash;
            }

            $apiResponse = wp_remote_get($reqURI);

            if (is_wp_error($apiResponse)) {
              $error_message = $apiResponse->get_error_message();
              echo "API call error: $error_message";
            }

            if (is_array($apiResponse)) {

              $respHeaders = $apiResponse['headers'];
              $respBody = $apiResponse['body'];

              /* if caching is enabled, save the response to a site option with the hash of the URI as the key, and save another site option cachetime_KEY with the current UNIX time */
              if ($bind_cache_minutes > 0) {

                if ($apiDebug) {
                  echo "\r\n\r\nsaving response to cache";
                }

                update_option('cache_' . $uriHash, $respBody);
                update_option('cachetime_' . $uriHash, time());

              }

              $apiResponseObject = json_decode($respBody);

            }


          break;

          case "POST":

            if ($apiDebug) {
              echo "\r\nmaking POST API call to: " . $reqURI;
            }

            $apiResponse = wp_remote_post($reqURI, array(
              'body'    => $postVars
            ));

            if (is_wp_error($apiResponse)) {
              $error_message = $apiResponse->get_error_message();
              echo "API call error: $error_message";
            }

            if (is_array($apiResponse)) {

              $respHeaders = $apiResponse['headers'];
              $respBody = $apiResponse['body'];

              $apiResponseObject = json_decode($respBody);

            }

          break;

        }

      }


      if ($apiDebug) {
        echo "\r\n\r\nCOMPLETE\r\n";
        print_r($apiResponseObject);
      }


    }


    return $apiResponseObject;


  }


  /* Custom meta box for page interface */
  public function apibind_add_meta_box() {

    add_meta_box(
      'apibind-meta-box',
      'API Data Sources',
      array($this, 'create_apibind_metabox'),
      array('page', 'post'),
      'side',
      'high'
    );

  }


  /* Save binding info on a post */
  public function apibind_post_save($post_id, $post) {

    /* Verify the source of this request */
    if (!isset( $_POST['apibind_box_nonce'] ) || !wp_verify_nonce($_POST['apibind_box_nonce'], basename( __FILE__ )))
    return $post_id;


    /* Go 'head and save the binding info */
    $binding_id = $_POST['binding_id'];

    if (!$binding_id || $binding_id == "") {

      update_post_meta($post_id, 'bind_status', '');
      update_post_meta($post_id, 'bind_id', '');
      update_post_meta($post_id, 'bind_field_mappings', Array());
      update_post_meta($post_id, 'bind_cache_minutes', 0);
      update_post_meta($post_id, 'bind_last_call', 0);
      update_post_meta($post_id, 'bind_last_result', '');

      return $post_id;

    }

    $bindings = self::fetchExistingBindings();
    $binding_field_options = Array();

    $binding = $bindings[$binding_id];
    $fields = $binding['reqfields'];

    $x = 0;

    foreach($fields as $field) {

      $binding_field_options[$x]['bind_to'] = $_POST[$binding_id . '-binding-opt-' . $x];
      $binding_field_options[$x]['bind_to_opt'] = $_POST[$binding_id . '-binding-opt-val-' . $x];

      $x++;

    }


    /* get and set the cache time for this API call */
    $cacheMinutes = $_POST['binding-' . $binding_id . '-cache'];

    if (!is_numeric($cacheMinutes)) {
      $cacheMinutes = 0;
    }


    /*  custom fields used to manage API binding to a post/page */
    update_post_meta($post_id, 'bind_status', 'enabled');
    update_post_meta($post_id, 'bind_id', $binding_id);
    update_post_meta($post_id, 'bind_field_mappings', $binding_field_options);
    update_post_meta($post_id, 'bind_cache_minutes', $cacheMinutes);
    update_post_meta($post_id, 'bind_last_call', 0);
    update_post_meta($post_id, 'bind_last_result', '');


    /* Redirect out */
    wp_redirect(admin_url('post.php?post=' . $post_id . '&action=edit'));


  }


  /* Outputs custom meta box for binding API to a page/post */
  public function create_apibind_metabox($post) {

    /* Load any pre-saved API binding info */
    if ($post) {

      $post_id = $post->ID;
      $meta = get_post_meta($post_id);

      $hasExistingBinding = false;

      $bind_id = 0;
      $bind_field_mappings = Array();
      $bind_cache_minutes = 0;
      $bind_last_call = 0;
      $bind_last_result = '';

      if ($meta['bind_status'] && $meta['bind_status'][0] == 'enabled') {

        /* There's an existing binding so let's load that info */
        $hasExistingBinding = true;

        $bind_id = $meta['bind_id'][0];
        $bind_field_mappings = unserialize($meta['bind_field_mappings'][0]);
        $bind_cache_minutes = $meta['bind_cache_minutes'][0];
        $bind_last_call = $meta['bind_last_call'][0];
        $bind_last_result = $meta['bind_last_result'][0];

      }

    }

    wp_nonce_field(basename( __FILE__ ), 'apibind_box_nonce');

    ?>

    <p><strong>Select an API Binding</strong></p>

    <label class="screen-reader-text" for="binding_id">API Binding</label>

    <?php

    $x = 0;
    $bindings = self::fetchExistingBindings();

    ?>

    <select name="binding_id" id="binding_id">
    	<option value="">(None)</option>

      <?php

      if (count($bindings)>0) {

        foreach($bindings as $key => $bind) {

          if (!$bind['deleted']) {

            ?>
            <option class="level-0" value="<?php echo $key; ?>"<?php if ($hasExistingBinding === true && $bind_id == $key) { echo ' selected'; } ?>><?php echo $bind['name']; ?></option>
            <?php

          }

          $x++;

        }

      }

      ?>

    </select>

    <?php

    $x = 0;

    foreach($bindings as $key => $bind) {

      if (!$bind['deleted']) {

      $fields = $bind['reqfields'];

      ?>
      <div id="binding-fields-<?php echo $key; ?>" class="binding-fields"<?php if ($hasExistingBinding === true && $bind_id == $key) { echo ' style="display:block!important;"'; } ?>>

      <hr />

      <p><strong>API Field Mapping</strong></p>

      <?php

      if (count($fields)===0) {
        ?>
        <p>This API binding has no field options</p>
        <?php
      } else {

        $y = 0;

        foreach($fields as $field) {

          $fieldOpt = $bind_field_mappings[$y];

          ?>

          <p><strong>&quot;<?php echo sanitize_text_field($field->fieldName); ?>&quot;</strong></p>

          <div>

            <div style="width:50%;float:left;">

              <select id="<?php echo $key; ?>-binding-opt-<?php echo $y; ?>" name="<?php echo $key; ?>-binding-opt-<?php echo $y; ?>" class="binding-opt-select">
                <option value=""<?php if ($fieldOpt && $fieldOpt['bind_to'] == '') { echo " selected"; } ?>>(Map to)</option>
                <option value="Default"<?php if ($fieldOpt && $fieldOpt['bind_to'] == 'Default') { echo " selected"; } ?>>Default value</option>
                <option value="Explicit"<?php if ($fieldOpt && $fieldOpt['bind_to'] == 'Explicit') { echo " selected"; } ?>>Explicit value</option>
                <option value="Get"<?php if ($fieldOpt && $fieldOpt['bind_to'] == 'Get') { echo " selected"; } ?>>HTTP GET var</option>
                <!--<option value="Post">HTTP POST var</option>-->
              </select>

              <?php

              $fieldVal = $field->defaultValue;

              if ($hasExistingBinding===true && $fieldOpt) {
                if ($fieldOpt['bind_to_opt']) {
                  $fieldVal = $fieldOpt['bind_to_opt'];
                }
              }

              ?>

              <input type="hidden" id="<?php echo $key; ?>-binding-opt-default-<?php echo $y; ?>" name="<?php echo $key; ?>-binding-opt-default-<?php echo $y; ?>" value="<?php echo sanitize_text_field($fieldVal); ?>" />

            </div>

            <div style="width:50%;float:left;">

              <input type="text" name="<?php echo $key; ?>-binding-opt-val-<?php echo $y; ?>" id="<?php echo $key; ?>-binding-opt-val-<?php echo $y; ?>" value="<?php echo sanitize_text_field($fieldVal); ?>" placeholder="" style="width:100%;max-width:100%;" />

            </div>

            <div style="clear:both;"></div>

          </div>

          <?php

          $y++;

        }

      }

      ?>

      <hr />

      <p><strong>Cache this API Call</strong></p>

      <div>

        <div style="width:55%;float:left;">
          Cache this request for
        </div>

        <div style="width:20%;float:left;">

          <?php

          $cacheVal = 0;

          if ($hasExistingBinding === true && is_numeric($bind_cache_minutes)) {
            $cacheVal = $bind_cache_minutes;
          }

          ?>

          <input type="number" name="binding-<?php echo $key; ?>-cache" id="binding-<?php echo $key; ?>-cache" value="<?php echo $cacheVal; ?>" style="width:100%;max-width:100%;" />
        </div>

        <div style="width:25%;float:left;text-align:right;">
          minutes
        </div>

        <div style="clear:both;"></div>

      </div>

      </div>
      <?php

      $x++;

      ?>

      <?php

    }

    }

    ?>

    <?php

  }


  /* Shutdown stuff */
  public function onShutdown() {

  }


}

/* Create the singleton */
new WPAPIBind();

?>
