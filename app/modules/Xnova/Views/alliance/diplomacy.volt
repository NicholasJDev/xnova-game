<table class="table">
	<tr>
		<td class="c" colspan="3">Ваши запросы</td>
	</tr>
	<? if (isset($parse['DMyQuery']) && count($parse['DMyQuery'])): ?>
		<? foreach ($parse['DMyQuery'] as $diplo): ?>
			<tr>
				<th><?=$diplo['name'] ?></th>
				<th><?=_getText('diplomacyStatus', $diplo['type']) ?></th>
				<th>
					<a href="{{ url('alliance/diplomacy/edit/del/id/'.$diplo['id'].'/') }}"><img src="<?=$this->url->getBaseUri() ?>assets/images/abort.gif" alt="Удалить заявку"></a>
				</th>
			</tr>
		<? endforeach; ?>
	<? else: ?>
		<tr><th colspan="3">нет</th></tr>
	<? endif; ?>
</table>
<div class="separator"></div>
<table class="table">
	<tr>
		<td class="c" colspan="3">Запросы вашему альянсу</td>
	</tr>
	<? if (isset($parse['DQuery']) && count($parse['DQuery'])): ?>
		<? foreach ($parse['DQuery'] as $diplo): ?>
			<tr>
				<th><?=$diplo['name'] ?></th>
				<th><?=_getText('diplomacyStatus', $diplo['type']) ?></th>
				<th>
					<a href="{{ url('alliance/diplomacy/edit/suc/id/'.$diplo['id'].'/') }}"><img src="<?=$this->url->getBaseUri() ?>assets/images/appwiz.gif" alt="Подтвердить"></a>
					<a href="{{ url('alliance/diplomacy/edit/del/id/'.$diplo['id'].'/') }}"><img src="<?=$this->url->getBaseUri() ?>assets/images/abort.gif" alt="Удалить заявку"></a>
				</th>
			</tr>
		<? endforeach; ?>
	<? else: ?>
		<tr><th colspan="3">нет</th></tr>
	<? endif; ?>
</table>
<div class="separator"></div>
<table class="table">
	<tr>
		<td class="c" colspan="4">Отношения между альянсами</td>
	</tr>
	<? if (isset($parse['DText']) && count($parse['DText'])): ?>
		<? foreach ($parse['DText'] as $diplo): ?>
			<tr>
				<th><?=$diplo['name'] ?></th>
				<th><?=_getText('diplomacyStatus', $diplo['type']) ?></th>
				<th>
					<a href="{{ url('alliance/diplomacy/edit/del/id/'.$diplo['id'].'/') }}"><img src="<?=$this->url->getBaseUri() ?>assets/images/abort.gif" alt="Удалить заявку"></a>
				</th>
			</tr>
		<? endforeach; ?>
	<? else: ?>
		<tr><th colspan="4">нет</th></tr>
	<? endif; ?>
</table>
<div class="separator"></div>
<form action="{{ url('alliance/diplomacy/edit/add/') }}" method="post">
	<table class="table">
		<tr>
			<td class="c" colspan="2">Добавить альянс в список</td>
		</tr>
		<tr>
			<th>
				<select name="ally" title="">
					<option value="0">список альянсов</option>
					<? foreach ($parse['a_list'] as $item): ?>
						<option value="<?=$item['id'] ?>"><?=$item['name'] ?> [<?=$item['tag'] ?>]</option>
					<? endforeach; ?>
				</select>
			</th>
			<th>
				<select name="status" title="">
					<option value="1">Перемирие</option>
					<option value="2">Мир</option>
					<option value="3">Война</option>
				</select>
			</th>
		</tr>

		<tr>
			<td class="c"><a href="{{ url('alliance/') }}">назад</a></td>
			<td class="c">
				<input type="submit" value="Добавить">
			</td>
		</tr>
	</table>
</form>