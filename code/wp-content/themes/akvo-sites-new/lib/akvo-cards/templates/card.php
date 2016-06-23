<div class='card'>
	<div class='card-header'>
		<h3 class='card-title'>
			<a href="<?php _e($atts['link']);?>"><?php _e($atts['title']);?></a>	
		</h3>
		<div class="card-info">
			<span><i class="fa fa-calendar"></i>&nbsp;<?php _e($atts['date']);?></span><span class='pull-right'><?php _e($atts['type']);?></span>
		</div>
		<div class='card-image' <?php if($atts['img']):?>style="background-image:url('<?php _e($atts['img']);?>');"<?php endif;?>></div>
	</div>
	<div class='card-content'>
		<?php echo truncate($atts['content'], 150);?>
	</div>
	<a class="btn btn-default card-more pull-right" href="<?php _e($atts['link']);?>">Read More &rarr;</a>
</div>	
