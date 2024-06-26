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
define('RET_NOTFOUND', 0);
define('RET_SUBNET',   1);
define('RET_SHNET',    2);

define('CONF:', '/etc/kea/kea-dhcp4.conf');
define('CONF5', '/etc/kea/kea-dhcp6.conf');
define('DHCPV4',      'dhcp4');
define('DHCPV6',      'dhcp6');
define('STR_DHCP4',   'Dhcp4');
define('STR_DHCP6',   'Dhcp6');
define('STR_SHARED',  'shared-networks');
define('STR_NAME',    'name');
define('STR_INTERFACE',    'interface');
define('STR_INTERFACEID',    'interface-id');
define('STR_RELAY',    'relay');
define('STR_AGENT',    'ip-addresses');
define('STR_SUBNET',  'subnet');
define('STR_SUBNET4', 'subnet4');
define('STR_SUBNET6', 'subnet6');
define('STR_POOL',    'pool');
define('STR_POOLS',   'pools');
define('STR_PD_POOLS',   'pd-pools');
define('STR_ARG',     'arguments');
define('STR_ID',      'id');
define('STR_OPT_DATA', 'option-data');
define('STR_OPT_NAME',  'name');
define('STR_OPT_VALUE', 'data');
define('STR_CLIENT_CLASSES', 'client-classes');
define('STR_CLIENT_CLASS', 'client-class');
define('STR_TEST', 'test');
define('STR_NO_MEMBER', 'no-member');

define('K_SESS_DHCP4', 'kea_conf_4');
define('K_SESS_DHCP6', 'kea_conf_6');
define('K_SESS_HISTORY4', 'history_4');
define('K_SESS_HISTORY6', 'history_6');

define('MAC_TYPE', '0');
define('DUID_TYPE', '1');
define('CIRCUITID_TYPE', '2');

/*****************************************************************************
* Class:  KeaConf
*
* [Description]
*   Class to read and use kea.conf
*****************************************************************************/
class KeaConf {

    public $result;
    public $all;
    public $dhcp4;
    public $dhcp6;
    public $err = ['e_msg' => '', 'e_log' => ''];
    private $_dhcpver;
   

    /************************************************************************
    * Method         : __construct
    * Description    : Check kea-dhcp4.conf, kea-dhcp6.conf file
                       and set property
    * args           : None
    * return         : None
    ************************************************************************/
    public function __construct($dhcpver)
    {
        global $appini;

        $this->_dhcpver = $dhcpver;

        if ($this->_dhcpver === DHCPV6) {
            $this->k_dhcp   = STR_DHCP6;
            $this->k_subnet = STR_SUBNET6;
            $this->k_shared = STR_SHARED;
            $this->k_sess_conf = K_SESS_DHCP6;
            $this->k_sess_hist = K_SESS_HISTORY6;
        } else {
            $this->k_dhcp   = STR_DHCP4;
            $this->k_subnet = STR_SUBNET4;
            $this->k_shared = STR_SHARED;
            $this->k_sess_conf = K_SESS_DHCP4;
            $this->k_sess_hist = K_SESS_HISTORY4;
        }

        /* read */
        $this->get_config($dhcpver);
    }

    /************************************************************************
    * Method         : mk_arr_all_subnet
    * Description    : create array (shared-network => subnet) from dhcp conf
    * args           : $dhcp_conf
    * return         : $data_all_subnet
    ************************************************************************/
    public function mk_arr_all_subnet($dhcp_conf)
    {
        $data_all_subnet = array();

        /* if exist shared-network */
        if (isset($dhcp_conf[$this->k_shared ])) {
            /* all subnet of shared-network part*/
            foreach ($dhcp_conf[$this->k_shared] as $id_sh => $one_sh) {
                /* if exist subnet in  shared-network */
                if (isset($one_sh[$this->k_subnet])) {
                    $data_all_subnet[$one_sh[STR_NAME]] = 
                                              $one_sh[$this->k_subnet]; 
                }
            }
        }

        /* all subnet of other subnet part */
        $data_all_subnet['OTHER:SUBNET'] = $dhcp_conf[$this->k_subnet];

        return $data_all_subnet;
    }

    /************************************************************************
    * Method         : search_subnet4
    * Description    : Check subnet4 and forward search
    * args           : $cond
    *                  $mode   foward|exact|all
    * return         : true or false
    ************************************************************************/
    public function search_subnet4($cond = null, $mode = 'all')
    {

        /* loop dhcp4 subnet4 */
        $result = [];
        $key_list = ["id" => "", "pools"=>"", "subnet"=>""];

        $all_subnet_data = $this->mk_arr_all_subnet($this->dhcp4);

        /* check presence dhcp4 configuration */
        if (empty($all_subnet_data)) {
            $this->err['e_msg'] = _("No subnet4 setting.");
            $this->err['e_log'] = "No subnet4 setting.";

            return [false,[]];
        }

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {
            if (!isset($data_subnet)) {
                break;
            }
            foreach ($data_subnet as $idx_subnet => $sub) {
                switch ($mode) {
                case "foward":
                    /* quote regular expression characters */
                    $esc_cond = preg_quote($cond);

                    if (preg_match("#^$esc_cond#", $sub['subnet']) != 0) {
                        $result[] =
                            array_merge($key_list, $data_subnet[$idx_subnet]);
                    }
                    break;

                case "exact":
                    if ($cond === $sub['subnet']) {
                        $result[] =
                            array_merge($key_list, $data_subnet[$idx_subnet]);
                    }
                    break;

                case "all":
                default:
                    $result[] =
                            array_merge($key_list, $data_subnet[$idx_subnet]);
                    break;
                }
            }
        }

        /* check result */
        if (empty($result)) {
            $this->err['e_msg'] = _('No result.');
            $this->err['e_log'] = 'No search result for subnet4.';

            return [false, []];
        }

        return [true, $result];
    }

    /************************************************************************
    * Method         : search_subnet6
    * Description    : Check subnet6 and forward search
    * args           : $subnet
    * return         : true or false
    ************************************************************************/
    public function search_subnet6($cond = null, $mode = 'all')
    {
       /* loop dhcp6 subnet6 */
        $result = [];
        $key_list = ["id" => "", "pools"=>"", "subnet"=>""];
        $all_subnet_data = $this->mk_arr_all_subnet($this->dhcp6);

        /* check presence dhcp4 configuration */
        if (empty($all_subnet_data)) {
            $this->err['e_msg'] = _("No subnet6 setting.");
            $this->err['e_log'] = "No subnet6 setting."; 

            return [false, []];
        }

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {
            foreach ($data_subnet as $idx_subnet => $sub) {

                list($addr, $mask) = explode("/", $sub['subnet']);
                $addr = inet_ntop(inet_pton($addr));
                $converted_subnet = $addr . "/" . $mask;

                switch ($mode) {
                case "foward":
                    /* quote regular expression characters */
                    $esc_cond = preg_quote($cond);

                    if (preg_match("#^$esc_cond#", $converted_subnet) != 0) {
                        $result[] =
                            array_merge($key_list, $data_subnet[$idx_subnet]);
                    }
                    break;

                case "exact":

                    if ($cond === $converted_subnet) {
                        $result[] =
                            array_merge($key_list, $data_subnet[$idx_subnet]);
                    }
                    break;

                case "all":
                default:
                    $result[] =
                            array_merge($key_list, $data_subnet[$idx_subnet]);
                    break;
                }
            }
        }

        /* check result */
        if (empty($result)) {
            $this->err['e_msg'] = _('No result.');
            $this->err['e_log'] = 'No search result for subnet6.';

            return [false, []];
        }

        return [true, $result];
    }

    /************************************************************************
    * Method         : check_subnet4
    * Description    : Investigate whether subnet is in keaconf(DHCPv6)
    * args           : $subnet
    * return         : true/false
    ************************************************************************/
    public function check_subnet4($subnet)
    {
        /* get all subnet in config */
        $all_subnet_data = $this->mk_arr_all_subnet($this->dhcp4);

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {
            /* When reading of keaconf succeeded */
            foreach ($data_subnet as $one) {

                /* When a matching subnet is in keaconf */
                if (array_key_exists('subnet', $one) && $one['subnet'] == $subnet) {

                    /* Check if there is subnet id */
                    if (array_key_exists('id', $one)) {
                        return true;
                    }
                }
            }
        }

        /* When matching subnet is not in keaconf */
        $form = _('No such subnet(%s)');
        $this->err['e_msg'] = sprintf($form, $subnet);
        $this->err['e_log'] = "No such subnet(" . $subnet . ").";
        return false;
    }

    /************************************************************************
    * Method         : check_subnet6
    * Description    : Investigate whether subnet is in keaconf(DHCPv6)
    * args           : $subnet
    * return         : true/false
    ************************************************************************/
    public function check_subnet6($subnet)
    {
        /* get all subnet in config */
        $all_subnet_data = $this->mk_arr_all_subnet($this->dhcp6);

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {
            /* When reading of keaconf succeeded */
            foreach ($data_subnet as $one) {

                /* When a matching subnet is in keaconf */
                if (array_key_exists('subnet', $one) && $one['subnet'] == $subnet) {

                    /* Check if there is subnet id */
                    if (array_key_exists('id', $one)) {
                        return true;
                    }
                }
            }
        }

        /* When matching subnet is not in keaconf */
        $form = _('No such subnet(%s)');
        $this->err['e_msg'] = sprintf($form, $subnet);
        $this->err['e_log'] = "No such subnet(" . $subnet . ").";
        return false;
    }

    /************************************************************************
    * Method         : check_id_subnet4
    * Description    : Check proper id and subnet(DHCPv4)
    * args           : $subnet
    * return         : $result
    ************************************************************************/
    public function check_id_subnet4($id, $subnet)
    {
        /* get all subnet in config */
        $all_subnet_data = $this->mk_arr_all_subnet($this->dhcp4);

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {

            /* When reading of keaconf succeeded */
            foreach ($data_subnet as $one) {

                if (array_key_exists('id', $one) && $one['id'] == $id) {
                    /* When matching subnet_id is in keaconf */
                    if ($one['subnet'] == $subnet) {

                        /* When a matching subnet is in keaconf */
                        return true;
                    }
                    /* When matching subnet is not in keaconf */
                    $form = _('No such subnet(%s)');
                    $this->err['e_msg'] = sprintf($form, $subnet);
                    $this->err['e_log'] = "No such subnet(" . $subnet . ").";

                    return false;
                }
            }
        }

        /* When matching subnet_id is not in keaconf */
        $form = _('No such subnet id(%s)');
        $this->err['e_msg'] = sprintf($form, $id);
        $this->err['e_log'] = "No such subnet id(" . $id . ").";

        return false;
    }

    /************************************************************************
    * Method         : check_id_subnet6
    * Description    : Check proper id and subnet(DHCPv6)
    * args           : $subnet
    * return         : $result
    ************************************************************************/
    public function check_id_subnet6($id, $subnet)
    {
        /* get all subnet in config */
        $all_subnet_data = $this->mk_arr_all_subnet($this->dhcp6);

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {
            foreach ($data_subnet as $one) {
                if (array_key_exists('id', $one) && $one['id'] == $id) {
                    /* When matching subnet_id is in keaconf */
                    $num = substr_count($one['subnet'], "/");
                    if ($num != 1) {
                        return false;
                    }

                    list($addr, $mask) = explode("/", $one['subnet']);

                    $ret = filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
                    if ($ret === false) {
                        return false;
                    }

                    $addr = inet_ntop(inet_pton($addr));
                    $one['subnet'] = $addr . "/" . $mask;

                    if ($one['subnet'] == $subnet) {
                        /* When a matching subnet is in keaconf */
                        return true;
                    }
                    /* When matching subnet is not in keaconf */
                    $form = _('No such subnet(%s)');
                    $this->err['e_msg'] = sprintf($form, $subnet);
                    $this->err['e_log'] = "No such subnet(" . $subnet . ").";

                    return false;
                }
            }
        }

        /* When matching subnet_id is not in keaconf */
        $form = _('No such subnet id(%s)');
        $this->err['e_msg'] = sprintf($form, $id);
        $this->err['e_log'] = "No such subnet id(" . $id . ").";

        return false;
    }

    /************************************************************************
    * Method         : get_subnet_id
    * Description    : Get subnet4 from keaconf
    * args           : $subnet
    * return         : $subnet_id
    ************************************************************************/
    public function get_subnet_id($subnet)
    {
        /* When reading of keaconf succeeded */
        /* search global subnet */
        foreach ($this->dhcp4['subnet4'] as $one) {

            /* When matching subnet_id is in keaconf */
            if (array_key_exists('subnet', $one) && $one['subnet'] == $subnet) {

                if (array_key_exists('id', $one)) {
                    $subnet_id = $one['id'];
                    return $subnet_id;
                }
            }
        }

        /* search shared-networks subnet */
        foreach ($this->dhcp4['shared-networks'] as $shared ) {
            foreach ($shared['subnet4'] as $one) {
                /* When matching subnet_id is in keaconf */
                if (array_key_exists('subnet', $one) && $one['subnet'] == $subnet) {

                    if (array_key_exists('id', $one)) {
                        $subnet_id = $one['id'];
                        return $subnet_id;
                    }
                }
            }
        }

        return NULL;
    }

    /************************************************************************
    * Method         : get_subnet_idv6
    * Description    : Get subnet4 from keaconf
    * args           : $subnet
    * return         : $subnet_id
    ************************************************************************/
    public function get_subnet_idv6($subnet)
    {
        /* When reading of keaconf succeeded */
        foreach ($this->dhcp6['subnet6'] as $one) {

            /* When matching subnet_id is in keaconf */
            if (array_key_exists('subnet', $one)) {
                list($addr, $mask) = explode("/", $one['subnet']);
                $addr = inet_ntop(inet_pton($addr));
                $converted_subnet = $addr . "/" . $mask;

                if ($converted_subnet == $subnet) {
                    if (array_key_exists('id', $one)) {
                        $subnet_id = $one['id'];
                        return $subnet_id;
                    }
                }

            }
        }

        /* search shared-networks subnet */
        foreach ($this->dhcp6['shared-networks'] as $shared) {
            foreach($shared['subnet6'] as $one) {
                /* When matching subnet_id is in keaconf */
                if (array_key_exists('subnet', $one)) {
                    list($addr, $mask) = explode("/", $one['subnet']);
                    $addr = inet_ntop(inet_pton($addr));
                    $converted_subnet = $addr . "/" . $mask;

                    if ($converted_subnet == $subnet) {
                        if (array_key_exists('id', $one)) {
                            $subnet_id = $one['id'];
                            return $subnet_id;
                        }
                    }
                }
            }
        }
        return NULL;
    }

    /************************************************************************
    * Method         : get_pools
    * Description    : Check subnet4 and get subnet4's pools
    * args           : $subnet
                       $mode - include or exclude(default)
    * return         : [true, result] or [false, []]
    ************************************************************************/
    public function get_pools($subnet, $mode = 'exclude')
    {
        $count_pool = 0;
        $result = [];
        [$ret, $sub4section] = $this->search_subnet4($subnet, 'exact');

        if ($ret === false)
        {
            return [false, []]; 
        }

        $pools = $sub4section[0]['pools'];
        if ($pools === "") {
            return [true, []];
        }
        /* Get only pools other than for option82 */
        if ($mode !== 'all') {
            [$ret, $pools] = $this->survey_option82_pools($pools, $mode);
        }

        /* check format pool  */
        if (is_array($sub4section)) {
            foreach ($pools as $pool_k => $pool_val) {
                /* get pool */
                list($min_pool, $max_pool) = get_kea_pool_v4($pool_val['pool']);

                /* check ipv4 format(min) */
                $ipv4_validater = new ipv4Validate();
                $ret = $ipv4_validater->run($min_pool);
                if ($ret === false) {
                    $this->err['e_msg'] = _('Invalid pool.');
                    $this->err['e_log'] = 'The pool format is invalid.';
                    return [false, []];
                }

                /* check ipv4 format(max) */
                $ret = $ipv4_validater->run($max_pool);
                if ($ret === false) {
                    $this->err['e_msg'] = _('Invalid pool.');
                    $this->err['e_log'] = 'The pool format is invalid.';
                    return [false, []];
                }

                /* adjust format of pool  
                 * eg: 192.168.2.9/29 → 192.168.2.9-192.168.2.14
                 * eg: 192.168.2.9-192.168.2.13 → 192.168.2.9-192.168.2.13
                 */
                $result[$count_pool] = ['pool' => $min_pool. '-'. $max_pool];

                $count_pool++;
            }
        }

        return [true, $result];
    }

    /************************************************************************
    * Method         : get_pools6
    * Description    : Check subnet6 and get subnet6's pools
    * args           : $subnet
    * return         : $result
    ************************************************************************/
    public function get_pools6($subnet)
    {
        $new_pools = [];
        [$ret, $sub6section] = $this->search_subnet6($subnet, 'exact');

	    if ($ret === false)
	    {
	        return [false, []];
	    }

        $pools = $sub6section[0][STR_POOLS];
        if ($pools === "") {
            return [true, ""];
        }

        /* check format */
        if (is_array($sub6section)) {
            foreach ($pools as $pool_k => $pool_val) {
                list($pool_min, $pool_max) = 
                      get_kea_pool_v6($pool_val[STR_POOL]);

                /* Fit the address format of the pool in kea.conf */
                $new_pools[$pool_k][STR_POOL] = $pool_min. '-'. $pool_max;
            }
	    }

        return [true, $new_pools];
    }

    /************************************************************************
    * Method         : get_config
    * Description    : Decode kea.conf and keep config
    * args           : $dhcpver dhcp4 or dhcp6
    * return         : false (when decode error)
    ************************************************************************/
    public function get_config ($dhcpver)
    {
        global $appini;

        if ($dhcpver === DHCPV6) {

            /* get dhcpd config from session */
            $sess_dhcpd_conf = $this->get_conf_from_sess();

            /* if dhcpd config do not exist in session */
            if ($sess_dhcpd_conf === NULL) {

                $ins_kea = new KeaAPI($dhcpver);

                /* occur error when get config */
                if ($ins_kea->errmsg !== NULL) {
                    $this->result = false;
                    $this->err['e_msg'] = _("API execution failed.");
                    $log_msg = "API execution failed.(%s)";
                    $this->err['e_log'] = sprintf($log_msg, $ins_kea->errmsg);
                    return false;
                }

                /* JSON to associative array */
                $this->all = $ins_kea->kea_dhcp6_conf;

                /* save config of kea-dhcp4 to session */
                $this->save_conf_to_sess($this->all);

            } else {
                $this->all = $sess_dhcpd_conf;
                $this->save_conf_to_sess($sess_dhcpd_conf);
            }

            /* keep config of dhcp6 */
            if (array_key_exists(STR_DHCP6, $this->all)) {
                $this->dhcp6 = $this->all[STR_DHCP6];
            }

        } else {

            /* get dhcpd config from session */
            $sess_dhcpd_conf = $this->get_conf_from_sess();
 
            /* if dhcpd config do not exist in session */
            if ($sess_dhcpd_conf === NULL) {

                $ins_kea = new KeaAPI($dhcpver);

                /* occur error when get config */
                if ($ins_kea->errmsg !== NULL) {
                    $this->result = false;
                    $this->err['e_msg'] = _("API execution failed.");
                    $log_msg = "API execution failed.(%s)";
                    $this->err['e_log'] = sprintf($log_msg, $ins_kea->errmsg);
                    return false;
                }

                /* JSON to associative array */
                $this->all = $ins_kea->kea_dhcp4_conf;

                /* save config of kea-dhcp4 to session */
                $this->save_conf_to_sess($this->all);

            } else {
                $this->all = $sess_dhcpd_conf;
                $this->save_conf_to_sess($sess_dhcpd_conf);
            }

            /* keep config of dhcp4 */
            if (array_key_exists(STR_DHCP4, $this->all)) {
                $this->dhcp4 = $this->all[STR_DHCP4];
            }

        }

    }

    /************************************************************************
    * Method         : del_elm_notuse_in_pool
    * Description    : keep properties in subnet['pools'][$key][xxx]
    * args           : $dhcpver dhcp4 or dhcp6
    * return         : false (when decode error)
    ************************************************************************/
    private function del_elm_notuse_in_pool($all_config, $dhcpver)
    {
        $cus_config = $all_config;

        if ($dhcpver === DHCPV6) {
            $k_dhcp = STR_DHCP6;
            $k_subnet = STR_SUBNET6;
        } else {
            $k_dhcp = STR_DHCP4;
            $k_subnet = STR_SUBNET4;
        }

        if (isset($cus_config[$k_dhcp][$k_subnet])) {
            foreach ($cus_config[$k_dhcp][$k_subnet] as $subnet_c => $subnet_data) {
                if (isset($subnet_data[STR_POOLS])) {
                    foreach ($subnet_data[STR_POOLS] as $pool_c => $pool_data) {
                        foreach ($pool_data as $pool_k => $pool_val) {
                            if ($pool_k !== STR_POOL) {
                                unset($cus_config[$k_dhcp]
                                      [$k_subnet][$subnet_c]
                                      [STR_POOLS][$pool_c]
                                      [$pool_k]);
                            }
                        }
                    }
                }
            }
        }

        if (isset($cus_config[$k_dhcp][$this->k_shared])) {
            foreach ($cus_config[$k_dhcp][$this->k_shared] as $c_sh => $arr_subnet_data) {
                foreach ($arr_subnet_data[$k_subnet] as $subnet_c => $subnet_data) {
                    if (isset($subnet_data[STR_POOLS])) {
                        foreach ($subnet_data[STR_POOLS] as $pool_c => $pool_data) {
                            foreach ($pool_data as $pool_k => $pool_val) {
                                if ($pool_k !== STR_POOL) {
                                    unset($cus_config[$k_dhcp][$this->k_shared]
                                      [$c_sh][$k_subnet][$subnet_c]
                                      [STR_POOLS][$pool_c]
                                      [$pool_k]);
                                }
                            }
                        }
                    }
                }
            }
        }

        return $cus_config;
    }

    /************************************************************************
    * Method         : get_subnet_part
    * Description    : get subnet part only
    * args           : $config
    * return         : subnet part or NULL
    ************************************************************************/
    public function get_subnet_part($config = null)
    {
        if ($config === null) {
            $config = $this->all;
        }

        if (isset($config[$this->k_dhcp][$this->k_subnet])) {
            return $config[$this->k_dhcp][$this->k_subnet];
        }

        return null;
    }

    /************************************************************************
    * Method         : add_subnet
    * Description    : add new subnet to config
    * args           : None
    * return         : true or false
    ************************************************************************/
    public function add_subnet($new_subnet)
    {
        /* current config */
        $new_conf = $this->all;

        if (!isset($new_conf[$this->k_dhcp][$this->k_subnet])) {
            return [false, []];
        }

        /* count number of subnet */
        $count_subnet =  count_array($new_conf[$this->k_dhcp][$this->k_subnet]);

        /* add new subnet */
        $new_conf[$this->k_dhcp][$this->k_subnet][$count_subnet] = $new_subnet;

        return [true, $new_conf];
    }

    /************************************************************************
    * Method         : subnet_exist_in_shnet
    * Description    : check subnet exsit in shared-network part
    * args           : None
    * return         : true or false
    ************************************************************************/
    public function subnet_exist_in_shnet($subnet, $sh_data)
    {
        foreach ($sh_data as $sh_subnet) {
            foreach ($sh_subnet[$this->k_subnet] as $one_subnet) {
                if ($subnet === $one_subnet[STR_SUBNET]) {
                    return true;
                }
            }
        }
        return false;
    }

    /************************************************************************
    * Method         : del_subnet
    * Description    : del subnet in config
    * args           : None
    * return         : true or false
    ************************************************************************/
    public function del_subnet($subnet)
    {
        /* current config */
        $new_conf = $this->all;

        /* check subnet belong shared-network part */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* if subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        
        /* if subnet exist in subnet part */
        if ($ret === RET_SUBNET) {
            unset($new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet]);
            $new_subnet = array_values($new_conf[$this->k_dhcp]
                                                   [$this->k_subnet]);
            $new_conf[$this->k_dhcp][$this->k_subnet] = $new_subnet;
        /* if subnet exist in shared-network part */
        } else if ($ret === RET_SHNET) {
            unset($new_conf[$this->k_dhcp]
                           [$this->k_shared]
                           [$pos_shnet]
                           [$this->k_subnet]
                           [$pos_subnet]);
            $new_subnet = array_values($new_conf[$this->k_dhcp]
                                                   [$this->k_shared]
                                                   [$pos_shnet]
                                                   [$this->k_subnet]);
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet] = $new_subnet;

        }

        return [true, $new_conf];
    }

    /************************************************************************
     * Method         : del_range
     * Description    : delele range of subnet in config
     * args           : None
     * return         : true or false 
     ************************************************************************/
    public function del_range($subnet, $pool_del)
    {
        /* range found flag */
        $range_found = false;
  
        /* pool after delete */
        $new_pool = [];

        /* current config */
        $new_conf = $this->all;

        /* check subnet belong shared-network part */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* if subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        /* if subnet exist in subnet part */
        if ($ret === RET_SUBNET) {
            /* if exist range */
            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_subnet]
                               [$pos_subnet]
                               [STR_POOLS])) {
                foreach ($new_conf[$this->k_dhcp]
                                  [$this->k_subnet]
                                  [$pos_subnet]
                                  [STR_POOLS] 
                         as $c_pool => $pool) {

                    /* get pool(min-max)*/
                    list($min_pool, $max_pool) = get_kea_pool($this->k_dhcp, $pool[STR_POOL]);
                    $conf_pool = $min_pool. '-'. $max_pool;

                    if ($conf_pool !== $pool_del) {
                        $new_pool[] = $pool;
                    } else {
                        $range_found = true;
                    }
                }
            }

        /* if subnet exist in shared-network part */
        } else if ($ret === RET_SHNET) {
            /* if exist range */
            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_shared]
                               [$pos_shnet][$this->k_subnet]
                               [$pos_subnet][STR_POOLS])) {
                foreach ($new_conf[$this->k_dhcp][$this->k_shared]
                         [$pos_shnet][$this->k_subnet][$pos_subnet][STR_POOLS]
                         as $c_pool => $pool) {

                    /* get pool(min-max)*/
                    list($min_pool, $max_pool) = get_kea_pool($this->k_dhcp, $pool[STR_POOL]);
                    $conf_pool = $min_pool. '-'. $max_pool;

                    if ($conf_pool !== $pool_del) {
                        $new_pool[] = $pool;
                    } else {
                        $range_found = true;
                    }
                }
            }
        } 

        /* deleting range do not exist in this subnet */
        if (!$range_found) {
            $this->result = false;
            $form = _("Pool IP address range has already been deleted.(%s)");
            $this->err['e_msg'] = sprintf($form, $pool_del);
            $log_msg = "Pool IP address range has already been deleted.(%s)(%s)";
            $this->err['e_log'] = sprintf($log_msg, $subnet, $pool_del);
            return [false, []];
        }

        /* if subnet do not exist in shared-network part */
        if ($ret === RET_SUBNET) {
            /* set new range of subnet */
            $new_conf[$this->k_dhcp]
                     [$this->k_subnet]
                     [$pos_subnet]
                     [STR_POOLS] = $new_pool;

        /* if subnet exist in shared-network part */
        } else if ($ret === RET_SHNET){
            /* set new range of subnet */
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet]
                     [$pos_subnet]
                     [STR_POOLS] = $new_pool;
        }

        return [true, $new_conf];
    }

    /************************************************************************
     * Method         : check_subnet_belongto
     * Description    : check subnet belong to subnet part or shared-network part
     * args           : $subnet        subnet will check
     *                : &$pos_subnet   position found subnet
     *                : &$pos_shnet    position found shared-network
     * return         : RET_SUBNET
     *                : RET_SHNET
     *                : RET_NOTFOUND
     ************************************************************************/
     public function check_subnet_belongto($subnet, &$pos_subnet, &$pos_shnet)
     {
        $flg_found = RET_NOTFOUND;

        /* get subnet part only */
        $subnet_data = $this->get_subnet_part();

        /* get shared-network part only */
        $shnet_data = $this->get_shared_part();

        /* the first, find in sunbnet data */
        foreach ($shnet_data as $c_shnet => $sh_subnet) {
            if (!isset($sh_subnet[$this->k_subnet])) {
                break;
            }
            foreach ($sh_subnet[$this->k_subnet] as $c_subnet => $one_subnet) {
                if ($subnet === $one_subnet[STR_SUBNET]) {
                    $pos_shnet = $c_shnet;
                    $pos_subnet = $c_subnet;
                    return RET_SHNET;
                }
            }
        }

        /* if not found in shared-network data then find in subnet data */
        if ($flg_found !== RET_SHNET) {
            /* find subnet in subnet data */
            foreach ($subnet_data as $c_subnet => $one_subnet) {
                if (isset($one_subnet[STR_SUBNET])) {
                    if ($one_subnet[STR_SUBNET] === $subnet) {
                        $pos_subnet = $c_subnet;
                        return RET_SUBNET;
                    }
                }
            }
        }

        return RET_NOTFOUND;
     }

    /************************************************************************
     * Method         : add_range
     * Description    : add new pool to subnet in config
     * args           : None
     * return         : true or false
     ************************************************************************/
    public function add_range($subnet, $new_pool)
    {
        $new_conf = $this->all;
        $count_pool = 0;

        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        /* subnet found in subnet data */
        if ($ret === RET_SUBNET) {

            if (isset($new_conf[$this->k_dhcp]
                                  [$this->k_subnet]
                                  [$pos_subnet][STR_POOLS])) {
                /* count number of pools */
                $count_pool = count_array($new_conf[$this->k_dhcp]
                                             [$this->k_subnet]
                                             [$pos_subnet][STR_POOLS]);
            }

            /* add new pool of subnet */
            $new_conf[$this->k_dhcp][$this->k_subnet]
                     [$pos_subnet][STR_POOLS][$count_pool] = $new_pool;

        /* sbnet found in shared-network data */
        } else if ($ret === RET_SHNET) {

            if (isset($new_conf[$this->k_dhcp]
                                  [$this->k_shared]
                                  [$pos_shnet]
                                  [$this->k_subnet]
                                  [$pos_subnet]
                                  [STR_POOLS])) {

                /* count number of pools */
                $count_pool = count_array($new_conf[$this->k_dhcp]
                                             [$this->k_shared]
                                             [$pos_shnet]
                                             [$this->k_subnet]
                                             [$pos_subnet]
                                            [STR_POOLS]);
            }

            /* add new pool of subnet */
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet]
                     [$pos_subnet]
                     [STR_POOLS]
                     [$count_pool] = $new_pool;

        }

        return [true, $new_conf];
    }

    /************************************************************************
     * Method         : array_merge_option
     * Description    : merge new options and org options
     * args           : $arr_opt_org
     *                : $arr_opt_add        
     * return         : $arr_opt_new
     ************************************************************************/
    public function array_merge_option($arr_opt_org, $arr_opt_add)  
    {
        /* new option */
        $arr_opt_new = $arr_opt_org;

        foreach ($arr_opt_org as $idx_org => $opt_org) {
            foreach ($arr_opt_add as $idx_add => $opt_add) {
                if ($opt_org[STR_OPT_NAME] === $opt_add[STR_OPT_NAME]) {

                    /* overwrite by new value */
                    $arr_opt_new[$idx_org] = $opt_add;

                    /* unset element */
                    unset($arr_opt_add[$idx_add]);
                }
            }
        }

        /* add to laster array */
        $arr_opt_new = array_merge($arr_opt_new, $arr_opt_add);  

        return $arr_opt_new;
    }

    /************************************************************************
     * Method         : array_del_option
     * Description    : delete option in array by option name
     * args           : $arr_opt_org       array option org
     *                : $optionname_del    array option will delete
     * return         : $arr_opt_new - found option name 
     *                : false        - not found optionname
     ************************************************************************/
    public function array_del_option($arr_opt_org, $optionname_del)
    {
        $found_flg = false;
        /* new option after delete option */
        $arr_opt_new = [];

        foreach ($arr_opt_org as $idx_org => $opt_org) {
            if (!in_array($opt_org[STR_OPT_NAME], $optionname_del)) {
                $arr_opt_new[] = $opt_org;
            } else {
                $found_flg = true;;
            }
        }

        if ($found_flg === true) {
            return $arr_opt_new;
        } else {
            return false;
        }
    }

    /************************************************************************
     * Method         : add_option
     * Description    : add new option to subnet
     * args           : $subnet
     *                : $postdata
     * return         : true or false 
     ************************************************************************/
    public function add_option($subnet, $new_opt_data)
    {
        $new_conf = $this->all;
        $org_optdata = array();

        /* check subnet belong to subnet part or shared-network part */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        /* subnet found in subnet data */
        if ($ret === RET_SUBNET) {
  
            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_subnet]
                               [$pos_subnet][STR_OPT_DATA])) {
                /* get current option data */
                $org_optdata = $new_conf[$this->k_dhcp]
                                        [$this->k_subnet]
                                        [$pos_subnet][STR_OPT_DATA];
            }

            if (is_array($org_optdata) === FALSE) {
                $org_optdata = array();
            }

            /* merge array option data */
            $new_opt_data = $this->array_merge_option($org_optdata, $new_opt_data);

            /* add new option to subnet */
            $new_conf[$this->k_dhcp][$this->k_subnet]
                     [$pos_subnet][STR_OPT_DATA]= $new_opt_data;

        /* sbnet found in shared-network data */
        } else if ($ret === RET_SHNET) {

            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_shared]
                               [$pos_shnet]
                               [$this->k_subnet]
                               [$pos_subnet]
                               [STR_OPT_DATA])) {
                /* get current option data */
                $org_optdata = $new_conf[$this->k_dhcp]
                                        [$this->k_shared]
                                        [$pos_shnet]
                                        [$this->k_subnet]
                                        [$pos_subnet]
                                        [STR_OPT_DATA];
            }

            if (is_array($org_optdata) === FALSE) {
                $org_optdata = array();
            }

            /* merge array option data */
            $new_opt_data = $this->array_merge_option($org_optdata, $new_opt_data);

            /* add new option to subnet */
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet]
                     [$pos_subnet]
                     [STR_OPT_DATA] = $new_opt_data;

        }

        return [true, $new_conf];
    }

    /************************************************************************
     * Method         : del_option
     * Description    : delete option in subnet
     * args           : $subnet
     *                : $postdata
     * return         : true or false 
     ************************************************************************/
    public function del_option($subnet, $optionname)
    {
        $new_conf = $this->all;

        /* check subnet belong to subnet part or shared-network part */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        /* subnet found in subnet data */
        if ($ret === RET_SUBNET) {
  
            /* get current option data */
            $org_optdata = $new_conf[$this->k_dhcp]
                                    [$this->k_subnet]
                                    [$pos_subnet][STR_OPT_DATA];

            /* merge array option data */
            $new_opt_data = $this->array_del_option($org_optdata, $optionname);
            if ($new_opt_data === false) {
                $this->result = false;
                $form = _("Option do not exist.");
                $this->err['e_msg'] = $form;
                $log_msg = "Option do not exist(%s)(%s).";
                $this->err['e_log'] = sprintf($log_msg, $subnet, implode(',', $optionname));
                return [false, []];
            }

            /* add new option to subnet */
            $new_conf[$this->k_dhcp][$this->k_subnet]
                     [$pos_subnet][STR_OPT_DATA]= $new_opt_data;

        /* subnet found in shared-network data */
        } else if ($ret === RET_SHNET) {

            /* count number of pools */
            $org_optdata = $new_conf[$this->k_dhcp]
                                    [$this->k_shared]
                                    [$pos_shnet]
                                    [$this->k_subnet]
                                    [$pos_subnet]
                                    [STR_OPT_DATA];

            /* merge array option data */
            $new_opt_data = $this->array_del_option($org_optdata, $optionname);
            if ($new_opt_data === false) {
                $this->result = false;
                $form = _("Option do not exist.");
                $this->err['e_msg'] = $form;
                $log_msg = "Option do not exist(%s)(%s).";
                $this->err['e_log'] = sprintf($log_msg, $subnet, implode(',', $optionname));
                return [false, []];
            }

            /* add new option to subnet */
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet]
                     [$pos_subnet]
                     [STR_OPT_DATA] = $new_opt_data;

        }

        return [true, $new_conf];
    }

    /************************************************************************
     * Method         : get_options
     * Description    : get options from subnet
     * args           : $subnet
     * return         : true or false
     ************************************************************************/
    public function get_options($subnet)
    {
        $new_conf = $this->all;
        $org_optdata = array();

        /* check subnet belong to subnet part or shared-network part */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        /* subnet found in subnet data */
        if ($ret === RET_SUBNET) {
            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_subnet]
                               [$pos_subnet][STR_OPT_DATA])) {
                /* get current option data */
                $org_optdata = $new_conf[$this->k_dhcp]
                                        [$this->k_subnet]
                                        [$pos_subnet][STR_OPT_DATA];
            }
        /* sbnet found in shared-network data */
        } else if ($ret === RET_SHNET) {
            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_shared]
                               [$pos_shnet]
                               [$this->k_subnet]
                               [$pos_subnet]
                               [STR_OPT_DATA])) {
                /* get current option data */
                $org_optdata = $new_conf[$this->k_dhcp]
                                        [$this->k_shared]
                                        [$pos_shnet]
                                        [$this->k_subnet]
                                        [$pos_subnet]
                                        [STR_OPT_DATA];
            }
        }

        return [true, $org_optdata];
    }

    /************************************************************************
     * Method         : edit_range
     * Description    : edit range of subnet in config
     * args           : None
     * return         : true or false 
     ************************************************************************/
    public function edit_range($subnet, $editing_pool, $pool_edit)
    {
        $range_found = false;
        $new_pool = [];
        $new_conf = $this->all;

        /* check subnet belong to shared-network */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* subnet do not exist */
        if ($ret === RET_NOTFOUND) {
            $this->result = false;
            $form = _("Subnet does not exist(%s).");
            $this->err['e_msg'] = sprintf($form, $subnet);
            $log_msg = "Subnet does not exist(%s).";
            $this->err['e_log'] = sprintf($log_msg, $subnet);
            return [false, []];
        }

        /* if subnet exist in subnet part */
        if ($ret === RET_SUBNET) {
            /* if exist range */
            if (isset($new_conf[$this->k_dhcp]
                               [$this->k_subnet][$pos_subnet][STR_POOLS])) {
                foreach ($new_conf[$this->k_dhcp]
                                  [$this->k_subnet]
                                  [$pos_subnet]
                                  [STR_POOLS] as $c_pool => $pool) {
                    /* get pool(min-max) */
                    list($min_pool, $max_pool) = get_kea_pool($this->k_dhcp, 
                                                             $pool[STR_POOL]);
                    $conf_pool = $min_pool. '-'. $max_pool;

                    if ($conf_pool !== $editing_pool) {
                        $new_pool[$c_pool] = $pool;
                    } else {
                        $new_pool[$c_pool] = $pool_edit;
                        $range_found = true;
                    }
                }

                /* overwrite pools */
                $new_conf[$this->k_dhcp]
                         [$this->k_subnet]
                         [$pos_subnet]
                         [STR_POOLS] = $new_pool;
            }

        /* if subnet exist in shared-network part */
        } else if ($ret === RET_SHNET) {
            /* if exist range */
            if (isset($new_conf[$this->k_dhcp][$this->k_shared]
                      [$pos_shnet][$this->k_subnet][$pos_subnet][STR_POOLS])) {
                foreach ($new_conf[$this->k_dhcp][$this->k_shared]
                         [$pos_shnet][$this->k_subnet][$pos_subnet][STR_POOLS]
                         as $c_pool => $pool) {

                    /* get pool(min-max) */
                    list($min_pool, $max_pool) = get_kea_pool($this->k_dhcp,
                                                              $pool[STR_POOL]);
                    $conf_pool = $min_pool. '-'. $max_pool;

                    if ($conf_pool !== $editing_pool) {
                        $new_pool[$c_pool] = $pool;
                    } else {
                        $new_pool[$c_pool] = $pool_edit;
                        $range_found = true;
                    }
                }

                /* overwrite pools */
                $new_conf[$this->k_dhcp][$this->k_shared]
                         [$pos_shnet][$this->k_subnet]
                         [$pos_subnet][STR_POOLS] = $new_pool;
            }
        }

        /* editing range do not exist in this subnet */
        if (!$range_found) {
            $this->result = false;
            $form = _("Editing Pool IP address range do not exist.(%s)");
            $this->err['e_msg'] = sprintf($form, $editing_pool);
            $log_msg = "Editing Pool IP address range do not exist.(%s)(%s)";
            $this->err['e_log'] = sprintf($log_msg, $subnet, $editing_pool);
            return [false, []];
        }

        return [true, $new_conf];
    }

    /************************************************************************
    * Method         : get_max_subnetid
    * Description    : get max subnet id in config
    *                  if subnet_id do not exist then return  1
    * args           : $dhcpver dhcp4 or dhcp6
    * return         : $max_id
    ************************************************************************/
    public function get_max_subnetid()
    {
        /* max subnet id in config */
        $max_id = 0;

        /* get subnet part only */
        $shared_part = $this->get_shared_part();

        /* if shared part is not null */
        if ($shared_part !== null) {
            foreach ($shared_part as $shared) {

                /* if subnet in shared part not empty */
                if (!empty($shared[$this->k_subnet])){
                    foreach ($shared[$this->k_subnet] as 
                                                $key_num => $sh_subnet) {

                        if (isset($sh_subnet[STR_ID]) && 
                                            ($sh_subnet[STR_ID] > $max_id)) {

                            /* get max id */
                            $max_id = $sh_subnet[STR_ID];
                        }
                    }
                }
            }
        }

        /* get subnet part only */
        $subnet_part = $this->get_subnet_part();

        /* if subnet part is not null */
        if ($subnet_part !== null) {
            foreach ($subnet_part as $subnet) {
                if (isset($subnet[STR_ID]) && ($subnet[STR_ID] > $max_id)) {

                    /* get max id */
                    $max_id = $subnet[STR_ID];
                }
            }
        }

        /* plus 1 */
        $max_id = $max_id + 1;

        return $max_id;
    }

    /************************************************************************
    * Method         : config_write
    * Description    : write config
    * args           : $config
    * return         : true or false
    ************************************************************************/
    public function config_write($config)
    {
        /* create object KeaAPI */
        $ins_kea = new KeaAPI($this->_dhcpver);

        /* occur error when update config */
        if ($ins_kea->errmsg !== NULL) {
            $this->result = false;
            $this->err['e_msg'] = _("Cannot update config file. Dhcp start-up error?");
            $log_msg = "Cannot update config file. Dhcp start-up error?(%s)";
            $this->err['e_log'] = sprintf($log_msg, $ins_kea->errmsg);
            return false;
        }

        /* write config */
        $ins_kea->dg_config_overwrite($config);

        /* occur error when write config */
        if ($ins_kea->errmsg !== NULL) {
            $this->result = false;
            $form = _('Cannot write config.(%s)');
            $this->err['e_msg'] = sprintf($form, $ins_kea->errmsg);
            $this->err['e_log'] = sprintf("Cannot write config.(%s)", 
                                  $ins_kea->errmsg);
            return false;
        }

        return true;
    }

    /************************************************************************
    * Method         : config_reflect
    * Description    : write config from session to runing config and file
    * args           : none
    * return         : true or false
    ************************************************************************/
    public function config_reflect()
    {
        /* get config from session */
        $dhcp_conf_sess =  $this->get_conf_from_sess();
        if ($dhcp_conf_sess === NULL) {
            return true;
        }

        /* get hist from session */
        $dhcp_hist_sess =  $this->get_hist_from_sess();
        if ($dhcp_hist_sess === NULL) {
            return true;
        }

        /* delete hash value */
        unset($dhcp_conf_sess['hash']);
        /* reflect config from session */
        $ret = $this->config_write($dhcp_conf_sess);

        return $ret;
    }

    /************************************************************************
    * Method         : save_conf_to_sess
    * Description    : save config of dhcpv4/v6 to sesssion
    * args           : $config
    * return         : void
    ************************************************************************/
    public function save_conf_to_sess($config)
    {
        $_SESSION[$this->k_sess_conf] = $config;
    }

    /************************************************************************
    * Method         : delete_hist_from_sess
    * Description    : delete history operation to sesssion
    * args           : $config
    * return         : void
    ************************************************************************/
    public function delete_hist_from_sess()
    {
        $_SESSION[$this->k_sess_hist] = NULL;
    }

    /************************************************************************
    * Method         : get_conf_from_sess
    * Description    : get config of dhcpv4/v6 from sesssion
    * args           : none
    * return         : void
    ************************************************************************/
    public function get_conf_from_sess()
    {
        if (!isset($_SESSION[$this->k_sess_conf])) {
            return NULL;
        }
        return $_SESSION[$this->k_sess_conf];
    }

    /************************************************************************
    * Method         : save_hist_to_sess
    * Description    : save history operation to sesssion history (array)
    * args           : $hist_new
    * return         : void
    ************************************************************************/
    public function save_hist_to_sess($hist_new)
    {
        /* if do not exist history */
        if (!isset($_SESSION[$this->k_sess_hist])) {
            $_SESSION[$this->k_sess_hist] = array($hist_new);

        /* if history existed then push new history to array history */
        } else {
            array_push($_SESSION[$this->k_sess_hist], $hist_new);
        }
    }

    /************************************************************************
    * Method         : get_hist_from_sess
    * Description    : save history operation to sesssion
    * args           : $hist_text
    * return         : void
    ************************************************************************/
    public function get_hist_from_sess()
    {
        if (!isset($_SESSION[$this->k_sess_hist])) {
            return NULL;
        }

        return $_SESSION[$this->k_sess_hist];
    }

    /************************************************************************
    * Method         : search_shared4
    * Description    : Check shared4 and forward search
    * args           : $shared
    * return         : true or false
    ************************************************************************/
    public function search_shared4($cond = null, $mode = 'all')
    {
        /* check presence dhcp4 configuration */
        if (empty($this->dhcp4)) {
            $this->err['e_msg'] = _("Cannot read configuration.");
            $this->err['e_log'] = "Cannot read configuration.";

            return [false, []];
        }

        /* check presence shared-networks configuration */
        if (empty($this->dhcp4[STR_SHARED][0])) {
            $form = _("Shared-network is empty.");
            $this->err['e_msg'] = sprintf($form, null);

            return [false, []];
        }

        $result = [];

        /* loop dhcp4 shared-networks4 */
        foreach ($this->dhcp4[STR_SHARED] as $i => $shared) {

            /* get shared_networks name */
            $result[$i][STR_NAME] = 
                            $this->dhcp4[STR_SHARED][$i][STR_NAME];

        }

        /* check result */
        if (empty($result)) {
            $this->err['e_msg'] = _('Shared-network is empty.');

            return [false, []];
        }

        return [true, $result];
    }

    /************************************************************************
    * Method         : search_shared6
    * Description    : Check shared6 and forward search
    * args           : $shared
    * return         : $result
    ************************************************************************/
    public function search_shared6($cond = null, $mode = 'all')
    {
        /* check presence dhcp6 configuration */
        if (empty($this->dhcp6)) {
            $this->err['e_msg'] = _("Cannot read configuration.");
            $this->err['e_log'] = "Cannot read configuration.";

            return [false, []];
        }

        /* check presence shared-networks configuration */
        if (empty($this->dhcp6[STR_SHARED][0])) {
            $this->err['e_msg'] = _("Shared-network is empty.");

            return [false, []];
        }

        $result = [];

        /* loop dhcp6 shared-networks6 */
        foreach ($this->dhcp6[STR_SHARED] as $i => $shared) {

            /* get shared_networks name */
            $result[$i][STR_NAME] =
                            $this->dhcp6[STR_SHARED][$i][STR_NAME];

        }

        /* check result */
        if (empty($result)) {
            $this->err['e_msg'] = _('Shared-network is empty.');
            return [false, []];
        }

        return [true, $result];
    }

    /************************************************************************
    * Method         : get_shared_part
    * Description    : get shared part only
    * args           : $config
    * return         : shared part or NULL
    ************************************************************************/
    public function get_shared_part($config = null)
    {
        if ($config === null) {
            $config = $this->all;
        }
        if (isset($config[$this->k_dhcp][$this->k_shared])) {
            return $config[$this->k_dhcp][$this->k_shared];
        }

        return null;
    }

    /************************************************************************
    * Method         : add_shared_name
    * Description    : add new shared_name to config
    * args           : None
    * return         : false or new_config
    ************************************************************************/
    public function add_shared_name($shared_data)
    {
        if (!isset($this->all[$this->k_dhcp][$this->k_shared])) {

            /* make shared-networks array */
            $this->all[$this->k_dhcp][$this->k_shared] = array() ;
        }

        /* count number of shared */
        $count_shared =  count_array($this->all[$this->k_dhcp][$this->k_shared]);

        $new_config = $this->all;
        $new_config[$this->k_dhcp][$this->k_shared][$count_shared] = 
                                                              $shared_data;


        return $new_config;
    }

    /************************************************************************
    * Method         : get_other_subnet
    * Description    : get other subnet
    * args           : None
    * return         : $other_subnet
    ************************************************************************/
     public function get_other_subnet()
    {

        $other_subnet = [];

        /* edit config data */
        $config = $this->all;

        /* get other subnet */
        foreach ($config[$this->k_dhcp][$this->k_subnet] as $subnet) {
            $other_subnet[] = $subnet[STR_SUBNET];
        }
        return $other_subnet;
    }

    /************************************************************************
    * Method         : get_shared_subnet
    * Description    : get shared subnet
    * args           : $name            name of shared-network
    * return         : $shared_subnet
    ************************************************************************/
     public function get_shared_subnet($name)
    {
        $shared_subnet = [];
        $config = $this->all;

        /* get shared subnet */
        foreach ($config[$this->k_dhcp][$this->k_shared] as $shared) {
            if ($shared[STR_NAME] == $name) {
                if (!isset($shared[$this->k_subnet])) {
                    break;
                }
                foreach ($shared[$this->k_subnet] as $subnet) {
                    $shared_subnet[] = $subnet[STR_SUBNET];
                }
                break;
            }
        }
        return $shared_subnet;
    }

    /************************************************************************
    * Method         : get_shared_params
    * Description    : get shared params(interface, interface-id, relay)
    * args           : $name            name of shared-network
    * return         : $shared_params
    ************************************************************************/
     public function get_shared_params($name)
    {
        $shared_subnet = [];
        $config = $this->all;

        /* get shared subnet */
        foreach ($config[$this->k_dhcp][$this->k_shared] as $shared) {
            if ($shared[STR_NAME] == $name) {
                $shared_params = $shared;
                break;
            }
        }
        return $shared_params;
    }

    /************************************************************************
    * Method         : delete_shared_network
    * Description    : delete shared_network to config
    * args           : $delete_data
    * return         : false or new_config
    ************************************************************************/
    public function delete_shared_network($delete_data)
    {
        $new_conf = $this->all;

        /* delete shared-network */
        foreach ($new_conf[$this->k_dhcp][$this->k_shared] as $key => $shared) {

            /* edit new name */
            if ($shared[STR_NAME] === $delete_data) {

                unset($new_conf[$this->k_dhcp]
                               [$this->k_shared]
                               [$key]);
            }
        }

        /* shared_network 並び替え */
        $new_shared = array_values($new_conf[$this->k_dhcp]
                                               [$this->k_shared]);
        $new_conf[$this->k_dhcp][$this->k_shared] = $new_shared;

        return $new_conf;
    }

    /************************************************************************
    * Method         : edit_shared_network
    * Description    : edit shared_name and shared_subnet to config
    * args           : None
    * return         : false or new_config
    ************************************************************************/
    public function edit_shared_network($edit_data)
    {

        $pos_shnet = "";
        $pos_subnet = "";
        $new_conf = $this->all;

        /* edit new name */
        foreach ($new_conf[$this->k_dhcp][$this->k_shared] as $key => $shared) {

            /* edit new name */
            if ($shared[STR_NAME] === $edit_data["old_shared_name"]) {
                $new_conf[$this->k_dhcp][$this->k_shared][$key][STR_NAME] = $edit_data["shared_name"];

                /* Processing for dhcpv6 */
                isset($edit_data["interface"]) && $new_conf[$this->k_dhcp][$this->k_shared][$key][STR_INTERFACE] = $edit_data["interface"];
                isset($edit_data["interface-id"]) && $new_conf[$this->k_dhcp][$this->k_shared][$key][STR_INTERFACEID] = $edit_data["interface-id"];
                isset($edit_data["relay"]) && $new_conf[$this->k_dhcp][$this->k_shared][$key][STR_RELAY] = $edit_data["relay"];
                break;
            }
        }
        /* get all shared-network subnet */
        $shared_subnet = 
                $this->get_shared_subnet($edit_data["old_shared_name"]);


        /* add shared_subnet */
        if (!empty($edit_data["shared_subnet"])) {
	        foreach ($edit_data["shared_subnet"] as $add_subnet) {
                $count_shared = 0;
                if (isset($new_conf[$this->k_dhcp]
                                   [$this->k_shared]
                                   [$key]
                                   [$this->k_subnet])) {
                    /* count number of shared */
                    $count_shared = count_array($new_conf[$this->k_dhcp]
                                                         [$this->k_shared]
                                                         [$key]
                                                         [$this->k_subnet]);
                }

                /* add shared_subnet */
                $ret = array_search($add_subnet, $shared_subnet);
                if ($ret === false) {
                    $ret = $this->check_subnet_belongto($add_subnet, $pos_subnet, $pos_shnet);

                    /* if subnet exist in global part */
                    if ($ret === RET_SUBNET) {
              
                    $new_conf[$this->k_dhcp]
                	         [$this->k_shared]
                	         [$key]
                	         [$this->k_subnet]
                	         [$count_shared] = 
                	$new_conf[$this->k_dhcp]
                		     [$this->k_subnet]
                		     [$pos_subnet];
              
                    unset($new_conf[$this->k_dhcp]
                                   [$this->k_subnet]
                                   [$pos_subnet]);
                    }
                }
            }
        }

        if (!empty($new_conf[$this->k_dhcp][$this->k_subnet])) {

            /* subnet 並び替え */
            $new_subnet = array_values($new_conf[$this->k_dhcp]
                                                   [$this->k_subnet]);

            $new_conf[$this->k_dhcp][$this->k_subnet] = $new_subnet;
        }

        /* del shared_subnet */
        foreach ($shared_subnet as $del_subnet) {

            /* count number of shared */
            $count_subnet = count_array($new_conf[$this->k_dhcp]
                                           [$this->k_subnet]);

            /* delete shared_subnet */
            $ret = FALSE;
            if (!empty($edit_data["shared_subnet"])) {
                $ret = array_search($del_subnet, $edit_data["shared_subnet"]);
            }

	    if ($ret === false || !isset($edit_data["shared_subnet"])) {
                $ret = $this->check_subnet_belongto
                                   ($del_subnet, $pos_subnet, $pos_shnet);

		/* shared_subnet unset */
                if ($ret === RET_SHNET) {

                    $new_conf[$this->k_dhcp]
                             [$this->k_subnet]
                             [$count_subnet] =
                                $new_conf[$this->k_dhcp]
                                         [$this->k_shared]
                                         [$pos_shnet]
                                         [$this->k_subnet]
                                         [$pos_subnet];

                    unset($new_conf[$this->k_dhcp]
                                   [$this->k_shared]
                                   [$pos_shnet]
                                   [$this->k_subnet]
                                   [$pos_subnet]);

                }
            }
        }

        if (!empty($new_conf[$this->k_dhcp]
                            [$this->k_shared]
                            [$pos_shnet]
                            [$this->k_subnet])) {

            /* shared_part 並び替え */
            $new_shared = array_values($new_conf[$this->k_dhcp]
                                               [$this->k_shared]
                                               [$pos_shnet]
                                               [$this->k_subnet]);
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet] = $new_shared;

        }
  
        return $new_conf;
    }

    /*************************************************************************
    * Method        : get_interfaces
    * Description   : Method for get network-interfaces
    * args          : None
    * return        : [true, $interfaces] or [false, []]
    *************************************************************************/
    public function get_interfaces()
    {
        $interfaces = [''];
        if (empty($this->dhcp6['interfaces-config']['interfaces']) || in_array('*', $this->dhcp6['interfaces-config']['interfaces']))
        {
            $server_if = net_get_interfaces();
            if ($server_if === false)
            {
                $this->err['e_msg'] = _('Failed to get network-interfaces.');
                $this->err['e_log'] = 'Failed to get network-interfaces.';
                return [false, []];
            }
            /* delete lo */
            unset($server_if['lo']);
            $if_names = array_keys($server_if);
            $interfaces = array_merge($interfaces, $if_names);
            return [true, $interfaces];
        }
        foreach($this->dhcp6['interfaces-config']['interfaces'] as $sess_if)
        {
            $interfaces[] = explode('/', $sess_if)[0];
        }

        return [true, $interfaces];
    }

    /*************************************************************************
    * Method        : get_one_subnet
    * Description   : Method for get one subnet data
    * args          : None
    * return        : [true, $subnetdata] or [false, []]
    *************************************************************************/
    public function get_one_subnet($subnet)
    {
        $subnetdata = [];
        $is_exist = false;
    
        /* check subnet part */
        $subnet_part = $this->get_subnet_part();
        foreach($subnet_part as $subnet_data) {
            if ($subnet_data[STR_SUBNET] === $subnet) {
                $subnetdata = $subnet_data;
                $is_exist = true;
                break;
            }
        }

        if ($is_exist === true) {
            return [true, $subnetdata];
        }

        /* check shared part */
        $shared_part = $this->get_shared_part();

        foreach ($shared_part as $shared_data) {
            foreach ($shared_data[$this->k_subnet] as $shared_subnet) {
                if ($shared_subnet[STR_SUBNET] === $subnet) {
                    $subnetdata = $shared_subnet;
                    $is_exist = true;
                }
            }
        }

        if ($is_exist === true) {
            return [true, $subnetdata];
        }

        $this->err['e_msg'] = _('Subnet does not exist in config.');
        $this->err['e_log'] = 'Subnet does not exist in config.';
        return [false, []];
    }

    /*************************************************************************
    * Method        : edit_subnet_config
    * Description   : Method for edit subnet config
    * args          : $subnet
    * return        : [true, $subnetdata] or [false, []]
    *************************************************************************/
    public function edit_subnet_config($subnet, $position, $subnetdata) {
        $new_conf = $this->all;

        foreach ($subnetdata as $key => $data) {
            /* uodate subnetdata */
            $new_conf[$this->k_dhcp]
                     [$this->k_subnet]
                     [$position][$key] = $data;
        }

        return $new_conf;
    }

    /************************************************************************
    * Method         : del_pd_pools
    * Description    : del pd-pools in config
    * args           : None
    * return         : true or false
    ************************************************************************/
    public function del_pd_pools($subnet, $prefix, $pos_subnet, $pos_shnet = null)
    {
        $delete_pos = null;
        /* get pd-pools position */
        [$ret, $subnetdata] = $this->get_one_subnet($subnet);
        foreach ($subnetdata[STR_PD_POOLS] as $key => $value) {
            if ($value['prefix'] === $prefix) {
                $delete_pos = $key;
                break;
            }
        }

        /* current config */
        $new_conf = $this->all;

        /* if subnet exist in subnet part */
        if ($pos_shnet === null) {
            unset($new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet][STR_PD_POOLS][$delete_pos]);
            /* rearrangement of sequence numbers  */
            $new_pd_pools = array_values($new_conf[$this->k_dhcp]
                                                   [$this->k_subnet]
                                                   [$pos_subnet]
                                                   [STR_PD_POOLS]);
            /* set new pd-pools  */
            $new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet][STR_PD_POOLS] = $new_pd_pools;
        /* if subnet exist in shared-network part */
        } else {
            unset($new_conf[$this->k_dhcp]
                           [$this->k_shared]
                           [$pos_shnet]
                           [$this->k_subnet]
                           [$pos_subnet]
                           [STR_PD_POOLS]
                           [$delete_pos]);
            /* rearrangement of sequence numbers */
            $new_pd_pools = array_values($new_conf[$this->k_dhcp]
                                                   [$this->k_shared]
                                                   [$pos_shnet]
                                                   [$this->k_subnet]
                                                   [$pos_subnet]
                                                   [STR_PD_POOLS]);
            /* set new pd-pools  */
            $new_conf[$this->k_dhcp]
                     [$this->k_shared]
                     [$pos_shnet]
                     [$this->k_subnet]
                     [$pos_subnet]
                     [STR_PD_POOLS] = $new_pd_pools;

        }

        return [true, $new_conf];
    }

    /************************************************************************
    * Method         : add_pd_pools
    * Description    : add pd-pools in config
    * args           : None
    * return         : true or false
    ************************************************************************/
    public function add_pd_pools($subnet, $pd_pools, $pos_subnet, $pos_shnet = null)
    {
        /* current config */
        $new_conf = $this->all;

        if ($pos_shnet === null) {
            $new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet][STR_PD_POOLS][] = $pd_pools;
        } else {
            $new_conf[$this->k_dhcp][$this->k_shared][$pos_shnet][$this->k_subnet][$pos_subnet][STR_PD_POOLS][] = $pd_pools;
        }

        return [true, $new_conf];
    }

    /************************************************************************
    * Method         : edit_pd_pools
    * Description    : edit pd-pools in config
    * args           : None
    * return         : true or false
    ************************************************************************/
    public function edit_pd_pools($subnet, $pd_pools, $pos_pd_pools, $pos_subnet, $pos_shnet = null)
    {
        /* current config */
        $new_conf = $this->all;

        if ($pos_shnet === null) {
            $new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet][STR_PD_POOLS][$pos_pd_pools] = 
                array_merge($new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet][STR_PD_POOLS][$pos_pd_pools], $pd_pools);
        } else {
            $new_conf[$this->k_dhcp][$this->k_shared][$pos_shnet][$this->k_subnet][$pos_subnet][STR_PD_POOLS][$pos_pd_pools] =
                array_merge($new_conf[$this->k_dhcp][$this->k_shared][$pos_shnet][$this->k_subnet][$pos_subnet][STR_PD_POOLS][$pos_pd_pools], $pd_pools);
        }
        return [true, $new_conf];
    }

    /************************************************************************
    * Method         : survey_option82_pools
    * Description    : Survey option82 pools from the pools array
    * args           : arr - pools
                       mode
                         - exclude: Returns an array of pools other than optin82
                         - include: Returns an array of optin82 pools
                         - search: Returns true/false if a pool of option82 exists
    * return         : [true, result] or [false, []]
    ************************************************************************/
    public function survey_option82_pools($pools, $mode = 'exclude') {
        /* return array */
        $result_pools = [];
        $opt82_found = false;

        foreach ($pools as $index => $one_pool) {
            if (isset($one_pool[STR_CLIENT_CLASS])) {
                if (preg_match('/^opt82_/', $one_pool[STR_CLIENT_CLASS]) === 1) {
                    switch ($mode) {
                        case 'include':
                            $result_pools[$one_pool[STR_CLIENT_CLASS]] = $one_pool;
                        case 'search':
                            $opt82_found = true;
                            break;
                    }
                } else {
                    if ($mode === 'exclude') {
                        $result_pools[$index] = $one_pool;
                    }
                }
            } else {
                if ($mode === 'exclude') {
                    $result_pools[$index] = $one_pool;
                }
            }
        }

        /* search mode processing */
        if ($mode === 'search') {
            if ($opt82_found === true) {
                return [true, []];
            } else {
                return [false, []];
            }
        }

        /* In include or exclude mode, if the result is empty */
        if (empty($result_pools)) {
            return [false, []];
        } 

        return [true, $result_pools];
    }

    /************************************************************************
    * Method         : get_client_class
    * Description    : Obtain client class settings
    * args           : mode
                         - all: Get settings for all client classes
                         - option82: Get only the client class setting for option82
    * return         : [true, result] or [false, []]
    ************************************************************************/
    public function get_client_class($mode = 'all') {
        /* Get settings for all client classes */
        $new_conf = $this->all;
        if (empty($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES])) {
            return [false, []];
        }

        /* If mode is all, returns all client classes */
        if ($mode === 'all') {
            return [true, $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES]];
        }

        /* If mode is option82, returns a client class starting with opt82_. */
        $option82_classes = [];
        foreach ($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES] as $client_classes) {
            if (isset($client_classes[STR_NAME]) && preg_match('/^opt82_/', $client_classes[STR_NAME]) === 1) {
                $option82_classes[] = $client_classes;
            }
        }

        return [true, $option82_classes];

    }

    /************************************************************************
    * Method         : format_test_value
    * Description    : Format the value of test
    * args           : string
                       identifier
                         - circuit_id
                         - remote_id
                         - mac_address
    * return         : [true, result, flg_hexed] or [false, "", flg_hexed]
    ************************************************************************/
    public function format_test_value($test_val, $identifier) {
        $flg_hexed = 'false';
        switch ($identifier) {
            case 'circuit_id':
                $circuit_id = '';
                /* get circuit-id */
                /* Match hexadecimal notation value */
                /*
                   hexadecimal notation circuit-id is in the following format
                   relay[1].hex == 0x****
                */
                if (preg_match('/relay4\[1\]\.hex == 0x\S+/', $test_val, $matches) === 1) {
                    $hexed_circuit = explode(' ', $matches[0])[2];
                    if (empty($hexed_circuit)) {
                        return [false, "", $flg_hexed];
                    }

                    $circuit_id = hex2bin(str_replace('0x', '', $hexed_circuit));
                    if ($circuit_id === false) {
                        return [false, "", $flg_hexed];
                    }

                }

                /* Matches values other than hexadecimal notation */
                /*
                   non-hexadecimal notation circuit-id is in the following format
                   relay[1].hex == '****'
                */
                else if (preg_match('/relay4\[1\]\.hex == \'.*?\'/', $test_val, $matches) === 1) {
                    $flg_hexed = 'true';
                    $circuit_id = explode('\'', $matches[0])[1];
                    if (empty($circuit_id)) {
                        return [false, "", $flg_hexed];
                    }
                }
                return [true, $circuit_id, $flg_hexed];

            case 'remote_id':
                $remote_id = '';
                /* get remote-id */
                /* Match hexadecimal notation value */
                /*
                   hexadecimal notation remote-id is in the following format
                   relay[2].hex == 0x****
                */
                if (preg_match('/relay4\[2\]\.hex == 0x\S+/', $test_val, $matches) === 1) {
                    $hexed_remote = explode(' ', $matches[0])[2];
                    if (empty($hexed_remote)) {
                        return [false, "", $flg_hexed];
                    }
                    $remote_id = hex2bin(str_replace('0x', '', $hexed_remote));
                    if ($remote_id === false) {
                        return [false, "", $flg_hexed];
                    }
                }

                /* Matches values other than hexadecimal notation */
                /*
                   non-hexadecimal notation remote-id is in the following format
                   relay[2].hex == '****'
                */
                else if (preg_match('/relay4\[2\]\.hex == \'.*?\'/', $test_val, $matches) === 1) {
                    $flg_hexed = 'true';
                    $remote_id = explode('\'', $matches[0])[1];
                    if (empty($remote_id)) {
                        return [false, "", $flg_hexed];
                    }
                }
                return [true, $remote_id, $flg_hexed];

            case 'mac_address':
                $mac_address = '';
                /* get mac_address */
                /*
                   mac_address is in the following format
                   pkt4.mac == 0x****
                */
                if (preg_match('/pkt4\.mac == 0x\S+/', $test_val, $matches) === 1) {
                    $org_mac = explode(' ', $matches[0])[2];
                    if (empty($org_mac)) {
                        return [false, "", $flg_hexed];
                    }
                    $mac_address = add_colon(str_replace('0x', '', $org_mac));
                }
                return [true, $mac_address, $flg_hexed];
        }
    }

    /************************************************************************
    * Method         : delete_option82
    * Description    : Deletion of the client class and the settings associated with the client class
    * args           : class_name
                       additional (Settings tied to the client class)
                         - pool
                       subnet (Required if additional is specified)
    * return         : $new_conf
    ************************************************************************/
    public function delete_option82($class_name, $subnet = null) {
        $flg_del_no_member = false;
        $new_conf = $this->all;
    
        /* delete client class */
        foreach($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES] as $index => $client_classes) {
            if ($client_classes[STR_NAME] === $class_name) {
                unset($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][$index]);
            }
            if ($client_classes[STR_NAME] === STR_NO_MEMBER) {
                /* delete class name from no-member */
                $no_member = $client_classes[STR_TEST];
                /* If the client class is the last one, delete the no-member class as well */
                /* 
                    no-member class is subject to the following conditions
                    not (member('opt82_bas_xxxx') or member('opt82_bas_xxxx') or member('opt82_adv_xxxx') ....)

                    The pattern does not match if there is only one client class, as in the following
                    not (member('opt82_bas_xxxx'))
                */
                $pattern = "/member\('$class_name'\) or | or member\('$class_name'\)/";
                $new_no_member = preg_replace($pattern, "", $no_member);
                if ($no_member === $new_no_member) {
                    $flg_del_no_member = true;
                    unset($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][$index]);
                } else {
                    $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][$index][STR_TEST] = $new_no_member;
                }
            }
        }

        /* Organize sequence numbers */
        $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES] = array_values($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES]);

        /* When the no-member client class is deleted, remove the no-member ties from all pools. */
        if (!empty($new_conf[$this->k_dhcp][STR_SHARED])) {
            foreach ($new_conf[$this->k_dhcp][STR_SHARED] as $sh_pos => $shared_networks) {
                foreach ($shared_networks[$this->k_subnet] as $sh_subpos => $sh_subnet) {
                    foreach ($sh_subnet[STR_POOLS] as $sh_poolpos => $sh_pools) {
                        if ($flg_del_no_member === true) {
                            if ($sh_pools[STR_CLIENT_CLASS] === STR_NO_MEMBER) {
                                unset($new_conf[$this->k_dhcp][STR_SHARED][$sh_pos][$this->k_subnet][$sh_subpos][STR_POOLS][$sh_poolpos][STR_CLIENT_CLASS]);
                            }
                        }
                        if ($sh_pools[STR_CLIENT_CLASS] === $class_name) {
                            unset($new_conf[$this->k_dhcp][STR_SHARED][$sh_pos][$this->k_subnet][$sh_subpos][STR_POOLS][$sh_poolpos]);
                        }
                        $new_conf[$this->k_dhcp][STR_SHARED]
                                                [$sh_pos]
                                                [$this->k_subnet]
                                                [$sh_subpos]
                                                [STR_POOLS]
                                                = array_values($new_conf[$this->k_dhcp][STR_SHARED][$sh_pos][$this->k_subnet][$sh_subpos][STR_POOLS]);
                    }
                }
            }
        }

        if (!empty($new_conf[$this->k_dhcp][$this->k_subnet])) {
            foreach ($new_conf[$this->k_dhcp][$this->k_subnet] as $subpos => $gl_subnet) {
                foreach ($gl_subnet[STR_POOLS] as $poolpos => $gl_pools) {
                    if ($flg_del_no_member === true) {
                        if ($gl_pools[STR_CLIENT_CLASS] === STR_NO_MEMBER) {
                            unset($new_conf[$this->k_dhcp][$this->k_subnet][$subpos][STR_POOLS][$poolpos][STR_CLIENT_CLASS]);
                        }
                    }
                    if ($gl_pools[STR_CLIENT_CLASS] === $class_name) {
                        unset($new_conf[$this->k_dhcp][$this->k_subnet][$subpos][STR_POOLS][$poolpos]);
                    }
                    $new_conf[$this->k_dhcp][$this->k_subnet]
                                            [$subpos]
                                            [STR_POOLS]
                                            = array_values($new_conf[$this->k_dhcp][$this->k_subnet][$subpos][STR_POOLS]);
                }
            }
        }

        return $new_conf;
    }

    /************************************************************************
    * Method         : create_payout_conditions
    * Description    : Deletion of the client class and the settings associated with the client class
    * args           : circuit_id
                       remote_id
                       mac_address
    * return         : $string
    ************************************************************************/
    public function create_payout_condition($circuit_id = null, $remote_id = null, $mac_address = null) {
        $identifiers = [];
        if (!is_null($circuit_id)) {
            $identifiers[] = "relay4[1].hex == " . $circuit_id;
        }

        if (!is_null($remote_id)) {
            $identifiers[] = "relay4[2].hex == " . $remote_id;
        }

        if (!is_null($mac_address)) {
            $identifiers[] = 'pkt4.mac == ' . $mac_address;
        }

        $payout_condition = implode(' and ', $identifiers);
        return $payout_condition;
    } 

    /************************************************************************
    * Method         : create_class_name
    * Description    : Create class name for option82.
    * args           : type
                       - bas
                       - adv
    * return         : $string
    ************************************************************************/
    public function create_class_name($type = 'bas') {
        $datetime = new DateTime();
        $now = $datetime->format("YmdHisu");
        $class_name = 'opt82_' . $type . '_' . $now;
        return $class_name;
    }

    /************************************************************************
    * Method         : add_option82
    * Description    : Adding option82 setting 
    * args           : params
                       subnet
    * return         : [true, $new_conf] or [false, $new_conf]
    ************************************************************************/
    public function add_option82($params, $subnet) {
        /* make add data */
        $class_data = [];
        $pool_data = [];
        $circuit_id = null;
        $remote_id = null;
        $mac_address = null;

        $flg_make_nomember = false;

        if ($params['is_advanced'] === 'true') {
            /* class name */
            $class_data = [
                STR_NAME => $this->create_class_name('adv'),
                STR_TEST => $params['advanced_setting'],
            ];
        } else {
            /* Circuit-ID */
            if ($params['circuit_id'] !== '') {
                if ($params['no_hex_circuit'] === 'true') {
                    $circuit_id = "'" . $params['circuit_id'] . "'";
                } else {
                    $circuit_id = '0x' . bin2hex($params['circuit_id']);
                }
            }

            /* Remote-ID */
            if ($params['remote_id'] !== '') {
                if ($params['no_hex_remote'] === 'true') {
                    $remote_id = "'" . $params['remote_id'] . "'";
                } else {
                    $remote_id = '0x' . bin2hex($params['remote_id']);
                }
            }

            /* MAC address */
            if ($params['mac_address'] !== '') {
                $mac_address = '0x' . remove_colon($params['mac_address']);
            }

            $class_data = [
                STR_NAME => $this->create_class_name('bas'),
                STR_TEST => $this->create_payout_condition($circuit_id, $remote_id, $mac_address),
            ];
        }
        /* pool data */
        $pool_data = [
            STR_POOL         => $params['pool_start'] . '-' . $params['pool_end'],
            STR_CLIENT_CLASS => $class_data[STR_NAME],
        ];

        /* current config */
        $new_conf = $this->all;

        /* When the client class exists and the no-member class also exists */
        if (!empty($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES]) 
            && in_array(STR_NO_MEMBER, array_column($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES], STR_NAME)) === true) 
        {
            /* 
                Add the name of the client class you created to the no-member condition
                The no-member condition is described as follows
                not (member('opt82_bas_YYYYMMDDhhmmssnnnnnn') or member('opt82_bas_YYYYMMDDhhmmssnnnnnn') or member('opt82_bas_YYYYMMDDhhmmssnnnnnn') ...)

                Add the client class name after member('opt82_bas_YYYYYYMMDDhhmmssnnnnnn')
            */
            $pos_nomember = array_key_last($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES]);
            if (preg_match('/not \((.*)\)/', $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][$pos_nomember][STR_TEST], $matches) === 1) {
                /* Update no-member conditions */
                $new_member = $matches[1] . " or member('" . $class_data[STR_NAME] . "')";
                $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][$pos_nomember][STR_TEST]
                    = 'not (' . $new_member . ')';
            } else {
                $this->err['e_msg'] = _('Failed to add option82 setting.');
                $this->err['e_log'] = 'Failed to add option82 setting.';
                return [false, []];
            }
            /* Insert the class created just before no-member because the no-member class must exist at the end of the array */
            array_splice($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES], $pos_nomember, 0, array($class_data));
            $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES] = array_values($new_conf[$this->k_dhcp][STR_CLIENT_CLASSES]);
        } else {
            /* insert class-data into client-classes */
            $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][] = $class_data;
            /* If no-member class does not exist, create it */
            $new_conf[$this->k_dhcp][STR_CLIENT_CLASSES][] = [
                STR_NAME => STR_NO_MEMBER,
                STR_TEST => "not (member('" . $class_data[STR_NAME] . "'))",
            ];
        }

        /* Add pool-data */
        $ret = $this->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);
        if ($ret === RET_SHNET) {
            $new_conf[$this->k_dhcp][STR_SHARED][$pos_shnet][$this->k_subnet][$pos_subnet][STR_POOLS][] = $pool_data;
        } else if ($ret === RET_SUBNET) {
            $new_conf[$this->k_dhcp][$this->k_subnet][$pos_subnet][STR_POOLS][] = $pool_data;
        }

        return [true, $new_conf];
    }

    /************************************************************************
    * Method         : assign_nomember
    * Description    : Assignment of no-member class
    * args           : $conf
    * return         : $new_conf
    ************************************************************************/
    public function assign_nomember($conf) {
        $new_conf = $conf;
        /* Surveying pools in the shared network */
        foreach ($conf[$this->k_dhcp][STR_SHARED] as $sh_pos => $shared) {
            foreach ($shared[$this->k_subnet] as $sub_pos => $subnet) {
                foreach($subnet[STR_POOLS] as $pool_pos => $pools) {
                    if (!isset($pools[STR_CLIENT_CLASS])) {
                        $new_conf[$this->k_dhcp][STR_SHARED]
                                                [$sh_pos]
                                                [$this->k_subnet]
                                                [$sub_pos]
                                                [STR_POOLS]
                                                [$pool_pos]
                                                [STR_CLIENT_CLASS] = STR_NO_MEMBER;
                    }
                }
            }
        }

        /* Examine a pool within a subnet */
        foreach ($conf[$this->k_dhcp][$this->k_subnet] as $sub_pos => $subnet) {
            foreach ($subnet[STR_POOLS] as $pool_pos => $pools) {
                if (!isset($pools[STR_CLIENT_CLASS])) {
                    $new_conf[$this->k_dhcp][$this->k_subnet]
                                            [$sub_pos]
                                            [STR_POOLS]
                                            [$pool_pos]
                                            [STR_CLIENT_CLASS] = STR_NO_MEMBER;
                }
            }
        }

        return $new_conf;
    }
}
