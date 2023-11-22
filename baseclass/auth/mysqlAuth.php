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
* Class: Auth
*
* [Description]
* Class for authentication and session.
*
******************************************************************************/
class mysqlAuth extends AbstractAuth {

    public $error;
    public $log_msg;

    /*************************************************************************
    * Method        : auth_user
    * Description   : User authentication.
    * args          : $id
    *               : $passwd
    *               : $dbdriver
    * return        : true/false
    **************************************************************************/
    public function auth_user($id, $passwd, $dbdriver = NULL)
    {
        /* Get passwd from database with loginid. */
        $sql = "SELECT password FROM auth WHERE user=:user";
        $value_arr = [
                         ":user" => $id,
                     ];

        $ret_arr = $dbdriver->fetch_all($sql, $value_arr);
        $count_ret = count($ret_arr);

        /* Cannot get passwd from database. */
        if ($count_ret === 0) {
            $this->log_msg = "Cannot find passord from database by login ID.(login ID:" . $id . ")";
            return false;
        }

        /* Many passwds from database. */
        if ($count_ret > 1) {
            $this->log_msg = "Got many passwords from database by login ID.(login ID:" . $id . ")";
            return false;
        }

        $passwd_from_db = $ret_arr[0]["password"];

        if (sha1($passwd) !== $passwd_from_db) {
            $this->log_msg = "Authentication failed.(login ID: " . $id . ")";
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : check_session()
    * Description   : Check session.
    * args          : $driver
    * return        : true/false
    **************************************************************************/
    public function check_session($driver)
    {
        $this->start_session();

        /* If the user is not authenticated */
        if (isset($_SESSION['login_id']) === false) {
            $this->log_msg = "Cannot find login ID from session.";
            $this->error = "INVALID_SESSION";
            return false;
        }

        /* Get id from database with loginid. */
        $sql = "SELECT user FROM auth WHERE user=:user";
        $value_arr = [
                         ":user" => $_SESSION['login_id'],
                     ];

        $ret_arr = $driver->fetch_all($sql, $value_arr);
        $count_ret = count($ret_arr);

        /* Cannot get user from database. */
        if ($count_ret === 0) {
            $this->log_msg = "Cannot find user from database by login ID: "
                             . $_SESSION['login_id'] . ".";
            $this->error = "INVALID_SESSION";
            return false;
        }

        /* Many users from database. */
        if ($count_ret > 1) {
            $this->log_msg = "Got many users from database by login ID: "
                             . $_SESSION['login_id'] . ".";
            $this->error = "INVALID_SESSION";
            return false;
        }

        if ($_SESSION['timeout'] < time()) {
            $this->log_msg = "session timeout. (login ID: "
                             . $_SESSION['login_id'] . ")";
            $this->error = "SESSION_TIMEOUT";
            return false;
        }

        /* get new session_id */
        session_regenerate_id();

        $new_sessionid = session_id();
        $_COOKIE[$this->cookie_name] = $new_sessionid;

        return true;
    }

    /*************************************************************************
    * Method        : check_page_session()
    * Description   : Check page to start or check session.
    * args          : 
    * return        : 1     login OK
    *                 0     auth failed
    *                 2     double login
    *                -1     systemerror
    **************************************************************************/
    public function check_page_session($dbdriver)
    {
        global $appini;

        foreach ($appini['path']['login'] as $key => $value) {
            if ($_SERVER['SCRIPT_NAME'] === $value) {
                return 1;
            }
        }

        $ret = $this->check_session($dbdriver);
        if ($ret === false) {
            return 0;
        }

        /* check double login */
        $ret = double_login_check($_SESSION["login_id"],
                                  $appini["session"]["timeout"],
                                  $errmsg);

        /* occur system error */
        if ($ret === RET_LOGIN_ERR) {
            $this->error = $errmsg;
            return -1;
        /* double login */
        } else if ($ret === RET_LOGIN_NG) {
            $this->error = $errmsg;
            return 2;
        }

        return 1;

    }

    public function getLoginID()
    {
        $this->start_session();
        if (isset($_SESSION["login_id"])) {
            return $_SESSION["login_id"];
        }
        return NULL;
    }
}
