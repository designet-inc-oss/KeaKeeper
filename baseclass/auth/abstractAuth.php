<?php
/********************************************************************
KeaKeeper

Copyright (C) 2017 DesigNET, INC.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
********************************************************************/


/******************************************************************************
* Class: AbstractAuth
*
* [Description]
* Class for authentication and session.
*
******************************************************************************/
abstract class AbstractAuth {

    public $error;
    public $log_msg;
    protected $cookie_name = 'PHPSESSID';
    protected $cookie_path = '/';
    protected $cookie_domain = '';
    protected $cookie_secure = false;
    protected $cookie_httponly = false;

    /*************************************************************************
    * Method        : auth_user
    * Description   : User authentication.
    * return        : true/false
    **************************************************************************/
    abstract public function auth_user($id, $passwd);

    /*************************************************************************
    * Method        : check_session()
    * Description   : Check session.
    * args          : $dbdriver
    * return        : true/false
    **************************************************************************/
    abstract public function check_session($driver);


    /*************************************************************************
    * Method        : __construct
    * Description   : Method to Set variables.
    * args          : none
    * return        : true/false
    **************************************************************************/
    public function __construct()
    {
        global $appini;

        /* If value of application.ini are empty, use dafault */
        if (isset($appini['session']['cookie'])) {
            if (!empty($appini['session']['cookie'])) {
                $this->cookie_name = $appini['session']['cookie'];
            }
        }

        if (isset($appini['session']['path'])) {
            if (!empty($appini['session']['path'])) {
                $this->cookie_path = $appini['session']['path'];
            }
        }

        if (isset($appini['session']['domain'])) {
            if (!empty($appini['session']['domain'])) {
                $this->cookie_domain = $appini['session']['domain'];
            }
        }

        if (isset($appini['session']['secure'])) {
            if (!empty($appini['session']['secure'])) {
                $this->cookie_secure = $appini['session']['secure'];
            }
        }

        if (isset($appini['session']['httponly'])) {
            if (!empty($appini['session']['httponly'])) {
                $this->cookie_httponly = $appini['session']['httponly'];
            }
        }
    }


    /*************************************************************************
    * Method        : start_session()
    * Description   : Start session and set cookie.
    * args          : $id (Default NULL)
    * return        : none
    **************************************************************************/
    public function start_session($id = NULL)
    {
        global $appini;

        session_set_cookie_params($appini['session']['timeout'],
                                  $this->cookie_path,
                                  $this->cookie_domain, $this->cookie_secure,
                                  $this->cookie_httponly);
        /* set cookie name */
        session_name($this->cookie_name);

        /* start session */
        session_start();

        /* If $id exists in the argument, set the value to the session and cookie variable */
        if (isset($id)) {
            /* timeout */
            $time_sessionstart = time();
            $timeout_session = $time_sessionstart + $appini['session']['timeout'];

            $_SESSION['login_id'] = $id;
            $_SESSION['timeout'] = $timeout_session;

            $session_id = session_id();
            $_COOKIE[$this->cookie_name] = $session_id;
        }
    }

    /*************************************************************************
    * Method        : end_session()
    * Description   : End session.
    * args          : none
    * return        : none
    **************************************************************************/
    public function end_session()
    {
        if(!isset($_SESSION)) {
            $this->start_session();
        }

        /* delete session params and cookie */
        $_SESSION = [];

        setcookie($this->cookie_name, '',
                  time() - 3600, $this->cookie_path,
                  $this->cookie_domain, $this->cookie_secure,
                  $this->cookie_httponly);

        /* destroy session */
        session_destroy();

    }
}
