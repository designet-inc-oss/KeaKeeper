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
* Class: Log
*
* [Description]
* Class for Output log messages.
*
******************************************************************************/
class Log {

    private $facility;
    private $ipaddr;
    private $user;
    private $file;

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

        $this->facility = $facilities_list[$appini['log']['facility']];

    }

    /*************************************************************************
    * Method        : __construct
    * Description   : Openlog automatically.
    * args          : $ipaddr Connection source IP address
    *               : $user   Login user name
    *               : $file   Log output file name
    * return        : None
    **************************************************************************/
    public function __construct($ipaddr, $user, $file)
    {
        global $appini;

        /* set property */
        $this->ipaddr = $ipaddr;
        $this->user = $user;
        $this->file = $file;

        /* replace facility */
        $this->_replace_facility();

        openlog($appini['log']['prog'], LOG_PID, $this->facility);
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
        syslog(LOG_ERR, $this->ipaddr . ":" . $this->user
                                      . ":" . $this->file . ":" . $msg);
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
}
