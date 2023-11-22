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
        if (array_key_exists(1, $option)) {
            $val = $option[1]($val);
        }

        global $store;

        /* make query for check duplicate */
        $cond = [$option[0] => $val];
        $dbutil = new dbutils($store->db);
        $dbutil->select('COUNT(' . $option[0] . ')');
        $dbutil->from('hosts');
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
* Class          : EditHost4
* Description    : Class for edit host
* args           : $store
*****************************************************************************/
class EditHost4
{
    public  $conf;
    private $pre;
    private $exist = [];
    private $store;
    private $msg_tag;
    private $err_tag;
    public  $subnet_val;
    public  $check_subnet;
    public  $host_id;

    /*************************************************************************
    * Method        : __construct
    * args          : $store
    * return        : None
    *************************************************************************/
    public function __construct($store)
    {
        $this->subnet_val = ['subnet_id' => get('subnet_id'),
                             'subnet'    => get('subnet'),
                             'host_id'   => get('host_id')];
        $this->pools = null;

        $this->msg_tag = ['e_hostname'             => null,
                          'e_dhcp_identifier_type' => null,
                          'e_dhcp_identifier'      => null,
                          'e_ipv4_address'         => null,
                          'e_domain_name_servers'  => null,
                          'e_routers'              => null,
                          'e_pool'                 => null,
                          'e_dhcp4_next_server'    => null,
                          'e_dhcp4_boot_file_name' => null,
                          'e_tftp_server_name'     => null,
                          'e_boot_file_name'       => null,
                          'code_6'                 => null,
                          'code_3'                 => null,
                          'code_66'                => null,
                          'code_67'                => null,
                          'is_show_warn_msg'       => 0,
                          'success'                => null];

        $this->err_tag = ['disp_msg'    => null,
                          'e_msg'       => null,
                          'e_subnet_id' => null,
                          'e_subnet'    => null,
                          'e_host_id'   => null,
                         ];

        $this->store = $store;

        $this->conf = new KeaConf(DHCPV4);
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
        $exist = $this->conf->check_id_subnet4($this->subnet_val['subnet_id'],
                                              $this->subnet_val['subnet']);

        if ($exist === false) {
            $this->err_tag['e_msg'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            $this->check_subnet = false;
            return;
        }

        /* check history in session */
        $history = $this->conf->get_hist_from_sess();
        if ($history !== NULL) {
            $this->msg_tag['is_show_warn_msg'] = 1;
        }
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
        $rules["subnet"]    = ["method" => "exist",
                               "msg" => [_("Can not find a subnet.")],
                               "log" =>
                                  ["Can not find a subnet in GET parameters."]];
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

        $pools_arr = $this->conf->get_pools($this->subnet_val['subnet']);
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
           'method' => 'domain|duplicate_hostid_notin:hostname',
           'msg'    => [_('Invalid hostname.'),
                        _('Hostname already exists.')],
           'log'    => ['Invalid hostname(' . $values['hostname'] . ').',
                       'hostname already exists(' . $values['hostname'] . ').'],
           'option' => ['allowempty']
          ];

        $rules['dhcp_identifier_type'] =
          [
           'method' => 'exist|regex:/^[0-2]$/',
           'msg'    => [_('Please enter type.'),
                        _('Invalid type of identifier.')],
           'log'    => ['Empty type of identifier.',
                        'Invalid type of identifier('
                        . $values['dhcp_identifier_type'] . ').']
          ];

        switch ($values['dhcp_identifier_type']) {
            case 0:
                $id_format = 'macaddr';
                break;
            case 1:
                $id_format = 'duid';
                break;
            case 2:
                $id_format = 'circuitid';
                break;
        }

        $rules['dhcp_identifier'] =
          [
           'method' =>
                  "exist|$id_format|max:64|duplicate_hostid_notin:HEX(dhcp_identifier):remove_both",
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

        $sub = $this->subnet_val['subnet'];
        $rules['ipv4_address'] =
          [
           'method' =>
           "exist|ipv4|insubnet4:$sub|outpool:$sub|duplicate_hostid_notin:INET_NTOA(ipv4_address)",
           'msg'    => [_('Please enter IPv4 address.'),
                        _('Invalid IPv4 address.'),
                        _('IPv4 address out of subnet range.'),
                        _('IPv4 address is within subnet pool range.'),
                        _('IPv4 address already exists.')],
           'log'    => ['Empty IPv4 address.',
                       'Invalid IPv4 address(' . $values['ipv4_address'] . ').',
           'IPv4 address out of subnet range(' . $values['ipv4_address'] . ').',
      'IPv4 address is within subnet pool range(' . $values['ipv4_address'] . ').',
                'IPv4 address already exists(' . $values['ipv4_address'] . ').']
          ];

        $rules['domain_name_servers'] =
          [
           'method' => 'ipaddrs4',
           'msg'    => [_('Invalid domain-name-server.')],
           'log'    => ['Invalid domain-name-server(' .
                        $values['domain_name_servers']. ').'],
           'option' => ['allowempty']
          ];

        $rules['routers'] =
          [
           'method' => 'ipaddrs4',
           'msg'    => [_('Invalid routers.')],
           'log'    => ['Invalid routers(' . $values['routers']. ').'],
           'option' => ['allowempty']
          ];

        $rules['dhcp4_next_server'] =
          [
           'method' => 'ipv4',
           'msg'    => [_('Invalid dhcp:next-server.')],
           'log'    => ['Invalid dhcp:next-server(' .
                        $values['dhcp4_next_server'] . ').'],
           'option' => ['allowempty']
          ];

        $rules['dhcp4_boot_file_name'] =
          [
           'method' => 'max:2048',
           'msg'    => [_('Invalid dhcp:boot-file.')],
           'log'    => ['Too long dhcp:boot-file(' .
                        $values['dhcp4_boot_file_name'] . ').'],
           'option' => ['allowempty']
          ];

        $rules['tftp_server_name'] =
          [
           'method' => 'servers',
           'msg'    => [_('Invalid tftp-server-name.')],
           'log'    => ['Invalid tftp-server-name(' .
                        $values['tftp_server_name']. ').'],
           'option' => ['allowempty']
          ];

        $rules['boot_file_name'] =
          [
           'method' => 'max:2048',
           'msg'    => [_('Invalid boot-file-name.')],
           'log'    => ['Too long boot-file-name(' .
                        $values['boot_file_name']. ').'],
           'option' => ['allowempty']
          ];

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
    * Description   : Method for displaying the template on the screen.
    * args          : $sub_id
    * return        : None
    **************************************************************************/
    public function fetch_existing($sub_id)
    {
        global $options;
        $host_id = $this->subnet_val['host_id'];

        /* call the methods fetch existing data */
        $exist_hosts   = $this->_fetch_exist_hosts($host_id, $sub_id);
        /* Error when there is no host ID data */
        if ($exist_hosts === null) {
            return;
        }

        $exist_options = $this->_fetch_exist_options($host_id);
        if ($exist_options !== null) {
            /* replace array depends on code */
            foreach ($exist_options as $option) {
                $col = array_search($option['code'], $options);
                
                $this->exist[$col] = $option['formatted_value'];
            }
        }
        $this->exist = array_merge($this->exist, $exist_hosts);
    }

    /*************************************************************************
    * Method        : _fetch_exist_hosts
    * Description   : Method for getting a value from the hosts table.
    * args          : $host_id
    *               : $sub_id
    * return        : array/null
    **************************************************************************/
    private function _fetch_exist_hosts($host_id, $sub_id)
    {
        $dbutil = new dbutils($this->store->db);

        $hexed_id  = $this->store->db->hex('dhcp_identifier');
        $ntoa_ip   = $this->store->db->inet_ntoa('ipv4_address');
        $ntoa_d4ns = $this->store->db->inet_ntoa('dhcp4_next_server');

        /* define insert columns */
        $columns = ['hostname', 'dhcp_identifier_type',
                    'dhcp4_boot_file_name', 'dhcp4_subnet_id',
                    "$hexed_id AS dhcp_identifier",
                    "$ntoa_d4ns AS dhcp4_next_server",
                    "$ntoa_ip AS ipv4_address"];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('hosts');
        $dbutil->where('host_id', $host_id);
        $dbutil->where('dhcp4_subnet_id', $sub_id);

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
           dhcp4_subnet_id,
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
        if ($hosts_data[0]['dhcp4_subnet_id'] === "" ||
            $hosts_data[0]['dhcp4_subnet_id'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find dhcp4_subnet_id.");
            $this->store->log->log('Cannot find dhcp4_subnet_id.', null);
            return null;

        }
        if ($hosts_data[0]['ipv4_address'] === "" ||
            $hosts_data[0]['ipv4_address'] === NULL) {
            $this->err_tag['disp_msg'] = _("Cannot find ipv4_address.");
            $this->store->log->log('Cannot find ipv4_address.', null);
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
        $dbutil->from('dhcp4_options');
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
    * Method        : _fetch_exist_options
    * Description   : Method for getting data from the options table.
    * args          : $host_id
    * return        : array/null
    **************************************************************************/
    private function _fetch_exist_options($host_id)
    {
        $dbutil = new dbutils($this->store->db);

        /* define insert columns */
        $columns = ['option_id', 'code', 'formatted_value'];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('dhcp4_options');
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
    * Method        : _hosts_query
    * Description   : Method for Updating hosts table
    * args          : $hosts_val
    * return        : None
    **************************************************************************/
    private function _hosts_query($hosts_val)
    {
        $dbutil = new dbutils($this->store->db);

        foreach ($hosts_val as $col => $data) {
            /* use MySQL function depends on column */
            switch ($col) {
                case 'dhcp_identifier_type':
                    $hosts_val[$col] = intval($data);
                    break;
                case 'dhcp_identifier':
                    $data = $this->store->db->dbh->quote($data);
                    $removed = remove_both($data);
                    $unhexed_id = $this->store->db->unhex($removed);
                    $hosts_val[$col] = $unhexed_id;
                    break;
                case 'ipv4_address':
                    $data = $this->store->db->dbh->quote($data);
                    if ($data != '') {
                        $aton_ip = $this->store->db->inet_aton($data);
                        $hosts_val[$col] = $aton_ip;
                    }
                    break;
                case 'dhcp4_next_server':
                    if ($data != '') {
                        $data = $this->store->db->dbh->quote($data);
                        $aton_d4ns = $this->store->db->inet_aton($data);
                        $hosts_val[$col] = $aton_d4ns;
                    }
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
            /* insert */
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
        $dbutil->where('host_id', $this->host_id);
        $dbutil->where('option_id', $option_id);
        $dbutil->where('code', $code);

        /* make FROM statement */
        $dbutil->from('dhcp4_options');

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
    * Description   : Method for Updating dhcp4_options table
    * args          : $options_val
    * return        : None
    **************************************************************************/
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
        if ($data !== "") {
            $data = $this->store->db->dbh->quote($data);
        }

        $dbutil = new dbutils($this->store->db);

            if ($data === "") {
                /********************************
                * delete
                *********************************/
                try {
                    $dbutil->from("dhcp4_options");
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
                    $log_msg = 'failed to insert data into dhcp4_options.';
                    throw new SyserrException($log_msg);
                }
            } else if ($this->_check_option_table($options[$col],
                                           $arr[$options[$col]]) === false) {
                /********************************
                * insert
                *********************************/
                $insert_data['code'] = $options[$col];
                $insert_data['formatted_value'] = $data;
                $insert_data['host_id'] = $this->subnet_val['host_id'];

                try {
                    $dbutil->into($insert_data);
                    $dbutil->from('dhcp4_options');
                    $dbutil->insert();
                } catch (Exception $e) {
                    /* if failed to insert, execute rollback */
                    $this->store->db->rollback();
                    $log_msg = 'failed to insert data into dhcp4_options.';
                    throw new SyserrException($log_msg);
                }

            } else {

                /********************************
                * update
                *********************************/
                try {
                    $dbutil->from("dhcp4_options");
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
                    $log_msg = 'failed to insert data into dhcp4_options.';
                    throw new SyserrException($log_msg);
                }
            }
        }
    }

    /*************************************************************************
    * Method        : update_params
    * Description   : Method for Updating hosts and dhcp4_options table
    * args          : None
    * return        : None
    **************************************************************************/
    public function update_params()
    {
        /* replace variable */
        $params = $this->pre;
        $params['dhcp4_subnet_id'] = $this->subnet_val['subnet_id'];

        /* begin transaction */
        $this->store->db->begin_transaction();

        /*****************
        * hosts
        *****************/
        /* make array for making insert hosts sql */
        $col_hosts = ['hostname', 'dhcp_identifier_type', 'dhcp_identifier',
                      'ipv4_address', 'dhcp4_next_server',
                      'dhcp4_boot_file_name'];

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

        /*****************
        * options
        *****************/
        /* make array for making insert options sql */
        $col_options = ['domain_name_servers', 'routers',
                        'tftp_server_name', 'boot_file_name'];

        $foroptions = [];
        /* input value into made array */
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
        $success_log = sprintf($log_format, $forhosts['ipv4_address'],
                                            $forhosts['dhcp_identifier']);

        $this->store->log->log($success_log, null);
        $this->msg_tag['success'] = _('Edit successful!');
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : None
    * return        : None
    **************************************************************************/
    public function display()
    {
        $errors = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign("subnet_val", $this->subnet_val);
        $this->store->view->assign("pools", $this->pools);
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->assign("exist", $this->exist);
        $this->store->view->render("edithost4.tmpl", $errors);
    }

}

/******************************************************************************
*  main
******************************************************************************/
$eh4 = new EditHost4($store);

if ($eh4->check_subnet === false) {
    $eh4->display();
    exit(1);
}

$apply = post('apply');

if (isset($apply)) {
    /************************************
    * Update section
    ************************************/
    $post = [
        'hostname'             => post('hostname'),
        'dhcp_identifier_type' => post('type'),
        'dhcp_identifier'      => post('identifier'),
        'ipv4_address'         => post('ip'),
        'domain_name_servers'  => post('domain-name-servers'),
        'routers'              => post('routers'),
        'dhcp4_next_server'    => post('dhcp-next-server'),
        'dhcp4_boot_file_name' => post('dhcp-boot-file'),
        'tftp_server_name'     => post('bootp-tftp-server-name'),
        'boot_file_name'       => post('bootp-boot-file'),
        'dhcp4_subnet_id'      => get('subnet_id')
    ];

    $ret = $eh4->validate_post($post);

    if (!$ret) {
        $eh4->display();
        exit(1);
    }
    $eh4->update_params();
}

/************************************
* Default section
************************************/
$eh4->fetch_existing($eh4->subnet_val['subnet_id']);

$eh4->display();
