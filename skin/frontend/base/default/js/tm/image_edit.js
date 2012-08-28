jQuery(document).ready(function ($) {
  var $form = $('#product_addtocart_form');

  $form
    .find('.tm-image-editor-menu')
    .parent()
    .mouseenter(function () {
      $this = $(this);

      var offset = $this.offset();

      $this
        .children('.tm-image-editor-menu')
        .css({
          top: offset.top + $this.height() - 10,
          left: offset.left + 10
        })
        .show();
    })
    .mouseleave(function () {
      $(this)
          .children('.tm-image-editor-menu')
          .hide();
    });

  $form
    .find('div.rotate-image')
    .filter('.rotate-left')
      .click({ rotate: 'left'}, rotate_button_click_handler)
    .end()
    .filter('.rotate-right')
      .click({ rotate: 'right'}, rotate_button_click_handler);

  function rotate_image (file, rotate, complete) {
    $.ajax({
      url: _tm_image_editor_controller_url,
      type: 'POST',
      dataType: 'json',
      data: { file: file, rotate:  rotate},
      error: function (jqXHR, status, errorThrown) {
        alert(status);
      },
      /*success: function (data, status, jqXHR) {
        console.log(data);
      },*/
      complete: complete
    });
  }

  function rotate_button_click_handler (event) {
    $this = $(this);

    $this
      .parent()
      .children('.rotate-image')
      .off('click', rotate_button_click_handler);

    event.preventDefault();

    var image = $(this)
                  .parent()
                  .children('input')
                  .val();

    var rotate = event.data.rotate;

    rotate_image(image, rotate, function () {
      //$this.on('click', { rotate: rotate }, rotate_button_click_handler);

      $this
        .parent()
        .remove();
    });

    var $imgs = $form
                  .find('img')
                  .filter('[src$="' + image + '"]');

    if (rotate == 'left')
      $imgs.addClass('rotate-90');
    else
      $imgs.addClass('rotate90');

    return false;
  }
});
