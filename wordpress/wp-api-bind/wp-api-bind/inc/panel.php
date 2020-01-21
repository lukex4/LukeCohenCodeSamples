
<div class="wrap">

  <?php screen_icon(); ?>

  <h1>API Bindings Administration</h1>

  <p>Add new API bindings, and manage your existing bindings</p>

  <hr />



  <div class="collapsibleInfo" style="height:auto;">

    <h1><span class="dashicons dashicons-plus-alt" style="font-size:30px;margin-right:12px;"></span> New binding</h1>

    <form class="pure-form pure-form-stacked" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">

      <legend>API Details</legend>
      <fieldset class="pure-group">

          <label for="apiname">API name</label>
          <input id="apiname" name="apiname" type="text" placeholder="Enter a name" class="pure-input-1-2">

          <label for="baseuri">Base URI</label>
          <input id="baseuri" name="baseuri" type="text" placeholder="Enter URI here" class="pure-input-1-2">

          <label for="reqtype">Request Type</label>
          <select id="reqtype" name="reqtype" class="pure-input-1-2">
              <option value="">Select...</option>
              <option value="POST">HTTP POST</option>
              <option value="GET">HTTP GET</option>
          </select>

      </fieldset>

      <legend>API Call Fields</legend>
      <fieldset class="pure-group">

        <fieldset class="pure-group" id="newFieldsGroup"></fieldset>

        <button type="button" id="newFieldButton" class="pure-button pure-button-primary">Add a field +</button>

      </fieldset>

      <legend>Save</legend>
      <fieldset class="pure-group">

        <input type="hidden" id="addedFields" name="addedFields" value="" />

        <input type="hidden" name="action" value="saveBinding" />
        <input type="hidden" name="bindingGUID" id="bindingGUID" value="<?php echo uniqid(); ?>" />
        <button type="submit" class="pure-button pure-button-primary">Save and create binding &raquo;</button>

      </fieldset>

      <?php wp_nonce_field('saveBinding', 'saveBinding_nonce'); ?>

    </form>

  </div>


  <hr />


  <div class="collapsibleInfo" style="height:auto;">

    <h1><span class="dashicons dashicons-networking" style="font-size:30px;margin-right:12px;"></span> Existing bindings</h1>

    <form class="pure-form pure-form-stacked" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">

      <?php

      $bindings = self::fetchExistingBindings();

      if (count($bindings)===0) {

        echo '<h2>No existing bindings</h2>';

      } else {

        $y = 0;

        foreach($bindings as $key => $bind) {

          if (!$bind['deleted']) {

          ?>

          <div class="pure-u-1-3">

          <fieldset class="pure-group">

              <label for="apiname">API name</label>
              <input id="apiname" name="apiname" type="text" placeholder="Enter a name" value="<?php echo sanitize_text_field($bind['name']); ?>" class="pure-input-1-2" readonly />

              <label for="baseuri">Base URI</label>
              <input id="baseuri" name="baseuri" type="text" placeholder="Enter URI here" value="<?php echo sanitize_text_field($bind['baseuri']); ?>" class="pure-input-1-2" readonly />

              <label for="reqtype">Request Type</label>
              <select id="reqtype" name="reqtype" class="pure-input-1-2" readonly disabled>
                  <option value="">Select...</option>
                  <option value="POST"<?php if ($bind['reqtype']=='POST') { echo ' selected'; } ?>>HTTP POST</option>
                  <option value="GET"<?php if ($bind['reqtype']=='GET') { echo ' selected'; } ?>>HTTP GET</option>
              </select>

          </fieldset>

          <h2>Fields</h2>
          <fieldset class="pure-group">

            <?php

            if ($bind['reqfields']) {

              $x = 0;
              $fieldCount = count($bind['reqfields']);

              foreach($bind['reqfields'] as $field) {

                ?>

                <label for="apiname">Field name</label>
                <input id="apiname" name="apiname" type="text" value="<?php echo sanitize_text_field($field->fieldName); ?>" class="pure-input-1-2" readonly />

                <label for="apiname">Default value</label>
                <input id="apiname" name="apiname" type="text" value="<?php echo sanitize_text_field($field->defaultValue); ?>" class="pure-input-1-2" readonly />

                <?php

                $x++;

                if ($x<$fieldCount) {
                  echo '<hr />';
                }

              }

            }

            ?>

          </fieldset>

          <h2>Delete?</h2>
          <fieldset>

            <?php wp_nonce_field('removeBinding', 'removeBinding_nonce'); ?>

            <input type="hidden" name="action" value="removeBinding" />
            <input type="hidden" name="removeID" value="<?php echo $key; ?>" />
            <button type="submit" class="pure-button pure-button-primary">Delete &raquo;</button>

          </fieldset>

          <hr />

        </div>

          <?php

          $y++;

          }

        }


      }

      ?>

    </form>

  </div>

</div>