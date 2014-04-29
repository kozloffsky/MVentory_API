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
 * @package MVentory/TM
 * @copyright Copyright (c) 2014 mVentory Ltd. (http://mventory.com)
 * @license http://creativecommons.org/licenses/by-nc-nd/4.0/
 * @author Anatoly A. Kazantsev <anatoly@mventory.com>
 */

jQuery(document).ready(function ($) {
  var new_rule = {
    'id': null,
    'category': null,
    'tm_category': null,
    'attrs' : []
  };

  var rules = [];

  var $rules = $('#tm-matching-rules')
  var $rule_template = $('#tm-matching-rules').find('> .tm-template');
  var $new_rule = $('#tm-matching-new-rule > .tm-inner');
  var $new_attr = $new_rule.find('> .tm-matching-new-attr');
  var $categories_wrapper = $('#categories-wrapper');

  var $save_rule_button = $('#tm-save-rule-button');

  var $tm_category = $('#tm-category');
  var $magento_category = $('#magento-category');

  var default_magento_category_text = $magento_category.html();

  $new_attr
    .find('> div > .tm-rule-attr')
    .on('change', function () {
      var $this = $(this);
      var attr_id = $this.val();

      var attr = tm_attrs[attr_id];

      var $parent = $this.parents('.tm-matching-new-attr');

      if (!$parent.next().length)
        $new_rule.append(reset_attr(clone_attr()));

      var $values = $parent
                      .removeClass('tm-not-completed')
                      .find('> div > .tm-rule-value')
                      .empty();

      for (var i in attr.values)
        $values.append($('<option>', {
          value: i,
          text: attr.values[i],
          class: attr.used_values[i] ? 'used-value' : ''
        }));

      $values.change();
    });

  $new_attr
    .find('> div > .tm-rule-value')
    .on('change', function () {
      new_rule.attrs = get_attrs();
      update_save_rule_button_state();
    });

  $new_attr
    .find('> .tm-matching-new-attr-buttons > .tm-remove-button')
    .on('click', function () {
      var $parent = $(this).parents('.tm-matching-new-attr');

      if ($parent.hasClass('tm-not-completed'))
        return false;

      $parent.remove();

      new_rule.attrs = get_attrs();
      update_save_rule_button_state();

      return false;
    });

  $save_rule_button.on('click', function () {
    if ($save_rule_button.hasClass('disabled'))
      return;

    new_rule.id = new_rule.attrs.length
                    ? 'rule' + new Date().getTime()
                      : TM_DEFAULT_RULE_ID;

    submit_rule(new_rule);

    for (var i = 0; i < new_rule.attrs.length; i++) {
      var attr = new_rule.attrs[i];

      $new_rule
        .find('> .tm-matching-new-attr')
        .last()
        .find('> div > .tm-rule-attr > [value="' + attr.id + '"]')
        .addClass('used-attr');

      $.map($.makeArray(attr.value), function (value, index) {
        tm_attrs[attr.id]['used_values'][value * 1] = true;
      });
    }

    var $default_rule = $('#' + TM_DEFAULT_RULE_ID);

    if (new_rule.id == TM_DEFAULT_RULE_ID && $default_rule.length) {
      update_categories_names($default_rule);
    } else {
      var $rule = $rule_template
                    .clone(true)
                    .removeClass('tm-template')
                    .attr('id', new_rule.id);

      var $list = $rule.find('> .tm-matching-rule-attrs > .tm-inner');
      var $attr_template = $list.find('> :first-child');

      update_categories_names($rule);

      if (new_rule.id == TM_DEFAULT_RULE_ID)
        $attr_template
          .clone()
          .html(TM_DEFAULT_RULE_TITLE)
          .appendTo($list);
      else
        for (var i = 0; i < new_rule.attrs.length; i++) {
          var attr = new_rule.attrs[i];
          var attr_data = tm_attrs[attr.id];

          var value = $.map($.makeArray(attr.value), function (value, index) {
            return attr_data.values[value];
          });

          var $values = $attr_template.clone();

          $values
            .find('> .tm-matching-rule-attr-name')
            .html(attr_data.label);

          $values
            .find('> .tm-matching-rule-attr-value')
            .html(value.join(', '));

          $list.append($values);
        }

      $attr_template.remove();

      if ($default_rule.length)
        $default_rule.before($rule)
      else
        $rules.append($rule);
    }

    if (new_rule.tm_category > 0)
      $('#tm_categories')
        .find('> tbody > tr > .radio > .category-check')
        .filter('[value="' + new_rule.tm_category + '"]')
        .parents('tr')
        .addClass('selected-row');

    rules.push(new_rule);

    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $('#tm-reset-rule-button').on('click', function () {
    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $('#tm-categories-button').on('click', function () {
    $('#loading-mask').show();

    $.ajax({
      url: tm_urls['categories'],
      data: { selected_categories: tm_used_categories },
      dataType: 'html',
      success: function (data, text_status, xhr) {
        $('#tm_categories_wrapper').html(data);

        $('#tm-categories-button').remove();

        var $table = $('#tm_categories');

        tm_apply_table_handlers($table, function (e) {
          var $this = $(this);

          var $tds = $this.find('>');

          var $radio = $tds
                         .filter('.radio')
                         .find('> .category-check')
                         .prop('checked', true);

          new_rule.tm_category = $radio.val();

          var name = $tds
                       .not('[class]')
                       .map(function () {
                         var text = $(this).text();

                         return text.length ? text : null;
                       })
                       .get()
                       .join(' - ');

          $tm_category.text(name);

          update_save_rule_button_state();
        });

        $('#tm_filter').on('keyup', function () {
          $.uiTableFilter($table, $(this).val());
        });
      },
      complete: function (xhr, text_status) {
        $('#loading-mask').hide();
      }
    });
  });

  $rules
    .find('> .tm-matching-rule > .tm-remove-button')
    .on('click', function () {
      var $rule = $(this).parent();

      remove_rule($rule.attr('id'));

      $rule.remove();

      return false;
    });

  $rules.sortable({
    items: '[id^="rule"]',
    placeholder: 'tm-rule-placeholder box',
    forcePlaceholderSize: true,
    axis: 'y',
    containment: 'parent',
    revert: 200,
    tolerance: 'pointer',
    update: function () {
      reorder_rules($rules.sortable('toArray'));
    }
  });

  $magento_category.on('click', function () {
    $categories_wrapper.toggle();

    return false;
  });

  $('#tm-ignore-button').on('click', function () {
    new_rule.tm_category = -1;
    $tm_category.text(TM_DONT_LIST_ON_TM_TITLE);
    update_save_rule_button_state();
  });

  function clone_attr () {
    return $new_rule
             .find('> .tm-matching-new-attr')
             .last()
             .clone(true);
  }

  function reset_attr ($attr) {
    return $attr
             .find('> .tm-rule-attr')
               .val('-1')
             .end();
  }

  function clear_attrs () {
    var $attr = clone_attr();

    $new_rule
      .find('> .tm-matching-new-attr')
      .remove();

    reset_attr($attr)
      .appendTo($new_rule);

    new_rule.attrs = [];
  }

  function uncheck_category () {
    $('#tm_categories')
      .find('> tbody > tr > .radio > .category-check:checked')
      .prop('checked', false);

    $tm_category.empty();
    $magento_category.text(default_magento_category_text);

    new_rule.category = null;
    new_rule.tm_category = null;
  }

  function get_attrs () {
    var attrs = [];

    $new_rule
      .find('> .tm-matching-new-attr')
      .each(function () {
        var attr = get_attr($(this));

        if (!(attr.id == '-1' || attr.value == null))
          attrs.push(attr);
      });

    return attrs;
  }

  function get_attr ($attrs) {
    return {
      id: $attrs.find('> div > .tm-rule-attr').val(),
      value: $attrs.find('> div > .tm-rule-value').val()
    }
  }

  function submit_rule (rule) {
    $.ajax({
      url: tm_urls['addrule'],
      type: 'POST',
      data: { rule: JSON.stringify(rule), form_key: FORM_KEY },
      success: function (data, text_status, xhr) {
        console.log(data);
      },
      complete: function (xhr, text_status) {
      }
    });
  }

  function remove_rule (rule_id) {
    $.ajax({
      url: tm_urls['remove'],
      type: 'POST',
      data: { rule_id: rule_id, form_key: FORM_KEY },
      success: function (data, text_status, xhr) {
        console.log(data);
      },
      complete: function (xhr, text_status) {
      }
    });
  }

  function reorder_rules (ids) {
    $.ajax({
      url: tm_urls['reorder'],
      type: 'POST',
      data: { ids: ids, form_key: FORM_KEY },
      success: function (data, text_status, xhr) {
        console.log(data);
      },
      complete: function (xhr, text_status) {
      }
    });
  }

  function update_save_rule_button_state () {
    if (new_rule.tm_category > 0 || new_rule.tm_category == -1
        || new_rule.category)
      $save_rule_button.removeClass('disabled');
    else
      $save_rule_button.addClass('disabled');
  }

  function update_categories_names ($rule) {
    $categories
      = $rule
          .find('> .tm-matching-rule-categories > .tm-inner > .tm-rule-category');

    var $category = $categories
                      .filter('.tm-category')
                      .text($tm_category.text());

    if (!(new_rule.tm_category > 0 || new_rule.tm_category == -1))
      $category.addClass('tm-rule-no-category');

    var $category = $categories
                      .filter('.magento-category')
                      .text($magento_category.text());

    if (!new_rule.category)
      $category.addClass('tm-rule-no-category');
  }

  function select_category (id, name) {
    new_rule.category = id;

    $magento_category.text(name);
    $categories_wrapper.toggle();

    update_save_rule_button_state();
  }

  window.tm_select_category = select_category;
});
