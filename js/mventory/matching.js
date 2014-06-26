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
 * @package MVentory/API
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

  var $rules = $('#mventory-rules')
  var $rule_template = $rules.children('.mventory-rule-template');
  var $new_rule = $('#mventory-rule-new').children('.mventory-inner');
  var $new_attr = $new_rule.children('.mventory-rule-new-attr');
  var $categories_wrapper = $('#mventory-categories-wrapper');

  var $save_rule_button = $('#mventory-rule-save');
  var $magento_category = $('#mventory-categories');

  var default_magento_category_text = $magento_category.html();

  $new_attr
    .find('> div > .mventory-rule-new-attr-name')
    .on('change', function () {
      var $this = $(this);
      var attr_id = $this.val();

      var attr = mventory_attrs[attr_id];

      var $parent = $this.parents('.mventory-rule-new-attr');

      if (!$parent.next().length)
        $new_rule.append(reset_attr(clone_attr()));

      var $values = $parent
                      .removeClass('mventory-state-not-completed')
                      .find('> div > .mventory-rule-new-attr-value')
                      .empty();

      for (var i in attr.values)
        $values.append($('<option>', {
          value: i,
          text: attr.values[i],
          class: attr.used_values[i] ? 'mventory-state-used-value' : ''
        }));

      $values.change();
    });

  $new_attr
    .find('> div > .mventory-rule-new-attr-value')
    .on('change', function () {
      new_rule.attrs = get_attrs();
      update_save_rule_button_state();
    });

  $new_attr
    .find('> .mventory-rule-new-attr-buttons > .mventory-rule-remove')
    .on('click', function () {
      var $parent = $(this).parents('.mventory-rule-new-attr');

      if ($parent.hasClass('mventory-state-not-completed'))
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
                      : MVENTORY_RULE_DEFAULT_ID;

    submit_rule(new_rule);

    for (var i = 0; i < new_rule.attrs.length; i++) {
      var attr = new_rule.attrs[i];

      $new_rule
        .find('> .mventory-rule-new-attr')
        .last()
        .find('> div > .mventory-rule-new-attr-name > [value="' + attr.id + '"]')
        .addClass('mventory-state-used-attr');

      $.map($.makeArray(attr.value), function (value, index) {
        mventory_attrs[attr.id]['used_values'][value * 1] = true;
      });
    }

    var $default_rule = $('#' + MVENTORY_RULE_DEFAULT_ID);

    if (new_rule.id == MVENTORY_RULE_DEFAULT_ID && $default_rule.length) {
      update_categories_names($default_rule);
    } else {
      var $rule = $rule_template
                    .clone(true)
                    .removeClass('mventory-rule-template')
                    .attr('id', new_rule.id);

      var $list = $rule.find('> .mventory-rule-attrs > .mventory-inner');
      var $attr_template = $list.find('> :first-child');

      update_categories_names($rule);

      if (new_rule.id == MVENTORY_RULE_DEFAULT_ID)
        $attr_template
          .clone()
          .html(MVENTORY_RULE_DEFAULT_TITLE)
          .appendTo($list);
      else
        for (var i = 0; i < new_rule.attrs.length; i++) {
          var attr = new_rule.attrs[i];
          var attr_data = mventory_attrs[attr.id];

          var value = $.map($.makeArray(attr.value), function (value, index) {
            return attr_data.values[value];
          });

          var $values = $attr_template.clone();

          $values
            .children('.mventory-rule-attr-name')
            .html(attr_data.label);

          $values
            .children('.mventory-rule-attr-value')
            .html(value.join(', '));

          $list.append($values);
        }

      $attr_template.remove();

      if ($default_rule.length)
        $default_rule.before($rule)
      else
        $rules.append($rule);
    }

    rules.push(new_rule);

    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $('#mventory-rule-reset').on('click', function () {
    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $rules
    .find('> .mventory-rule > .mventory-rule-remove')
    .on('click', function () {
      var $rule = $(this).parent();

      remove_rule($rule.attr('id'));

      $rule.remove();

      return false;
    });

  $rules.sortable({
    items: '[id^="rule"]',
    placeholder: 'mventory-rule-placeholder box',
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

  function clone_attr () {
    return $new_rule
             .find('> .mventory-rule-new-attr')
             .last()
             .clone(true);
  }

  function reset_attr ($attr) {
    return $attr
             .children('.mventory-rule-new-attr-name')
               .val('-1')
             .end();
  }

  function clear_attrs () {
    var $attr = clone_attr();

    $new_rule
      .find('> .mventory-rule-new-attr')
      .remove();

    reset_attr($attr)
      .appendTo($new_rule);

    new_rule.attrs = [];
  }

  function uncheck_category () {
    $magento_category.text(default_magento_category_text);

    new_rule.category = null;
  }

  function get_attrs () {
    var attrs = [];

    $new_rule
      .find('> .mventory-rule-new-attr')
      .each(function () {
        var attr = get_attr($(this));

        if (!(attr.id == '-1' || attr.value == null))
          attrs.push(attr);
      });

    return attrs;
  }

  function get_attr ($attrs) {
    return {
      id: $attrs.find('> div > .mventory-rule-new-attr-name').val(),
      value: $attrs.find('> div > .mventory-rule-new-attr-value').val()
    }
  }

  function submit_rule (rule) {
    $.ajax({
      url: mventory_urls['addrule'],
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
      url: mventory_urls['remove'],
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
      url: mventory_urls['reorder'],
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
    if (new_rule.category)
      $save_rule_button.removeClass('disabled');
    else
      $save_rule_button.addClass('disabled');
  }

  function update_categories_names ($rule) {
    $category = $rule
      .find('> .mventory-rule-categories .mventory-rule-category')
      .text($magento_category.text())

    if (!new_rule.category)
      $category.addClass('mventory-state-no-category');
  }

  function select_category (id, name) {
    new_rule.category = id;

    $magento_category.text(name);
    $categories_wrapper.toggle();

    update_save_rule_button_state();
  }

  window.mventory_select_category = select_category;
});
