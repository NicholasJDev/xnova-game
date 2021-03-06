<?php

namespace Xnova\Controllers;

/**
 * @author AlexPro
 * @copyright 2008 - 2018 XNova Game Group
 * Telegram: @alexprowars, Skype: alexprowars, Email: alexprowars@gmail.com
 */

use Xnova\CombatReport;
use Xnova\Controller;
use Xnova\Exceptions\ErrorException;
use Xnova\Exceptions\RedirectException;

/**
 * @RoutePrefix("/rw")
 * @Route("/")
 * @Private
 */
class RwController extends Controller
{
	public function initialize ()
	{
		parent::initialize();

		if ($this->dispatcher->wasForwarded())
			return;
	}

	/**
	 * @Route("/{id:[0-9]+}/{k:[a-z0-9]+}{params:(/.*)*}")
	 */
	public function indexAction ()
	{
		if (!$this->request->hasQuery('id'))
			throw new ErrorException('Боевой отчет не найден');

		$raportrow = $this->db->query("SELECT * FROM game_rw WHERE `id` = '" . $this->request->getQuery('id', 'int') . "'")->fetch();

		if (!isset($raportrow['id']))
			throw new RedirectException('Данный боевой отчет удалён с сервера', 'Ошибка', '', 0);

		$user_list = json_decode($raportrow['id_users'], true);
		
		if (isset($raportrow['id']) && !$this->user->isAdmin() && (!isset($_GET['k']) ||  md5($this->config->application->encryptKey.$raportrow['id']) != $_GET['k']))
			throw new RedirectException('Не правильный ключ', 'Ошибка', '', 0);
		elseif (!in_array($this->user->id, $user_list) && !$this->user->isAdmin())
			throw new RedirectException('Вы не можете просматривать этот боевой доклад', 'Ошибка', '', 0);
		else
		{
			if ($this->request->isAjax() && $this->auth->isAuthorized())
			{
				$Page = "";

				if ($user_list[0] == $this->user->id && $raportrow['no_contact'] == 1 && !$this->user->isAdmin())
					$Page .= "Контакт с вашим флотом потерян.<br>(Ваш флот был уничтожен в первой волне атаки.)";
				else
				{
					$result = json_decode($raportrow['raport'], true);

					$report = new CombatReport($result[0], $result[1], $result[2], $result[3], $result[4], $result[5]);
					$formatted_cr = $report->report();

					$Page .= $formatted_cr['html'];
				}
		
				$Page .= "<div class='separator'></div><<div class='text-center'>ID боевого доклада: <a href=\"".$this->url->get('log/new/')."?code=" . md5($this->config->application->encryptKey.$raportrow['id']) . $raportrow['id'] . "/\"><font color=red>" . md5('xnovasuka' . $raportrow['id']) . $raportrow['id'] . "</font></a></div>";

				$this->tag->setTitle('Боевой доклад');
				$this->view->setVar('html', $Page);
				$this->showTopPanel(false);
			}
			else
			{
				$result = json_decode($raportrow['raport'], true);

				$Page = "<!DOCTYPE html><html><head><title>Боевой доклад</title>";
				$Page .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"".$this->url->getStatic('assets/build/app/bootstrap.css')."\">";
				$Page .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"".$this->url->getStatic('assets/build/app/style.css')."\">";
				$Page .= "<meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />";
				$Page .= "</head><body>";
				$Page .= "<table width=\"99%\"><tr><td>";

				$users = array_count_values($user_list);
		
				if ($user_list[0] == $this->user->id && $users[$this->user->id] == 1 && $raportrow['no_contact'] == 1 && !$this->user->isAdmin())
				{
					$Page .= "Контакт с вашим флотом потерян.<br>(Ваш флот был уничтожен в первой волне атаки.)";
				}
				else
				{
					$report = new CombatReport($result[0], $result[1], $result[2], $result[3], $result[4], $result[5], $result[6]);
					$formatted_cr = $report->report();

					$Page .= $formatted_cr['html'];
				}
		
				$Page .= "</td></tr><tr align=center><td>ID боевого доклада: <a href=\"".$this->url->get('log/new/')."?code=" . md5($this->config->application->encryptKey.$raportrow['id']) . $raportrow['id'] . "\"><font color=red>" . md5('xnovasuka' . $raportrow['id']) . $raportrow['id'] . "</font></a></td></tr>";
				$Page .= "</table></body></html>";
		
				echo $Page;

				$this->view->disable();
				die();
			}
		}
	}
}