<?php
namespace App\Controllers\Fleet;

use App\Controllers\FleetController;
use App\Fleet;
use App\Lang;
use App\Sql;

class StageTwo
{
	public function show (FleetController $controller)
	{
		if ($controller->user->vacation > 0)
			$controller->message("Нет доступа!");

		if (!isset($_POST['crc']) || ($_POST['crc'] != md5($controller->user->id . '-CHeAT_CoNTROL_Stage_02-' . date("dmY", time()) . '-' . $_POST["usedfleet"])))
			$controller->message('Ошибка контрольной суммы!');

		Lang::includeLang('fleet');

		if (isset($_POST['moon']) && intval($_POST['moon']) != $controller->planet->id && ($controller->planet->planet_type == 3 || $controller->planet->planet_type == 5) && $controller->planet->sprungtor > 0)
		{
			$RestString = GetNextJumpWaitTime($controller->planet->toArray());
			$NextJumpTime = $RestString['value'];
			$JumpTime = time();

			if ($NextJumpTime == 0)
			{
				$TargetPlanet = intval($_POST['moon']);
				$TargetGate = $controller->db->query("SELECT `id`, `planet_type`, `sprungtor`, `last_jump_time` FROM game_planets WHERE `id` = '" . $TargetPlanet . "';")->fetch();

				if (($TargetGate['planet_type'] == 3 || $TargetGate['planet_type'] == 5) && $TargetGate['sprungtor'] > 0)
				{
					$RestString = GetNextJumpWaitTime($TargetGate);

					if ($RestString['value'] == 0)
					{
						$ShipArray = array();
						$SubQueryOri = array();
						$SubQueryDes = array();

						foreach ($controller->game->reslist['fleet'] AS $Ship)
						{
							$ShipLabel = "ship" . $Ship;

							if (!isset($_POST[$ShipLabel]) || !is_numeric($_POST[$ShipLabel]) || intval($_POST[$ShipLabel]) < 0)
								continue;

							if (abs(intval($_POST[$ShipLabel])) > $controller->planet->{$controller->game->resource[$Ship]})
								$ShipArray[$Ship] = $controller->planet->{$controller->game->resource[$Ship]};
							else
								$ShipArray[$Ship] = abs(intval($_POST[$ShipLabel]));

							if ($ShipArray[$Ship] != 0)
							{
								$SubQueryOri['-'.$controller->game->resource[$Ship]] = $ShipArray[$Ship];
								$SubQueryDes['+'.$controller->game->resource[$Ship]] = $ShipArray[$Ship];
							}
						}

						if (count($SubQueryOri))
						{
							$SubQueryOri['last_jump_time'] = $JumpTime;
							$SubQueryDes['last_jump_time'] = $JumpTime;

							Sql::build()->update('game_planets')->set($SubQueryOri)->where('id', '=', $controller->planet->id)->execute();
							Sql::build()->update('game_planets')->set($SubQueryDes)->where('id', '=', $TargetGate['id'])->execute();

							Sql::build()->update('game_users')->setField('current_planet', $TargetGate['id'])->where('id', '=', $controller->user->id)->execute();

							$controller->planet->last_jump_time = $JumpTime;
							$RestString = GetNextJumpWaitTime($controller->planet->toArray());

							$RetMessage = _getText('gate_jump_done') . " - " . $RestString['string'];
						}
						else
							$RetMessage = _getText('gate_wait_data');
					}
					else
						$RetMessage = _getText('gate_wait_dest') . " - " . $RestString['string'];
				}
				else
					$RetMessage = _getText('gate_no_dest_g');
			}
			else
				$RetMessage = _getText('gate_wait_star') . " - " . $RestString['string'];

			$controller->message($RetMessage, 'Результат', "?set=fleet", 5);
		}

		$parse = array();

		$galaxy = $controller->request->getPost('galaxy', 'int', 0);
		$system = $controller->request->getPost('system', 'int', 0);
		$planet = $controller->request->getPost('planet', 'int', 0);
		$type 	= $controller->request->getPost('planet_type', 'int', 0);
		$acs 	= $controller->request->getPost('acs', 'int', 0);

		$fleetmission 	= $controller->request->getPost('target_mission', 'int', 0);
		$fleetarray 	= json_decode(base64_decode(str_rot13($controller->request->getPost('usedfleet', null, ''))), true);

		$YourPlanet = false;
		$UsedPlanet = false;

		$TargetPlanet = $controller->db->query("SELECT * FROM game_planets WHERE `galaxy` = '" . $galaxy . "' AND `system` = '" . $system . "' AND `planet` = '" . $planet . "' AND `planet_type` = '" . $type . "'")->fetch();

		if ($galaxy == $TargetPlanet['galaxy'] && $system == $TargetPlanet['system'] && $planet == $TargetPlanet['planet'] && $type == $TargetPlanet['planet_type'])
		{
			if ($TargetPlanet['id_owner'] == $controller->user->id || ($this->$controller->ally_id > 0 && $TargetPlanet['id_ally'] == $controller->user->ally_id))
			{
				$YourPlanet = true;
				$UsedPlanet = true;
			}
			else
				$UsedPlanet = true;
		}

		$missiontype = Fleet::getFleetMissions($fleetarray, Array($galaxy, $system, $planet, $type), $YourPlanet, $UsedPlanet, ($acs > 0));

		if ($TargetPlanet['id_owner'] == 1 || $controller->user->isAdmin())
			$missiontype[4] = _getText('type_mission', 4);

		$SpeedFactor = $controller->game->getSpeed('fleet');
		$AllFleetSpeed = Fleet::GetFleetMaxSpeed($fleetarray, 0, $controller->user);
		$GenFleetSpeed = intval($_POST['speed']);
		$MaxFleetSpeed = min($AllFleetSpeed);

		$distance = Fleet::GetTargetDistance($controller->planet->galaxy, $_POST['galaxy'], $controller->planet->system, $_POST['system'], $controller->planet->planet, $_POST['planet']);
		$duration = Fleet::GetMissionDuration($GenFleetSpeed, $MaxFleetSpeed, $distance, $SpeedFactor);
		$consumption = Fleet::GetFleetConsumption($fleetarray, $SpeedFactor, $duration, $distance, $controller->user);

		$stayConsumption = Fleet::GetFleetStay($fleetarray, $controller->getDI());

		if ($controller->user->rpg_meta > time())
			$stayConsumption = ceil($stayConsumption * 0.9);

		$parse['missions_selected'] = array();
		$parse['missions'] = array();

		if (count($missiontype) > 0)
		{
			$i = 0;

			foreach ($missiontype as $a => $b)
			{
				if (($fleetmission > 0 && $fleetmission == $a) || (!isset($missiontype[$fleetmission]) && $i == 0) || count($missiontype) == 1)
					$parse['missions_selected'] = $a;

				$parse['missions'][$a] = $b;

				$i++;
			}
		}

		$parse['thisresource1'] = floor($controller->planet->metal);
		$parse['thisresource2'] = floor($controller->planet->crystal);
		$parse['thisresource3'] = floor($controller->planet->deuterium);
		$parse['consumption'] = $consumption;
		$parse['stayConsumption'] = $stayConsumption;
		$parse['dist'] = $distance;
		$parse['acs'] = $acs;
		$parse['thisgalaxy'] = $controller->planet->galaxy;
		$parse['thissystem'] = $controller->planet->system;
		$parse['thisplanet'] = $controller->planet->planet;
		$parse['galaxy'] = $_POST["galaxy"];
		$parse['system'] = $_POST["system"];
		$parse['planet'] = $_POST["planet"];
		$parse['planettype'] = $_POST["planettype"];
		$parse['speed'] = $_POST["speed"];
		$parse['usedfleet'] = $_POST["usedfleet"];
		$parse['maxepedition'] = $_POST["maxepedition"];
		$parse['curepedition'] = $_POST["curepedition"];
		$parse['crc'] = md5($controller->user->id . '-CHeAT_CoNTROL_Stage_03-' . date("dmY", time()) . '-' . $_POST["usedfleet"]);

		$parse['ships'] = array();

		foreach ($fleetarray as $i => $count)
		{
			$ship = array
			(
				'id' => $i,
				'count' => $count,
				'consumption' => Fleet::GetShipConsumption($i, $controller->user),
				'speed' => Fleet::GetFleetMaxSpeed("", $i, $controller->user),
				'stay' => $controller->game->CombatCaps[$i]['stay'],
			);

			if (isset($controller->user->{'fleet_' . $i}) && isset($controller->game->CombatCaps[$i]['power_consumption']) && $controller->game->CombatCaps[$i]['power_consumption'] > 0)
				$ship['capacity'] = round($controller->game->CombatCaps[$i]['capacity'] * (1 + $controller->user->{'fleet_' . $i} * ($controller->game->CombatCaps[$i]['power_consumption'] / 100)));
			else
				$ship['capacity'] = $controller->game->CombatCaps[$i]['capacity'];

			$parse['ships'][] = $ship;
		}

		if (isset($missiontype[15]))
			$parse['expedition_hours'] = round($controller->user->{$controller->game->resource[124]} / 2) + 1;

		$controller->view->setVar('parse', $parse);
		$controller->tag->setTitle(_getText('fl_title_2'));
	}
}

?>