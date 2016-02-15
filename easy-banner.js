jQuery(document).ready(function($) {
	//from wp_localize_script()
	var prefix = easy_banner.plugin_prefix;
	var banner_entry_template_html = easy_banner.banner_entry_template_html;
	var position_names_hash = easy_banner.position_names_hash;

	//add button
	$('.' + prefix + 'add-btn').click(function(event)　{
		event.preventDefault();

		//check values

		//banner
		var banner_value = $('.' + prefix + 'banner-select').val();
		if (banner_value == '#NONE#') {
			alert('バナーを選択してください。');
			return;
		}
		var banner_name = $('.' + prefix + 'banner-select').find('option:selected').text()

		//position
		var position_values = new Array();
		var position_names = new Array();
		$('.' + prefix + 'pos-check').filter(":checked").each(function(i, elem) {
			position_values.push(elem.value);
			position_names.push(position_names_hash[elem.value]);
		});
		if (position_values.length == 0) {
			alert('位置を指定してください。');
			return;
		}
		
		//new banner entry
		var entryElement = $(banner_entry_template_html);
		entryElement.find('.' + prefix + 'ids').val(banner_value);
		entryElement.find('.' + prefix + 'banner_name').html(banner_name);

		entryElement.find('.' + prefix + 'positions').val(position_values.join(','));
		entryElement.find('.' + prefix + 'position_names').html(position_names.join(', '));

		//delete button
		entryElement.find('.' + prefix + 'delete-btn').click(function(event)　{
			event.preventDefault();

			this.closest('.' + prefix + 'entry').remove();
		});

		$('.' + prefix + 'banners').append(entryElement);

		//reset add banner form
		$('.' + prefix + 'banner-select').val('#NONE#');
		$('.' + prefix + 'pos-check').attr("checked",false);

	});

	//delete button
	$('.' + prefix + 'delete-btn').click(function(event)　{
		event.preventDefault();

		this.closest('.' + prefix + 'entry').remove();
	});
});