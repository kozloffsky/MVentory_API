/**
 * MVentory
 *
 * @category MVentory
 * @package  js
 * @author   MVentory <???@mventory.com>
 */

jQuery(document).ready(function ($) {
  var new_rule = {
    'category': null,
    'attrs' : []
  };

  var rules = [];

  var $rules = $('#tm-matching-rules')
  var $new_rule = $('#tm-matching-new-rule > .tm-inner');
  var $new_cat_name = $('#tm-matching-new-cat-name > .tm-inner');

  var $rule_template = $('#tm-matching-rules').find('> .tm-template');

  var $save_rule_button = $('#tm-save-rule-button');

  var $new_attr = $new_rule.find('> .tm-matching-new-attr');

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
                      .prop('multiple', attr.type == 'multiselect')
                      .empty();

      for (var i in attr.values)
        $values.append($('<option>', { value: i, text: attr.values[i] }))

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

    submit_rule(new_rule);

    var $rule = $rule_template
                  .clone()
                  .removeClass('tm-template')

    var $list = $rule.find('> .tm-matching-rule-attrs > .tm-inner');
    var $attr_template = $list.find('> :first-child');

    $rule
      .find('> .tm-matching-rule-category > .tm-inner')
      .text($new_cat_name.text());

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

    $rules.append($rule);

    rules.push(new_rule);

    clear_attrs();
    uncheck_category();

    update_save_rule_button_state();
  });

  $('#tm-categories-button').on('click', function () {
    $('#loading-mask').show();

    $.ajax({
      url: tm_urls['categories'],
      dataType: 'html',
      success: function (data, text_status, xhr) {
        $('#tm_categories_wrapper').html(data);

        $('#tm-categories-button').hide();

        var $table = $('#tm_categories');

        tm_apply_table_handlers($table, function (e) {
          var $this = $(this);

          var $tds = $this.find('>');

          var $radio = $tds
                         .filter('.radio')
                         .find('> .category-check')
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

          $new_cat_name.text(name);

          update_save_rule_button_state();
          scrollTo('#tm-matching-new-rule-wrapper', $(window).scrollTop() - e.pageY);
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

    $new_cat_name.empty();
    new_rule.category = null;
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

  function scrollTo (selector, offset) {
    $('html, body')
      .animate({ scrollTop: $(selector).offset().top + offset }, 200);
  }

  function update_save_rule_button_state () {
    if (/^\d+$/.test(new_rule.category) && new_rule.attrs.length)
      $save_rule_button.removeClass('disabled')
    else
      $save_rule_button.addClass('disabled')
  }
});
