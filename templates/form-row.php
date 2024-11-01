<tr>
	<td><?php echo $key; ?>
	<td><select name="wccf_fields[<?php echo $key; ?>][type]"><option selected="selected" value="text">Text</option></select> (More Types coming soon)</td>
	<td><input type="text" name="wccf_fields[<?php echo $key; ?>][label]" value="<?php if (isset($fieldset['label'])) { echo $fieldset['label']; } ?>" /></td>
	<td><input type="text" name="wccf_fields[<?php echo $key; ?>][key]" value="<?php if (isset($fieldset['key'])) { echo $fieldset['key']; } ?>" /></td>
	<td><input type="checkbox" name="wccf_fields[<?php echo $key; ?>][required]" value="yes" <?php if (isset($fieldset['required'])) { checked("yes", $fieldset['required']); } ?></td>
	<td><a href='#' class='wccf_delete_field'>Delete</a>
</tr>