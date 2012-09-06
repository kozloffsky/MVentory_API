(function ($) {

function mt_checkbox_handler ($selected_table, on_add, on_remove) {
  var $this = $(this);

  var $tr = $this.parents('tr');

  if ($this.prop('checked')) {
    $_tr = $tr
             .clone()
             .removeClass('even odd on-mouse')
             .appendTo($selected_table.children('tbody'));

    $tr.addClass('selected-row');

    on_add($_tr);
  } else {
    $tr.removeClass('selected-row');

    var id = $this.val();

    $selected_table
      .find('> tbody > tr > .checkbox > .category-check[value="' + id + '"]')
      .parents('tr')
      .next('.category-attrs')
        .remove()
      .end()
      .remove();

    on_remove();
  }
}

function st_checkbox_handler ($main_table) {
  var $this = $(this);

  var id = $this.val();

  $main_table
      .find('> tbody > tr > .checkbox > .category-check[value="' + id + '"]')
      .prop('checked', false)
      .parents('tr')
      .removeClass('selected-row');

  $this
    .parents('tr')
    .next('.category-attrs')
      .remove()
    .end()
    .remove();

  collectIds();
}

function mouseover_handler () {
  $(this).addClass('on-mouse');
}

function mouseout_handler () {
  $(this).removeClass('on-mouse');
}

function click_handler (event) {
  if ($(event.target).is('td')) {
    var link = $(this)
                 .find('> .checkbox > .category-url')
                 .val();

    window.open(link, '_blank');
  }
}

function add_for_category ($tr) {
  $tr
    .find('> .checkbox > .category-check')[0]
    .onclick = window.tm_second_checkbox;

  collectIds();
}

function remove_for_category () {
  collectIds();
}

function collectIds () {
  var $ids = ''

  $('#tm_selected_categories')
    .find('> tbody > tr > .checkbox > .category-check')
    .each(function () {
      $ids += ',' + $(this).val();
    });

  $('#group_4tm_assigned_categories')
    .val($ids.substring(1));
}

window.tm_main_checkbox = function (event) {
  if (!event)
    var event = window.event;

  mt_checkbox_handler.call(event.target,
                           $('#tm_selected_categories'),
                           add_for_category,
                           remove_for_category);
}

window.tm_second_checkbox = function (event) {
  if (!event)
    var event = window.event;

  st_checkbox_handler.call(event.target, $('#tm_categories'));
}

window.tm_mouseclick = function (event) {
  if (!event)
    var event = window.event;

  click_handler.call($(event.target).parent(), event);
}

window.tm_mouseover = function (event) {
  if (!event)
    var event = window.event;

  mouseover_handler.call($(event.target).parents('tr'));
}

window.tm_mouseout = function (event) {
  if (!event)
    var event = window.event;

  mouseout_handler.call($(event.target).parents('tr'));
}

window.tm_filter = function () {
  var text = $('#tm_filter').val();

  $.uiTableFilter($('#tm_categories'), text);
}

window.tm_categories = function ($main_table, $selected_table, url_templates) {
  var $submit = $('#tm_submit_button').on('click', submit_handler);

  $('#tm_filter').on('keyup', function () {
    $.uiTableFilter($main_table, $(this).val());
  });

  $main_table
    .find('> tbody > tr')
    .on({
      click: click_handler,
      mouseover: mouseover_handler,
      mouseout: mouseout_handler
    })
    .find('> .checkbox > .category-check')
    .on('click', tm_main_checkbox);
    

  $selected_table
    .find('> tbody > tr')
    .on({
      click: click_handler,
      mouseover: mouseover_handler,
      mouseout: mouseout_handler
    })
    .find('> .checkbox > .category-check')
    .on('click', tm_second_checkbox);

  function tm_main_checkbox () {
    mt_checkbox_handler.call(this,
                             $selected_table,
                             add_for_product,
                             remove_for_product);
  }

  function tm_second_checkbox () {
    $submit.removeClass('disabled');
  }

  function add_for_product ($tr) {
    $('#tm_no_selected_message').addClass('no-display');

    $tr.on({
      click: click_handler,
      mouseover: mouseover_handler,
      mouseout: mouseout_handler
    });

    var $input = $tr.find('> .checkbox > .category-check');

    $input
      .prop('name', 'selected_categories')
      .prop('type', 'radio')
      .on('click', tm_second_checkbox);

    $submit.removeClass('disabled');
  }

  function remove_for_product () {
    var $inputs = $selected_table
                    .find('> tbody > tr > .checkbox > .category-check')

    if (!$inputs.length) {
      $('#tm_no_selected_message').removeClass('no-display');
      $submit.addClass('disabled');

      return;
    }

    var is_checked = $inputs
                       .filter(':checked')
                       .length;

    if (!is_checked)
      $submit.addClass('disabled');
  }

  function submit_handler () {
    var id = $selected_table
               .find('> tbody > tr > .checkbox > .category-check')
               .filter(':checked')
               .val();

    if (!id)
      return false;

    setLocation(url_templates['submit'].replace('{{tm_category_id}}', id));
  }
}

})(jQuery)
