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


define('BACKUP_DHCPV4', '/etc/kea/kea-dhcp4.conf.backup');
define('BACKUP_DHCPV6', '/etc/kea/kea-dhcp6.conf.backup');
define('LOCK_DHCPV4',   '/etc/kea/kea-dhcp4.conf.lock');
define('LOCK_DHCPV6', ' /etc/kea/kea-dhcp6.conf.lock');
define('API_SERVER',    'http://127.0.0.1:8080');

/*****************************************************************************
* Class:  KeaAPI
*
* [Description]
*   Class to 
*      get config
*      test config
*      set config
*      write config
*****************************************************************************/
class KeaAPI {
 
    /**
     * const variable
     */

    /**
     * error message
     */
    const ERRMSG_JSON_DECODE       = 'json decode failed.(%s)';
    const ERRMSG_JSON_ENCODE       = 'json encode failed.(%s)';
    const ERRMSG_CONF_FMT_INVALID  = 'config format is invalid.(%s)';
    const ERRMSG_CONF_NOT_EXIT_RET = 'result not exist in response.(%s)';
    const ERRMSG_CONF_UNKNOWN_ERR  = 'can not get error message.(%s)';
    const ERRMSG_CONF_GET          = 'config-get failed.(%s)';
    const ERRMSG_CONF_TEST         = 'config-get failed.(%s)';
    const ERRMSG_CONF_SET          = 'config-set failed.(%s)';
    const ERRMSG_CONF_WRITE        = 'config-write failed.(%s)';
    const ERRMSG_BACKUP_FILE       = 'backup file failed.(%s)(%s)';

    /**
     * properties
     */
    public $server         = NULL;
    public $kea_dhcp4_conf = NULL;
    public $kea_dhcp6_conf = NULL;
    public $dhcpv4backup   = NULL;
    public $dhcpv6backup   = NULL;
    public $dhcpv4lock     = NULL;
    public $dhcpv6lock     = NULL;
    public $errmsg         = NULL;
    public $dhcpver        = NULL; 

    /************************************************************************
    * Method         : __construct
    * Description    : constructor
    * args           : $this->dhcpver    ipv4 or ipv6
    * return         : None
    ************************************************************************/
    public function __construct($dhcpver)
    {
        /* set version */
        $this->dhcpver = $dhcpver;

        /* set value to properties */
        $this->_set_properties();

        /* retrieves the current configuration */
        $this->dg_config_get();
    }

    /************************************************************************
    * Method         : _set_properties
    * Description    : set value to properties
    * args           : none
    * return         : void
    ************************************************************************/
    public function _set_properties()
    {
        global $appini;

        /* default value of properties */
        $arr_item_sets = ["server"       => API_SERVER, 
                          "dhcpv4backup" => BACKUP_DHCPV4,
                          "dhcpv6backup" => BACKUP_DHCPV6, 
                          "dhcpv4lock"   => LOCK_DHCPV4,
                          "dhcpv6lock"   => LOCK_DHCPV6,
                         ];

        /* loop then set */
        foreach ($arr_item_sets as $item => $const_val) {
            if (!array_key_exists('api', $appini)) {
                $this->$item = $const_val;
            } else if (!array_key_exists($item, $appini['api'])) {
                $this->$item = $const_val;
            } else {
                $this->$item = $appini['api'][$item];
            }
        }
    }

    /************************************************************************
    * Method         : _json_to_array
    * Description    : retrieves the current configuration
    * args           : $response
    *                  $config_ref     config was converted to array
    * return         : true of false
    ************************************************************************/
    private function _json_to_array($response, &$config_ref)
    {
        /* decode jsondata to array */
        $arr_conf = json_decode($response, TRUE);
        if ($arr_conf === FALSE) {
            $this->errmsg = sprintf(KeaAPI::ERRMSG_JSON_DECODE, $response);
            return false;
        }

        if (!isset($arr_conf[0])) {
            $this->errmsg = sprintf(KeaAPI::ERRMSG_CONF_FMT_INVALID, $response);
            return false;
        }

        if (!isset($arr_conf[0]['result'])) {
            $this->errmsg = sprintf(KeaAPI::ERRMSG_CONF_NOT_EXIT_RET, $response);
            return false;
        }
 
        if ($arr_conf[0]['result'] !== 0) {
            if (isset($arr_conf[0]['text'])) {
                $errmsg = $arr_conf[0]['text'];
                $this->errmsg = "$errmsg";
            } else {
                $this->errmsg = sprintf(KeaAPI::ERRMSG_CONF_UNKNOWN_ERR, $response);
            }
            return false;
        }

        $config_ref = $arr_conf;

        return true;
    }

    /************************************************************************
    * Method         : dg_config_get
    * Description    : retrieves the current configuration
    * args           : None
    * return         : true of false
    ************************************************************************/
    public function dg_config_get()
    {
        $json_post = '{ "command": "config-get", "service": [ "'. $this->dhcpver. '" ] }';

        /* send post jsondata */
        $ret = curl_sendpost($this->server, $json_post, $ref_body, $errmsg);
        if ($ret === FALSE) {
            $this->errmsg = $errmsg;
            return false;
        }

        /* check response */
        $ret = $this->_json_to_array($ref_body, $arr_conf);
        if ($ret === false) {
            return false;
        }

        /* dhvpv6 */
        if ($this->dhcpver === DHCPV6) {
            $this->kea_dhcp6_conf = $arr_conf[0][STR_ARG];

        /* dhcpv4 */
        } else {
            $this->kea_dhcp4_conf = $arr_conf[0][STR_ARG];
        }

        return true;
    }

    /************************************************************************
    * Method         : dg_config_test
    * Description    : check whether the new configuration supplied in the 
    *                    command's arguments can be loaded
    * args           : $arguments
    * return         : true or false
    ************************************************************************/
    public function dg_config_test($arguments)
    {
        $json_post = '{ "command": "config-test", 
                        "service": [ "'. $this->dhcpver. '" ], 
                        "arguments": '. $arguments. '}';

       /* send post jsondata */
        $ret = curl_sendpost($this->server, $json_post, $ref_body, $errmsg);
        if ($ret === FALSE) {
            $this->errmsg = $errmsg;
            return false;
        }

        /* check response */
        $ret = $this->_json_to_array($ref_body, $arr_conf);
        if ($ret === false) {
            return false;
        }

        return true;
    }

    /************************************************************************
    * Method         : dg_config_set
    * Description    : replace its current configuration with 
    *                    the new configuration supplied in 
    *                    the command's arguments.
    * args           : $arguments
    * return         : true or false
    ************************************************************************/
    public function dg_config_set($arguments)
    {
        $json_post = '{ "command": "config-set",
                        "service": [ "'. $this->dhcpver. '" ],
                        "arguments": '. $arguments. '}';

        /* send post jsondata */
        $ret = curl_sendpost($this->server, $json_post, $ref_body, $errmsg);
        if ($ret === FALSE) {
            $this->errmsg = $errmsg;
            return false;
        }

        /* check response */
        $ret = $this->_json_to_array($ref_body, $arr_conf);
        if ($ret === false) {
            return false;
        }

        return true;
    }

    /************************************************************************
    * Method         : dg_config_write
    * Description    : write its current configuration to a file
    * args           : $filepath   specifies the name of the file to 
    *                      write configuration to
    * return         : true or false
    ************************************************************************/
    public function dg_config_write($filepath = NULL)
    {
        if ($filepath == NULL) {
            /* will write to kea-dhcp4.conf */
            $json_post = '{ "command": "config-write",
                            "service": [ "'. $this->dhcpver. '" ] }';
        } else {
            /* will write to $filepath */
            $json_post = '{ "command": "config-write",
                            "service": [ "'. $this->dhcpver. '" ],
                            "arguments": {
                                "filename": "'. $filepath. '"
                            }
                          }';
        }

        /* send post jsondata */
        $ret = curl_sendpost($this->server, $json_post, $ref_body, $errmsg);
        if ($ret === FALSE) {
            $this->errmsg = $errmsg;
            return false;
        }

        /* check response */
        $ret = $this->_json_to_array($ref_body, $arr_conf);
        if ($ret === false) {
            return false;
        }

        return true;
    }

    /************************************************************************
    * Method         : dg_config_overwrite
    * Description    : write its current configuration to a file
    * args           : $newconfig    new config (array)     
    * return         : true or false
    ************************************************************************/
    public function dg_config_overwrite($newconfig)
    {
        global $appini;
    
        /* set path to file */
        if ($this->dhcpver === DHCPV6) {
            $pathdhcp = $appini['conf']['pathdhcp6'];
            $backupdhcp = $this->dhcpv6backup;
            $lockdhcp = $this->dhcpv6lock;
        } else {
            $pathdhcp = $appini['conf']['pathdhcp4'];
            $backupdhcp = $this->dhcpv4backup;
            $lockdhcp = $this->dhcpv4lock;
        }

        /* open lock file */
        $fp = fopen($lockdhcp, "w");
        if ($fp === false) {
            $this->errmsg = "can not create lock file($lockdhcp)";
            return false;
        }

        /* acquire an exclusive lock */
        if (!flock($fp, LOCK_EX)) {
            $this->errmsg = "can not lock file($lockdhcp)";
            fclose($fp);
            return false;
        }

        /* convert array newconfig to json */
        $json_config = json_encode($newconfig);
        if ($json_config === false) {
            $this->errmsg = sprintf(KeaAPI::ERRMSG_JSON_ENCODE, $this->dhcpver);
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        /* test config */
        $ret = $this->dg_config_test($json_config);
        if ($ret === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        /* write current config to backupfile */
        $ret = $this->dg_config_write($backupdhcp);
        if ($ret === false) {
            $this->errmsg = sprintf(KeaAPI::ERRMSG_BACKUP_FILE,
                                    $backupdhcp, $this->errmsg);
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        /* set new config to running configuration */
        $ret = $this->dg_config_set($json_config);
        if ($ret === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        /* write config */
        $ret = $this->dg_config_write();
        if ($ret === false) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        /* delete lock file */
        unlink($lockdhcp);

        return true;
    }
}
