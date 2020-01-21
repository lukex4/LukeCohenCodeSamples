


var newBindingFields = Array();


/* Save fields array to hidden form field */
var updateFieldsField = function() {

  var fieldOutput = JSON.stringify(newBindingFields);
  document.getElementById("addedFields").value = fieldOutput;

};


/* Remove new field */
var removeNewField = function(fieldID) {

  if (isNaN(fieldID)) {
    return false;
  }

  /* blank out the field name in our array but leave the array item there, so new fields retain auto increment integrity */
  if (newBindingFields[fieldID]) {
    newBindingFields[fieldID] = {};
  }

  /* remove the fieldset from the DOM */
  jQuery('#fields-' + fieldID).empty().remove();

  updateFieldsField();

};


jQuery(function() {

  console.log('im-api-bind.js ready');

  jQuery("#newFieldButton").click(function() {

    /* First check if there's a 'new field' form in the DOM that hasn't been filled */
    var hasEmptyField = false;

    if (document.getElementsByClassName('apinewfieldname').length) {

      jQuery('.apinewfieldname').each(function() {

        if (jQuery(this).val().length === 0) {
          alert('You have an empty new field entry already');
          hasEmptyField = true;
          return;
        }

      });

    }


    if (hasEmptyField === false) {

      /* Add a new field field */

      var x = newBindingFields.length;

      var newFieldMarkup = '<div id="fields-' + x + '"><label for="newfield-' + x + '">Field name</label><input id="newfield-' + x + '" type="text" placeholder="Enter field name" class="pure-input-1-3 apinewfieldname"><br />';

      newFieldMarkup += '<label for="newfield-default-' + x + '">Default value</label><input id="newfield-default-' + x + '" type="text" placeholder="Enter default value or leave empty" class="pure-input-1-3 apinewfielddefault"><br />';

      newFieldMarkup += '<span class="fieldDel"><a href="javascript:;" onClick="javascript:removeNewField(' + x + ');"><span class="dashicons dashicons-trash"></span>Delete field</a></span><br /></div>';

      jQuery('#newFieldsGroup').append(newFieldMarkup);

      newBindingFields[x] = {
        'fieldName'     : '',
        'defaultValue'  : ''
      };

      console.log('newBindingFields', newBindingFields);

      updateFieldsField();

    }


    /* save field name to array on keyup on the field */
    jQuery('.apinewfieldname').keyup(function() {

      var fieldID = jQuery(this).attr('id').replace('newfield-', '');

      var edited = {};
      edited.fieldName = jQuery(this).val();
      edited.defaultValue = newBindingFields[fieldID].defaultValue;

      newBindingFields[fieldID] = edited;

      console.log(newBindingFields);

      updateFieldsField();

    });


    /* save default value to array on keyup on the field */
    jQuery('.apinewfielddefault').keyup(function() {

      var fieldID = jQuery(this).attr('id').replace('newfield-default-', '');

      var edited = {};
      edited.fieldName = newBindingFields[fieldID].fieldName;
      edited.defaultValue = jQuery(this).val();

      newBindingFields[fieldID] = edited;

      console.log(newBindingFields);

      updateFieldsField();

    });


  });


  /* On page->binding dropdown change */
  var curBindID = '';

  jQuery('#binding_id').change(function() {

    var binding_id = jQuery('#binding_id').val();
    console.log('binding_id', binding_id);

    if (binding_id) {

      jQuery('#binding-fields-' + binding_id).show();
      curBindID = binding_id;

    }

    if (binding_id == '') {
      jQuery('.binding-fields').hide();
    }

  });


  /* On page->binding->field options dropdown change */
  jQuery('.binding-opt-select').change(function() {

    var fieldOption = jQuery(this).val();
    var fieldID = jQuery(this).attr('id').replace(curBindID + '-binding-opt-', '');

    console.log('fieldID', fieldID);

    switch(fieldOption) {

      case "":

        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr("placeholder", "");

      break;

      case "Default":

        var defaultVal = jQuery('#' + curBindID + '-binding-opt-default-' + fieldID).val();

        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr("placeholder", defaultVal);
        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr('disabled', 'disabled');

      break;

      case "Get":

        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr("placeholder", "Enter GET var name");
        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr('disabled', false);

      break;

      case "Post":

        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr("placeholder", "Enter POST var name");
        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr('disabled', false);

      break;

      case "Explicit":

        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr("placeholder", "Enter value");
        jQuery('#' + curBindID + '-binding-opt-val-' + fieldID).attr('disabled', false);

      break;

    }

  });


});
