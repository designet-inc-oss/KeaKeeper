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


require "../bootstrap.php";

/*************************************************************************
* Class          : authValidate
* Description    : Validation class that authenticate users
* args           : $val     - store
*                : $options - method options
* return         : true or false
*************************************************************************/
class authValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* Authenticate user */
        $ret = $val->auth->auth_user($this->allval['login_id'],
                                     $this->allval['password'],
                                     $val->db);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/*************************************************************************
* Class          : Login
* Description    : Class for login page
* args           : None
* return         : true or false
*************************************************************************/
class Login {

    private $msg_tag;

    /*************************************************************************
    * Method        : __construct
    * Description   : Method for setting tags automatically
    * args          : None
    * return        : None
    **************************************************************************/
    public function __construct()
    {
        /* Tag */
        $this->msg_tag = ["e_login_id" => "",
                          "e_password" => "",
                          "e_auth"     => "",
                         ];
    }

    /*************************************************************************
    * Method        : session_ctrl
    * Description   : Method for when values are received.
    * args          : $ctrl
    *               : $store
    * return        : None
    **************************************************************************/
    public function session_ctrl($ctrl, $store)
    {
        if ($ctrl === "logout") {
            $login_id = $store->auth->getLoginID();
            $store->auth->end_session();
            $this->msg_tag["e_auth"] = _("logged out.");
            $log_msg = "logged out.";
            $store->log->log($log_msg, NULL);

            /* delete lock file */
            $ret = delete_lockfile($login_id, $errmsg); 
            if ($errmsg !== NULL) {
                $store->log->log($errmsg, NULL);
            }
        } else if ($ctrl === "sesstimeout") {
            $store->auth->end_session();
            $this->msg_tag["e_auth"] = _("Session timed out.");
            $log_msg = "Session timed out.";
            $store->log->log($log_msg, NULL);
        } else if ($ctrl === "invalidsess") {
            $this->msg_tag["e_auth"] = _("Invalid session.");
            $log_msg = "Invalid session.";
            $store->log->log($log_msg, NULL);
        }
        $this->display($store);
    }
    /*************************************************************************
    * Method        : auth_login
    * Description   : Method for when the user presses the login button.
    * args          : $login_id
    *               : $password
    *               : $store
    * return        : None
    **************************************************************************/
    public function auth_login($login_id, $password, $store)
    {
        global $appini;

        /* After pressing the login button */

        /* Check validation */
        $rules["login_id"] = ["method"=>"exist",
                              "msg"=>[_("Please enter your ID.")],
                              "log"=>["Empty user id."],
                             ];

        $rules["password"] = ["method"=>"exist",
                              "msg"=>[_("Please enter your password.")],
                              "log"=>["Empty password."],
                             ];

        $rules["auth"] = ["method"=>"auth",
                          "msg"=>[_("Authentication failed.")],
                          "log"=>["Authentication failed."],
                          "option"=>["skiponfail"],
                         ];

        $post = [
                 "login_id" => $login_id,
                 "password" => $password,
                 "auth"     => $store,
                ];

        $validater = new validater($rules, $post, true);
        $this->msg_tag = $validater->tags;

        /* When validation check succeeds */
        if ($validater->err['result'] === true) {

            /* check double login */
            $ret = double_login_check($login_id, 
                                      $appini["session"]["timeout"], 
                                      $errmsg);
            /* occur system error */
            if ($ret === RET_LOGIN_ERR) {
                $store->auth->end_session();
                $store->log->log($errmsg, NULL);
                $store->view->render("syserror.tmpl", NULL);
                exit(1);

            /* double login */
            } else if ($ret === RET_LOGIN_NG) {
                $store->auth->end_session();
                $store->log->log($errmsg, NULL);
                $this->msg_tag["e_auth"] = _("Double login.");

            /* authentication success */
            } else {
                /* session start */
                $store->auth->start_session($login_id);
                header('Location: searchlease4.php' );
                exit(0);
            }
        }

        /* output log */
        if (isset($store->auth->log_msg)) {
            array_push($validater->logs, $store->auth->log_msg);
        }
        $store->log->output_log_arr($validater->logs);

        $this->display($store);
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : None
    * return        : None
    **************************************************************************/
    public function display($store)
    {
        $store->view->render("index.tmpl", $this->msg_tag);
    }
}

/*************************************************************************
*  main
*************************************************************************/
$login_obj = new Login();

/**********************************
* Session control section
***********************************/
$ctrl = get('ctrl');
if ($ctrl !== NULL) {
    $login_obj->session_ctrl($ctrl, $store);
    exit(0);
}

/**********************************
* Auth section
***********************************/
$login_id = post('login_id');
$password = post('password');
if ($login_id !== NULL && $password !== NULL) {
    $login_obj->auth_login($login_id, $password, $store);
    exit(0);
}

/**********************************
* Default section
***********************************/
$login_obj->display($store);
exit(0);
