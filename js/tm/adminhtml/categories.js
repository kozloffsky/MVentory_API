/**
 * MVentory
 *
 * @category MVentory
 * @package  js
 * @author   MVentory <???@mventory.com>
 */

(function ($) {

function apply_table_handlers ($target, row_click_handler) {

  //Handlers

  function highlight_category () {
    var $this = $(this);

    $this.addClass('on-mouse');

    if ($this.hasClass('category-attrs'))
      $this
        .prev()
        .addClass('on-mouse');
    else {
      $next = $this.next();

      if ($next.hasClass('category-attrs'))
        $next.addClass('on-mouse');
    }
  }

  function dehighlight_category () {
    var $this = $(this);

    $this.removeClass('on-mouse');

    if ($this.hasClass('category-attrs'))
      $this
        .prev()
        .removeClass('on-mouse');
    else {
      $next = $this.next();

      if ($next.hasClass('category-attrs'))
        $next.removeClass('on-mouse');
    }
  }

  var $trs = $target.is('table')
               ? $target.find('> tbody > tr')
                 : $target;

  $trs
    .on({
      click: function (event) {
        if (!$(event.target).is('a'))
          row_click_handler.call(this, event);
      },
      mouseover: highlight_category,
      mouseout: dehighlight_category
    })
    .find('> .checkbox > .category-check')
    .on('click', function () {
      $this = $(this);

      $this.prop('checked', !$this.prop('checked'));
    })
}

function categories_table (url_templates, on_add, on_remove) {

  //Handlers

  function show_all_categories_handler () {
    $('#loading-mask').show();

    $.ajax({
      url: url_templates['categories'],
      dataType: 'html',
      success: function (data, text_status, xhr) {
        $('#tm_categories_wrapper').html(data);

        $all_categories_button.hide();

        var $table = $('#tm_categories');

        apply_table_handlers($table, row_click_handler_wrapper);

        $('#tm_filter').on('keyup', function () {
          $.uiTableFilter($table, $(this).val());
        });
      },
      complete: function (xhr, text_status) {
        $('#loading-mask').hide();
      }
    });
  }

  function row_click_handler (on_add, on_remove) {
    var $this = $(this);
    var $selected_table = $('#tm_selected_categories');

    var $checkbox = $this.find('> .checkbox > .category-check');

    if (!$checkbox.prop('checked')) {
      $checkbox.prop('checked', true);

      $_tr = $this
               .clone()
               .removeClass('even odd on-mouse')
               .appendTo($selected_table.children('tbody'));

      $this.addClass('selected-row');

      on_add($_tr);
    } else {
      $checkbox.prop('checked', false);
      $this.removeClass('selected-row');

      var id = $checkbox.val();

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

  function row_click_handler_wrapper () {
    return row_click_handler.call(this, on_add, on_remove);
  }

  var $all_categories_button = $('#tm_categories_button')
                                 .on('click', show_all_categories_handler);
}

function tm_categories_for_product (url_templates) {
  var $selected_categories = $('#tm_selected_categories');

  function on_add ($tr) {
    $('#tm_no_selected_message').addClass('no-display');

    $tr
      .find('> .checkbox > .category-check')
      .prop('name', 'product[tm_category]')
      .prop('type', 'radio');

    apply_table_handlers($tr, row_click_handler);

    $submit.removeClass('disabled');
  }

  function on_remove () {
    var $inputs = $selected_categories
                    .find('> tbody > tr > .checkbox > .category-check');

    if (!$inputs.length) {
      $('#tm_no_selected_message').removeClass('no-display');
      $('#tm_submit_button').addClass('disabled');

      return;
    }

    var is_checked = $inputs
                       .filter(':checked')
                       .length;

    if (!is_checked)
      $submit.addClass('disabled');
  }

  //Handlers

  function submit_handler () {
    $('#product_edit_form')
      .attr('action', url_templates['submit'])
      .submit();
  }

  function update_handler () {
    $('#product_edit_form')
      .attr('action', url_templates['update'])
      .submit();
  }

  function row_click_handler () {
    $(this)
      .find('> .checkbox > .category-check')
      .prop('checked', true);

    $submit.removeClass('disabled');
  }

  var $submit = $('#tm_submit_button').on('click', submit_handler);
  var $update = $('#tm_update_button').on('click', update_handler);

  apply_table_handlers($selected_categories, row_click_handler);
  categories_table(url_templates, on_add, on_remove);
}

function tm_categories_for_category (url_templates) {
  var $selected_categories = $('#tm_selected_categories');

  function collectIds () {
    var $ids = ''

    $selected_categories
      .find('> tbody > tr > .checkbox > .category-check')
      .each(function () {
        $ids += ',' + $(this).val();
      });

    $('#group_4tm_assigned_categories')
      .val($ids.substring(1));
  }

  function on_add ($tr) {
    $tr.on('click', row_click_handler);

    collectIds();
  }

  function on_remove () {
    collectIds();
  }

  //Handlers

  function row_click_handler () {
    var $this = $(this);

    var id = $this
               .find('> .checkbox > .category-check')
               .val();

    $('#tm_categories')
        .find('> tbody > tr > .checkbox > .category-check[value="' + id + '"]')
        .prop('checked', false)
        .parents('tr')
        .removeClass('selected-row');

    $this
      .next('.category-attrs')
        .remove()
      .end()
      .remove();

    collectIds();
  }

  apply_table_handlers($selected_categories, row_click_handler);
  categories_table(url_templates, on_add, on_remove);
}

function update_total_price (price, data) {
  var $price_parts = $('#tm_price_parts');

  var price = parseFloat(price);

  var shipping_rate = $('#tm_shipping_type').val() == 3 //Free shipping
                        ? parseFloat(data['free_shipping_cost'])
                          : parseFloat(data['shipping_rate']);

  var tm_fees = $('#tm_shipping_type').val() == 3 //Free shipping
                  ? parseFloat(data['free_shipping_fees'])
                    : parseFloat(data['fees']);

  var add_tm_fees = $('#tm_add_fees').is(':checked') && tm_fees;

  if (!(shipping_rate || add_tm_fees)) {
    $price_parts.hide();

    $('#tm_total_price').html((price).toFixed(2));

    return;
  }

  var $shipping_rate_wrapper = $price_parts
                                 .children('#tm_shipping_rate_wrapper');

  var $fees_wrapper = $price_parts.children('#tm_fees_wrapper');

  if (shipping_rate) {
    $shipping_rate_wrapper.show();

    price += shipping_rate;
  } else
    $shipping_rate_wrapper.hide();

  if (add_tm_fees) {
    $fees_wrapper.show();

    price += tm_fees;
  } else
    $fees_wrapper.hide();

  $('#tm_shipping_rate').html(shipping_rate.toFixed(2));
  $('#tm_fees').html(tm_fees.toFixed(2));
  $('#tm_total_price').html((price).toFixed(2));

  $price_parts.show();
}

//Export functions to global namespace
window.tm_categories_for_product = tm_categories_for_product;
window.tm_categories_for_category = tm_categories_for_category;
window.tm_update_total_price = update_total_price;
window.tm_apply_table_handlers = apply_table_handlers;

})(jQuery)
