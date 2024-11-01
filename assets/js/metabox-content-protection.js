jQuery(function($) {

  var metaboxContainer = $('#ptc-trident_content-protection div.inside');

  // variables that must be set again after reloading elements
  var productsFieldset = metaboxContainer.find('fieldset.ptc-trident-conditions-products');
  var userStatesFieldset = metaboxContainer.find('fieldset.ptc-trident-conditions-user-states');

  /* INHERITANCE OVERRIDING */
  // NOTE: The inheritance options header is conditional and may not exist
  metaboxContainer.on('click', 'header.ptc-trident-protection-inheritance button.ptc-trident-inheritance-toggle', function() {

    let inheritanceButton = $(this);
    let inheritanceInput = inheritanceButton.siblings('input[name=ptc_trident_conditions_inheritance]');

    if ( inheritanceInput.length !== 1 ) {
      console.error('[PTC Trident] Inheritance input was not found! Cannot set inheritance mode.');
      return;
    }

    let inheritanceOnVal = 'inherit';
    let inheritanceOffVal = 'override';

    let descendOptionsRow = metaboxContainer.find('fieldset.ptc-trident-conditions-options div.ptc-trident-conditions-options-row-descend');

    if ( inheritanceInput.val() === inheritanceOnVal ) {
      // is inheriting, so enable all inputs and set to off
      disable_element(productsFieldset, false);
      disable_element(userStatesFieldset, false);
      descendOptionsRow.show();
      inheritanceInput.val(inheritanceOffVal);
      inheritanceButton.html('Clear Overrides');
    } else if ( inheritanceInput.val() === inheritanceOffVal ) {
      // is overriding, so clear all inputs, disable, and set to on
      disable_element(productsFieldset, true);
      disable_element(userStatesFieldset, true);
      restore_ancestor_settings();
      descendOptionsRow.hide();
      descendOptionsRow.find('input').prop('checked', false);
      inheritanceInput.val(inheritanceOnVal);
      inheritanceButton.html('Apply Overrides');
    }

  });
  /* END INHERITANCE OVERRIDING */

  /* GUTENBERG CODE */
  try {
    if (
      typeof wp.data !== "undefined"
      && wp.data.select('core/editor') !== null
    ) {

      let afterPostSaved = false;
      let hasChangedOptions = false;

      let wasSavingPost = wp.data.select( 'core/editor' ).isSavingPost();
      let wasAutosavingPost = wp.data.select( 'core/editor' ).isAutosavingPost();
      let wasSavingMetaBoxes = wp.data.select( 'core/edit-post' ).isSavingMetaBoxes();

      wp.data.subscribe(function() {

        if ( ! hasChangedOptions ) {
          return;
        }

        const isSavingPost = wp.data.select( 'core/editor' ).isSavingPost();
        const isAutosavingPost = wp.data.select( 'core/editor' ).isAutosavingPost();
        const isSavingMetaBoxes = wp.data.select( 'core/edit-post' ).isSavingMetaBoxes();

        // Save metaboxes on save completion, except for autosaves that are not a post preview.
        const shouldTrigger = ( wasSavingPost && ! isSavingPost && ! wasAutosavingPost && wasSavingMetaBoxes && ! isSavingMetaBoxes );

        // Save current state for next inspection.
        wasSavingPost = ( isSavingPost || wasSavingPost );
        wasAutosavingPost = ( isAutosavingPost || wasAutosavingPost );
        wasSavingMetaBoxes = ( isSavingMetaBoxes || wasSavingMetaBoxes );

        if ( shouldTrigger ) {

            wasSavingPost = false;
            wasAutosavingPost = false;
            wasSavingMetaBoxes = false;

            let currentHTML = metaboxContainer.html();
            metaboxContainer.html('<p class="ptc-notice-refreshing-metabox"><i class="fa fa-refresh fa-spin"></i>Refreshing settings...</p>');

            let data = {
              'action': 'ptc_trident_refresh_content_protection',
              'ptc_trident_content_protection_nonce': ptc_trident_content_protection.nonce,
              'post_id': wp.data.select('core/editor').getCurrentPostId(),
            };

            $.post(ajaxurl, data, function(res) {

              if(res.status == 'success') {
                hasChangedOptions = false;
                metaboxContainer.html(res.data);
                // set variables after reloading
                productsFieldset = metaboxContainer.find('fieldset.ptc-trident-conditions-products');
                userStatesFieldset = metaboxContainer.find('fieldset.ptc-trident-conditions-user-states');
              } else {
                console.error(res.data);
                metaboxContainer.html(currentHTML);
              }

            }, 'json')
              .fail(function() {
                console.error('Failed to refresh PTC Trident Content Protection metabox content.');
                metaboxContainer.html(currentHTML);
              });

        }//end if should trigger

      });//end wp.data.subscribe listener

      /* NOTE INPUT CHANGES */
      metaboxContainer.on('change', ':input', function() {
        hasChangedOptions = true;
      });

    }//end if wp.data Gutenberg

    /*--- HELPERS ---*/

    function disable_element(jquery_obj, if_disable = true) {
      if(if_disable) {
        jquery_obj.css('pointer-events', 'none');
        jquery_obj.prop('disabled', true);
      } else {
        jquery_obj.css('pointer-events', 'auto');
        jquery_obj.prop('disabled', false);
      }
    }//end disable_element()

    function restore_ancestor_settings() {
      // products condition
      metaboxContainer
        .find('fieldset.ptc-trident-conditions-products select#ptc-trident-conditions-method')
        .val(function(index, currentValue) {
        return ( $(this).data('ancestor-value') === 'all' ) ? 'all' : 'any';
      });
      // products checked or not
      productsFieldset.find('input[type=checkbox]').val( productsFieldset.data('ancestor-value') );
      // user state checked or not
      userStatesFieldset.find('input[type=radio][value='+userStatesFieldset.data('ancestor-value')+']').prop('checked', true);
    }//end restore_ancestor_settings()

  } catch(error) {
    console.error('[PTC Trident] ' + error.message);
  }/* END GUTENBERG CODE */

});//end document ready