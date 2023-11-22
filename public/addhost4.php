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
* Class          : AddHost4
* Description    : Class for add host
* args           : $store
*****************************************************************************/
class AddHost4
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
                             'subnet'    => get('subnet')];
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
                          'is_show_warn_msg'       => 0,
                          'success'                => null];

        $this->err_tag = ['e_subnet'    => null,
                          'e_subnet_id' => null,
                          'disp_msg'    => null,
                          'e_msg'       => null];

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
           'method' => 'domain|duplicate:hostname',
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
                $id_num = 0;
                break;
            case 1:
                $id_format = 'duid';
                $id_num = 1;
                break;
            case 2:
                $id_format = 'circuitid';
                $id_num = 2;
                break;
        }

        $rules['dhcp_identifier'] =
          [
           'method' =>
           "exist|$id_format|max:64|duplicate:HEX(dhcp_identifier):remove_both:$id_num",
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
           "exist|ipv4|insubnet4:$sub|outpool:$sub|duplicate:INET_NTOA(ipv4_address)",
           'msg'    => [_('Please enter IP address.'),
                        _('Invalid IP address.'),
                        _('IP address out of subnet range.'),
                        _('IP address is within subnet pool range.'),
                        _('IP address already exists.')],
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
        $exist_options = $this->_fetch_exist_options($host_id);

        if ($exist_hosts === null) {
            return;
        }

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
    * args          : 
    * return        :
    *************************************************************************/
    private function _fetch_exist_hosts($host_id, $sub_id)
    {
        $dbutil = new dbutils($this->store->db);

        $hexed_id  = $this->store->db->hex('dhcp_identifier');
        $ntoa_ip   = $this->store->db->inet_ntoa('ipv4_address');
        $ntoa_d4ns = $this->store->db->inet_ntoa('dhcp4_next_server');

        /* define insert columns */
        $columns = ['hostname', 'dhcp_identifier_type', 'dhcp4_boot_file_name',
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
        $columns = ['code', 'formatted_value'];

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
                case 'dhcp_identifier_type':
                case 'dhcp4_subnet_id':
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

        try {
            /* insert */
            $dbutil->into($hosts_val);
            $dbutil->from('hosts');
            $dbutil->insert();
        } catch (Exception $e) {
            /* if failed to insert, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to insert data into hosts.';
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : options_query
    * args          : 
    * return        :
    *************************************************************************/
    private function _options_query($options_val)
    {
        global $options;

        $dbutil = new dbutils($this->store->db);

        /* define insert column */
        $lastid = $this->store->db->last_insertid();
        $insert_data = ['host_id' => $lastid,
                        'code' => '', 'formatted_value' => ''];

        /* input value into array */
        foreach ($options_val as $col => $data) {
            $data = $this->store->db->dbh->quote($data);

            $insert_data['code'] = $options[$col];
            $insert_data['formatted_value'] = $data;

            try {
                /* insert */
                $dbutil->into($insert_data);
                $dbutil->from('dhcp4_options');
                $dbutil->insert();
            } catch (Exception $e) {
                /* if failed to insert, execute rollback */
                $this->store->db->rollback();
                $log_msg = 'failed to insert data into dhcp4_options.';
                throw new SyserrException($log_msg);
            }
        }
    }

    /*************************************************************************
    * Method        : insert_params
    * args          : 
    * return        :
    *************************************************************************/
    public function insert_params ()
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
                      'ipv4_address', 'dhcp4_next_server', 'dhcp4_subnet_id',
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

        /* input value into made array */
        $foroptions = [];
        foreach ($col_options as $col) {
            /* skip empty value */
            if ($params[$col] === '') {
                continue;
            }
            $foroptions[$col] = $params[$col];
        }

        /* pass made array insert method */
        if (!empty($foroptions)) {
            $sql = $this->_options_query($foroptions);
        }

        /* commit inserted data */
        $this->store->db->commit();

        $log_format = "Add successful.(ip: %s id: %s)";
        $success_log = sprintf($log_format, $forhosts['ipv4_address'],
                                            $forhosts['dhcp_identifier']);

        $this->store->log->log($success_log, null);
        $this->msg_tag['success'] = _('Add successful!');
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
        $this->store->view->render("addhost4.tmpl", $errors);
    }

}

/******************************************************************************
*  main
******************************************************************************/
$ah4 = new AddHost4($store);

if ($ah4->check_subnet === false) {
    $ah4->display();
    exit(1);
}

$apply = post('apply');

if (isset($apply)) {
    /************************************
    * Insert section
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

    $ret = $ah4->validate_post($post);

    if (!$ret) {
        $ah4->display();
        exit(1);
    }

    $ah4->insert_params();

    $ah4->display();
    exit;
}

/************************************
* Default section
************************************/
$host_id = get('host_id');
if ($host_id !== null) {
    $ah4->fetch_existing($host_id, $ah4->subnet_val['subnet_id']);
}

$ah4->display();
