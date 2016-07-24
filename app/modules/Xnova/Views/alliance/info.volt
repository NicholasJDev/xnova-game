<table class="table" style="table-layout: fixed;">
	<tr>
		<td class="c" colspan="2">Информация об альянсе</td>
	</tr>
	<? if ($parse['image'] != ""): ?>
		<tr><th colspan="2"><img src="<?=$parse['image'] ?>" style="max-width:100%"></th></tr>
	<? endif; ?>
	<?=$parse['image'] ?>
	<tr>
		<th><?=_getText('Tag') ?></th>
		<th><?=$parse['tag'] ?></th>
	</tr>
	<tr>
		<th><?=_getText('Name') ?></th>
		<th><?=$parse['name'] ?></th>
	</tr>
	<tr>
		<th><?=_getText('Members') ?></th>
		<th><?=$parse['member_scount'] ?></th>
	</tr>
	<? if ($parse['description'] != ''): ?>
		<tr>
			<td class="b" colspan="2" height="100" style="padding:3px;">
				<span id="m1"></span>
				<script type="text/javascript">Text('<?=str_replace(["\r\n", "\n", "\r"], '', stripslashes($parse['description'])) ?>', 'm1');</script>
			</td>
		</tr>
	<? endif; ?>
	<? if ($parse['web'] != ''): ?>
		<tr><th>Сайт альянса:</th><th><a href="<?=$parse['web'] ?>" target="_blank"><?=$parse['web'] ?></a></th></tr>
	<? endif; ?>

	<? if (isset($userId) && $userId != 0 && $parse['request']): ?>
		<tr><th>Вступление</th><th><a href="{{ url('alliance/apply/allyid/'.$parse['id'].'/') }}">Нажмите сюда для подачи заявки</a></th></tr>
	<? endif; ?>
</table>