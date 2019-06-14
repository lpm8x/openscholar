/**
 *  Javascript for the Control Panel menu.
 *
 *  Core's tabledrag is not enough for our use case as we are working with multiple groups(Menus) at once.
 *  And want to be able drag between those.
 *
 *   Does two things:
 *   1. Changes the menu select when the user drags a row
 *   2. Removes the 'hidden' class when the user selects a new menu from the select.
 */
(function ($, Drupal) {

    let drag;

    function changeSelect() {
        let $this = $(this.oldRowElement),
            $prev = $this.prevAll('.section-heading').sort(function (a, b) {
                let ad = Math.abs($this.index() - $(a).index()),
                    bd = Math.abs($this.index() - $(b).index());
                return (ad - bd);
            }).first(),
            val = $prev.find('.menu-name').val(),
            select = $this.find('.menu-name');

        if (typeof this.rowObject.children !== 'undefined') {
            $.each(this.rowObject.children, function () {
                $(this).find('.menu-name').val(val);
            });
        }

        select.val(val);
        emptySections();
    }

    function changeRegion() {
        // remove the hidden class
        let self = this;
        $('input').filter(function (i) {
            return (this.value && this.value === self.value);
        }).parents('tr').removeClass('hidden');

        // move the field to the new region
        let $row = $(self).parents('tr'),
            row = $row.get(0),
            $dest = $('tr.section-heading').filter(function() {
                return ($('.menu-name', this).val() === self.value);
            });
        $dest.after($row);

        // deal with tabledrag

        emptySections();
    }

    /**
     * Deal with the empty section message.
     * If there no links in the section, switch to the region-empty class
     * Otherwise, switch to region-populated
     */
    function emptySections() {
        let $table = $(drag.table),
            // get all the section messages
            $sections = $('.section-message');

        // loop through each select
        // find it's section header, then look down for the section message
        $('select.menu-name', $table).each(function () {
            let $header = $(this).parents('tr').prevAll('.section-heading').first(),
                $message = $header.nextAll('.section-message').first();

            // remove this message from the list we made earlier
            $sections = $sections.not($message);
            $message.removeClass('section-empty').addClass('section-populated');
        });

        // at this point, the $sections should only contain
        // section messages in sections that are empty
        $sections.removeClass('section-populated').addClass('section-empty');
    }

    Drupal.behaviors.cpMenuForm = {
        attach: function (ctx) {
            // remove the 'hidden' class when a menu is changed
            $('select.menu-name', ctx).change(changeRegion);

            drag = Drupal.tableDrag['cp-build-menu-table'];
            drag.onDrop = changeSelect;
        }
    };

    Drupal.behaviors.cpMenuFormValidation = {
        attach: function(ctx) {
            $('#cp-menu-build').submit( function(event) {
                let show = false;
                $('.draggable').each(function() {
                    if ($(this).find('.indentation').length >= 4) {
                        event.preventDefault();
                        show = true;
                    }
                });
                if (show) {
                    $('#cp-menu-build').prepend('<div class="messages error">' + Drupal.t('Our themes do not support more than four menu levels') + '</div>');
                }
            });
        }
    };

})(jQuery, Drupal);