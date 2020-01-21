
jQuery(document).ready(function () {


  /* Create the hidden form field that will hold our tag flags */
  var hiddenField = jQuery('<input/>', {type:'hidden', id:'immFlagTags', name:'immFlagTags', value:''});
  hiddenField.appendTo('#poststuff');


  /* Array that holds all the taxonomies for the current post, and their flag status */
  var allTaxonomies = [];


  /* Function returns a taxonomy type, and its name, when passed a span from inside the 'tagchecklist' div in the post editor */
  var taxAttributes = function(spanObject) {

    var tagType, tagText;

    tagType = jQuery(spanObject).find('a').attr('id').split('-')[0];
    tagText = jQuery(spanObject).text();

    /* Remove the 'non-breaking' space and the 'X' from the delete button ('X ') to get accurate tag text */
    tagText = tagText.replace(/\u00A0/g, '').trim().substr(1);

    var attributes = {};
    attributes.tagType = tagType;
    attributes.tagText = tagText;

    return attributes;

  };


  /* Updates the immFlagTags hidden field, so we can pass the flagged tags back to our PHP */
  var updateHiddenField = function() {

    var allTaxonomiesStr = '{';
    var taxesCount = Object.keys(allTaxonomies).length;
    var x = 0;

    for (var key in allTaxonomies) {

      allTaxonomiesStr = allTaxonomiesStr + '"' + key + '":' + '"' + allTaxonomies[key] + '"';
      x++;

      if (x < taxesCount) {
        allTaxonomiesStr = allTaxonomiesStr + ', ';
      }

    }

    allTaxonomiesStr = allTaxonomiesStr + '}';

    jQuery('#immFlagTags').val(allTaxonomiesStr);

  };


  /* This function helps to maintain state; every time a new tag is added in the editor, Wordpress re-renders the tag list, so this must be re-run to ensure flagged tags remain red/not during the editing session */
  var taxonomiesDiscovery = function() {

    /* Loop through each set of taxonomies (if no custom taxonomies are in place, this will only happen once, for WP's own tags) */
    jQuery('.tagchecklist').each(function(i, o) {

      /* Loop through each span inside a .tagchecklist div */
      jQuery(o).find('span').each(function(j, p) {

        if (jQuery(p).has('.ntdelbutton')) {

          /* This is a relevant tag-containing span, so extract the tag */
          var attribs, tagType, tagText, arrayRef;

          attribs = taxAttributes(p);
          tagType = attribs.tagType;
          tagText = attribs.tagText;

          arrayRef = tagType + '__' + tagText;

          if (allTaxonomies[arrayRef] === undefined) {

            /* If this tag isn't already in our 'allTaxonomies' array, add it */
            allTaxonomies[arrayRef] = false;

          } else {

            /* Or, we already have the tag in our array, let's check the priority status and highlight the tag if it has priority */
            if (allTaxonomies[arrayRef] === true) {
              jQuery(p).addClass('priority-tag');
            }

          }

        }

      });

    });

    /* TODO: Run a check for deleted tags that exist in allTaxonomies, and remove them */



  };


  /* This is the closest we can get to ascertaining when a tag has been added or deleted in edit mode */
  jQuery('.tagchecklist').bind("DOMSubtreeModified", function() {
    taxonomiesDiscovery();
  });


  /* This is the closest we can get to ascertaining when a tag has been clicked whilst in edit mode */
  jQuery('.tagchecklist').click(function() {

    if (event.target.nodeName === 'SPAN') {

      var theTarget = jQuery(event.target);

      if (theTarget.has('.ntdelbutton')) {

        var attribs, tagType, tagText, arrayRef, arrayCur;

        attribs = taxAttributes(theTarget);
        tagType = attribs.tagType;
        tagText = attribs.tagText;

        arrayRef = tagType + '__' + tagText;
        arrayCur = allTaxonomies[arrayRef];

        theTarget.toggleClass('priority-tag');

        if (arrayCur === true) {
          allTaxonomies[arrayRef] = false;
        } else {
          allTaxonomies[arrayRef] = true;
        }

      }

    }

    updateHiddenField();

  });


  /* Run this at start, if this isn't a new post */
  if (actionFlags && actionFlags.action && actionFlags.action == 'edit') {

    /* Loop through any existing priority flags on this post and pass to our allTaxonomies[] array for local processing */
    var curFlags = actionFlags.flags;

    for (var key in curFlags) {

      var flagSlug = curFlags[key].slug;
      allTaxonomies[flagSlug] = true;

    }

    taxonomiesDiscovery();

  }


});
