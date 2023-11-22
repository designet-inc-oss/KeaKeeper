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
* Class          : AddHost6
* Description    : Class for add host
* args           : $store
*****************************************************************************/
class AddHost6
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
                          'e_dhcp_identifier'      => null,
                          'e_address'         => null,
                          'e_domain_name_servers'  => null,
                          'e_routers'              => null,
                          'e_pool'                 => null,
                          'e_prefix'               => null,
                          'e_type'                 => null,
                          'checked'                => null,
                          'is_show_warn_msg'       => 0,
                          'success'                => null];

        $this->err_tag = ['e_subnet'    => null,
                          'e_subnet_id' => null,
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
           'method' => 'domain|duplicate:hostname',
           'msg'    => [_('Invalid hostname.'),
                        _('Hostname already exists.')],
           'log'    => ['Invalid hostname(' . $values['hostname'] . ').',
                       'hostname already exists(' . $values['hostname'] . ').'],
           'option' => ['allowempty']
          ];

        $rules['dhcp_identifier'] =
          [
           'method' =>
           "exist|duid|max:64|duplicate:HEX(dhcp_identifier):remove_both:1",
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
            $method_str = "exist|ipv6_delegate:$prefix|insubnet_delegate6:$sub:$prefix|outpool_delegate6:$sub:$prefix|duplicate_delegate6:$prefix";
            $msg1 = 'Invalid IPv6 address(' . $values['address'] . "/" . $prefix . ').';
            $msg2 = 'IPv6 address out of subnet range(' . $values['address'] . "/" . $prefix . ').';
            $msg3 = 'IPv6 address is within subnet pool range(' . $values['address'] . "/" . $prefix . ').';
            $msg4 = 'IPv6 address already exists(' . $values['address'] . "/" . $prefix . ').';
        } else {
            /* When prefix delegate is not checked */
            $method_str = "exist|ipv6|insubnet6:$sub|outpool6:$sub|duplicate6";
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
                    'prefix is not an integer(' . $values['prefix']. ').',
                    'prefix is smaller than 1(' . $values['prefix']. ').',
                    'prefix is larger than 128(' . $values['prefix']. ').'];
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
        $columns = ['hostname',
                    "$hexed_id AS dhcp_identifier"];

        /* make sql and fetch */
        $dbutil->select($columns);
        $dbutil->from('hosts');
        $dbutil->where('host_id', $host_id);
        $dbutil->where('dhcp6_subnet_id', $sub_id);

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
        $dbutil->from('dhcp6_options');
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
    * Method        : reserv_query
    * args          :
    * return        :
    *************************************************************************/
    private function _reserv_query($reserv_val, $lastid)
    {
        $dbutil = new dbutils($this->store->db);

        /* define insert column */
        $insert_data = ['host_id' => $lastid,
                        'address' => '', 'prefix_len' => '', 'type' => ''];

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

        try {
            /* insert */
            $dbutil->into($insert_data);
            $dbutil->from('ipv6_reservations');
            $dbutil->insert();
        } catch (Exception $e) {
            /* if failed to insert, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to insert data into ipv6_reservations.';
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : options_query
    * args          : 
    * return        :
    *************************************************************************/
    private function _options_query($options_val, $lastid)
    {
        global $options;

        $dbutil = new dbutils($this->store->db);

        /* define insert column */
        $insert_data = ['host_id' => $lastid,
                        'code' => '', 'formatted_value' => ''];

        /* input value into array */
        foreach ($options_val as $col => $data) {
            $insert_data['code'] = $options[$col];

            /* Check either IP address or host name */
            $ipaddr = ipv6Validate::run($data);
            $host   = domainValidate::run($data);
            if ($ipaddr !== false) {
                /* If it is an IP address, match the format */
                $data = inet_ntop(inet_pton($data));
            }

            $data = $this->store->db->dbh->quote($data);
            $insert_data['formatted_value'] = $data;

            try {
                /* insert */
                $dbutil->into($insert_data);
                $dbutil->from('dhcp6_options');
                $dbutil->insert();
            } catch (Exception $e) {
                /* if failed to insert, execute rollback */
                $this->store->db->rollback();
                $log_msg = 'failed to insert data into dhcp6_options.';
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
            /* skip empty value */
            if ($params[$col] === '') {
                continue;
            }
            $foroptions[$col] = $params[$col];
        }

        /* pass made array insert method */
        if (!empty($foroptions)) {
            $sql = $this->_options_query($foroptions, $lastid);
        }

        /* commit inserted data */
        $this->store->db->commit();

        $log_format = "Add successful.(ip: %s id: %s)";
        $success_log = sprintf($log_format, $forreserv['address'],
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
        $this->store->view->render("addhost6.tmpl", $errors);
    }

}

/******************************************************************************
*  main
******************************************************************************/
$ah6 = new AddHost6($store);

if ($ah6->check_subnet === false) {
    $ah6->display();
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

    $ret = $ah6->validate_post($post);

    if (!$ret) {
        $ah6->display();
        exit(1);
    }

    $ah6->insert_params();

    $ah6->display();
    exit;
}

/************************************
* Default section
************************************/
$host_id = get('host_id');
if ($host_id !== null) {
    $ah6->fetch_existing($host_id, $ah6->subnet_val['subnet_id']);
}

$ah6->display();
