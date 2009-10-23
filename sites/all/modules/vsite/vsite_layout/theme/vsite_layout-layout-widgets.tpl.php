<?php
/**
 *  template for theming a list of widgets
 *  Variables:
 *  ----------
 *  $wgts -> list of all the widgets (dpm($wgts) for more info)
 *  $wgts_id -> the id of the ul
 *  $wgts_class -> the class of the ul
 */
?>

<ul id="<?php print $wgts_id; ?>" class = "<?php print $wgts_class; ?>">
	<?php foreach($wgts as $w):?>
		<li class="scholarlayout-item" id="<?php print $w['module']; ?>_<?php print $w['delta']; ?>" <?php if(isset($w['hidden']) && $w['hidden']) print " style='display:none;' "; ?>> <?php print $w['label']; ?>
		<!--
		<span class="scholarlayout-item-settings">Edit
      <ul class="item-settings-popup">
        <li>Settings</li>
        <li>Settings</li>
        <li>Settings</li>
        <li>Settings</li>
        <li>Settings</li>
      </ul>
		 </span>
		 -->
		 <div class="close-this">Remove</div>
		 <?php
		 if($w['overides']){
		 	 ?>

		   <span class="scholarlayout-item-settings">Appears here on all pages with these <span>exceptions</span>
        <ul class="item-settings-popup">
		 	 <?php
		 	  foreach ($w['overides'] as $overide) print "<li>{$overide}</li>";
		 	 ?>
		 	  </ul>
		 	 </span>
		 	 <?php
		 }
		 ?>

		</li>
	<?php endforeach?>
</ul>

