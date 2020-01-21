<?php

/*

Uninstalls IM API Bind and cleans up a few things

*/

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

function uninstallAPIBind() {

  /* Delete all existing bindings */
  update_option('im-api-bind-bindings', '');

}

register_uninstall_hook( __FILE__, 'uninstallAPIBind');

?>