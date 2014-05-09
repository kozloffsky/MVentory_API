/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Creative Commons License BY-NC-ND.
 * NonCommercial — You may not use the material for commercial purposes.
 * NoDerivatives — If you remix, transform, or build upon the material,
 * you may not distribute the modified material.
 * See the full license at http://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * See http://mventory.com/legal/licensing/ for other licensing options.
 *
 * @package MVentory/TradeMe
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
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
    .find('> .checkbox > .trademe-category-selector')
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
        $('#trademe-categories-wrapper').html(data);

        $all_categories_button.hide();

        var $table = $('#trademe-categories');

        apply_table_handlers($table, row_click_handler_wrapper);

        $('#trademe-categories-filter').on('keyup', function () {
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
    var $selected_table = $('#trademe-selected-categories');

    var $checkbox = $this.find('> .checkbox > .trademe-category-selector');

    if (!$checkbox.prop('checked')) {
      $checkbox.prop('checked', true);

      $_tr = $this
               .clone()
               .removeClass('even odd on-mouse')
               .appendTo($selected_table.children('tbody'));

      $this.addClass('trademe-state-selected');

      on_add($_tr);
    } else {
      $checkbox.prop('checked', false);
      $this.removeClass('trademe-state-selected');

      var id = $checkbox.val();

      $selected_table
        .find(
          '> tbody > tr > .checkbox > .trademe-category-selector[value="' + id
          + '"]'
        )
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

  var $all_categories_button = $('#trademe-categories-show')
                                 .on('click', show_all_categories_handler);
}

function categories_for_product (url_templates) {
  var $selected_categories = $('#trademe-selected-categories');

  function on_add ($tr) {
    $('#trademe-message-no-selected').addClass('no-display');

    $tr
      .find('> .checkbox > .trademe-category-selector')
      .prop('name', 'trademe_category')
      .prop('type', 'radio');

    apply_table_handlers($tr, row_click_handler);

    $submit.removeClass('disabled');
  }

  function on_remove () {
    var $inputs = $selected_categories
      .find('> tbody > tr > .checkbox > .trademe-category-selector');

    if (!$inputs.length) {
      $('#trademe-message-no-selected').removeClass('no-display');
      $('#trademe-categories-show').addClass('disabled');

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
      .find('> .checkbox > .trademe-category-selector')
      .prop('checked', true);

    $submit.removeClass('disabled');
  }

  var $submit = $('#trademe-submit').on('click', submit_handler);
  var $update = $('#trademe-update').on('click', update_handler);

  apply_table_handlers($selected_categories, row_click_handler);
  categories_table(url_templates, on_add, on_remove);
}

function update_total_price (price, data) {
  var $price_parts = $('#trademe-price');

  var price = parseFloat(price);

  var shipping_type_value = $('#trademe-tab-shipping-type').val();

  if (shipping_type_value == -1)
    shipping_type_value = data['shipping_type'];

  var shipping_rate = shipping_type_value == 3 //Free shipping
                        ? parseFloat(data['free_shipping_cost'])
                          : parseFloat(data['shipping_rate']);

  var fees = shipping_type_value == 3 //Free shipping
                  ? parseFloat(data['free_shipping_fees'])
                    : parseFloat(data['fees']);

  var add_fees_value = $('#trademe-tab-add-fees').val();

  if (add_fees_value == -1)
    add_fees_value = data['add_fees'];

  var add_fees = add_fees_value == 1 && fees;

  if (!(shipping_rate || add_fees)) {
    $price_parts.hide();

    $('#trademe-total-price').html((price).toFixed(2));

    return;
  }

  var $shipping_rate_wrapper = $price_parts
        .children('#trademe-wrapper-shipping-rate'),
      $fees_wrapper = $price_parts.children('#trademe-wrapper-fees');

  if (shipping_rate) {
    $shipping_rate_wrapper.show();

    price += shipping_rate;
  } else
    $shipping_rate_wrapper.hide();

  if (add_fees) {
    $fees_wrapper.show();

    price += fees;
  } else
    $fees_wrapper.hide();

  $('#trademe-shippingrate').html(shipping_rate.toFixed(2));
  $('#trademe-fees').html(fees.toFixed(2));
  $('#trademe-total-price').html((price).toFixed(2));

  $price_parts.show();
}

//Export functions to global namespace
window.trademe_categories = categories_for_product;
window.trademe_update_total_price = update_total_price;
window.trademe_categories_handlers = apply_table_handlers;

})(jQuery)
