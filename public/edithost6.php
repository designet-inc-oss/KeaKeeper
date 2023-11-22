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


require '../bootstrap.php';

/*****************************************************************************
* Class          : duplicate_hostid_notinValidate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate_hostid_notinValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (array_key_exists(2, $option)) {
            $val = $option[2]($val);
        }

        global $store;

        /* make query for check duplicate */
        $cond = [$option[1] => $val];
        $dbutil = new dbutils($store->db);
        $dbutil->select('COUNT(' . $option[1] . ')');
        $dbutil->from($option[0]);
        $dbutil->where($cond);
        $dbutil->where('host_id NOT IN', get('host_id'));

        /* fetch COUNT query's result */
        $ret = $dbutil->get();

        /* greater than 0, already exists */
        if (max($ret[0]) > 0) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicate6_hostid_notinValidate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate6_hostid_notinValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select('address,prefix_len,type');
        $dbutil->from('ipv6_reservations');
        $dbutil->where('host_id NOT IN', get('host_id'));

        /* fetch COUNT query's result */
        $ret = $dbutil->get();

        foreach ($ret as $key => $value) {
            if ($value['type'] == 0) {
                /* compare ipv6 address from db */
                $db_addr = inet_pton($value['address']);
                $post_addr = inet_pton($val);

                /* Compare addresses */
                if ($db_addr == $post_addr) {
                    return false;
                }

            } else if ($value['type'] == 2) {
                /* compare range of ipv6 address from db */
                $db_addr = $value['address'];
                $post_prefix = $value['prefix_len'];

                $binPrefix = $this->masktobyte($post_prefix);
                $db_addr_min = inet_pton($db_addr);
                $db_addr_max = inet_pton($db_addr) | ~$binPrefix;
                $post_addr = inet_pton($val);

                /* Compare addresses */
                if ($post_addr >= $db_addr_min && $post_addr <= $db_addr_max) {
                    return false;
                }
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicate_delegate6_hostid_notinValidate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate_delegate6_hostid_notinValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select('address, prefix_len, type');
        $dbutil->from('ipv6_reservations');
        $dbutil->where('host_id NOT IN', get('host_id'));

        /* fetch COUNT query's result */
        $ret = $dbutil->get();
        foreach ($ret as $key => $value) {
            if ($value['type'] == 0) {
                /* compare ipv6 address from db */
                $db_addr = inet_pton($value['address']);

                /* range of ipv6 address from post */
                $binPrefix = $this->masktobyte($option[0]);
                $post_addr_max = inet_pton($val) | ~$binPrefix;
                $post_addr_min = inet_pton($val);

                /* Compare addresses */
                if ($db_addr >= $post_addr_min && $db_addr <= $post_addr_max) {
                    return false;
                }

            } else if ($value['type'] == 2) {
                /* compare range of ipv6 address from db */
                $db_addr = $value['address'];
                $post_prefix = $value['prefix_len'];

                $binPrefix = $this->masktobyte($post_prefix);
                $db_addr_min = inet_pton($db_addr);
                $db_addr_max = inet_pton($db_addr) | ~$binPrefix;

                /* range of ipv6 address from post */
                $binPrefix = $this->masktobyte($option[0]);
                $post_addr_max = inet_pton($val) | ~$binPrefix;
                $post_addr_min = inet_pton($val);

                /* Compare addresses */
                if ($post_addr_min <= $db_addr_max && $db_addr_min <= $post_addr_max) {
                    return false;
                }
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : EditHost6
* Description    : Class for add host
* args           : $store
*****************************************************************************/
class EditHost6
{
    public  $conf;
    private $pre;
    private $exist = [];
    private $store;
    private $msg_tag;
    private $err_tag;
    public  $subnet_val;
    public  $check_subnet;

    /************************************************************************
    * Method        : __construct
    * args          : None
    * return        : None
    *************************************************************************/
    public function __construct($store)
    {
        $this->subnet_val = ['subnet_id' => get('subnet_id'),
                             'subnet'    => get('subnet'),
                             'host_id'   => get('host_id')];
        $this->pools = null;
        $this->msg_tag = ['e_hostname'             => null,
                          'e_dhcp_identifier'      => null,
                          'e_address'         => null,
                          'e_domain_name_servers'  => null,
                          'e_routers'              => null,
                          'e_pool'                 => null,
                          'e_prefix'               => null,
                          'e_type'                 => null,
                          'checked'                => null,
                          'code_6'                 => null,
                          'code_3'                 => null,
                          'code_66'                => null,
                          'code_67'                => null,
                          'is_show_warn_msg'       => 0,
                          'success'                => null];

        $this->err_tag = ['e_subnet'    => null,
                          'e_subnet_id' => null,
                          'e_host_id'   => null,
                          'disp_msg'    => null,
                          'e_msg'       => null];

        $this->store = $store;

        $this->conf = new KeaConf(DHCPV6);
        if ($this->conf->result === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            $this->check_subnet = false;
            return;
        }

        /* check subnet and subnet_id existsnce in GET */
        $valid = $this->_validate_subnet();
        if ($valid === false) {
            $this->check_subnet = false;
            return;
        }

        /* check subnet and subnet_id existsnce in config file */
        $exist = $this->conf->check_id_subnet6($this->subnet_val['subnet_id'],
                                              $this->subnet_val['subnet']);

        if ($exist === false) {
            $this->err_tag['e_msg'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
        }

        /* check history in session */
        $history = $this->conf->get_hist_from_sess();
        if ($history !== NULL) {
            $this->msg_tag['is_show_warn_msg'] = 1;
        }
    }

    /*************************************************************************
    * Method        : _get_optionid_tag
    * Description   : Method for getting option_id from the options table.
    * args          : None
    * return        : None
    **************************************************************************/
    private function _get_optionid_tag()
    {
        $host_id = $this->subnet_val['host_id'];

        $dbutil = new dbutils($this->store->db);

        /* define insert columns */
        $columns = ['option_id', 'code'];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('dhcp6_options');
        $dbutil->where('host_id', $host_id);

        /* return all data */
        $option_data = $dbutil->get();

        /* return one data */
        if (empty($option_data)) {
            return;
        }
        foreach ($option_data as $item) {
            if ($item['code'] == 6) {
                $this->msg_tag['code_6'] = $item['option_id'];
            }
            if ($item['code'] == 3) {
                $this->msg_tag['code_3'] = $item['option_id'];
            }
            if ($item['code'] == 66) {
                $this->msg_tag['code_66'] = $item['option_id'];
            }
            if ($item['code'] == 67) {
                $this->msg_tag['code_67'] = $item['option_id'];
            }
        }
        return;
    }

    /*************************************************************************
    * Method        : _validate_subnet
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    private function _validate_subnet()
    {
        $rules["subnet_id"] = ["method" => "exist",
                               "msg" => [_("Can not find a subnet id.")],
                               "log" =>
                               ["Can not find a subnet id in GET parameters."]];
        $rules["subnet"]    = ["method" => "exist|subnet6",
                               "msg" => [_("Can not find a subnet."),
                                         _("Invalid subnet.")],
                               "log" =>
                                  ["Can not find a subnet in GET parameters."],
                                  ["Invalid subnet in GET parameters."]];
        $rules["host_id"]    = ["method" => "exist",
                               "msg" => [_("Can not find a host id.")],
                               "log" =>
                                  ["Can not find a host id in GET parameters."]];

        $validater = new validater($rules, $this->subnet_val, true);
        $this->subnet_val = $validater->err["keys"];
        $this->err_tag = array_merge($this->err_tag, $validater->tags);

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        list($addr, $mask) = explode("/", $this->subnet_val['subnet']);
        $addr = inet_ntop(inet_pton($addr));
        $this->subnet_val['subnet'] = $addr . "/" . $mask;

        /* get pools */
        $pools_arr = $this->conf->get_pools6($this->subnet_val['subnet']);
        if ($pools_arr === false) {
            $this->msg_tag['e_pool'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        $pools = [];
        if (is_array($pools_arr)) {
            foreach ($pools_arr as $key => $value) {
                $pools[] = $value['pool'];
            }
            $this->pools = $pools;
        }

        return true;
    }

    /*************************************************************************
    * Method        : validate_post
    * args          : $values - POST values
    * return        : true or false
    *************************************************************************/
    public function validate_post($values)
    {
        /*  define rules */
        $rules['hostname'] =
          [
           'method' => 'domain|duplicate_hostid_notin:hosts:hostname',
           'msg'    => [_('Invalid hostname.'),
                        _('Hostname already exists.')],
           'log'    => ['Invalid hostname(' . $values['hostname'] . ').',
                       'hostname already exists(' . $values['hostname'] . ').'],
           'option' => ['allowempty']
          ];

        $rules['dhcp_identifier'] =
          [
           'method' =>
           "exist|duid|max:64|duplicate_hostid_notin:hosts:HEX(dhcp_identifier):remove_both",
           'msg'    => [_('Please enter Identifier.'),
                        _('Invalid identifier.'),
                        _('Invalid identifier.'),
                        _('Identifier already exists.')],
           'log'    => ['Empty identifier.',
                        'Invalid identifier('
                        . $values['dhcp_identifier'] . ').',
                        'Invalid identifier('
                        . $values['dhcp_identifier'] . ').',
                    'Identifier already exists('
                    . $values['dhcp_identifier'] . ').']
          ];

        $rules['type'] =
          [
           'method' => 'exist|regex:/^[02]$/',
           'msg'    => [_('Please enter prefix delegation.'),
                        _('Invalid prefix delegation.')],
           'log'    => ['Empty prefix delegation(' . $values['type'] . ').',
                        'Numbers other than 0 or 2 are entered in prefix delegation.(' . $values['type'] . ').'],
           'option' => ['allowempty']
          ];

        $sub = $this->subnet_val['subnet'];
        $prefix = $values['prefix'];

        if ($values['type'] !== NULL) {
            /* When prefix delegate is checked */
            $method_str = "exist|ipv6_delegate:$prefix|insubnet_delegate6:$sub:$prefix|outpool_delegate6:$prefix|duplicate_delegate6_hostid_notin:$prefix";
            $msg1 = 'Invalid IPv6 address(' . $values['address'] . "/" . $prefix . ').';
            $msg2 = 'IPv6 address out of subnet range(' . $values['address'] . "/" . $prefix . ').';
            $msg3 = 'IPv6 address is within subnet pool range(' . $values['address'] . "/" . $prefix . ').';
            $msg4 = 'IPv6 address already exists(' . $values['address'] . "/" . $prefix . ').';
        } else {
            /* When prefix delegate is not checked */
            $method_str = "exist|ipv6|insubnet6:$sub|outpool6:$sub|duplicate6_hostid_notin";
            $msg1 = 'Invalid IPv6 address(' . $values['address'] . ').';
            $msg2 = 'IPv6 address out of subnet range(' . $values['address'] . ').';
            $msg3 = 'IPv6 address is within subnet pool range(' . $values['address'] . ').';
            $msg4 = 'IPv6 address already exists(' . $values['address'] . ').';
        }

        $rules['address'] =
          [
           'method' =>
            $method_str,
           'msg'    => [_('Please enter IP address.'),
                        _('Invalid IP address.'),
                        _('IP address out of subnet range.'),
                        _('IP address is within subnet pool range.'),
                        _('IP address already exists.')],
           'log'    => ['Empty IPv6 address.',
                        $msg1,
                        $msg2,
                        $msg3,
                        $msg4]
          ];

        $rules['domain_name_servers'] =
          [
           'method' => 'ipaddrs6',
           'msg'    => [_('Invalid domain-name-server.')],
           'log'    => ['Invalid domain-name-server(' .
                        $values['domain_name_servers']. ').'],
           'option' => ['allowempty']
          ];

        $rules['routers'] =
          [
           'method' => 'ipaddrs6',
           'msg'    => [_('Invalid routers.')],
           'log'    => ['Invalid routers(' . $values['routers']. ').'],
           'option' => ['allowempty']
          ];

        if ($values['type'] !== NULL) {
            /* When prefix delegate is checked */
            $method_str = 'exist|int|intmin:1|intmax:128';
            $msg = [_('Please enter prefix.'),
                    _('Invalid prefix.'),
                    _('Invalid prefix.'),
                    _('Invalid prefix.')];
            $log = ['Empty prefix.',
                    'prefix is not an integer(' . $values['prefix'] . ').',
                    'prefix is smaller than 1(' . $values['prefix'] . ').',
                    'prefix is larger than 128(' . $values['prefix'] . ').'];
        } else {
            /* When prefix delegate is not checked */
            $method_str = "exist|regex:/^128$/";
            $msg = [_('Please enter prefix.'),
                    _('Prefix is not 128.')];
            $log = ['Empty prefix.',
                    'Prefix is not 128.(' . $values['prefix'] . ')'];
        }
        $rules['prefix'] =
          [
           'method' => $method_str,
           'msg'    => $msg,
           'log'    => $log,
          ];

        if ($values['type'] !== NULL) {
            $this->msg_tag['checked'] = 'checked';
        }

        /* input store into values */
        $values['store'] = $this->store;

        /* validate */
        $validater = new validater($rules, $values, true);

        /* keep validated value into property */
        $this->pre = $validater->err["keys"];

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->_get_optionid_tag();
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : fetch_existing
    * args          : 
    * return        :
    *************************************************************************/
    public function fetch_existing($host_id, $sub_id)
    {
        global $options;

        /* call the methods fetch existing data */
        $exist_hosts   = $this->_fetch_exist_hosts($host_id, $sub_id);
        $exist_ipv6    = $this->_fetch_exist_ipv6_reserv($host_id);
        $exist_options = $this->_fetch_exist_options($host_id);

        if ($exist_hosts === null) {
            return;
        }

        if ($exist_ipv6 === null) {
            return; 
        }

        if ($exist_options !== null) {
            /* replace array depends on code */
            foreach ($exist_options as $option) {
                $col = array_search($option['code'], $options);
                
                $this->exist[$col] = $option['formatted_value'];
            }
        }

        if ($exist_ipv6['type'] == 2) {
            $this->msg_tag['checked'] = "checked";
        } 

        $this->exist = array_merge($this->exist, $exist_hosts);
        $this->exist = array_merge($this->exist, $exist_ipv6);
    }

    /*************************************************************************
    * Method        : _fetch_exist_ipv6_reserv
    * args          :
    * return        :
    *************************************************************************/
    private function _fetch_exist_ipv6_reserv($host_id)
    {
        $dbutil = new dbutils($this->store->db);

        /* define select columns */
        $columns = ['type',
                    'prefix_len',
                    'address'];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('ipv6_reservations');
        $dbutil->where('host_id', $host_id);

        $ipv6_data = $dbutil->get();

        /* return one data */
        if (empty($ipv6_data)) {
            $msg = _("There is no data in ipv6_reservations table concerning the host ID(%s).");
            $this->err_tag['disp_msg'] = sprintf($msg, $host_id);
            $this->store->log->log('There is no data in ipv6_reservations table concerning the host ID('
                                   . $host_id .').', null);
            return null;
        }

        /* Check the existence of
           address,
           prefix,
           type */
        if ($ipv6_data[0]['address'] === "" ||
            $ipv6_data[0]['address'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find address.");
            $this->store->log->log('Cannot find address.', null);
            return null;
        }
        if ($ipv6_data[0]['prefix_len'] === "" ||
            $ipv6_data[0]['prefix_len'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find prefix_len.");
            $this->store->log->log('Cannot find prefix_len.', null);
            return null;

        }
        if ($ipv6_data[0]['type'] === "" ||
            $ipv6_data[0]['type'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find type.");
            $this->store->log->log('Cannot find type.', null);
            return null;

        }

        return $ipv6_data[0];
    }

    /*************************************************************************
    * Method        : _fetch_exist_hosts
    * args          : 
    * return        :
    *************************************************************************/
    private function _fetch_exist_hosts($host_id, $sub_id)
    {
        $dbutil = new dbutils($this->store->db);

        $hexed_id  = $this->store->db->hex('dhcp_identifier');

        /* define insert columns */
        $columns = ['hostname', 'dhcp_identifier_type','dhcp6_subnet_id',
                    "$hexed_id AS dhcp_identifier"];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('hosts');
        $dbutil->where('host_id', $host_id);
        $dbutil->where('dhcp6_subnet_id', $sub_id);

        $hosts_data = $dbutil->get();

        /* return one data */
        if (empty($hosts_data)) {
            $msg = _("There is no data concerning the host ID(%s).");
            $this->err_tag['disp_msg'] = sprintf($msg, $host_id);
            $this->store->log->log('There is no data concerning the host ID('
                                   . $host_id .').', null);
            return null;
        }

        /* Check the existence of
           dhcp_identifier,
           dhcp_identifier_type,
           dhcp6_subnet_id,
           ipv4_address */
        if ($hosts_data[0]['dhcp_identifier'] === "" ||
            $hosts_data[0]['dhcp_identifier'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find dhcp_identifier.");
            $this->store->log->log('Cannot find dhcp_identifier.', null);
            return null;
        }
        if ($hosts_data[0]['dhcp_identifier_type'] === "" ||
            $hosts_data[0]['dhcp_identifier_type'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find dhcp_identifier_type.");
            $this->store->log->log('Cannot find dhcp_identifier_type.', null);
            return null;

        }
        if ($hosts_data[0]['dhcp6_subnet_id'] === "" ||
            $hosts_data[0]['dhcp6_subnet_id'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find dhcp6_subnet_id.");
            $this->store->log->log('Cannot find dhcp6_subnet_id.', null);
            return null;

        }

        /* add colon to Identifier */
        $data = [];
        foreach ($hosts_data as $item) {
            $item['dhcp_identifier'] = add_colon($item['dhcp_identifier']);
            $data[] = $item;
        }

        return $data[0];
    }

    /*************************************************************************
    * Method        : _fetch_exist_options
    * args          : 
    * return        :
    *************************************************************************/
    private function _fetch_exist_options($host_id)
    {
        $dbutil = new dbutils($this->store->db);

        /* define insert columns */
        $columns = ['option_id', 'code', 'formatted_value'];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('dhcp6_options');
        $dbutil->where('host_id', $host_id);

        /* return all data */
        $option_data = $dbutil->get();

        /* return one data */
        if (empty($option_data)) {
            return null;
        }

        $code6_flag  = 0;
        $code3_flag  = 0;
        $code66_flag = 0;
        $code67_flag = 0;

        foreach ($option_data as $item) {
            if ($item['code'] == 6) {
                $this->msg_tag['code_6'] = $item['option_id'];
                if ($item['formatted_value'] !== NULL) {
                    $code6_flag = 1;
                    $this->pre['domain_name_servers'] = $item['formatted_value'];
                } else {
                    $this->pre['domain_name_servers'] = "";
                }
            }

            if ($item['code'] == 3) {
                $this->msg_tag['code_3'] = $item['option_id'];
                if ($item['formatted_value'] !== NULL) {
                    $code3_flag = 1;
                    $this->pre['routers'] = $item['formatted_value'];
                } else {
                    $this->pre['routers'] = "";
                }
            }
            if ($item['code'] == 66) {
                $this->msg_tag['code_66'] = $item['option_id'];
                if ($item['formatted_value'] !== NULL) {
                    $code66_flag = 1;
                    $this->pre['bootp_tftp_server_name'] = $item['formatted_value'];
                } else {
                    $this->pre['bootp_tftp_server_name'] = "";
                }
            }

            if ($item['code'] == 67) {
                $this->msg_tag['code_67'] = $item['option_id'];
                if ($item['formatted_value'] !== NULL) {
                    $code67_flag = 1;
                    $this->pre['bootp_file_name'] = $item['formatted_value'];
                } else {
                    $this->pre['bootp_file_name'] = "";
                }
            }
        }

        if ($code6_flag === 0) {
            $this->pre['domain_name_servers'] = "";
        }
        if ($code3_flag === 0) {
            $this->pre['routers'] = "";
        }
        if ($code66_flag === 0) {
            $this->pre['tftp_server_name'] = "";
        }
        if ($code67_flag === 0) {
            $this->pre['boot_file_name'] = "";
        }

        return $option_data;
    }

    /*************************************************************************
    * Method        : hosts_query
    * args          : 
    * return        :
    *************************************************************************/
    private function _hosts_query($hosts_val)
    {
        $dbutil = new dbutils($this->store->db);

        foreach ($hosts_val as $col => $data) {

            /* use MySQL function depends on column */
            switch ($col) {
                case 'dhcp6_subnet_id':
                    $hosts_val[$col] = intval($data);
                    break;
                case 'dhcp_identifier':
                    $data = $this->store->db->dbh->quote($data);
                    $removed = remove_both($data);
                    $unhexed_id = $this->store->db->unhex($removed);
                    $hosts_val[$col] = $unhexed_id;
                    break;
                default:
                    $data = $this->store->db->dbh->quote($data);
                    $hosts_val[$col] = $data;
                    break;
            }
        }

        foreach ($hosts_val as $key => $value) {
            $arr[] = [$key => $value];
        }

        try {
            /* update */
            $dbutil->set($arr);
            $dbutil->from('hosts');
            $dbutil->where(["host_id"=>$this->subnet_val['host_id']]);
            $dbutil->update();
        } catch (Exception $e) {
            /* if failed to update, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to update hosts table.';
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : reserv_query
    * args          :
    * return        :
    *************************************************************************/
    private function _reserv_query($reserv_val, $lastid)
    {
        $dbutil = new dbutils($this->store->db);

        /* define insert column */
        $update_data = ['address' => '', 'prefix_len' => '', 'type' => ''];

        foreach ($reserv_val as $col => $data) {

            /* use MySQL function depends on column */
            switch ($col) {
                case 'prefix':
                    $insert_data['prefix_len'] = intval($data);
                    break;
                case 'address':
                    $data = inet_pton($data);
                    $data = inet_ntop($data);
                    $data = $this->store->db->dbh->quote($data);
                    $insert_data['address'] = $data;
                    break;
                case 'type':
                    if($data === NULL) {
                        $insert_data['type'] = 0;
                    } else {
                        $insert_data['type'] = intval($data);
                    }
            }
        }

        foreach ($insert_data as $key => $value) {
            $arr[] = [$key => $value];
        }

        try {
            /* update */
            $dbutil->set($arr);
            $dbutil->from('ipv6_reservations');
            $dbutil->where(["host_id"=>$this->subnet_val['host_id']]);
            $dbutil->update();
        } catch (Exception $e) {
            /* if failed to update, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to update ipv6_reservations table.';
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : _check_option_table
    * Description   : Method for checking whether a record
    *                                             in the options table exists
    * args          : $code
    *               : $option_id
    * return        : true/false
    **************************************************************************/
    private function _check_option_table($code, $option_id)
    {
        $dbutil = new dbutils($this->store->db);

        /* make SELECT statement */
        $dbutil->select('*');

        /* make where statement */
        $dbutil->where('host_id', $this->subnet_val['host_id']);
        $dbutil->where('option_id', $option_id);
        $dbutil->where('code', $code);

        /* make FROM statement */
        $dbutil->from('dhcp6_options');

        /* count result */
        $count_ret = count($dbutil->get());

        /* Cannot get data from database */
        if ($count_ret === 0) {
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : options_query
    * args          : 
    * return        :
    *************************************************************************/
    private function _options_query($options_val)
    {
        global $options;

        foreach ($options as $key => $value) {
            $arr[6] = post('code_6');
            $arr[3] = post('code_3');
            $arr[66] = post('code_66');
            $arr[67] = post('code_67');
        }

        /* input value into array */
        foreach ($options_val as $col => $data) {

        $dbutil = new dbutils($this->store->db);

            if ($data === "") {
                /********************************
                * delete
                *********************************/
                try {
                    $dbutil->from("dhcp6_options");
                    $where = ["host_id"=>$this->subnet_val['host_id'],
                              "code"=>$options[$col],
                              "option_id"=>$arr[$options[$col]],
                             ];
                    /* make where statement */
                    $dbutil->where($where);
                    $dbutil->delete();
                } catch (Exception $e) {
                    /* if failed to insert, execute rollback */
                    $this->store->db->rollback();
                    $log_msg = 'failed to insert data into dhcp6_options.';
                    throw new SyserrException($log_msg);
                }
            } else if ($this->_check_option_table($options[$col],
                                           $arr[$options[$col]]) === false) {
                /********************************
                * insert
                *********************************/
                $ipaddr = ipv6Validate::run($data);
                $host   = domainValidate::run($data);
                if ($ipaddr !== false) {
                    /* If it is an IP address, match the format */
                    $data = inet_ntop(inet_pton($data));
                }
                $data = $this->store->db->dbh->quote($data);

                $insert_data['code'] = $options[$col];
                $insert_data['formatted_value'] = $data;
                $insert_data['host_id'] = $this->subnet_val['host_id'];

                try {
                    $dbutil->into($insert_data);
                    $dbutil->from('dhcp6_options');
                    $dbutil->insert();
                } catch (Exception $e) {
                    /* if failed to insert, execute rollback */
                    $this->store->db->rollback();
                    $log_msg = 'failed to insert data into dhcp6_options.';
                    throw new SyserrException($log_msg);
                }

            } else {
                /********************************
                * update
                *********************************/
                $ipaddr = ipv6Validate::run($data);
                $host   = domainValidate::run($data);
                if ($ipaddr !== false) {
                    /* If it is an IP address, match the format */
                    $data = inet_ntop(inet_pton($data));
                }
                $data = $this->store->db->dbh->quote($data);

                try {
                    $dbutil->from("dhcp6_options");
                    $where = ["host_id"=>$this->subnet_val['host_id'],
                              "code"=>$options[$col],
                              "option_id"=>$arr[$options[$col]],
                             ];
                    $dbutil->where($where);
                    $update = [["formatted_value" => $data],
                    ];
                    $dbutil->set($update);
                    $ret = $dbutil->update();

                } catch (Exception $e) {
                    /* if failed to insert, execute rollback */
                    $this->store->db->rollback();
                    $log_msg = 'failed to update dhcp6_options table.';
                    throw new SyserrException($log_msg);
                }
            }
        }
    }

    /*************************************************************************
    * Method        : update_params
    * args          : 
    * return        :
    *************************************************************************/
    public function update_params ()
    {
        /* replace variable */
        $params = $this->pre;
        $params['dhcp6_subnet_id'] = $this->subnet_val['subnet_id'];
        $params['dhcp_identifier_type'] = 1;

        /* begin transaction */
        $this->store->db->begin_transaction();

        /*****************
        * hosts
        *****************/
        /* make array for making insert hosts sql */
        $col_hosts = ['hostname', 'dhcp_identifier',
                      'dhcp_identifier_type', 'dhcp6_subnet_id'];

        $forhosts = [];
        /* input value into made array */
        foreach ($col_hosts as $col) {
            /* skip empty value */
            if ($params[$col] === '') {
                continue;
            }
            $forhosts[$col] = $params[$col];
        }

        /* pass made array insert method */
        $this->_hosts_query($forhosts);
 
        /* define insert column */
        $lastid = $this->store->db->last_insertid();

        /*****************
        * ipv6_reservations
        *****************/
        /* make array for making insert ipv6_reservations sql */
        $col_reserv = ['address', 'prefix', 'type'];

        /* input value into made array */
        $forreserv = [];
        foreach ($col_reserv as $col) {
            /* skip empty value */
            if ($params[$col] === '') {
                continue;
            }
            $forreserv[$col] = $params[$col];
        }

        /* pass made array insert method */
        if (!empty($forreserv)) {
            $sql = $this->_reserv_query($forreserv, $lastid);
        }

        /*****************
        * options
        *****************/
        /* make array for making insert options sql */
        $col_options = ['domain_name_servers', 'routers'];

        /* input value into made array */
        $foroptions = [];
        foreach ($col_options as $col) {
            $foroptions[$col] = $params[$col];
        }

        /* pass made array insert method */
        if (!empty($foroptions)) {
            $sql = $this->_options_query($foroptions);
        }

        /* commit inserted data */
        $this->store->db->commit();

        $log_format = "Edit successful.(ip: %s id: %s)";
        $success_log = sprintf($log_format, $forreserv['address'],
                                            $forhosts['dhcp_identifier']);

        $this->store->log->log($success_log, null);
        $this->msg_tag['success'] = _('Edit successful!');
    }

    /*************************************************************************
    * Method        : display
    * args          : 
    * return        :
    *************************************************************************/
    public function display()
    {
        $errors = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign("subnet_val", $this->subnet_val);
        $this->store->view->assign("pools", $this->pools);
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->assign("exist", $this->exist);
        $this->store->view->render("edithost6.tmpl", $errors);
    }

}

/******************************************************************************
*  main
******************************************************************************/
$eh6 = new EditHost6($store);

if ($eh6->check_subnet === false) {
    $eh6->display();
    exit(1);
}

$apply = post('apply');

if (isset($apply)) {
    /************************************
    * Insert section
    ************************************/
    $post = [
        'hostname'             => post('hostname'),
        'dhcp_identifier'      => post('identifier'),
        'address'              => post('ip'),
        'prefix'               => post('prefix'),
        'domain_name_servers'  => post('domain-name-servers'),
        'routers'              => post('routers'),
        'dhcp4_subnet_id'      => get('subnet_id'),
        'type'                 => post('delegation')
    ];

    $ret = $eh6->validate_post($post);

    if (!$ret) {
        $eh6->display();
        exit(1);
    }

    $eh6->update_params();
}

/************************************
* Default section
************************************/
$host_id = get('host_id');
if ($host_id !== null) {
    $eh6->fetch_existing($host_id, $eh6->subnet_val['subnet_id']);
}

$eh6->display();
