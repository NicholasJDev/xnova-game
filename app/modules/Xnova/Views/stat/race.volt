<div class="table middle">
	<div class="row">
		<div class="c col-xs-2">Место</div>
		<div class="c col-xs-1">&nbsp;</div>
		<div class="c col-xs-3">Игроков</div>
		<div class="c col-xs-3">Очков</div>
		<div class="c col-xs-3">Очки на игрока</div>
	</div>
	<? if (count($stat) > 0): ?>
		<? foreach ($stat AS $s): ?>
			<div class="row">
				<div class="th col-xs-2"><?=$s['rank'] ?></div>
				<div class="th col-xs-1 text-xs-center"><img src="<?=$this->url->getBaseUri() ?>assets/images/skin/race<?=$s['race'] ?>.gif" width="30" height="30"></div>
				<div class="th col-xs-3"><?=$s['count'] ?></div>
				<div class="th col-xs-3"><?=$s['points'] ?></div>
				<div class="th col-xs-3"><?=$s['pointatuser'] ?></div>
			</div>
		<? endforeach; ?>
	<? endif; ?>
</div>