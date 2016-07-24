<div class="block start race">
	<div class="title">Выбор фракции</div>
	<div class="content">
		<? if ($message != ''): ?>
			<div class="errormessage"><?=$message ?></div>
		<? endif; ?>
		<form action="" method="POST" id="tabs">
			<? foreach (_getText('race') AS $i => $name): if (!$name) continue; ?>
				<input type="radio" name="race" value="<?=$i ?>" id="f_<?=$i ?>" <?=($this->request->getPost('race') == $i ? 'checked' : '') ?>>
				<label for="f_<?=$i ?>" class="avatar">
					<img src="<?=$this->url->getBaseUri() ?>assets/images/skin/race<?=$i ?>.gif" alt=""><br>
					<h3><?=$name ?></h3>
					<span>
						<?=_getText('info', 700+$i) ?>
					</span>
				</label>
			<? endforeach; ?>
			<br>
			<input type="submit" name="save" value="Продолжить">
			<br><br>
		</form>
	</div>
</div>