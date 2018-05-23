<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'), 'autologin')) {
    echo getErrorHTML('Clef API non valide, vous n\'etes pas autorisé à effectuer cette action');
	die();
}

if (init('test') != '') {
	echo 'OK';
	die();
}

if (init('id')) {

    $ip = getClientIp();

    $autologin = autologin::byLogicalId(init('id'), 'autologin');
	if (!is_object($autologin)) {
        echo getErrorHTML("ID does not exist");
        die();
    }
    if ($autologin->getIsEnable() == 0) {
        echo getErrorHTML("Autologin session is disabled");
        die();
    }

    $allowedIP = $autologin->getIP();
    $url = $autologin->getRedirectUrl();
    $user = $autologin->getUser();
    $hashRegisteredDevice = $autologin->getHash();
    $hashregisterdevice = $autologin->getHash();
    $sessionid = $autologin->getSessionId();

    if ($allowedIP != $ip) {
        echo getErrorHTML("IP $ip not allowed (allowed IP is $allowedIP)! ");
        die();
    }

    if (!is_object($user)) {
        echo getErrorHTML("User does not exist or is disabled");
        die();
    }
    if ($user->getOptions('localOnly', 0) == 1 && network::getUserLocation() != 'internal') {
        echo getErrorHTML("User is configured to accept only local connection");
        die();
    }

    $key = explode('-', $hashregisterdevice);
    $rdk = $key[1];

    $registerDevice = $user->getOptions('registerDevice', array());
	if (!is_array($registerDevice)) {
		$registerDevice = array();
	}
    if (!isset($registerDevice[sha512($rdk)])) {
        $autologin->saveHash();
        $registerDevice = $user->getOptions('registerDevice', array());
    }

    $cache = cache::byKey('current_sessions');
    $sessions = $cache->getValue(array());
    session_id($sessionid);
    if (!isset($sessions[session_id()])) {
        $sessions[session_id()] = array();
        $sessions[session_id()]['login'] = $user->getLogin();
        $sessions[session_id()]['user_id'] = $user->getId();
        $sessions[session_id()]['datetime'] = date('Y-m-d H:i:s');
	    $sessions[session_id()]['ip'] = getClientIp();
        cache::set('current_sessions', $sessions);
        $user->setOptions('lastConnection', date('Y-m-d H:i:s'));
        $user->save();
    }
    log::add('autologin', 'info', __('Connexion de l\'utilisateur ', __FILE__) . $user->getLogin() . " ($ip)");

    $registerDevice[sha512($rdk)]['datetime'] = date('Y-m-d H:i:s');
    $user->setOptions('registerDevice', $registerDevice);
    $user->save();

    @session_start();
    $_SESSION['user'] = $user;
    $_SESSION['jeedom_token'] = ajax::getToken();
    $_SESSION['alreadyRegister'] = 1;
    @session_write_close();

    $cookieTimeout = time() + 365 * 24 * 3600;
    header("refresh: 2; url=$url");
    setcookie('sess_id', $sessionid, $cookieTimeout, "/", '', false, true);
	setcookie('registerDevice', $hashRegisteredDevice, $cookieTimeout, "/", '', false, true);
    setcookie('jeedom_token', ajax::getToken(), $cookieTimeout, "/", '', false, true);

    echo getHTML();
    die();
}
else {
    echo getErrorHTML("Missing ID");
	die();
}

function getErrorHTML($error) {
    $html  = '';
    $html .= '<br><br><br><center>';
    $html .= '<img src="/core/img/logo-jeedom-grand-nom-couleur.svg" width="200"><br><br><br><br>';
    $html .= '<span style="font-family: Verdana, Helvetica, sans-serif;font-weight: bold;font-size: 25px;">Autologin</span><br><br>';
    $html .= '<img src="/plugins/autologin/desktop/images/thumb.png" width="80"><br><br><br>';
    $html .= '<span style="color: red;font-family: Verdana, Helvetica, sans-serif;font-weight: bold;font-size: 20px;">';
    $html .= $error;
    $html .= '</span><br><br>';
    $html .= '<span style="font-family: Verdana, Helvetica, sans-serif;font-weight: normal;font-size: 15px;">Check plugin configuration</span>';
    $html .= '</center><br>';
    log::add('autologin', 'error', $error);
    return $html;
}

function getHTML() {
    $html  = '';
    $html .= '<br><br><br><center>';
    $html .= '<img src="/core/img/logo-jeedom-grand-nom-couleur.svg" width="200"><br><br><br><br>';
    //$html .= '<span style="font-family: Verdana, Helvetica, sans-serif;font-weight: bold;font-size: 24px;">Autologin</span><br><br>';
    $html .= '<img src="/plugins/autologin/desktop/images/thumb.png" width="80"><br><br><br>';
    $html .= '<span style="font-family: Verdana, Helvetica, sans-serif;font-size: 20px;">';
    $html .= "Processing, please wait...";
    $html .= '</span>';
    $html .= '</center><br>';
    return $html;
}