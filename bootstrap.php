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

/* Define Application root path. */
define('APP_ROOT', realpath(dirname(__FILE__) . '/'));
define('APPINI', APP_ROOT. "/config/application.ini");
define('SYSERR_TMPL', APP_ROOT. "/view/tmpl/syserror.tmpl");

/* Require vendor libraries. */
require APP_ROOT. "/vendor/smarty/Smarty.class.php";

/* Require base libraries. */
require APP_ROOT. "/baseclass/exception/exception.php";
require APP_ROOT. "/baseclass/dbdriver/mysql.php";
require APP_ROOT. "/baseclass/dbdriver/dbutils.php";
require APP_ROOT. "/baseclass/view/i18n.php";
require APP_ROOT. "/baseclass/view/view.php";
require APP_ROOT. "/baseclass/view/pagination.php";
require APP_ROOT. "/baseclass/validate/validate.php";
require APP_ROOT. "/baseclass/auth/abstractAuth.php";
require APP_ROOT. "/baseclass/auth/mysqlAuth.php";
require APP_ROOT. "/baseclass/utils/syslogHelper.php";
require APP_ROOT. "/baseclass/utils/httpHelper.php";
require APP_ROOT. "/baseclass/utils/config.php";
require APP_ROOT. "/class/utils.php";
require APP_ROOT. "/class/keaconf.php";
require APP_ROOT. "/class/keavalidate.php";
require APP_ROOT. "/class/dgcurl.php";
require APP_ROOT. "/class/keaapi.php";
require APP_ROOT. "/class/keaoption.php";
require APP_ROOT. "/inc/options.php";

/* Require own libraries. */

$conf = new Config();
$appini = $conf->appini;

/******************************************************************************
* Class:  bootStrap
* 
* [Description]
*  Class to initialize the application.
*  All methods included in the bootStrap class are executed 
*  by the runBootStrap class.
*
******************************************************************************/
Class bootStrap {

    public $dbdriver;
    public $log;

    public function initDB()
    {
        $db = new Mysql();
        $this->dbdriver = $db;
        return $db;
    }

    public function initLog()
    {
        $log = new Syslog();
        $this->log = $log;
        return $log;
    }

    public function initAuth()
    {
        global $appini;
        $auth = new mysqlAuth();

        $ret = $auth->check_page_session($this->dbdriver);
        /* auth failed */
        if ($ret === 0) {

            if ($auth->error === "INVALID_SESSION") {
                $auth->end_session();
                $this->log->log($auth->log_msg, NULL);
                header('Location: index.php?ctrl=invalidsess' );
                exit(0);
            } else if ($auth->error === "SESSION_TIMEOUT") {
                $auth->end_session();
                $this->log->log($auth->log_msg, NULL);
                header('Location: index.php?ctrl=sesstimeout' );
                exit(0);
            }

        /* double login */
        } else if ($ret === 2) {
            $auth->end_session();
            $view = new view();
            $view->lang = $appini['i18n']['lang'];
            $view->setgettext();
            $this->log->log($auth->error, NULL);
            $tag_msg['e_auth'] = _("Double login.");
            $view->render("index.tmpl", $tag_msg);
            exit(1);

        /* system error */
        } else if ($ret === -1){
            $auth->end_session();
            $view = new view();
            $view->lang = $appini['i18n']['lang'];
            $view->setgettext();
            $this->log->log($auth->error, NULL);
            $view->render("syserror.tmpl", NULL);
            exit(1);
        }

        return $auth;
    }

    public function initView()
    {
        global $appini;
        $view = new view();
        $view->lang = $appini['i18n']['lang'];
        $view->setgettext();

        return $view;
    }
}

/******************************************************************************
* Class: runBootStrap
* 
* [Description]
* This Class for automatically executing methods registered in bootStrap.
*
******************************************************************************/
Class runBootStrap{
    /*************************************************************************
    * Method        : __construct
    * Description   : Automatically executing methods registered in bootStrap. 
    * args          : None
    * return        : None 
    **************************************************************************/
    public function __construct()
    {

        /* Get bootStrap constructer. */
        $bs = new bootStrap();

        /* Get bootStrap methods */
        $methods = get_class_methods($bs);

        foreach ($methods as $method) {
            /* convert method name(ex: initConfig -> config) */
            $key = str_replace("init", "", $method);
            $key = strtolower($key);

            /* Run method. */
            $this->$key = $bs->$method();
        }
    }

    /*************************************************************************
    * Method        : __destruct
    * Description   : Methods used for opening general resources.
    * args          : None
    * return        : None 
    **************************************************************************/
    public function  __destruct() {
        $this->dbdriver = null;
    }
}

/* Run bootstrap */
$store = new runBootStrap();

