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

jQuery(document).ready(function ($) {
  var new_rule = {
    'id': null,
    'category': null,
    'attrs' : []
  };

  var rules = [];

  var $rules = $('#trademe-rules')
  var $rule_template = $rules.children('.trademe-rule-template');
  var $new_rule = $('#trademe-rule-new').children('.trademe-inner');
  var $new_attr = $new_rule.children('.trademe-rule-new-attr');

  var $save_rule_button = $('#trademe-rule-save');

  var $category = $('#trademe-rule-new-category');

  $new_attr
    .find('> div > .trademe-rule-new-attr-name')
    .on('change', function () {
      var $this = $(this);
      var attr_id = $this.val();

      var attr = trademe_attrs[attr_id];

      var $parent = $this.parents('.trademe-rule-new-attr');

      if (!$parent.next().length)
        $new_rule.append(reset_attr(clone_attr()));

      var $values = $parent
                      .removeClass('trademe-state-not-completed')
                      .find('> div > .trademe-rule-new-attr-value')
                      .empty();

      for (var i in attr.values)
        $values.append($('<option>', {
          value: i,
          text: attr.values[i],
          class: attr.used_values[i] ? 'trademe-state-used-value' : ''
        }));

      $values.change();
    });

  $new_attr
    .find('> div > .trademe-rule-new-attr-value')
    .on('change', function () {
      new_rule.attrs = get_attrs();
      update_save_rule_button_state();
    });

  $new_attr
    .find('> .trademe-rule-new-attr-buttons > .trademe-rule-remove')
    .on('click', function () {
      var $parent = $(this).parents('.trademe-rule-new-attr');

      if ($parent.hasClass('trademe-state-not-completed'))
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
                      : TRADEME_RULE_DEFAULT_ID;

    submit_rule(new_rule);

    for (var i = 0; i < new_rule.attrs.length; i++) {
      var attr = new_rule.attrs[i];

      $new_rule
        .find('> .trademe-rule-new-attr')
        .last()
        .find('> div > .trademe-rule-new-attr-name > [value="' + attr.id + '"]')
        .addClass('trademe-state-used-attr');

      $.map($.makeArray(attr.value), function (value, index) {
        trademe_attrs[attr.id]['used_values'][value * 1] = true;
      });
    }

    var $default_rule = $('#' + TRADEME_RULE_DEFAULT_ID);

    if (new_rule.id == TRADEME_RULE_DEFAULT_ID && $default_rule.length) {
      update_categories_names($default_rule);
    } else {
      var $rule = $rule_template
                    .clone(true)
                    .removeClass('trademe-rule-template')
                    .attr('id', new_rule.id);

      var $list = $rule.find('> .trademe-rule-attrs > .trademe-inner');
      var $attr_template = $list.find('> :first-child');

      update_categories_names($rule);

      if (new_rule.id == TRADEME_RULE_DEFAULT_ID)
        $attr_template
          .clone()
          .html(TRADEME_RULE_DEFAULT_TITLE)
          .appendTo($list);
      else
        for (var i = 0; i < new_rule.attrs.length; i++) {
          var attr = new_rule.attrs[i];
          var attr_data = trademe_attrs[attr.id];

          var value = $.map($.makeArray(attr.value), function (value, index) {
            return attr_data.values[value];
          });

          var $values = $attr_template.clone();

          $values
            .find('> .trademe-rule-attr-name')
            .html(attr_data.label);

          $values
            .find('> .trademe-rule-attr-value')
            .html(value.join(', '));

          $list.append($values);
        }

      $attr_template.remove();

      if ($default_rule.length)
        $default_rule.before($rule)
      else
        $rules.append($rule);
    }

    if (new_rule.category > 0)
      $('#trademe-categories')
        .find('> tbody > tr > .radio > .trademe-category-selector')
        .filter('[value="' + new_rule.category + '"]')
        .parents('tr')
        .addClass('trademe-state-selected');

    rules.push(new_rule);

    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $('#trademe-rule-reset').on('click', function () {
    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $('#trademe-rule-categories').on('click', function () {
    $('#loading-mask').show();

    $.ajax({
      url: trademe_urls['categories'],
      data: { selected_categories: trademe_used_categories },
      dataType: 'html',
      success: function (data, text_status, xhr) {
        $('#trademe_wrapper_categories').html(data);

        $('#trademe-rule-categories').remove();

        var $table = $('#trademe-categories');

        trademe_categories_handlers($table, function (e) {
          var $this = $(this);

          var $tds = $this.find('>');

          var $radio = $tds
                         .filter('.radio')
                         .find('> .trademe-category-selector')
                         .prop('checked', true);

          new_rule.category = $radio.val();

          var name = $tds
                       .not('[class]')
                       .map(function () {
                         var text = $(this).text();

                         return text.length ? text : null;
                       })
                       .get()
                       .join(' - ');

          $category.text(name);

          update_save_rule_button_state();
        });

        $('#trademe-categories-filter').on('keyup', function () {
          $.uiTableFilter($table, $(this).val());
        });
      },
      complete: function (xhr, text_status) {
        $('#loading-mask').hide();
      }
    });
  });

  $rules
    .find('> .trademe-rule > .trademe-rule-remove')
    .on('click', function () {
      var $rule = $(this).parent();

      remove_rule($rule.attr('id'));

      $rule.remove();

      return false;
    });

  $rules.sortable({
    items: '[id^="rule"]',
    placeholder: 'trademe-rule-placeholder2 box',
    forcePlaceholderSize: true,
    axis: 'y',
    containment: 'parent',
    revert: 200,
    tolerance: 'pointer',
    update: function () {
      reorder_rules($rules.sortable('toArray'));
    }
  });

  $('#trademe-rule-ignore').on('click', function () {
    new_rule.category = -1;
    $category.text(TRADEME_DONT_LIST_TITLE);
    update_save_rule_button_state();
  });

  function clone_attr () {
    return $new_rule
             .find('> .trademe-rule-new-attr')
             .last()
             .clone(true);
  }

  function reset_attr ($attr) {
    return $attr
             .find('> .trademe-rule-new-attr-name')
               .val('-1')
             .end();
  }

  function clear_attrs () {
    var $attr = clone_attr();

    $new_rule
      .find('> .trademe-rule-new-attr')
      .remove();

    reset_attr($attr).appendTo($new_rule);

    new_rule.attrs = [];
  }

  function uncheck_category () {
    $('#trademe-categories')
      .find('> tbody > tr > .radio > .trademe-category-selector:checked')
      .prop('checked', false);

    $category.empty();

    new_rule.category = null;
  }

  function get_attrs () {
    var attrs = [];

    $new_rule
      .find('> .trademe-rule-new-attr')
      .each(function () {
        var attr = get_attr($(this));

        if (!(attr.id == '-1' || attr.value == null))
          attrs.push(attr);
      });

    return attrs;
  }

  function get_attr ($attrs) {
    return {
      id: $attrs.find('> div > .trademe-rule-new-attr-name').val(),
      value: $attrs.find('> div > .trademe-rule-new-attr-value').val()
    }
  }

  function submit_rule (rule) {
    $.ajax({
      url: trademe_urls['addrule'],
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
      url: trademe_urls['remove'],
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
      url: trademe_urls['reorder'],
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
    if (new_rule.category > 0 || new_rule.category == -1)
      $save_rule_button.removeClass('disabled');
    else
      $save_rule_button.addClass('disabled');
  }

  function update_categories_names ($rule) {
    var $_category = $rule
      .find('> .trademe-rule-categories .trademe-rule-category')
      .text($category.text())

    if (!(new_rule.category > 0 || new_rule.category == -1))
      $_category.addClass('trademe-state-no-category');
  }
});
