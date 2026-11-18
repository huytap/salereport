<hr>
<?php if(count($data->getData())>0 || count($data2->getData())>0 || count($data3->getData())>0){?>
	<h3>REMINDER</h3>
<?php }?>
<div class="row">
	<?php if(count($data->getData())>0){?>
		<div class="col-md-4">
			<label class="col-md-3 col-sm-5">ENT: </label>
		    <marquee class="col-md-9 col-sm-7" behavior="scroll" direction="left" id="ent">
		    	<ul>
					<?php
					foreach($data->getData() as $dt){
						echo '<li>'.ExtraHelper::date_2_show($dt['ent']).' ('.$dt->user->username.')</li>';
					}
					?>
				</ul>
		    </marquee>
		</div>
	<?php }?>
	<?php if(count($data2->getData())>0){?>
	    <div class="col-md-4">
		    <label class="col-md-3 col-sm-4">Sales: </label>
		    <marquee class="col-md-9 col-sm-8" behavior="scroll" direction="left" id="sales">
		    	<ul>
					<?php
					foreach($data2->getData() as $dt){
						echo '<li>'.ExtraHelper::date_2_show($dt['sales']).' ('.$dt->user->username.')</li>';
					}
					?>
				</ul>
		    </marquee>
		</div>
	<?php }?>
	<?php if(count($data3->getData())>0){?>
		<div class="col-md-4">
			<label class="col-md-3 col-sm-4">Site INS: </label>
		    <marquee class="col-md-9 col-sm-8" behavior="scroll" direction="left" id="site_ins">
		    	<ul>
					<?php
					foreach($data3->getData() as $dt){
						echo '<li>'.ExtraHelper::date_2_show($dt['site_ins']).' ('.$dt->user->username.')</li>';
					}
					?>
				</ul>
		    </marquee>
		</div>
	<?php }?>
</div>