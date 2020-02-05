/**
 * @file
 * Parses contributor name.
 */

(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.parseAuthor = {
    attach: function (context, settings) {

      var parse_label = {
        'prefix' : '<strong>' + Drupal.t('Prefix') + '</strong>',
        'first_name' : '<strong>' + Drupal.t('FirstName') + '</strong>',
        'middle_name': '<strong>' + Drupal.t('MiddleName') + '</strong>',
        'last_name': '<strong>' + Drupal.t('LastName') + '</strong>',
        'suffix' : '<strong>' + Drupal.t('Suffix') + '</strong>',
      };
      var vsite = settings.spaces.url;

      $('#edit-authors', context).find('input[name^="author"]').once('parseAuthor').each(function() {
        var $this = $(this);
        $this.after('<div class="os-author-parse-info"></div>');

        $this.keyup(function() {
          var name = $this.val();
          if (name.length > 2) {
            $.ajax({
              url: vsite + 'contributor/parse/autocomplete/' + name,
              dataType: 'json',
              success: function (data) {
                var output = '';
                $.each(data, function(key, value) {
                  if (parse_label[key]) {
                    output += parse_label[key] + ': ' + value.trim() + ' ';
                  }
                });
                $this.siblings('.os-author-parse-info').html(output);
              }
            });
          }
          else {
            $this.siblings('.os-author-parse-info').text('');
          }
        });
      });
    }
  };

})(jQuery, Drupal);
