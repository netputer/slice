$(document).ready(function() {

	jQuery.event.props.push('dataTransfer');

	$(document).on('dragenter', function(e) {
		e.preventDefault();

		$('#dropbox').addClass('drag');
	});

	$(document).on('dragleave', function(e) {
		e.preventDefault();
	});

	$(document).on('drop', function(e) {
		e.preventDefault();

		var files = e.dataTransfer.files;

		for (var i = 0; i < files.length; i++) {

			var file = files[i];
			var file_content = null;

			var reader = new FileReader();
			var load_times = 0;

			reader.onload = (function(current) {
				return function(e) {
					$('#temp').append('<input type="hidden" name="photo[' + current.name + ']" value="' + e.target.result + '" />');
					load_times++;
				};
			})(file);

			reader.onloadend = function() {
				if (load_times == files.length) {
					$('#form').submit();
					$('#temp').html('');
				}
			}

			reader.readAsDataURL(file);
		}
	});

	$('#file').on('change', function() {
		$('#form').submit();
	});

});