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
* Class: Syslog
*
* [Description]
* Class for Output log messages.
*
******************************************************************************/
class Syslog {

    private $facility;

    /* flag */
    public $output_ipaddr = true;
    public $output_user   = null;
    public $output_file   = true;

    /*************************************************************************
    * Method        : __construct
    * Description   : Openlog automatically.
    * args          : None
    * return        : None
    **************************************************************************/
    public function __construct()
    {
        global $appini;

        /* replace facility */
        $this->_replace_facility();

        openlog($appini['log']['prog'], LOG_PID, $this->facility);
    }

    /*************************************************************************
    * Method        : replace_facility
    * Description   : Replace the facility specified in application.ini.
    * args          : None
    * return        : None
    **************************************************************************/
    private function _replace_facility()
    {
        global $appini;

        $facilities_list = [
                       "auth"     => LOG_AUTH,
                       "authpriv" => LOG_AUTHPRIV,
                       "cron"     => LOG_CRON,
                       "daemon"   => LOG_DAEMON,
                       "kern"     => LOG_KERN,
                       "local0"   => LOG_LOCAL0,
                       "local1"   => LOG_LOCAL1,
                       "local2"   => LOG_LOCAL2,
                       "local3"   => LOG_LOCAL3,
                       "local4"   => LOG_LOCAL4,
                       "local5"   => LOG_LOCAL5,
                       "local6"   => LOG_LOCAL6,
                       "local7"   => LOG_LOCAL7,
                       "lpr"      => LOG_LPR,
                       "mail"     => LOG_MAIL,
                       "news"     => LOG_NEWS,
                       "syslog"   => LOG_SYSLOG,
                       "user"     => LOG_USER,
                       "uucp"     => LOG_UUCP,
                      ];

        if (isset($facilities_list[$appini['log']['facility']])) {
            $this->facility = $facilities_list[$appini['log']['facility']];
        } else {
            $this->facility = LOG_LOCAL0;
        }
    }

    /*************************************************************************
    * Method        : output_log
    * Description   : Output log messages.
    * args          : $msg    Log message
    * return        : None
    **************************************************************************/
    public function output_log($msg)
    {
        /* output log */
        syslog(LOG_ERR, $msg);
    }

    /*************************************************************************
    * Method        : log
    * Description   : Make and output log messages.
    * args          : $format    Format containing log message.
    *               : $arr       String to replace
    * return        : None
    **************************************************************************/
    public function log($format, $arr)
    {
        $ipaddr = "";
        $user   = "";
        $file   = "";

        /* set ipaddr */
        if ($this->output_ipaddr === true) {
            if (isset($_SERVER["REMOTE_ADDR"])) {
                $ipaddr = $_SERVER["REMOTE_ADDR"] . ":";
            }
        } 

        /* set user */
        if ($this->output_user !== null) {
            if (isset($_SESSION[$this->output_user])) {
                $user = $_SESSION[$this->output_user] . ":";
            }
        }

        /* set file */
        if ($this->output_file === true) {
            $associnfo = array_reverse(debug_backtrace());
            if (isset($associnfo[0]["file"])) {
                $file = basename($associnfo[0]["file"]) . ":";
            } else {
                $trace = array_reverse($associnfo[0]["args"][0]->getTrace());
                $file = basename($trace[0]["file"]) . ":";
            }
        }

        /* make pre message */
        $pre = $ipaddr . $user . $file;

        /* make log message */
        $msg = vsprintf($format, $arr);

        /* make log message */
        $msg = $pre . $msg;

        /* output log message */
        $this->output_log($msg);
    }

    /*************************************************************************
    * Method        : close_log
    * Description   : Close the connection to the system log.
    * args          : None
    * return        : None
    **************************************************************************/
    public function close_log()
    {
        closelog();
    }

    /*************************************************************************
    * Method        : output_log_arr
    * Description   : Format the array as a log and output it.
    * args          : $log_array
    * return        : None
    **************************************************************************/
    public function output_log_arr($log_array)
    {
        foreach ($log_array as $value) {
            /* output log */
            $this->log($value, NULL);
        }
    }
    /*************************************************************************
    * Method        : __destruct
    * Description   : Close the connection to the system log automatically.
    * args          : None
    * return        : None
    **************************************************************************/
    public function __destruct()
    {
        $this->close_log();
    }
}
