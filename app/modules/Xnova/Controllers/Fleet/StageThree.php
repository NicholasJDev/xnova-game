<?php

namespace Xnova\Controllers\Fleet;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Friday\Core\Options;
use Xnova\Controllers\FleetController;
use Xnova\Exceptions\ErrorException;
use Xnova\Exceptions\RedirectException;
use Xnova\Fleet;
use Xnova\Format;
use Xnova\Models;
use Friday\Core\Lang;

class StageThree
{
	public function show (FleetController $controller)
	{
		if ($controller->user->vacation > 0)
			throw new ErrorException("Нет доступа!");

		Lang::includeLang('fleet', 'xnova');

		$galaxy = (int) $controller->request->getPost('galaxy', 'int', 0);
		$system = (int) $controller->request->getPost('system', 'int', 0);
		$planet = (int) $controller->request->getPost('planet', 'int', 0);
		$planet_type = (int) $controller->request->getPost('planet_type', 'int', 0);

		$fleetMission = (int) $controller->request->getPost('mission', 'int', 0);
		$expTime = (int) $controller->request->getPost('expeditiontime', 'int', 0);

		$fleetarray = json_decode(base64_decode(str_rot13($controller->request->getPost('fleet', null, ''))), true);

		if (!$fleetMission)
			throw new RedirectException("<span class=\"error\"><b>Не выбрана миссия!</b></span>", 'Ошибка', "/fleet/", 2);

		if (($fleetMission == 1 || $fleetMission == 6 || $fleetMission == 9 || $fleetMission == 2) && Options::get('disableAttacks', 0) > 0 && time() < Options::get('disableAttacks', 0))
			throw new RedirectException("<span class=\"error\"><b>Посылать флот в атаку временно запрещено.<br>Дата включения атак " . $controller->game->datezone("d.m.Y H ч. i мин.", Options::get('disableAttacks', 0)) . "</b></span>", 'Ошибка');

		$allianceId = (int) $controller->request->getPost('alliance', 'int', 0);

		$fleet_group_mr = 0;

		if ($allianceId > 0)
		{
			if ($fleetMission == 2)
			{
				$aks_count_mr = $controller->db->query("SELECT a.* FROM game_aks a, game_aks_user au WHERE au.aks_id = a.id AND au.user_id = ".$controller->user->id." AND au.aks_id = ".$allianceId."");

				if ($aks_count_mr->numRows() > 0)
				{
					$aks_tr = $aks_count_mr->fetch();

					if ($aks_tr['galaxy'] == $galaxy && $aks_tr['system'] == $system && $aks_tr['planet'] == $planet && $aks_tr['planet_type'] == $planet_type)
						$fleet_group_mr = $allianceId;
				}
			}
		}

		if (($allianceId == 0 || $fleet_group_mr == 0) && ($fleetMission == 2))
			$fleetMission = 1;

		$protection = (int) $controller->config->game->get('noobprotection') > 0;

		if (!is_array($fleetarray))
			throw new RedirectException("<span class=\"error\"><b>Ошибка в передаче параметров!</b></span>", 'Ошибка', "/fleet/", 2);

		foreach ($fleetarray as $Ship => $Count)
		{
			if ($Count > $controller->planet->getUnitCount($Ship))
				throw new RedirectException("<span class=\"error\"><b>Недостаточно флота для отправки на планете!</b></span>", 'Ошибка', "/fleet/", 2);
		}

		if ($planet_type != 1 && $planet_type != 2 && $planet_type != 3 && $planet_type != 5)
			throw new RedirectException("<span class=\"error\"><b>Неизвестный тип планеты!</b></span>", 'Ошибка', "/fleet/", 2);

		if ($controller->planet->galaxy == $galaxy && $controller->planet->system == $system && $controller->planet->planet == $planet && $controller->planet->planet_type == $planet_type)
			throw new RedirectException("<span class=\"error\"><b>Невозможно отправить флот на эту же планету!</b></span>", 'Ошибка', "/fleet/", 2);

		if ($fleetMission == 8)
			$select = $controller->db->query("SELECT id FROM game_planets WHERE galaxy = '" . $galaxy . "' AND system = '" . $system . "' AND planet = '" . $planet . "' AND (planet_type = 1 OR planet_type = 5)");
		else
			$select = $controller->db->query("SELECT id FROM game_planets WHERE galaxy = '" . $galaxy . "' AND system = '" . $system . "' AND planet = '" . $planet . "' AND planet_type = '" . $planet_type . "'");

		if ($fleetMission != 15)
		{
			if ($select->numRows() == 0 && $fleetMission != 7 && $fleetMission != 10)
				throw new RedirectException("<span class=\"error\"><b>Данной планеты не существует!</b> - [".$galaxy.":".$system.":".$planet."]</span>", 'Ошибка #1', "/fleet/", 20);
			elseif ($fleetMission == 9 && $select->numRows() == 0)
				throw new RedirectException("<span class=\"error\"><b>Данной планеты не существует!</b> - [".$galaxy.":".$system.":".$planet."]</span>", 'Ошибка #2', "/fleet/", 20);
			elseif ($select->numRows() == 0 && $fleetMission == 7 && $planet_type != 1)
				throw new RedirectException("<span class=\"error\"><b>Колонизировать можно только планету!</b></span>", 'Ошибка', "/fleet/", 2);
		}
		else
		{
			if ($controller->user->getTechLevel('expedition') >= 1)
			{
				$ExpeditionEnCours = Models\Fleet::count(['owner = ?0 AND mission = ?1', 'bind' => [$controller->user->id, 15]]);
				$MaxExpedition = 1 + floor($controller->user->getTechLevel('expedition') / 3);
			}
			else
			{
				$MaxExpedition = 0;
				$ExpeditionEnCours = 0;
			}

			if ($controller->user->getTechLevel('expedition') == 0)
				throw new RedirectException("<span class=\"error\"><b>Вами не изучена \"Экспедиционная технология\"!</b></span>", 'Ошибка', "/fleet/", 2);
			elseif ($ExpeditionEnCours >= $MaxExpedition)
				throw new RedirectException("<span class=\"error\"><b>Вы уже отправили максимальное количество экспедиций!</b></span>", 'Ошибка', "/fleet/", 2);

			if ($expTime <= 0 || $expTime > (round($controller->user->getTechLevel('expedition') / 2) + 1))
				throw new RedirectException("<span class=\"error\"><b>Вы не можете столько времени летать в экспедиции!</b></span>", 'Ошибка', "/fleet/", 2);
		}

		$planetRow = $select->fetch();

		$targetPlanet = Models\Planet::findFirst((int) $planetRow['id']);

		if (!$targetPlanet)
		{
			$YourPlanet = false;
			$UsedPlanet = false;
		}
		elseif ($targetPlanet->id_owner == $controller->user->id || ($controller->user->ally_id > 0 && $targetPlanet->id_ally == $controller->user->ally_id))
		{
			$YourPlanet = true;
			$UsedPlanet = true;
		}
		else
		{
			$YourPlanet = false;
			$UsedPlanet = true;
		}

		if ($fleetMission == 4 && ($targetPlanet->id_owner == 1 || $controller->user->isAdmin()))
			$YourPlanet = true;

		$missiontype = Fleet::getFleetMissions($fleetarray, [$galaxy, $system, $planet, $planet_type], $YourPlanet, $UsedPlanet, ($fleet_group_mr > 0));

		if (!in_array($fleetMission, $missiontype))
			throw new RedirectException("<span class=\"error\"><b>Миссия неизвестна!</b></span>", 'Ошибка', "/fleet/", 2);

		if ($fleetMission == 8 && $targetPlanet->debris_metal == 0 && $targetPlanet->debris_crystal == 0)
		{
			if ($targetPlanet->debris_metal == 0 && $targetPlanet->debris_crystal == 0)
				throw new RedirectException("<span class=\"error\"><b>Нет обломков для сбора.</b></span>", 'Ошибка', "/fleet/", 2);
		}

		if ($targetPlanet)
		{
			$targerUser = $controller->db->query("SELECT * FROM game_users WHERE id = '" . $targetPlanet->id_owner . "';")->fetch();

			if (!$targerUser)
				throw new RedirectException("<span class=\"error\"><b>Неизвестная ошибка #FLTNFU".$targetPlanet->id_owner."</b></span>", 'Ошибка', "/fleet/", 2);
		}
		else
			$targerUser = $controller->user->toArray();

		if (($targerUser['authlevel'] > 0 && $controller->user->authlevel == 0) && ($fleetMission != 4 && $fleetMission != 3))
			throw new RedirectException("<span class=\"error\"><b>На этого игрока запрещено нападать</b></span>", 'Ошибка', "/fleet/", 2);

		$diplomacy = false;

		if ($controller->user->ally_id != 0 && $targerUser['ally_id'] != 0 && $fleetMission == 1)
		{
			$diplomacy = $controller->db->query("SELECT * FROM game_alliance_diplomacy WHERE (a_id = " . $targerUser['ally_id'] . " AND d_id = " . $controller->user->ally_id . ") AND status = 1")->fetch();

			if ($diplomacy && $diplomacy['type'] < 3)
				throw new RedirectException("<span class=\"error\"><b>Заключён мир или перемирие с альянсом атакуемого игрока.</b></span>", "Ошибка дипломатии", "/fleet/", 2);
		}

		if ($protection && $targetPlanet && in_array($fleetMission, [1, 2, 5, 6, 9]) && $controller->user->authlevel < 2)
		{
			$protectionPoints = (int) $controller->config->game->get('noobprotectionPoints');
			$protectionFactor = (int) $controller->config->game->get('noobprotectionFactor');

			if ($protectionPoints <= 0)
				$protection = false;

			if ($targerUser['onlinetime'] < (time() - 86400 * 7) || $targerUser['banned'] > 0)
				$protection = false;

			if ($fleetMission == 5 && $targerUser['ally_id'] == $controller->user->ally_id)
				$protection = false;

			if ($protection)
			{
				$MyPoints = (int) $controller->db->fetchColumn("SELECT total_points FROM game_statpoints WHERE stat_type = '1' AND stat_code = '1' AND id_owner = :id", ['id' => $controller->user->id]);
				$HePoints = (int) $controller->db->fetchColumn("SELECT total_points FROM game_statpoints WHERE stat_type = '1' AND stat_code = '1' AND id_owner = :id", ['id' => $targerUser['id']]);

				if ($HePoints < $protectionPoints)
					throw new RedirectException("<span class=\"success\"><b>Игрок находится под защитой новичков!</b></span>", 'Защита новичков', "/fleet/", 2);

				if ($protectionFactor && $MyPoints > $HePoints * $protectionFactor)
					throw new RedirectException("<span class=\"success\"><b>Этот игрок слишком слабый для вас!</b></span>", 'Защита новичков', "/fleet/", 2);
			}
		}

		if ($targerUser['vacation'] > 0 && $fleetMission != 8 && !$controller->user->isAdmin())
			throw new RedirectException("<span class=\"success\"><b>Игрок в режиме отпуска!</b></span>", 'Режим отпуска', "/fleet/", 2);

		$flyingFleets = Models\Fleet::count(['owner = ?0', 'bind' => [$controller->user->id]]);

		$maxFleets = $controller->user->getTechLevel('computer') + 1;

		if ($controller->user->rpg_admiral > time())
			$maxFleets += 2;

		if ($maxFleets <= $flyingFleets)
			throw new RedirectException("Все слоты флота заняты. Изучите компьютерную технологию для увеличения кол-ва летящего флота.", "Ошибка", "/fleet/", 2);

		$resources = $controller->request->getPost('resource');
		$resources = array_map('intval', $resources);

		if (array_sum($resources) < 1 && $fleetMission == 3)
			throw new RedirectException("<span class=\"success\"><b>Нет сырья для транспорта!</b></span>", _getText('type_mission', 3), "/fleet/", 2);

		if ($fleetMission != 15)
		{
			if (!$targetPlanet && $fleetMission < 7)
				throw new RedirectException("<span class=\"error\"><b>Планеты не существует!</b></span>", 'Ошибка', "/fleet/", 2);

			if ($targetPlanet && ($fleetMission == 7 || $fleetMission == 10))
				throw new RedirectException("<span class=\"error\"><b>Место занято</b></span>", 'Ошибка', "/fleet/", 2);

			if ($targetPlanet && $targetPlanet->getBuildLevel('ally_deposit') == 0 && $targerUser['id'] != $controller->user->id && $fleetMission == 5)
				throw new RedirectException("<span class=\"error\"><b>На планете нет склада альянса!</b></span>", 'Ошибка', "/fleet/", 2);

			if ($fleetMission == 5)
			{
				$friend = $controller->db->query("SELECT id FROM game_buddy WHERE ((sender = " . $controller->user->id . " AND owner = " . $targerUser['id'] . ") OR (owner = " . $controller->user->id . " AND sender = " . $targerUser['id'] . ")) AND active = 1 LIMIT 1")->fetch();

				if ($targerUser['ally_id'] != $controller->user->ally_id && !isset($friend['id']) && (!$diplomacy || ($diplomacy && $diplomacy['type'] != 2)))
					throw new RedirectException("<span class=\"error\"><b>Нельзя охранять вражеские планеты!</b></span>", 'Ошибка', "/fleet/", 2);
			}

			if ($targetPlanet && $targetPlanet->id_owner == $controller->user->id && ($fleetMission == 1 || $fleetMission == 2))
				throw new RedirectException("<span class=\"error\"><b>Невозможно атаковать самого себя!</b></span>", 'Ошибка', "/fleet/", 2);

			if ($targetPlanet && $targetPlanet->id_owner == $controller->user->id && $fleetMission == 6)
				throw new RedirectException("<span class=\"error\"><b>Невозможно шпионить самого себя!</b></span>", 'Ошибка', "/fleet/", 2);

			if (!$YourPlanet && $fleetMission == 4)
				throw new RedirectException("<span class=\"error\"><b>Выполнение данной миссии невозможно!</b></span>", 'Ошибка', "/fleet/", 2);
		}

		$speedPossible = [10, 9, 8, 7, 6, 5, 4, 3, 2, 1];

		$maxFleetSpeed 		= min(Fleet::GetFleetMaxSpeed($fleetarray, 0, $controller->user));
		$fleetSpeedFactor 	= $controller->request->getPost('speed', 'int', 10);
		$gameFleetSpeed 	= $controller->game->getSpeed('fleet');

		if (!in_array($fleetSpeedFactor, $speedPossible))
			throw new RedirectException("<span class=\"error\"><b>Читеришь со скоростью?</b></span>", 'Ошибка', "/fleet/", 2);

		if (!$planet_type)
			throw new RedirectException("<span class=\"error\"><b>Ошибочный тип планеты!</b></span>", 'Ошибка', "/fleet/", 2);

		$errorlist = "";

		if (!$galaxy || $galaxy > $controller->config->game->maxGalaxyInWorld || $galaxy < 1)
			$errorlist .= _getText('fl_limit_galaxy');

		if (!$system || $system > $controller->config->game->maxSystemInGalaxy || $system < 1)
			$errorlist .= _getText('fl_limit_system');

		if (!$planet || $planet > ($controller->config->game->maxPlanetInSystem + 1) || $planet < 1)
			$errorlist .= _getText('fl_limit_planet');

		if ($errorlist != '')
			throw new RedirectException("<span class=\"error\">" . $errorlist . "</span>", 'Ошибка', "/fleet/", 2);

		if (!isset($fleetarray))
			throw new RedirectException("<span class=\"error\"><b>" . _getText('fl_no_fleetarray') . "</b></span>", 'Ошибка', "/fleet/", 2);

		$fleet = new Models\Fleet();

		$distance 		= Fleet::GetTargetDistance($controller->planet->galaxy, $galaxy, $controller->planet->system, $system, $controller->planet->planet, $planet);
		$duration 		= Fleet::GetMissionDuration($fleetSpeedFactor, $maxFleetSpeed, $distance, $gameFleetSpeed);
		$consumption 	= Fleet::GetFleetConsumption($fleetarray, $gameFleetSpeed, $duration, $distance, $controller->user);

		$fleet_group_time = 0;

		if ($fleet_group_mr > 0)
		{
			// Вычисляем время самого медленного флота в совместной атаке
			$flet = Models\Fleet::find(['column' => 'id, start_time, end_time', 'conditions' => 'group_id = ?0', 'bind' => [$fleet_group_mr]]);

			$fleet_group_time = $duration + time();
			$arrr = [];

			foreach ($flet as $i => $flt)
			{
				if ($flt->start_time > $fleet_group_time)
					$fleet_group_time = $flt->start_time;

				$arrr[$i]['id'] = $flt->id;
				$arrr[$i]['start'] = $flt->start_time;
				$arrr[$i]['end'] = $flt->end_time;
			}
		}

		if ($fleet_group_mr > 0)
			$fleet->start_time = $fleet_group_time;
		else
			$fleet->start_time = $duration + time();

		if ($fleetMission == 15)
		{
			$StayDuration = $expTime * 3600;
			$StayTime = $fleet->start_time + $StayDuration;
		}
		else
		{
			$StayDuration = 0;
			$StayTime = 0;
		}

		$FleetStorage = 0;
		$fleet_array = [];

		foreach ($fleetarray as $Ship => $Count)
		{
			$Count = (int) $Count;
			$FleetStorage += $controller->registry->CombatCaps[$Ship]['capacity'] * $Count;

			$fleet_array[] = [
				'id' => (int) $Ship,
				'count' => $Count
			];

			$controller->planet->setUnit($Ship, -$Count, true);
		}

		$FleetStorage -= $consumption;
		$StorageNeeded = 0;

		if ($resources['metal'] < 1)
			$TransMetal = 0;
		else
		{
			$TransMetal = $resources['metal'];
			$StorageNeeded += $TransMetal;
		}

		if ($resources['crystal'] < 1)
			$TransCrystal = 0;
		else
		{
			$TransCrystal = $resources['crystal'];
			$StorageNeeded += $TransCrystal;
		}

		if ($resources['deuterium'] < 1)
			$TransDeuterium = 0;
		else
		{
			$TransDeuterium = $resources['deuterium'];
			$StorageNeeded += $TransDeuterium;
		}

		$TotalFleetCons = 0;

		if ($fleetMission == 5)
		{
			$holdTime = (int) $controller->request->getPost('holdingtime', 'int', 0);

			if (!in_array($holdTime, [0, 1, 2, 4, 8, 16, 32]))
				$holdTime = 0;

			$FleetStayConsumption = Fleet::GetFleetStay($fleetarray);

			if ($controller->user->rpg_meta > time())
				$FleetStayConsumption = ceil($FleetStayConsumption * 0.9);

			$FleetStayAll = $FleetStayConsumption * $holdTime;

			if ($FleetStayAll >= ($controller->planet->deuterium - $TransDeuterium))
				$TotalFleetCons = $controller->planet->deuterium - $TransDeuterium;
			else
				$TotalFleetCons = $FleetStayAll;

			if ($FleetStorage < $TotalFleetCons)
				$TotalFleetCons = $FleetStorage;

			$FleetStayTime = round(($TotalFleetCons / $FleetStayConsumption) * 3600);

			$StayDuration = $FleetStayTime;
			$StayTime = $fleet->start_time + $FleetStayTime;
		}

		if ($fleet_group_mr > 0)
			$fleet->end_time = $StayDuration + $duration + $fleet_group_time;
		else
			$fleet->end_time = $StayDuration + (2 * $duration) + time();

		$StockMetal 	= $controller->planet->metal;
		$StockCrystal 	= $controller->planet->crystal;
		$StockDeuterium = $controller->planet->deuterium - ($consumption + $TotalFleetCons);

		$StockOk = ($StockMetal >= $TransMetal && $StockCrystal >= $TransCrystal && $StockDeuterium >= $TransDeuterium);

		if (!$StockOk && (!$targetPlanet || $targetPlanet->id_owner != 1))
			throw new RedirectException("<span class=\"error\"><b>" . _getText('fl_noressources') . Format::number($consumption) . "</b></span>", 'Ошибка', "/fleet/", 2);

		if ($StorageNeeded > $FleetStorage && !$controller->user->isAdmin())
			throw new RedirectException("<span class=\"error\"><b>" . _getText('fl_nostoragespa') . Format::number($StorageNeeded - $FleetStorage) . "</b></span>", 'Ошибка', "/fleet/", 2);

		// Баш контроль
		if ($fleetMission == 1)
		{
			$night_time = mktime(0, 0, 0, date('m', time()), date('d', time()), date('Y', time()));

			$log = $controller->db->query("SELECT kolvo FROM game_logs WHERE s_id = '".$controller->user->id."' AND mission = 1 AND e_galaxy = " . $targetPlanet->galaxy . " AND e_system = " . $targetPlanet->system . " AND e_planet = " . $targetPlanet->planet . " AND time > " . $night_time . "")->fetch();

			if (!$controller->user->isAdmin() && isset($log['kolvo']) && $log['kolvo'] > 2 && (($diplomacy && $diplomacy['type'] != 3) || !$diplomacy))
				throw new RedirectException("<span class=\"error\"><b>Баш-контроль. Лимит ваших нападений на планету исчерпан.</b></span>", 'Ошибка', "/fleet/", 2);

			if (isset($log['kolvo']))
				$controller->db->query("UPDATE game_logs SET kolvo = kolvo + 1 WHERE s_id = '".$controller->user->id."' AND mission = 1 AND e_galaxy = " . $targetPlanet->galaxy . " AND e_system = " . $targetPlanet->system . " AND e_planet = " . $targetPlanet->planet . " AND time > " . $night_time . "");
			else
				$controller->db->query("INSERT INTO game_logs VALUES (1, " . time() . ", 1, " . $controller->user->id . ", " . $controller->planet->galaxy . ", " . $controller->planet->system . ", " . $controller->planet->planet . ", " . $targetPlanet->id_owner . ", " . $targetPlanet->galaxy . ", " . $targetPlanet->system . ", " . $targetPlanet->planet . ")");

		}
		//

		// Увод флота
		//$fleets_num = $this->db->query("SELECT id FROM game_fleets WHERE mission = '1' AND end_galaxy = ".$controller->planet->data['galaxy']." AND end_system = ".$controller->planet->data['system']." AND end_planet = ".$controller->planet->data['planet']." AND end_type = ".$controller->planet->data['planet_type']." AND start_time < ".(time() + 5)."");

		//if (db::num_rows($fleets_num) > 0)
		//		message ("<span class=\"error\"><b>Ваш флот не может взлететь из-за находящегося поблизости от орбиты планеты атакующего флота.</b></span>", 'Ошибка', "fleet." . $phpEx, 2);
		//

		if ($fleet_group_mr > 0 && $fleet_group_time > 0 && isset($arrr))
		{
			foreach ($arrr AS $id => $row)
			{
				$end = $fleet_group_time + $row['end'] - $row['start'];

				$controller->db->updateAsDict($fleet->getSource(), ['start_time' => $fleet_group_time, 'end_time' => $end, 'update_time' => $fleet_group_time], 'id = '.$row['id']);
			}
		}

		/*if (($fleetMission == 1 || $fleetMission == 2 || $fleetMission == 3) && $targerUser['id'] != $controller->user->id && !$controller->user->isAdmin())
		{
			$check = $controller->db->fetchColumn("SELECT COUNT(*) as num FROM game_log_ip WHERE id = ".$targerUser['id']." AND time > ".(time() - 86400 * 3)." AND ip IN (SELECT ip FROM game_log_ip WHERE id = ".$controller->user->id." AND time > ".(time() - 86400 * 3).")");

			if ($check > 0 || $targerUser['ip'] == $controller->user->ip)
				throw new RedirectException("<span class=\"error\"><b>Вы не можете посылать флот с миссией \"Транспорт\" и \"Атака\" к игрокам, с которыми были пересечения по IP адресу.</b></span>", 'Ошибка', "/fleet/", 5);
		}*/

		if ($fleetMission == 3 && $targerUser['id'] != $controller->user->id && !$controller->user->isAdmin())
		{
			if ($targerUser['onlinetime'] < (time() - 86400 * 7))
				throw new RedirectException("<span class=\"error\"><b>Вы не можете посылать флот с миссией \"Транспорт\" к неактивному игроку.</b></span>", 'Ошибка', "/fleet/", 5);

			$cnt = $controller->db->fetchColumn("SELECT COUNT(*) as num FROM game_log_transfers WHERE user_id = ".$controller->user->id." AND target_id = ".$targerUser['id']." AND time > ".(time() - 86400 * 7)."");

			if ($cnt >= 3)
				throw new RedirectException("<span class=\"error\"><b>Вы не можете посылать флот с миссией \"Транспорт\" другому игроку чаще 3х раз в неделю.</b></span>", 'Ошибка', "/fleet/", 5);

			$cnt = $controller->db->fetchColumn("SELECT COUNT(*) as num FROM game_log_transfers WHERE user_id = ".$controller->user->id." AND target_id = ".$targerUser['id']." AND time > ".(time() - 86400 * 1)."");

			if ($cnt > 0)
				throw new RedirectException("<span class=\"error\"><b>Вы не можете посылать флот с миссией \"Транспорт\" другому игроку чаще одного раза в день.</b></span>", 'Ошибка', "/fleet/", 5);

			//$equiv = $TransMetal + $TransCrystal * 2 + $TransDeuterium * 4;

			//if ($equiv > 15000000)
			//	throw new RedirectException("<span class=\"error\"><b>Вы не можете посылать флот с миссией \"Транспорт\" другому игроку с количеством ресурсов большим чем 15кк в эквиваленте металла.</b></span>", 'Ошибка', "/fleet/", 5);

			$controller->db->insertAsDict('game_log_transfers', [
				'time' => time(),
				'user_id' => $controller->user->id,
				'data' => json_encode([
					'planet' => [
						'galaxy' => $controller->planet->galaxy,
						'system' => $controller->planet->system,
						'planet' => $controller->planet->planet,
						'type' => $controller->planet->planet_type,
					],
					'target' => [
						'galaxy' => $galaxy,
						'system' => $system,
						'planet' => $planet,
						'type' => $planet_type,
					],
					'fleet' => $fleet_array,
					'resources' => [
						'metal' => $TransMetal,
						'crystal' => $TransCrystal,
						'deuterium' => $TransDeuterium,
					]
				]),
				'target_id' => $targetPlanet->id_owner
			]);

			$str_error = "Информация о передаче ресурсов добавлена в журнал оператора.<br>";
		}

		if (false && $targetPlanet && $targetPlanet->id_owner == 1)
		{
			$fleet->start_time = time() + 30;
			$fleet->end_time = time() + 60;

			$consumption = 0;
		}

		if (false && $controller->user->isAdmin() && $fleetMission != 6)
		{
			$fleet->start_time 	= time() + 15;
			$fleet->end_time 	= time() + 30;

			if ($StayTime)
				$StayTime = $fleet->start_time + 5;

			$consumption = 0;
		}

		$tutorial = $controller->db->query("SELECT id, quest_id FROM game_users_quests WHERE user_id = ".$controller->user->getId()." AND finish = '0' AND stage = 0")->fetch();

		if (isset($tutorial['id']))
		{
			Lang::includeLang('tutorial', 'xnova');

			$quest = _getText('tutorial', $tutorial['quest_id']);

			foreach ($quest['TASK'] AS $taskKey => $taskVal)
			{
				if ($taskKey == 'FLEET_MISSION' && $taskVal == $fleetMission)
				{
					$controller->db->query("UPDATE game_users_quests SET stage = 1 WHERE id = " . $tutorial['id'] . ";");
				}
			}
		}

		if ($fleetMission == 1)
		{
			$raunds = $controller->request->getPost('raunds', 'int', 6);
			$raunds = max(min(10, $raunds), 6);
		}
		else
			$raunds = 0;

		$fleet->create([
			'owner' 				=> $controller->user->id,
			'owner_name' 			=> $controller->planet->name,
			'mission' 				=> $fleetMission,
			'fleet_array' 			=> $fleet_array,
			'start_galaxy' 			=> $controller->planet->galaxy,
			'start_system' 			=> $controller->planet->system,
			'start_planet' 			=> $controller->planet->planet,
			'start_type' 			=> $controller->planet->planet_type,
			'end_stay' 				=> $StayTime,
			'end_galaxy' 			=> $galaxy,
			'end_system' 			=> $system,
			'end_planet' 			=> $planet,
			'end_type' 				=> $planet_type,
			'resource_metal' 		=> $TransMetal,
			'resource_crystal' 		=> $TransCrystal,
			'resource_deuterium' 	=> $TransDeuterium,
			'target_owner' 			=> ($targetPlanet ? $targetPlanet->id_owner : 0),
			'target_owner_name' 	=> ($targetPlanet ? $targetPlanet->name : ''),
			'group_id' 				=> $fleet_group_mr,
			'raunds' 				=> $raunds,
			'create_time' 			=> time(),
			'update_time' 			=> $fleet->start_time
		]);

		$controller->planet->metal 		-= $TransMetal;
		$controller->planet->crystal 	-= $TransCrystal;
		$controller->planet->deuterium 	-= $TransDeuterium + $consumption + $TotalFleetCons;

		$controller->planet->update();

		$html  = "<table class=\"table\">";
		$html .= "<tr>";
		$html .= "<td class=\"c\" colspan=\"2\"><span class=\"success\">" . ((isset($str_error)) ? $str_error : _getText('fl_fleet_send')) . "</span></td>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_mission') . "</th>";
		$html .= "<th>" . _getText('type_mission', $fleetMission) . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_dist') . "</th>";
		$html .= "<th>" . Format::number($distance) . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_speed') . "</th>";
		$html .= "<th>" . Format::number($maxFleetSpeed) . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_deute_need') . "</th>";
		$html .= "<th>" . Format::number($consumption) . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_from') . "</th>";
		$html .= "<th>" . $controller->planet->galaxy . ":" . $controller->planet->system . ":" . $controller->planet->planet . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_dest') . "</th>";
		$html .= "<th>" . $galaxy . ":" . $system . ":" . $planet . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_time_go') . "</th>";
		$html .= "<th>" . $controller->game->datezone("d H:i:s", $fleet->start_time) . "</th>";
		$html .= "</tr><tr>";
		$html .= "<th>" . _getText('fl_time_back') . "</th>";
		$html .= "<th>" . $controller->game->datezone("d H:i:s", $fleet->end_time) . "</th>";
		$html .= "</tr><tr>";
		$html .= "<td class=\"c\" colspan=\"2\">" . _getText('fl_title') . "</td>";

		foreach ($fleetarray as $Ship => $Count)
		{
			$html .= "</tr><tr>";
			$html .= "<th>" . _getText('tech', $Ship) . "</th>";
			$html .= "<th>" . Format::number($Count) . "</th>";
		}

		$html .= "</tr></table>";

		throw new RedirectException($html, '', '/fleet/', 3);
	}
}