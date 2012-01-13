/**
 * Allows users to add posts to their manual lists without an additional 
 * page load on top of the ajax call
 */
Drupal.behaviors.os_boxes_follow = function (ctx) {
	if ($('#follow-links-list', ctx).length == 0) return;	// do nothing if our table doesn't exist
	
	var $form = $('#boxes-box-form'),
		template = '<tr class="draggable">'+$('#edit-links-blank-title').parents('tr').hide().html()+'</tr>',
		tableDrag = Drupal.tableDrag['follow-links-list'],
		new_id = parseInt($('#edit-count').val());
	
	// add a new row to the table, set all its form elements to the right values and make it draggable
	$('.add_new', $form).click(function (e) {
		var val = $('#edit-link-to-add', $form).val(),
			patt = /^https?:\/\/([^\/]+)/,
			matches = patt.exec(val),
			id = new_id++,
			new_row = $(template.replace(/blank/g, id)),
			i, fd;
		
		// there should actually be something in the field
		if (matches != null) {
			var count = $('#edit-count'),
				domain = matches[1],
				domains = Drupal.settings.follow_networks;
			count.val(parseInt(count.val())+1);
			
			// get domain
			for (i in domains) {
				fd = domains[i];
				if (domain.indexOf(fd.domain) != -1) {
					domain = i;
					break;
				}
			}
			
			// if we don't have a valid domain, don't make a new row
			if (domain != matches[1]) {			
				// set all the form elements in the new row
				$('span', new_row).addClass(domain).text(val);
				$('#edit-links-'+id+'-title', new_row).val(val);
				$('#edit-links-'+id+'-domain', new_row).val(domain);
				$('#edit-links-'+id+'-weight', new_row).addClass('field-weight').val(id);
				$('#edit-links-'+id+'-weight', new_row).parents('td').css('display', 'none');
				//$('.tabledrag-handle', new_row).remove();
				$('table tbody', $form).append(new_row);
				new_row = $('#edit-links-'+id+'-title', $form).parents('tr');
				
				setup_remove(new_row);

				tableDrag.makeDraggable(new_row[0]);
			}
			else {
				// alert the user that the domain was not invalid.
				// bein' lazy for now
				alert(val+' is not from a valid social media domain.');
			}
			
			$('#edit-link-to-add', $form).val('');
		}
	});
	
	// set up remove links.
	function setup_remove(ctx) {
		$('.remove', ctx).click(function () {
			var $this = $(this);
			$this.parents('tr').remove();
			
			// decrement counter
			var count = $('#edit-count');
			count.val(parseInt(count.val())-1);
			
			return false;
		});
	}
	
	setup_remove($form);
};