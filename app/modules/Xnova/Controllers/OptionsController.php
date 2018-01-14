<?php

namespace Xnova\Controllers;

/**
 * @author AlexPro
 * @copyright 2008 - 2016 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Friday\Core\Options;
use Xnova\Exceptions\ErrorException;
use Xnova\Exceptions\RedirectException;
use Xnova\Format;
use Xnova\Helpers;
use Friday\Core\Lang;
use PHPMailer\PHPMailer\PHPMailer;
use Xnova\Models\Fleet;
use Xnova\Models\Planet;
use Xnova\User;
use Xnova\Queue;
use Xnova\Controller;

/**
 * @RoutePrefix("/options")
 * @Route("/")
 * @Route("/{action}/")
 * @Route("/{action}{params:(/.*)*}")
 * @Private
 */
class OptionsController extends Controller
{
	public function initialize ()
	{
		parent::initialize();
		
		if ($this->dispatcher->wasForwarded())
			return;

		Lang::includeLang('options', 'xnova');
	}

	public function externalAction ()
	{
		if (isset($_REQUEST['token']) && $_REQUEST['token'] != '')
		{
			$s = file_get_contents('http://u-login.com/token.php?token=' . $_REQUEST['token'] . '&host=' . $_SERVER['HTTP_HOST']);
			$data = json_decode($s, true);

			if (isset($data['identity']))
			{
				$identity = isset($data['profile']) && $data['profile'] != '' ? $data['profile'] : $data['identity'];

				$check = $this->db->query("SELECT user_id FROM game_users_auth WHERE external_id = '".$identity."'")->fetch();

				if (!isset($check['user_id']))
					$this->db->insertAsDict('game_users_auth', ['user_id' => $this->user->getId(), 'external_id' => $identity, 'create_time' => time()]);
				else
					throw new RedirectException('Данная точка входа уже используется', 'Ошибка', '/options/');
			}
			else
				throw new RedirectException('Ошибка получения данных', 'Ошибка', '/options/');
		}

		$this->response->redirect('options/');
	}

	public function emailAction ()
	{
		$inf = $this->db->query("SELECT * FROM game_users_info WHERE id = " . $this->user->id . "")->fetch();

		if (isset($_POST['db_password']) && isset($_POST['email']))
		{
			if (md5($_POST["db_password"]) != $inf["password"])
				throw new RedirectException('Heпpaвильный тeкyщий пapoль', 'Hacтpoйки', '/options/email/', 3);
			else
			{
				$email = $this->db->query("SELECT user_id FROM game_log_email WHERE user_id = " . $this->user->id . " AND ok = 0;")->fetch();

				if (isset($email['user_id']))
					throw new RedirectException('Заявка была отправлена ранее и ожидает модерации.', 'Hacтpoйки', '/options/', 3);
				else
				{
					$email = $this->db->query("SELECT id FROM game_users_info WHERE email = '" . addslashes(htmlspecialchars(trim($_POST['email']))) . "';")->fetch();

					if (!isset($email['id']))
					{
						$this->db->query("INSERT INTO game_log_email VALUES (" . $this->user->id . ", " . time() . ", '" . addslashes(htmlspecialchars($_POST['email'])) . "', 0);");

						User::sendMessage(1, false, time(), 4, $this->user->username, 'Поступила заявка на смену Email от '.$this->user->username.' на '.addslashes(htmlspecialchars($_POST['email'])).'. <a href="/admin/email/">Сменить</a>');

						throw new RedirectException('Заявка отправлена на рассмотрение', 'Hacтpoйки', '/options/', 3);
					}
					else
						throw new RedirectException('Данный email уже используется в игре.', 'Hacтpoйки', '/options/', 3);
				}
			}
		}

		$this->tag->setTitle('Hacтpoйки');
		$this->showTopPanel(false);
	}

	public function changeAction ()
	{
		if (isset($_POST['ld']) && $_POST['ld'] != '')
		{
			$this->ld();
		}

		$inf = $this->db->query("SELECT * FROM game_users_info WHERE id = " . $this->user->id . "")->fetch();

		if (isset($_POST["db_character"]) && trim($_POST["db_character"]) != '' && trim($_POST["db_character"]) != $this->user->username && mb_strlen(trim($_POST["db_character"]), 'UTF-8') > 3)
		{
			$_POST["db_character"] = preg_replace("/([\s\x{0}\x{0B}]+)/iu", " ", trim($_POST["db_character"]));

			if (preg_match("/^[А-Яа-яЁёa-zA-Z0-9_\-\!\~\.@ ]+$/u", $_POST['db_character']))
				$username = addslashes($_POST['db_character']);
			else
				$username = $this->user->username;
		}
		else
			$username = $this->user->username;

		if (isset($_POST['email']) && !is_email($inf['email']) && is_email($_POST['email']))
		{
			$e = addslashes(htmlspecialchars(trim($_POST['email'])));

			$email = $this->db->query("SELECT id FROM game_users_info WHERE email = '" . $e . "';")->fetch();

			if (!isset($email['id']))
			{
				$password = Helpers::randomSequence();

				$this->db->updateAsDict('game_users_info', ['email' => $e, 'password' => md5($password)], 'id = '.$this->user->getId());

				$mail = new PHPMailer();

				$mail->isMail();
				$mail->isHTML(true);
				$mail->CharSet = 'utf-8';
				$mail->setFrom(Options::get('email_notify'), Options::get('site_title'));
				$mail->addAddress($e, Options::get('site_title'));
				$mail->Subject = 'Пароль в Xnova Game: '.$this->config->game->universe.' вселенная';
				$mail->Body = "Ваш пароль от игрового аккаунта '" . $this->user->username . "': " . $password;
				$mail->send();

				throw new ErrorException('Ваш пароль от аккаунта: '.$password.'. Обязательно смените его на другой в настройках игры. Копия пароля отправлена на указанный вами электронный почтовый ящик.', 'Предупреждение');
			}
			else
				throw new RedirectException('Данный email уже используется в игре.', 'Hacтpoйки', '/options/', 3);
		}

		if ($this->user->vacation > time())
		{
			$vacation = $this->user->vacation;
		}
		else
		{
			$vacation = 0;

			if (isset($_POST["urlaubs_modus"]) && $_POST["urlaubs_modus"] == 'on')
			{
				$queueManager = new Queue();
				$queueCount = 0;

				$BuildOnPlanets = Planet::find(['columns' => 'queue', 'conditions' => 'id_owner = ?0', 'bind' => [$this->user->id]]);

				foreach ($BuildOnPlanets as $BuildOnPlanet)
				{
					$queueManager->loadQueue($BuildOnPlanet->queue);

					$queueCount += $queueManager->getCount();
				}

				$UserFlyingFleets = Fleet::count(['owner = ?0', 'bind' => [$this->user->id]]);

				if ($queueCount > 0)
					throw new RedirectException('Heвoзмoжнo включить peжим oтпycкa. Для включeния y вac нe дoлжнo идти cтpoитeльcтвo или иccлeдoвaниe нa плaнeтe. Строится: '.$queueCount.' объектов.', "Oшибкa", "/overview/", 5);
				elseif ($UserFlyingFleets > 0)
					throw new RedirectException('Heвoзмoжнo включить peжим oтпycкa. Для включeния y вac нe дoлжeн нaxoдитьcя флoт в пoлeтe.', "Oшибкa", "/overview/", 5);
				else
				{
					if ($this->user->vacation == 0)
						$vacation = time() + $this->config->game->get('vocationModeTime', 172800);
					else
						$vacation = $this->user->vacation;

					$this->db->query("UPDATE game_planets SET metal_mine_porcent = '0', crystal_mine_porcent = '0', deuterium_mine_porcent = '0', solar_plant_porcent = '0', fusion_plant_porcent = '0', solar_satelit_porcent = '0' WHERE id_owner = '" . $this->user->id . "'");
				}
			}
		}

		$Del_Time = (isset($_POST["db_deaktjava"]) && $_POST["db_deaktjava"] == 'on') ? (time() + 604800) : 0;

		if (!$this->user->isVacation())
		{
			$sex = ($this->request->getPost('sex', 'string', 'M') == 'F') ? 2 : 1;

			$color = $this->request->getPost('color', 'int', 1);
			if ($color < 1 || $color > 13)
				$color = 1;

			$timezone = $this->request->getPost('timezone', 'int', 0);
			if ($timezone < -32 || $timezone > 16)
				$timezone = 0;

			$SetSort = $this->request->getPost('settings_sort', 'int', 0);
			$SetOrder = $this->request->getPost('settings_order', 'int', 0);
			$about = Format::text($this->request->getPost('text', 'string', ''));
			$spy = $this->request->getPost('spy', 'int', 1);

			if ($spy < 1 || $spy > 1000)
				$spy = 1;

			$options = $this->user->getUserOption();
			$options['records'] 		= (isset($_POST["records"]) && $_POST["records"] == 'on') ? 1 : 0;
			$options['security'] 		= (isset($_POST["security"]) && $_POST["security"] == 'on') ? 1 : 0;
			$options['bb_parser'] 		= (isset($_POST["bbcode"]) && $_POST["bbcode"] == 'on') ? 1 : 0;
			$options['ajax_navigation'] = (isset($_POST["ajaxnav"]) && $_POST["ajaxnav"] == 'on') ? 1 : 0;
			$options['gameactivity'] 	= (isset($_POST["gameactivity"]) && $_POST["gameactivity"] == 'on') ? 1 : 0;
			$options['planetlist']		= (isset($_POST["planetlist"]) && $_POST["planetlist"] == 'on') ? 1 : 0;
			$options['planetlistselect']= (isset($_POST["planetlistselect"]) && $_POST["planetlistselect"] == 'on') ? 1 : 0;
			$options['only_available']	= (isset($_POST["available"]) && $_POST["available"] == 'on') ? 1 : 0;

			$this->db->query("UPDATE game_users SET options = '".$this->user->packOptions($options)."', sex = '" . $sex . "', vacation = '" . $vacation . "', deltime = '" . $Del_Time . "' WHERE id = '" . $this->user->id . "'");

			$update = [];

			if ($SetSort != $inf['planet_sort'])
				$update['planet_sort'] = $SetSort;

			if ($SetOrder != $inf['planet_sort_order'])
				$update['planet_sort_order'] = $SetOrder;

			if ($color != $inf['color'])
				$update['color'] = $color;

			if ($timezone != $inf['timezone'])
				$update['timezone'] = $timezone;

			if ($about != $inf['about'])
				$update['about'] = $about;

			if ($spy != $inf['spy'])
				$update['spy'] = $spy;

			if (count($update))
				$this->db->updateAsDict('game_users_info', $update, 'id = '.$this->user->id);

			$this->session->remove('config');
			$this->cache->delete('app::planetlist_'.$this->user->getId());
		}
		else
			$this->db->query("UPDATE game_users SET vacation = '" . $vacation . "', deltime = '" . $Del_Time . "' WHERE id = '" . $this->user->id . "' LIMIT 1");

		if (isset($_POST["db_password"]) && $_POST["db_password"] != "" && $_POST["newpass1"] != "")
		{
			if (md5($_POST["db_password"]) != $inf["password"])
				throw new RedirectException('Heпpaвильный тeкyщий пapoль', 'Cмeнa пapoля', '/options/', 3);
			elseif ($_POST["newpass1"] == $_POST["newpass2"])
			{
				$newpass = md5($_POST["newpass1"]);
				$this->db->query("UPDATE game_users_info SET password = '" . $newpass . "' WHERE id = '" . $this->user->id . "' LIMIT 1");

				$this->auth->remove(false);

				throw new RedirectException('Уcпeшнo', 'Cмeнa пapoля', '/', 2);
			}
			else
				throw new RedirectException('Bвeдeнныe пapoли нe coвпaдaют', 'Cмeнa пapoля', '/options/', 3);
		}

		if ($this->user->username != $username)
		{
			if ($inf['username_last'] > (time() - 86400))
			{
				throw new RedirectException('Смена игрового имени возможна лишь раз в сутки.', 'Cмeнa имeни', '/options/', 3);
			}
			else
			{
				$query = $this->db->query("SELECT id FROM game_users WHERE username = '" . $username . "'");
				if ($query->numRows() == 0)
				{
					if (preg_match("/^[a-zA-Za-яA-Я0-9_\.\,\-\!\?\*\ ]+$/u", $username) && mb_strlen($username, 'UTF-8') >= 5)
					{
						$this->db->query("UPDATE game_users SET username = '" . $username . "' WHERE id = '" . $this->user->id . "' LIMIT 1");
						$this->db->query("UPDATE game_users_info SET username_last = '" . time() . "' WHERE id = '" . $this->user->id . "' LIMIT 1");
						$this->db->query("INSERT INTO game_log_username VALUES (" . $this->user->id . ", " . time() . ", '" . $username . "');");

						throw new RedirectException('Уcпeшнo', 'Cмeнa имeни', '/', 2);
					}
					else
						throw new RedirectException('Дaннoe имя aккayнтa cлишкoм кopoткoe или имeeт зaпpeщeнныe cимвoлы', 'Cмeнa имeни', '/options/', 3);
				}
				else
					throw new RedirectException('Дaннoe имя aккayнтa yжe иcпoльзyeтcя в игpe', 'Cмeнa имeни', '/options/', 3);
			}
		}

		throw new RedirectException(_getText('succeful_save'), "Hacтpoйки игpы", '/options/', 3);
	}

	private function ld ()
	{
		if (!isset($_POST['ld']) || $_POST['ld'] == '')
			throw new RedirectException('Ввведите текст сообщения', 'Ошибка', '/options/', 3);
		else
		{
			$this->db->query("INSERT INTO game_private (u_id, text, time) VALUES (" . $this->user->id . ", '" . addslashes(htmlspecialchars($_POST['ld'])) . "', " . time() . ")");
			
			throw new RedirectException('Запись добавлена в личное дело', 'Успешно', '/options/', 3);
		}
	}
	
	public function indexAction ()
	{
		$inf = $this->db->query("SELECT * FROM game_users_info WHERE id = " . $this->user->id . "")->fetch();

		$parse = [];

		if ($this->user->vacation > 0)
		{
			$parse['um_end_date'] = $this->game->datezone("d.m.Y H:i:s", $this->user->vacation);
			$parse['opt_delac_data'] = ($this->user->deltime > 0) ? " checked='checked'/" : '';
			$parse['opt_modev_data'] = ($this->user->vacation > 0) ? " checked='checked'/" : '';
			$parse['opt_usern_data'] = $this->user->username;

			$this->view->pick('options/vacation');
		}
		else
		{
			$parse['opt_lst_ord_data'] = "<option value =\"0\"" . (($inf['planet_sort'] == 0) ? " selected" : "") . ">" . _getText('opt_lst_ord0') . "</option>";
			$parse['opt_lst_ord_data'] .= "<option value =\"1\"" . (($inf['planet_sort'] == 1) ? " selected" : "") . ">" . _getText('opt_lst_ord1') . "</option>";
			$parse['opt_lst_ord_data'] .= "<option value =\"2\"" . (($inf['planet_sort'] == 2) ? " selected" : "") . ">" . _getText('opt_lst_ord2') . "</option>";
			$parse['opt_lst_ord_data'] .= "<option value =\"3\"" . (($inf['planet_sort'] == 3) ? " selected" : "") . ">Типу</option>";

			$parse['opt_lst_cla_data'] = "<option value =\"0\"" . (($inf['planet_sort_order'] == 0) ? " selected" : "") . ">" . _getText('opt_lst_cla0') . "</option>";
			$parse['opt_lst_cla_data'] .= "<option value =\"1\"" . (($inf['planet_sort_order'] == 1) ? " selected" : "") . ">" . _getText('opt_lst_cla1') . "</option>";

			$parse['avatar'] = '';

			if ($inf['image'] != '')
				$parse['avatar'] = "<img src='".$this->url->getBaseUri()."assets/avatars/".$inf['image']."' height='100'><br>";
			elseif ($this->user->avatar != 0)
			{
				if ($this->user->avatar != 99)
					$parse['avatar'] = "<img src='".$this->url->getBaseUri()."assets/images/faces/" . $this->user->sex . "/" . $this->user->avatar . "s.png' height='100'><br>";
				else
					$parse['avatar'] = "<img src='".$this->url->getBaseUri()."assets/avatars/upload_" . $this->user->id . ".jpg' height='100'><br>";
			}

			$parse['opt_usern_datatime'] = $inf['username_last'];
			$parse['opt_usern_data'] = $this->user->username;
			$parse['opt_mail_data'] = $inf['email'];
			$parse['opt_sec_data'] = ($this->user->getUserOption('security') == 1) ? " checked='checked'" : '';
			$parse['opt_record_data'] = ($this->user->getUserOption('records') == 1) ? " checked='checked'" : '';
			$parse['opt_bbcode_data'] = ($this->user->getUserOption('bb_parser') == 1) ? " checked='checked'/" : '';
			$parse['opt_ajax_data'] = ($this->user->getUserOption('ajax_navigation') == 1) ? " checked='checked'/" : '';
			$parse['opt_gameactivity_data'] = ($this->user->getUserOption('gameactivity') == 1) ? " checked='checked'/" : '';
			$parse['opt_planetlist_data'] = ($this->user->getUserOption('planetlist') == 1) ? " checked='checked'/" : '';
			$parse['opt_planetlistselect_data'] = ($this->user->getUserOption('planetlistselect') == 1) ? " checked='checked'/" : '';
			$parse['opt_available_data'] = ($this->user->getUserOption('only_available') == 1) ? " checked='checked'/" : '';
			$parse['opt_delac_data'] = ($this->user->deltime > 0) ? " checked='checked'/" : '';
			$parse['opt_modev_data'] = ($this->user->vacation > 0) ? " checked='checked'/" : '';
			$parse['sex'] = $this->user->sex;
			$parse['about'] = $inf['about'];
			$parse['timezone'] = $inf['timezone'];
			$parse['spy'] = $inf['spy'];
			$parse['color'] = $inf['color'];

			$parse['auth'] = $this->db->extractResult($this->db->query("SELECT * FROM game_users_auth WHERE user_id = ".$this->user->getId().""));

			$parse['bot_auth'] = $this->db->fetchOne('SELECT * FROM bot_requests WHERE user_id = '.$this->user->getId().'');
		}

		$this->view->setVar('parse', $parse);
		$this->tag->setTitle('Hacтpoйки');
		$this->showTopPanel(false);
	}
}