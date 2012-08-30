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

  collectIds();
}

function collectIds () {
  var $ids = ''

  $('#tm_selected_categories')
    .find('> tbody > tr > .checkbox > input')
    .each(function () {
      $ids += ',' + $(this).val();
    });

  $('#group_4tm_assigned_categories')
    .val($ids.substring(1));
}

window.tm_categories = function ($main_table, $selected_table, url_templates) {
  var $submit = $('#tm_submit_button').on('click', submit_handler);

  $('#tm_filter').on('keyup', function () {
    $.uiTableFilter($main_table, $(this).val());
  });

  $main_table
    .find('> tbody > tr > .checkbox > input')
    .on('click', select_handler);

  $selected_table
    .find('> tbody > tr > .checkbox > input')
    .on('click', unselect_handler);

  function select_handler () {
    var $this = $(this);

    var $tr = $this.parents('tr');

    $main_table
      .find('> tbody > .no-display')
      .removeClass('no-display');

    var $tbody = $selected_table.children('tbody');

    $tbody
      .children('tr')
      .not('#tm_no_selected_message')
      .remove();

    $('#tm_no_selected_message').addClass('no-display');

    $tr
      .clone()
      .appendTo($tbody)
      .find('> .checkbox > input')
      .one('click', unselect_handler);

    $submit.removeClass('disabled');

    $tr.addClass('no-display');

    $this.prop('checked', false);
  }

  function unselect_handler () {
    var $this = $(this);

    var id = $this.val();

    if ($this.prop('checked')) {
      $submit.removeClass('disabled');

      var $tr = $this.parents('tr');

      $selected_table
        .find('> tbody > tr')
        .not($tr)
        .remove();

      $tr = $main_table
              .find('> tbody > tr > .checkbox > input[value="' + id + '"]')
              .parents('tr');

      $main_table
        .find('> tbody > tr')
        .not($tr)
        .removeClass('no-display');
    } else {
      $main_table
        .find('> tbody > tr > .checkbox > input[value="' + id + '"]')
        .parents('tr')
        .removeClass('no-display');

      $this
        .parents('tr')
        .remove();

       $('#tm_no_selected_message').removeClass('no-display');

      $submit.addClass('disabled');
    }
  }

  function submit_handler () {
    var id = $selected_table
               .find('> tbody > tr > .checkbox > input')
               .filter(':checked')
               .val();

    if (!id)
      return false;

    setLocation(url_templates['submit'].replace('{{tm_category_id}}', id));
  }
}

})(jQuery)
