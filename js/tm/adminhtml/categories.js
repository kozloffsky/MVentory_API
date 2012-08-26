(function ($) {

window.tm_filter = function () {
  var text = $('#tm_filter').val();

  $.uiTableFilter($('#tm_categories'), text);
}

window.tm_select = function (event) {
  if (!event)
    var event = window.event;

  var $input = $(event.target);

  var $tr = $input
              .parents('tr');

  $tr
    .clone()
    .appendTo($('#tm_selected_categories > tbody'))
    .find('input')[0]
    .onclick = window.tm_unselect;

  $tr
    .addClass('no-display');

  collectIds();

  $input
    .prop('checked', false);
}

window.tm_unselect = function (event) {
  if (!event)
    var event = window.event;

  var $input = $(event.target);

  var id = $input.val();

  $a = $('#tm_categories')
   .find('> tbody > tr > .checkbox > input[value="' + id + '"]')
   .parents('tr')
   .removeClass('no-display');

  $input
    .parents('tr')
    .remove();
}

function collectIds () {
  var $ids = ''

  $('#tm_selected_categories')
    .find('> tbody > tr > .checkbox > input')
    .each(function () {
      $ids += ',' + $(this).val();
    });

  $('#mventory_tm_category')
    .val($ids.substring(1));
}

})(jQuery)
