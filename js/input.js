/* global acf */

(function($) {

  // page_link
  acf.fields.post_or_taxonomy_link = acf.fields.select.extend({
    type: 'post_or_taxonomy_link',
    pagination: true,
  });

})(jQuery);
