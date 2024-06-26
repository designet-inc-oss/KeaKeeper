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
* Class          : BulkHost6
* Description    : Class for bulk host
* args           : $store
*****************************************************************************/
class BulkHost6
{
    public  $conf;
    private $pre;
    private $exist = [];
    private $store;
    private $msg_tag;
    private $tag_arr;
    private $csv_err;
    public  $check_conf;

    /************************************************************************
    * Method        : __construct
    * args          : None
    * return        : None
    *************************************************************************/
    public function __construct($store)
    {
        $this->msg_tag = ['success'                => null,
                          'disp_msg'               => null,
                          'e_msg'                  => null];
        $this->is_show_warn_msg = 0;

        $this->store = $store;

        $this->conf = new KeaConf(DHCPV6);
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            $this->check_conf = false;
            return;
        }

        $this->mode = '0';

        /* check history in session */
        $history = $this->conf->get_hist_from_sess();
        if ($history !== NULL) {
            $this->is_show_warn_msg = 1;
        }
    }

    /*************************************************************************
    * Method        : validate_prefix
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    private function validate_prefix($data, $line)
    {
        if ($data['type'] === 'ip') {
            /* type is IP */
            $method = 'exist|regex:/^128$/';
            $msg = [_('Please enter prefix length.') . sprintf(_('(line: %s)'), $line),
                    _('Prefix length is not 128.') . sprintf(_('(line: %s)'), $line)];
            $log = ['Empty prefix length.(line: ' . $line . ')',
                    'Prefix length is not 128.(' . $data['prefix']. ').(line: ' . $line . ')'];
        } else {
            /* type is Prefix */
            $method = 'exist|int|intmin:1|intmax:128';
            $msg = [_('Please enter prefix length.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid prefix length.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid prefix length.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid prefix length.') . sprintf(_('(line: %s)'), $line)];
            $log = ['Empty prefix length.',
                        'prefix length is not an integer(' . $data['prefix']. ').(line: ' . $line . ')',
                        'prefix length is smaller than 1(' . $data['prefix']. ').(line: ' . $line . ')',
                        'prefix length is larger than 128(' . $data['prefix']. ').(line: ' . $line . ')'];
        }

        $rules['prefix'] =
          [
           'method' => $method,
           'msg'    => $msg,

           'log'    => $log,
          ];

        $validater = new validater($rules, $data, true);
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;

    }

    /*************************************************************************
    * Method        : validate_subnet
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    private function validate_subnet($data, $line)
    {
        $rules["subnet"]    = ["method" => "exist|subnet6|subnetinconf6",
                               "msg" => [_('Please enter subnet.') . sprintf(_('(line: %s)'), $line), 
                                         _('Invalid subnet.') . sprintf(_('(line: %s)'), $line),
                                         _('Subnet id or Subnet does not exist in keaconf.') . sprintf(_('(line: %s)'), $line)],
                               "log" =>
                                  ['Empty subnet' . '(line: ' . $line . ')',
                                   'Invalid subnet.(line: ' . $line . ')',
                                   'Subnet id or subnet does not exist in keaconf(' . $data['subnet'] . ').(line: ' . $line . ')']];

        $validater = new validater($rules, $data, true);
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : validate_type
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    private function validate_type($data, $line)
    {
        $rules['type'] =
          [
           'method' => 'exist|v6type',
           'msg'    => [_('Please enter reservation mode.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid reservation mode.') .  sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty reservation mode(' . $data['type'] . ').',
                        'For prefix reservation, enter ip or prefix.(' . $data['type'] . ').'],
          ];

        $validater = new validater($rules, $data, true);
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : validate_post_del
    * args          : $values - POST values
    *               : $line   - Number of lines in conf
    * return        : true or false
    *************************************************************************/
    public function validate_post_del($values, $line)
    {
        $sub = $values['subnet'];
        $prefix = $values['prefix'];

        if ($values['type'] == 'prefix') {
            /* When prefix reservation is checked */
            $ipv6_check = "exist|ipv6_prefreserve:$prefix|insubnet_prefreserve:$sub:$prefix|outpool_prefreserve:$sub:$prefix|checkexistprefreserve:$prefix";
        } else if ($values['type'] == 'ip') {
            /* When prefix reservation is not checked */
            $ipv6_check = "exist|ipv6|insubnet6:$sub|outpool6:$sub|checkexistipv6";
        }

        $rules['address'] =
          [
           'method' => $ipv6_check,
           'msg'    => [_('Please enter IP address.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid IP address.') . sprintf(_('(line: %s)'), $line),
                        _('IP address out of subnet range.') . sprintf(_('(line: %s)'), $line),
                        _('IP address is within subnet pool range.') . sprintf(_('(line: %s)'), $line),
                        _('Reservation IP has already been deleted.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty IPv6 address.(line: ' . $line . ')',
                        'Invalid IPv6 address(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')',
                        'IPv6 address out of subnet range(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')',
                        'IPv6 address is within subnet pool range(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')',
                        'Reservation IP has already been deleted(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')']
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
            $this->tag_arr = $validater->tags;
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : validate_post_add
    * args          : $values - POST values
    *               : $line   - Number of lines in conf
    * return        : true or false
    *************************************************************************/
    public function validate_post_add($values, $line)
    {
        /*  define rules */
        $rules['hostname'] =
          [
           'method' => 'domain|duplicate:hostname',
           'msg'    => [_('Invalid hostname.') . sprintf(_('(line: %s)'), $line),
                        _('Hostname already exists.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Invalid hostname(' . $values['hostname'] . ').(line: ' . $line . ')',
                       'hostname already exists(' . $values['hostname'] . ').(line: ' . $line . ')'],
           'option' => ['allowempty']
          ];


        $rules['dhcp_identifier_type'] =
          [
           'method' => 'exist|regex:/^DUID$/',
           'msg'    => [_('Please enter type.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid type of identifier.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty type of identifier.(line: ' . $line . ')',
                        'Invalid type of identifier('
                        . $values['dhcp_identifier_type'] . ').(line: ' . $line . ')']
          ];

        switch ($values['dhcp_identifier_type']) {
            case "DUID":
                $id_format = 'duid';
                $id_num = 1;
                break;
            default:
                $id_format = "";
                $id_num = "";
                break;
        }

        if ($id_format !== "" || $id_num !== "") {
            /* When identifier_type is correct */
            $rules['dhcp_identifier'] =
              [
               'method' =>
               "exist|$id_format|max:64|duplicate:HEX(dhcp_identifier):remove_both:$id_num",
               'msg'    => [_('Please enter Identifier.') . sprintf(_('(line: %s)'), $line),
                            _('Invalid identifier.') . sprintf(_('(line: %s)'), $line),
                            _('Invalid identifier.') . sprintf(_('(line: %s)'), $line),
                            _('Identifier already exists.') . sprintf(_('(line: %s)'), $line)],
               'log'    => ['Empty identifier.(line: ' . $line . ')',
                            'Invalid identifier('
                            . $values['dhcp_identifier'] . ').(line: ' . $line . ')',
                            'Invalid identifier('
                            . $values['dhcp_identifier'] . ').(line: ' . $line . ')',
                        'Identifier already exists('
                        . $values['dhcp_identifier'] . ').(line: ' . $line . ')']
              ];
        }

        $sub = $values['subnet'];
        $prefix = $values['prefix'];

        if ($values['type'] == 'prefix') {
            /* When prefix reservation is checked */
            $ipv6_check = "exist|ipv6_prefreserve:$prefix|insubnet_prefreserve:$sub:$prefix|outpool_prefreserve:$sub:$prefix|duplicate_prefreserve:$prefix";
        } else if ($values['type'] == 'ip') {
            /* When prefix reservation is not checked */
            $ipv6_check = "exist|ipv6|insubnet6:$sub|outpool6:$sub|duplicate6";
        }

        $rules['address'] =
          [
           'method' => $ipv6_check,
           'msg'    => [_('Please enter IP address.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid IP address.') . sprintf(_('(line: %s)'), $line),
                        _('IP address out of subnet range.') . sprintf(_('(line: %s)'), $line),
                        _('IP address is within subnet pool range.') . sprintf(_('(line: %s)'), $line),
                        _('IP address already exists.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty IPv6 address.(line: ' . $line . ')',
                       'Invalid IPv6 address(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')',
           'IPv6 address out of subnet range(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')',
      'IPv6 address is within subnet pool range(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')',
                'IPv6 address already exists(' . $values['address'] . '/' . $values['prefix'] . ').(line: ' . $line . ')']
          ];

        $rules['dns_servers'] =
          [
           'method' => 'ipaddrs6',
           'msg'    => [_('Invalid DNS Server Address.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Invalid DNS Server Address(' .
                        $values['dns_servers']. ').(line: ' . $line . ')'],
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
            $this->tag_arr = $validater->tags;
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : _hosts_query
    * args          : $hosts_val
    * return        : none
    *************************************************************************/
    private function _hosts_query($hosts_val)
    {
        $dbutil = new dbutils($this->store->db);

        foreach ($hosts_val as $col => $data) {

            /* use MySQL function depends on column */
            switch ($col) {
                case 'dhcp_identifier_type':
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
                    $data = $this->store->db->dbh->quote($data);
                    $aton_addr = $this->store->db->inet6_aton($data);
                    $insert_data['address'] = $aton_addr;
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
    * args          : $options_val
    * return        : none
    *************************************************************************/
    private function _options_query($options_val, $lastid)
    {
        global $options;
        global $scope_id;

        $dbutil = new dbutils($this->store->db);

        /* define insert column */
        $insert_data = ['host_id' => $lastid,
                        'code' => '', 'formatted_value' => '', 'scope_id' => $scope_id['host']];

        /* input value into array */
        foreach ($options_val as $col => $data) {
            $insert_data['code'] = $options[$col];
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
    * args          : $csv_data
    * return        : none
    *************************************************************************/
    public function insert_params ($csv_data)
    {
        /* replace variable */
        $params = $csv_data;
        $params['dhcp6_subnet_id'] = $this->conf->get_subnet_idv6($params['subnet']);
        if ($params['dhcp_identifier_type'] === 'DUID') {
            $params['dhcp_identifier_type'] = 1;
        }

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
        if ($params['type'] == 'ip') {
            $params['type'] = 0;
        } else {
            $params['type'] = 2;
        }

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
        $col_options = ['dns_servers'];

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

        $log_format = "Add successful.(ip: %s id: %s)";
        $success_log = sprintf($log_format, $forreserv['address'],
                                            $forhosts['dhcp_identifier']);

        $this->store->log->log($success_log);
        $this->msg_tag['success'] = _('Add successful!');
    }

    /*************************************************************************
    * Method        : delete
    * Description   : Method for deleting the selected host
    * args          : $ipaddr
    * return        : true/false
    **************************************************************************/
    public function delete($ipaddr)
    {
        $dbutil = new dbutils($this->store->db);

        /* make sql and fetch */
        $dbutil->select('host_id');
        $dbutil->from('ipv6_reservations');

        $aton_addr = $this->store->db->inet6_aton($ipaddr);
        $dbutil->where(sprintf('address = "%s"', $aton_addr));

        /* return all data */
        $hosts_data = $dbutil->get();

        /* delete */
        $host_id = $hosts_data[0]['host_id'];

        /* delete from dhcp6_options */
        try {
            $this->dbutil = new dbutils($this->store->db);
            /* make FROM statement */
            $this->dbutil->from('dhcp6_options');
            /* make where statement of subnet_id */
            $this->dbutil->where('host_id', $host_id);
            $this->dbutil->delete();

        } catch (Exception $e) {
            /* if failed to insert, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to delete data from dhcp6_options.';
            throw new SyserrException($log_msg);
        }

        /* delete from hosts */
        try {
            $this->dbutil = new dbutils($this->store->db);
            /* make FROM statement */
            $this->dbutil->from('hosts');
            /* make where statement of subnet_id */
            $this->dbutil->where('host_id', $host_id);
            $this->dbutil->delete();

        } catch (Exception $e) {
            /* if failed to insert, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to delete data from hosts.';
            throw new SyserrException($log_msg);
        }

        try {
            /* delete from hosts */
            $this->dbutil = new dbutils($this->store->db);
            /* make FROM statement */
            $this->dbutil->from('ipv6_reservations');
            /* make where statement of subnet_id */
            $this->dbutil->where('host_id', $host_id);
            $this->dbutil->delete();

        } catch (Exception $e) {
            /* if failed to insert, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to delete data from ipv6_reservations.';
            throw new SyserrException($log_msg);
        }

        $log_msg = "Reservation IP deleted.(ip: " . $ipaddr . ")";
        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Reservation IP deleted.");

    }

    /*************************************************************************
    * Method         : masktobyte
    * Description    : Creates binary data of the same format as that
    *                   generated by inet_pton () from the specified subnet mask.
    * args           : $mask
    * return         : $binMask
    *************************************************************************/
    public function masktobyte($mask)
    {
        /* Represent mask as 32 digit hexadecimal number */
        /* Set f */
        $binMask = str_repeat('f', $mask / 4);
        /* Set not f */
        switch ($mask % 4) {
            case 1:
                $binMask .= "8";
                break;
            case 2:
                $binMask .= "c";
                break;
            case 3:
                $binMask .= "e";
                break;
        }
        /* Fill in the digits */
        $binMask = str_pad($binMask, 32, '0');
        /* Pack in binary string in hexadecimal format (H *) */
        $binMask = pack("H*", $binMask);

        return $binMask;
    }

    /*************************************************************************
    * Method        : duplicate_ip
    * args          : $fp
    *               : $mode
    * return        : true/false
    *************************************************************************/
    public function duplicate_ip($duplicate_arr, $oneaddr_arr)
    {
        foreach ($duplicate_arr as $key => $value) {
            if (array_key_exists('type', $value)) {
                
                if ($value['type'] == 'ip') {
                    /* compare ipv6 address from db */
                    $db_addr = inet_pton($value['address']);
                    $post_addr = inet_pton($oneaddr_arr['address']);

                    /* Compare addresses */
                    if ($db_addr == $post_addr) {
                        return false;
                    }

                } else if ($value['type'] == 'prefix') {
                    /* compare range of ipv6 address from db */
                    $db_addr = $value['address'];
                    $post_prefix = $value['prefix_len'];

                    $binPrefix = $this->masktobyte($post_prefix);
                    $db_addr_min = inet_pton($db_addr);
                    $db_addr_max = inet_pton($db_addr) | ~$binPrefix;
                    $post_addr = inet_pton($oneaddr_arr['address']);

                    /* Compare addresses */
                    if ($post_addr >= $db_addr_min && $post_addr <= $db_addr_max) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /*************************************************************************
    * Method        : duplicate_prefix
    * args          : $fp
    *               : $mode
    * return        : true/false
    *************************************************************************/
    public function duplicate_prefix($duplicate_arr, $oneaddr_arr)
    {
        foreach ($duplicate_arr as $key => $value) {
            if (isset($value['type'])) {
                if ($value['type'] == 'ip') {
                    /* compare ipv6 address from db */
                    $db_addr = inet_pton($value['address']);

                    /* range of ipv6 address from post */
                    $binPrefix = $this->masktobyte($oneaddr_arr['prefix']);
                    $post_addr_max = inet_pton($oneaddr_arr['address']) | ~$binPrefix;
                    $post_addr_min = inet_pton($oneaddr_arr['address']);

                    /* Compare addresses */
                    if ($db_addr >= $post_addr_min && $db_addr <= $post_addr_max) {
                        return false;
                    }

                } else if ($value['type'] == 'prefix') {
                    /* compare range of ipv6 address from db */
                    $db_addr = $value['address'];
                    $post_prefix = $value['prefix_len'];

                    $binPrefix = $this->masktobyte($post_prefix);
                    $db_addr_min = inet_pton($db_addr);
                    $db_addr_max = inet_pton($db_addr) | ~$binPrefix;

                    /* range of ipv6 address from post */
                    $binPrefix = $this->masktobyte($oneaddr_arr['prefix']);
                    $post_addr_max = inet_pton($oneaddr_arr['address']) | ~$binPrefix;
                    $post_addr_min = inet_pton($oneaddr_arr['address']);

                    /* Compare addresses */
                    if ($post_addr_min <= $db_addr_max && $db_addr_min <= $post_addr_max) {
                        return false;
                    }
                }
            }
        }
        return true;
    }

    /*************************************************************************
    * Method        : apply_csvfile
    * args          : $mode
    * return        : true/false
    *************************************************************************/
    public function apply_csvfile($mode)
    {
        global $log_msg;
        $all_tag = [];
        $all_data = [];
        $duplicate_arr['hostname'] = [];
        $duplicate_arr['dhcp_identifier'] = [];
        $duplicate_arr['address'] = [];

        $line = 0;
        $err_flag = 0;

        /* check csv file */
        if ($_FILES["csvfile"]["tmp_name"] == "") {
            $this->store->log->output_log("Csv file is not selected.");
            $this->msg_tag['disp_msg'] = _("Please select csv file.");
            return FALSE;
        }

        /* open csvfile */
        $fp = fopen($_FILES["csvfile"]["tmp_name"], 'r');
        if ($fp === FALSE) {
            $this->store->log->output_log("Failed to open csvfile.("
                                         . $_FILES["csvfile"]["name"] . ")");
            $this->msg_tag['disp_msg'] = _("Failed to open csvfile.");
            return FALSE;
        }

        while (($tmpline = fgets($fp)) !== FALSE) {

            /* Count of rows */
            $line++;
            $all_tag[$line] = array();
            $this->tag_arr = [];

            /* Skip comments */
            if (substr($tmpline, 0, 1) === '#') {
                continue;
            }

            /* Separate by commas */
            $tmpline = rtrim($tmpline);
            $csvdata = str_getcsv($tmpline);

            $duplicate_flag = 0;
            $this->msg_tag = ['success'                => null,
                              'disp_msg'               => null,
                              'e_msg'                  => null];

            /* Check number of columns */
            if (count_array($csvdata) !== 7) {
                $this->store->log->output_log("Invalid number of columns.(line: " . $line . ")");
                $this->tag_arr['e_csv_column'] = _("Invalid number of columns.") . sprintf(_('(line: %s)'), $line);

                $all_tag[$line] = $this->tag_arr;
                $err_flag = 1;
                continue;
            }

            /* check IP address */
            if ($csvdata[2] == "") {
                $this->store->log->output_log("Empty IP address and prefix.(line: " . $line . ")");
                $this->tag_arr['e_csv_prefix'] = _("Please enter IP address and prefix.") . sprintf(_('(line: %s)'), $line);
                $all_tag[$line] = $this->tag_arr;
                $err_flag = 1;
                continue;
            }

            /* check prefix */
            if (strpos($csvdata[2], '/') === false) {
                $this->store->log->output_log("Empty prefix.(line: " . $line . ")");
                $this->tag_arr['e_csv_prefix'] = _("Please enter prefix.") . sprintf(_('(line: %s)'), $line);
                $all_tag[$line] = $this->tag_arr;
                $err_flag = 1;
                continue;
            }

            list($addr, $prefix) = explode('/', $csvdata[2]);

            /* Validation check */
            $data = [
                'subnet'               => $csvdata[0],
                'type'                 => $csvdata[1],
                'address'              => $addr,
                'prefix'               => $prefix,
                'dhcp_identifier_type' => $csvdata[3],
                'dhcp_identifier'      => $csvdata[4],
                'hostname'             => $csvdata[5],
                'dns_servers'          => $csvdata[6],
            ];

            /* First check subnet, type, prefix */
            $ret = $this->validate_subnet($data, $line);
            if ($ret === false) {
                $all_tag[$line] = $this->msg_tag;
                $err_flag = 1;
                continue;
            }

            $ret = $this->validate_type($data, $line);
            if ($ret === false) {
                $all_tag[$line] = $this->msg_tag;
                $err_flag = 1;
                continue;
            }

            $ret = $this->validate_prefix($data, $line);
            if ($ret === false) {
                $all_tag[$line] = $this->msg_tag;
                $err_flag = 1;
                continue;
            }

            /* convert subnet */
            list($addr, $mask) = explode("/", $data['subnet']);
            $addr = inet_ntop(inet_pton($addr));
            $data['subnet'] = $addr . "/" . $mask;

            /* Add mode */
            if ($mode == 0) {
                $ret = $this->validate_post_add($data, $line);
            /* Delete mode */
            } else if ($mode == 1) {
                $ret = $this->validate_post_del($data, $line);
            }

            /* When errors occured, get log */
            if ($ret === FALSE || $duplicate_flag === 1) {
                $err_flag = 1;
                $all_tag[$line] = $this->tag_arr;
            }
            /* Store values for duplication check in array */
            $this->pre['subnet'] = $data['subnet'];
            $this->pre['prefix'] = $data['prefix'];
            $this->pre['type'] = $data['type'];
            $all_data[$line] = $this->pre;
        }

        /* After validation checks are completed, duplication checks is performed */
        if ($err_flag !== 1) {
            foreach ($all_data as $key => $one_data) {
                $duplicate_flag = 0;

                /* Duplicate check in CSV file */
                /* Add mode */
                if ($mode == 0) {
                    /* check hostname */
                    if (in_array($one_data['hostname'], $duplicate_arr['hostname'])) {
                        $duplicate_flag = 1;
                        $this->store->log->output_log("hostname is duplicated in registration data.(line: " . $key . ")");
                        $this->tag_arr['e_csv_hostname'] = _("hostname is duplicated in registration data.") . sprintf(_('(line: %s)'), $key);
                        $all_tag[$key]['e_csv_hostname'] = $this->tag_arr['e_csv_hostname'];
                    }

                    /* check identifier */
                    if (in_array($one_data['dhcp_identifier'], $duplicate_arr['dhcp_identifier'])) {
                        $duplicate_flag = 1;
                        $this->store->log->output_log("dhcp_identifier is duplicated in registration data.(line: " . $key . ")");
                        $this->tag_arr['e_csv_identifier'] = _("identifier is duplicated in registration data.") . sprintf(_('(line: %s)'), $key);
                        $all_tag[$key]['e_csv_identifier'] = $this->tag_arr['e_csv_identifier'];
                    }

                    /* Store values for duplication check in array */
                    if ($one_data['dhcp_identifier'] != "") {
                        $duplicate_arr['dhcp_identifier'][] = $one_data['dhcp_identifier'];
                    }
                    if ($one_data['hostname'] != "") {
                        $duplicate_arr['hostname'][] = $one_data['hostname'];
                    }
                }

                /* check ip address */
                if ($one_data['type'] == "ip") {
                    /* ip mode */
                    $ret = $this->duplicate_ip($duplicate_arr, $one_data);
                    if ($ret === false) {
                        $duplicate_flag = 1;
                        $this->store->log->output_log("IP addr is duplicated in registration data.(line: " . $key . ")");
                        $this->tag_arr['e_csv_ipv6'] = _("IP address is duplicated in registration data.") . sprintf(_('(line: %s)'), $key);
                        $all_tag[$key]['e_csv_ipv6'] = $this->tag_arr['e_csv_ipv6'];
                        $err_flag = 1;
                        continue;
                    }
                    $duplicate_arr[$key]['address'] = $one_data['address'];
                    $duplicate_arr[$key]['type'] = $one_data['type'];
                } else {
                    /* prefix mode */
                    $ret = $this->duplicate_prefix($duplicate_arr, $one_data);
                    if ($ret === false) {
                        $duplicate_flag = 1;
                        $this->store->log->output_log("IP addr is duplicated in registration data.(line: " . $key . ")");
                        $this->tag_arr['e_csv_ipv6'] = _("IP address is duplicated in registration data.") . sprintf(_('(line: %s)'), $key);
                        $all_tag[$key]['e_csv_ipv6'] = $this->tag_arr['e_csv_ipv6'];
                        $err_flag = 1;
                        continue;

                    }
                    $duplicate_arr[$key]['address'] = $one_data['address'];
                    $duplicate_arr[$key]['type'] = $one_data['type'];
                    $duplicate_arr[$key]['prefix_len'] = $one_data['prefix'];
                }
            }
        }

        /* merge tag array */
        $merge_tag_arr = [];
        foreach ($all_tag as $value) {
            foreach ($value as $key => $val) {
                if (preg_match("/^e_/", $key) && $val != "") {
                    array_push($merge_tag_arr, $val);
                }
            }
        }

        /* Validation error */
        if ($err_flag === 1) {
            $this->csv_err = $merge_tag_arr;
            return FALSE;
        }

        /* If file is empty */
        if ($line == 0) {
            $this->store->log->output_log("The file content is empty.");
            $this->msg_tag['disp_msg'] = _("The file content is empty.");
            return FALSE;
        }

        /* begin transaction */
        $this->store->db->begin_transaction();

        /* Add */
        if ($mode == 0) {
            $duplicate_arr = array();
            foreach ($all_data as $key => $one_data) {
                $this->insert_params($one_data);
            }
        } else if ($mode == 1) {
            /* Delete */
            foreach ($all_data as $one_data) {
                $this->delete($one_data['address']);
            }
        }

        /* commit inserted data */
        $this->store->db->commit();

        return TRUE;
    }

    /*************************************************************************
    * Method        : display
    * args          : 
    * return        :
    *************************************************************************/
    public function display()
    {
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->assign("csverr", $this->csv_err);
        $this->store->view->assign("exist", $this->exist);
        $this->store->view->assign("is_show_warn_msg", $this->is_show_warn_msg);
        $this->store->view->assign("mode", $this->mode);
        $this->store->view->render("bulkhost6.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
*  main
******************************************************************************/
$bh6 = new BulkHost6($store);

if ($bh6->check_conf === false) {
    $bh6->display();
    exit(1);
}

$apply = post('apply');

if (isset($apply)) {
    /************************************
    * apply section
    ************************************/
    $mode = post('mode');
    $bh6->mode = $mode;
    $ret = $bh6->apply_csvfile($mode);

    $bh6->display();
    exit;
}

/************************************
* default section
************************************/
$bh6->display();
