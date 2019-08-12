jQuery(document).ready(function($) {
  $('input.enabled[type=checkbox]').change(function() {
    $('.'+$(this).attr('id')).prop('disabled', (_, val) => !val);
    $('#public_'+$(this).attr('id')).prop('disabled', !$(this).prop('checked'));
  });
  $('input.enabled[type=checkbox][checked=checked]').each(function() {
    $('.'+$(this).attr('id')).prop('disabled', false);
  });
  $('input.public[type=checkbox]').change(function() {
    if ($(this).prop('checked')) {
      $('#'+$(this).attr('id').slice(0, -33)).prop('checked', true);
    } else {
      $('.public.' + $(this).attr('id').slice(7)).prop('checked', false);
    }
  });
});

