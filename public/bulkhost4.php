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
* Class          : BulkHost4
* Description    : Class for bulk host
* args           : $store
*****************************************************************************/
class BulkHost4
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
        $this->pre = [];
        $this->mode = '0';
        $this->config_type = 'host';
        $this->allowleased = 'false';

        $this->conf = new KeaConf(DHCPV4);
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            $this->check_conf = false;
            return;
        }

        /* check history in session */
        $history = $this->conf->get_hist_from_sess();
        if ($history !== NULL) {
            $this->is_show_warn_msg = 1;
        }
    }

    /*************************************************************************
    * Method        : validate_subnet
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    private function validate_subnet($data, $line)
    {
        /* Initializing Error Messages */
        $this->msg_tag = ['success'                => null,
                          'disp_msg'               => null,
                          'e_msg'                  => null];

        /*  define rules */
        $rules['subnet'] =
          [
           'method' => 'exist|subnetinconf4',
           'msg'    => [_('Please enter subnet.') . sprintf(_('(line: %s)'), $line),
                        _('Subnet id or Subnet does not exist in keaconf.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty subnet' . '(line: ' . $line . ')',
                        'Subnet id or subnet does not exist in keaconf(' . $data['subnet'] . ').(line: ' . $line . ')']
          ];

        $validater = new validater($rules, $data, true);
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        $this->pre['subnet'] = $data['subnet'];

        return true;
    }

    /*************************************************************************
    * Method        : validate_post_del_host
    * args          : $values - POST values
    *               : $line   - Number of lines in conf
    * return        : true or false
    *************************************************************************/
    public function validate_post_del_host($values, $line)
    {
        /* Initializing Error Messages */
        $this->msg_tag = ['success'                => null,
                          'disp_msg'               => null,
                          'e_msg'                  => null];

        /*  define rules */
        $sub = $values['subnet'];

        $rules['ipv4_address'] =
          [
           'method' => "exist|ipv4|insubnet4:$sub|outpool:$sub|checkexistipv4",
           'msg'    => [_('Please enter IP address.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid IP address.') . sprintf(_('(line: %s)'), $line),
                        _('IP address out of subnet range.') . sprintf(_('(line: %s)'), $line),
                        _('IP address is within subnet pool range.') . sprintf(_('(line: %s)'), $line),
                        _('Reservation IP has already been deleted.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty IPv4 address.(line: ' . $line . ')',
                        'Invalid IPv4 address(' . $values['ipv4_address'] . ').(line: ' . $line . ')',
                        'IPv4 address out of subnet range(' . $values['ipv4_address'] . ').(line: ' . $line . ')',
                        'IPv4 address is within subnet pool range(' . $values['ipv4_address'] . ').(line: ' . $line . ')',
                        'Reservation IP has already been deleted(' . $values['ipv4_address'] . ').(line: ' . $line . ')']
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
    * Method        : validate_post_add_host
    * args          : $values - POST values
    *               : $line   - Number of lines in conf
    * return        : true or false
    *************************************************************************/
    public function validate_post_add_host($values, $line)
    {
        /* Initializing Error Messages */
        $this->msg_tag = ['success'                => null,
                          'disp_msg'               => null,
                          'e_msg'                  => null];

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
           'method' => 'exist|regex:/^[0-2]$/',
           'msg'    => [_('Please enter type.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid type of identifier.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty type of identifier.(line: ' . $line . ')',
                        'Invalid type of identifier('
                        . $values['dhcp_identifier_type'] . ').(line: ' . $line . ')']
          ];

        switch ($values['dhcp_identifier_type']) {
            case "MAC":
                $id_format = 'MAC';
                $values['dhcp_identifier_type'] = MAC_TYPE;
                $method = "exist|macaddr|max:64|duplicate:HEX(dhcp_identifier):remove_both:0|duplicate_option82_mac";
                break;
        /* Discontinued in version 1.05 due to migration of functionality to the option82 management screen */
        //    case "Circuit-ID":
        //        $values['dhcp_identifier_type'] = CIRCUITID_TYPE;
        //        $method = "exist|circuitid|max:129|duplicate:dhcp_identifier";
        //        break;
            default:
                $id_format = '';
                break;
        }

        if ($id_format === 'MAC') {

            $rules['dhcp_identifier'] =
              [
               'method' => $method,
               'msg'    => [_('Please enter Identifier.') . sprintf(_('(line: %s)'), $line),
                            _('Invalid identifier.') . sprintf(_('(line: %s)'), $line),
                            _('Invalid identifier.') . sprintf(_('(line: %s)'), $line),
                            _('Identifier already exists.') . sprintf(_('(line: %s)'), $line),
                            _('Identifier already exists.') . sprintf(_('(line: %s)'), $line)],
               'log'    => ['Empty identifier.(line: ' . $line . ')',
                            'Invalid identifier('
                            . $values['dhcp_identifier'] . ').(line: ' . $line . ')',
                            'Invalid identifier('
                            . $values['dhcp_identifier'] . ').(line: ' . $line . ')',
                            'Identifier already exists('
                            . $values['dhcp_identifier'] . ').(line: ' . $line . ')',
                            'Identifier already exists('
                            . $values['dhcp_identifier'] . ').(line: ' . $line . ')']
              ];
        }

        $sub = $values['subnet'];

        $rules['ipv4_address'] =
          [
           'method' => "exist|ipv4|insubnet4:$sub|outpool:$sub|duplicate:INET_NTOA(ipv4_address)",
           'msg'    => [_('Please enter IP address.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid IP address.') . sprintf(_('(line: %s)'), $line),
                        _('IP address out of subnet range.') . sprintf(_('(line: %s)'), $line),
                        _('IP address is within subnet pool range.') . sprintf(_('(line: %s)'), $line),
                        _('IP address already exists.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty IPv4 address.(line: ' . $line . ')',
                       'Invalid IPv4 address(' . $values['ipv4_address'] . ').(line: ' . $line . ')',
           'IPv4 address out of subnet range(' . $values['ipv4_address'] . ').(line: ' . $line . ')',
      'IPv4 address is within subnet pool range(' . $values['ipv4_address'] . ').(line: ' . $line . ')',
                'IPv4 address already exists(' . $values['ipv4_address'] . ').(line: ' . $line . ')']
          ];

        $rules['domain_name_servers'] =
          [
           'method' => 'ipaddrs4',
           'msg'    => [_('Invalid domain-name-server.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Invalid domain-name-server(' .
                        $values['domain_name_servers']. ').(line: ' . $line . ')'],
           'option' => ['allowempty']
          ];

        $rules['routers'] =
          [
           'method' => 'ipaddrs4',
           'msg'    => [_('Invalid routers.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Invalid routers(' . $values['routers']. ').(line: ' . $line . ')'],
           'option' => ['allowempty']
          ];

        $rules['dhcp4_next_server'] =
          [
           'method' => 'ipv4',
           'msg'    => [_('Invalid dhcp:next-server.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Invalid dhcp:next-server(' .
                        $values['dhcp4_next_server'] . ').(line: ' . $line . ')'],
           'option' => ['allowempty']
          ];

        $rules['dhcp4_boot_file_name'] =
          [
           'method' => 'max:2048',
           'msg'    => [_('Invalid dhcp:boot-file.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Too long dhcp:boot-file(' .
                        $values['dhcp4_boot_file_name'] . ').(line: ' . $line . ')'],
           'option' => ['allowempty']
          ];

        $rules['tftp_server_name'] =
          [
           'method' => 'servers',
           'msg'    => [_('Invalid tftp-server-name.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Invalid tftp-server-name(' .
                        $values['tftp_server_name']. ').(line: ' . $line . ')'],
           'option' => ['allowempty']
          ];

        $rules['boot_file_name'] =
          [
           'method' => 'max:2048',
           'msg'    => [_('Invalid boot-file-name.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Too long boot-file-name(' .
                        $values['boot_file_name']. ').(line: ' . $line . ')'],
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
    * Method        : validate_post_add_option82
    * args          : $params - POST values
    *               : $line   - Number of lines in conf
    * return        : true or false
    *************************************************************************/
    public function validate_post_add_option82($params, $line)
    {
        /* Initializing Error Messages */
        $this->msg_tag = ['success'                => null,
                          'disp_msg'               => null,
                          'e_msg'                  => null];

        $subnet = $params['subnet'];
        /* When Circuit-ID, Remote-ID, and MAC address are all from */
        if ($params['circuit_id'] === '' && $params['remote_id'] === '' && $params['mac_address'] === '') {
            $this->tag_arr['e_identifier'] = _('One of Circuit ID, Remote ID, or MAC address must be entered.') . sprintf(_('(line: %s)'), $line);
            $err_log = sprintf('One of Circuit ID, Remote ID, or MAC address must be entered.(line: %s)', $line);
            $this->store->log->log($err_log);
            return false;
        }

        /* Circuit-ID and Remote-ID, even though they have values, no_hex_circuit or no_hex_remote does not exist */
        if ($params['circuit_id'] !== '' && $params['no_hex_circuit'] === '') {
            $this->tag_arr['e_no_hex_circuit'] = _('Please enter circuit_id_not_hex.') . sprintf(_('(line: %s)'), $line);
            $err_log = sprintf('Please enter circuit_id_not_hex.(line: %s)', $line);
            $this->store->log->log($err_log);
            return false;
        }

        /* Circuit-ID and Remote-ID, even though they have values, no_hex_circuit or no_hex_remote does not exist */
        if ($params['remote_id'] !== '' && $params['no_hex_remote'] === '') {
            $this->tag_arr['e_no_hex_remote'] = _('Please enter remote_id_not_hex.') . sprintf(_('(line: %s)'), $line);
            $err_log = sprintf('Please enter remote_id_not_hex.(line: %s)', $line);
            $this->store->log->log($err_log);
            return false;
        }

        /*  define rules */
        /* Parameter stand-alone inspection */
        $rules["pool_start"] = [
            "method"=>"exist|ipv4|insubnet4:$subnet|ipv4overlap",
            "msg"=> [
                _('Please enter Pool IP address range(start).') . sprintf(_('(line: %s)'), $line),
                _('Invalid Pool IP address range(start).') . sprintf(_('(line: %s)'), $line),
                _('Pool IP address(start) is outside the range of the subnet.') . sprintf(_('(line: %s)'), $line),
                _('Pool IP address(start) already exists.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                'Please enter Pool IP address range(start).' . sprintf('(line: %s)', $line),
                sprintf('Invalid Pool IP address range(start).(%s)(line: %s)', $params['pool_start'], $line),
                sprintf('Pool IP address(start) is outside the range of the subnet.(%s)(line: %s)', $params['pool_start'], $line),
                sprintf('Pool IP address(start) already exists.(%s)(line: %s)', $params['pool_start'], $line),
            ],
        ];

        $rules["pool_end"] = [
            "method"=>"exist|ipv4|insubnet4:$subnet|ipv4overlap",
            "msg"=> [
                _('Please enter Pool IP address(end).') . sprintf(_('(line: %s)'), $line),
                _('Invalid Pool IP address(end).') . sprintf(_('(line: %s)'), $line),
                _('Pool IP address(end) is outside the range of the subnet.') . sprintf(_('(line: %s)'), $line),
                _('Pool IP address(end) already exists.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                sprintf('Please enter Pool IP address(end).(line: %s)', $line),
                sprintf('Invalid Pool IP address(end).(%s)(line: %s)', $params['pool_end'], $line),
                sprintf('Pool IP address(end) is outside the range of the subnet.(%s)(line: %s)', $params['pool_end'], $line),
                sprintf('Pool IP address(end) already exists.(%s)(line: %s)', $params['pool_end'], $line),
            ],
        ];

        $rules["circuit_id"] = [
            "method"=>"exist|invalid_chars",
            "msg"=> [
                _(''),
                _('Circuit-ID contains characters that cannot be used.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                '',
                sprintf('Circuit-ID contains characters that cannot be used.(%s)(line: %s)', $params['circuit_id'], $line),
            ],
            "option" => [ 'allowempty'],
        ];

        $rules['no_hex_circuit'] = [
            "method" => "true_or_false",
            "msg"=> [
                _('circuit_id_not_hex is in bad format.') . sprintf(_('(line: %s)'), $line),
            ],
            "log"=> [
                sprintf('circuit_id_not_hex is in bad format.(%s)(line: %s)', $params['no_hex_circuit'], $line),
            ],
        ];

        $rules["remote_id"] = [
            "method"=>"exist|invalid_chars",
            "msg"=> [
                _(''),
                _('Remote-ID contains characters that cannot be used.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                '',
                sprintf('Remote-ID contains characters that cannot be used.(%s)(line: %s)', $params['remote_id'], $line),
            ],
            "option" => [ 'allowempty'],
        ];

        $rules['no_hex_remote'] = [
            "method" => "true_or_false",
            "msg"=> [
                _('remote_id_not_hex is in bad format.') . sprintf(_('(line: %s)'), $line),
            ],
            "log"=> [
                sprintf('remote_id_not_hex is in bad format.(%s)(line: %s)', $params['no_hex_remote'], $line),
            ],
        ];

        $rules["mac_address"] = [
            "method"=>"exist|macaddr|max:64|duplicate:HEX(dhcp_identifier):remove_both:0|duplicate_option82_mac",
            'msg'    => [
                _(''),
                _('MAC address format is incorrect.') . sprintf(_('(line: %s)'), $line),
                _('MAC address format is incorrect.') . sprintf(_('(line: %s)'), $line),
                _('MAC address already exists.') . sprintf(_('(line: %s)'), $line),
                _('MAC address already exists.') . sprintf(_('(line: %s)'), $line),
            ],
            'log'    => [
                '',
                sprintf('MAC address format is incorrect.(%s)(line: %s)', $params['mac_address'], $line),
                sprintf('MAC address format is incorrect.(%s)(line: %s)', $params['mac_address'], $line),
                sprintf('MAC address already exists.(%s)(line: %s)', $params['mac_address'], $line),
                sprintf('MAC address already exists.(%s)(line: %s)', $params['mac_address'], $line),
            ],
            "option" => [ 'allowempty'],
        ];

        /* input store into values */
        $params['store'] = $this->store;

        $validater = new validater($rules, $params, true);

        /* keep insert params */
        $this->pre = $validater->err["keys"];

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->tag_arr = $validater->tags;
            return false;
        }

        /* Rule Initialization */
        $rules = [];
        $pool_start = $params['pool_start'];

        /* Combined inspection of multiple parameters */
        /*
            The method delimiter is a backslash
            Argument delimiter is a comma
        */

        $rules["pool_end"] = [
            "method"=>"greateripv4,$pool_start\\includepool,$pool_start,$subnet",
            "msg"=> [
                _('Pool IP address(start) greater then Pool IP address(end).') . sprintf(_('(line: %s)'), $line),
                _('Pool IP address range includes used pools.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                sprintf('Pool IP address(start) greater then Pool IP address(end).(%s-%s)(line: %s)', $params['pool_start'], $params['pool_end'], $line),
                sprintf('Pool IP address range includes used pools.(%s-%s)(line: %s)', $params['pool_start'], $params['pool_end'], $line),
            ],
        ];

        /* Variables for creating conditions */
        $circuit_id = null;
        $remote_id = null;
        $mac_address = null;

        /* Check for duplicate conditions combining Circuit-ID, Remote-ID, and MAC address */
        /* Assemble payout conditions for inspection */
        if ($params['circuit_id'] !== '') {
            if ($params['no_hex_circuit'] === 'true') {
                $circuit_id = "'" . $params['circuit_id'] . "'";
            } else {
                $circuit_id = '0x' . bin2hex($params['circuit_id']);
            }
        }

        if ($params['remote_id'] !== '') {
            if ($params['no_hex_remote'] === 'true') {
                $remote_id = "'" . $params['remote_id'] . "'";
            } else {
                $remote_id = '0x' . bin2hex($params['remote_id']);
            }
        }

        if ($params['mac_address'] !== '') {
            $mac_address = '0x' . remove_colon($params['mac_address']);
        }
        $basic_condition = $this->conf->create_payout_condition($circuit_id, $remote_id, $mac_address);

        $rules["circuit_id"] = [
            "method"=>"duplicate_payout_condition,dhcp4,$basic_condition",
            "msg"=> [
                _('Same condition is already exists.')  . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                sprintf('Same condition is already exists.(%s)(line: %s)', $basic_condition, $line),
            ],
        ];

        /* Separators are defined by prohibited characters */
        $validater = new validater($rules, $params, true, "\\", ',');

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->tag_arr = $validater->tags;
            return false;
        }

        /* Check for the existence of leased IP addresses within the range of IP addresses on loan */
        $rules = [];
        $pool_end = $params['pool_end'];
        $allow_leased = $params['allowleased'];
        $rules["alreadyleased"] = [
            "method"=>"alreadyleased4:$pool_start:$pool_end:$allow_leased:$subnet",
            "msg"=> [
                _('Leased IP addresses exist within the Pool IP address range.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                sprintf('Leased IP addresses exist within the Pool IP address range.(%s-%s)(line: %s)', $pool_start, $pool_end, $line),
            ],
        ];

        $validater = new validater($rules, $params, true);

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
    * Method        : validate_post_del_option82
    * args          : $params - POST values
    *               : $line   - Number of lines in conf
    * return        : true or false
    *************************************************************************/
    public function validate_post_del_option82($params, $line)
    {
        /* Initializing Error Messages */
        $this->msg_tag = ['success'                => null,
                          'disp_msg'               => null,
                          'e_msg'                  => null];

        /*  define rules */
        $rules["class_name"] = [
            "method"=>"exist|option82format|classexist:dhcp4",
            "msg"=> [
                 _('Please enter class name.') . sprintf(_('(line: %s)'), $line),
                 _('Class name must begin with opt82_.') . sprintf(_('(line: %s)'), $line),
                 _('Class name does not exist in config.') . sprintf(_('(line: %s)'), $line),
             ],
             "log"=> [
                 sprintf('Please enter class name.(line: %s)', $line),
                 sprintf('Class name must begin with opt82_.(%s)(line: %s)', $params["class_name"], $line),
                 sprintf('Class name does not exist in config.(%s)(line: %s)', $params["class_name"], $line),
            ],
        ];

        /* input store into values */
        $params['store'] = $this->store;

        $validater = new validater($rules, $params, true);

        /* keep insert params */
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
                case 'dhcp4_subnet_id':
                    $hosts_val[$col] = intval($data);
                    break;
                case 'dhcp_identifier':
                    $data = $this->store->db->dbh->quote($data);
                    if (strval($hosts_val['dhcp_identifier_type']) === CIRCUITID_TYPE )
                    {
                        $hosts_val[$col] = $data;
                        break;
                    }
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
    * args          : $options_val
    * return        : none
    *************************************************************************/
    private function _options_query($options_val)
    {
        global $options;
        global $scope_id;

        $dbutil = new dbutils($this->store->db);

        /* define insert column */
        $lastid = $this->store->db->last_insertid();
        $insert_data = ['host_id' => $lastid,
                        'code' => '', 'formatted_value' => '', 
                        'scope_id' => $scope_id['host']];

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
    * args          : $csv_data
    * return        : none
    *************************************************************************/
    public function insert_params ($csv_data)
    {
        /* replace variable */
        $params = $csv_data;
        $params['dhcp4_subnet_id'] = $this->conf->get_subnet_id($csv_data['subnet']);

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

        $log_format = "Add successful.(ip: %s id: %s)";
        $success_log = sprintf($log_format, $forhosts['ipv4_address'],
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
        $dbutil->from('hosts');

        $inet_ipaddr = $this->store->db->inet_aton($ipaddr, true);
        $dbutil->where(sprintf('ipv4_address = %s', $inet_ipaddr));

        /* return all data */
        $hosts_data = $dbutil->get();

        /* delete */
        $host_id = $hosts_data[0]['host_id'];

        /* delete from dhcp4_options */
        try {
            $this->dbutil = new dbutils($this->store->db);
            /* make FROM statement */
            $this->dbutil->from('dhcp4_options');
            /* make where statement of subnet_id */
            $this->dbutil->where('host_id', $host_id);
            $this->dbutil->delete();

        } catch (Exception $e) {
            /* if failed to insert, execute rollback */
            $this->store->db->rollback();
            $log_msg = 'failed to delete data from dhcp4_options.';
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

        $log_msg = "Reservation IP deleted.(ip: " . $ipaddr . ")";
        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Reservation IP deleted.");

    }

    /*************************************************************************
    * Method        : apply_csvfile_host
    * args          : $fp
    *               : $mode
    * return        : true/false
    *************************************************************************/
    public function apply_csvfile_host($mode)
    {
        global $log_msg;
        $all_tag = [];
        $all_data = [];
        $duplicate_arr['hostname'] = [];
        $duplicate_arr['dhcp_identifier'] = [];
        $duplicate_arr['ipv4_address'] = [];

        $line = 0;
        $err_flag = 0;

        /* check csv file */
        if ($_FILES["csvfile"]["tmp_name"] == "") {
            $this->store->log->output_log("Csv file is not selected.");
            $this->msg_tag['disp_msg'] = _("Please select csv file.");
            return false;
        }

        /* open csvfile */
        $fp = fopen($_FILES["csvfile"]["tmp_name"], 'r');
        if ($fp === false) {
            $this->store->log->output_log("Failed to open csvfile.("
                                         . $_FILES["csvfile"]["name"] . ")");
            $this->msg_tag['disp_msg'] = _("Failed to open csvfile.");
            return false;
        }

        while (($tmpline = fgets($fp)) !== false) {

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
            if (count_array($csvdata) !== 11) {
                $this->store->log->output_log("Invalid number of columns.(line: " . $line . ")");
                $this->tag_arr['e_csv_column'] = _("Invalid number of columns.") . sprintf(_('(line: %s)'), $line);

                $all_tag[$line] = $this->tag_arr;
                $err_flag = 1;
                continue;
            }

            /* Validation check */
            $data = [
                'subnet'               => $csvdata[0],
                'dhcp_identifier_type' => $csvdata[1],
                'dhcp_identifier'      => $csvdata[2],
                'ipv4_address'         => $csvdata[3],
                'hostname'             => $csvdata[4],
                'dhcp4_next_server'    => $csvdata[5],
                'dhcp4_boot_file_name' => $csvdata[6],
                'domain_name_servers'  => $csvdata[7],
                'routers'              => $csvdata[8],
                'tftp_server_name'     => $csvdata[9],
                'boot_file_name'       => $csvdata[10]
            ];

            /* First check subnet */
            $ret = $this->validate_subnet($data, $line);
            if ($ret === false) {
                $all_tag[$line] = $this->msg_tag;
                $err_flag = 1;
                continue;
            }

            /* Add mode */
            if ($mode == 0) {
                $ret = $this->validate_post_add_host($data, $line);

                /* Duplicate check in CSV file */
                if (in_array($data['hostname'], $duplicate_arr['hostname'])) {
                    $duplicate_flag = 1;
                    $this->store->log->output_log("hostname is duplicated in registration data.(line: " . $line . ")");
                    $this->tag_arr['e_csv_hostname'] = _("hostname is duplicated in registration data.") . sprintf(_('(line: %s)'), $line);
                }

                if (in_array($data['dhcp_identifier'], $duplicate_arr['dhcp_identifier'])) {
                    $duplicate_flag = 1;
                    $this->store->log->output_log("dhcp_identifier is duplicated in registration data.(line: " . $line . ")");
                    $this->tag_arr['e_csv_identifier'] = _("identifier is duplicated in registration data.") . sprintf(_('(line: %s)'), $line);
                }

                /* Store values for duplication check in array */
                if ($data['dhcp_identifier'] != "") { 
                    $duplicate_arr['dhcp_identifier'][] = $data['dhcp_identifier'];
                }
                if ($data['hostname'] != "") {
                    $duplicate_arr['hostname'][] = $data['hostname'];
                }

            /* Delete mode */
            } else if ($mode == 1) {
                $ret = $this->validate_post_del_host($data, $line);
            }

            /* Duplicate check in CSV file */
           if (in_array($data['ipv4_address'], $duplicate_arr['ipv4_address'])) {
               $duplicate_flag = 1;
                $this->store->log->output_log("ipv4_address is duplicated in registration data.(line: " . $line . ")");
               $this->tag_arr['e_csv_ipv4'] = _("IP address is duplicated in registration data.") . sprintf(_('(line: %s)'), $line);
            }

            /* When errors occured, get log */
            if ($ret === false || $duplicate_flag === 1) {
                $err_flag = 1;
                $all_tag[$line] = $this->tag_arr;
            }

            /* Store values for duplication check in array */
            if ($data['ipv4_address'] != "") {
                $duplicate_arr['ipv4_address'][] = $data['ipv4_address'];
            }

            $this->pre['subnet'] = $data['subnet'];
            $all_data[] = $this->pre;
        }

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
            return false;
        }

        /* If file is empty */
        if ($line == 0) {
            $this->store->log->output_log("The file content is empty.");
            $this->msg_tag['disp_msg'] = _("The file content is empty.");
            return false;
        }

        /* begin transaction */
        $this->store->db->begin_transaction();

        /* Add */
        if ($mode == 0) {
            foreach ($all_data as $one_data) {
                $this->insert_params($one_data);
            }
        } else if ($mode == 1) {
            /* Delete */
            foreach ($all_data as $one_data) {
                $this->delete($one_data['ipv4_address']);
            }
        }

        /* commit inserted data */
        $this->store->db->commit();

        return true;
    }

    /*************************************************************************
    * Method        : apply_csvfile_option82_add
    * args          : $mode
    * return        : true/false
    *************************************************************************/
    public function apply_csvfile_option82_add($allowleased) {
        global $log_msg;
        $all_tag = [];
        $all_data = [];

        $line = 0;
        $err_flag = 0;
        $require_column = 8;

        /* check csv file */
        if ($_FILES["csvfile"]["tmp_name"] == "") {
            $this->store->log->output_log("Csv file is not selected.");
            $this->msg_tag['disp_msg'] = _("Please select csv file.");
            return false;
        }

        /* open csvfile */
        $fp = fopen($_FILES["csvfile"]["tmp_name"], 'r');
        if ($fp === false) {
            $this->store->log->output_log("Failed to open csvfile.("
                                         . $_FILES["csvfile"]["name"] . ")");
            $this->msg_tag['disp_msg'] = _("Failed to open csvfile.");
            return false;
        }

        while (($tmpline = fgets($fp)) !== false) {

            /* Count of rows */
            $line++;
            $all_tag[$line] = array();
            $this->tag_arr = [];

            /* Skip comments */
            if (substr($tmpline, 0, 1) === '#') {
                continue;
            }

            /* Skip first line */
            if (substr($tmpline, 0, 6) === 'subnet') {
                continue;
            }

            /* Separate by commas */
            $tmpline = rtrim($tmpline);
            $csvdata = str_getcsv($tmpline);

            $this->msg_tag = ['success'                => null,
                              'disp_msg'               => null,
                              'e_msg'                  => null];

            /* Check number of columns */
            if (count_array($csvdata) !== 8) {
                $this->store->log->output_log("Invalid number of columns.(line: " . $line . ")");
                $this->tag_arr['e_csv_column'] = _("Invalid number of columns.") . sprintf(_('(line: %s)'), $line);

                $all_tag[$line] = $this->tag_arr;
                $err_flag = 1;
                continue;
            }

            /* Validation check */
            $all_data[$line] = [
                'subnet'            => $csvdata[0],
                'pool_start'        => $csvdata[1],
                'pool_end'          => $csvdata[2],
                'circuit_id'        => $csvdata[3],
                'no_hex_circuit'    => strtolower($csvdata[4]),
                'remote_id'         => $csvdata[5],
                'no_hex_remote'     => strtolower($csvdata[6]),
                'mac_address'       => $csvdata[7],
                'allowleased'       => $allowleased,
                'alreadyleased'     => 'false',
                'is_advanced'       => 'false',
            ];
        }

        /* If file is empty */
        if ($line == 0) {
            $this->store->log->output_log("The file content is empty.");
            $this->msg_tag['disp_msg'] = _("The file content is empty.");
            return false;
        }

        /* Keep pre-added settings for rollback */
        $before_config = $this->conf->get_conf_from_sess();
        $before_hist = $this->conf->get_hist_from_sess();
        $total_num = count_array($all_data);
        $merge_tag_arr = [];
        foreach ($all_data as $line => $data) {
            /* First check subnet */
            $ret = $this->validate_subnet($data, $line);
            if ($ret === false) {
                $all_tag[$line] = $this->msg_tag;
                $err_flag = 1;
                continue;
            }

            $ret = $this->validate_post_add_option82($data, $line);

            /* When errors occured, get log */
            if ($ret === false) {
                $err_flag = 1;
                $all_tag[$line] = $this->tag_arr;
                continue;
            }

            $this->pre['subnet'] = $data['subnet'];
            $this->pre['is_advanced'] = 'false';

            [$ret, $new_config] = $this->conf->add_option82($data, $data['subnet']); 
            if ($ret === false) {
                $err_flg = 1;
                $this->msg_tag['e_msg'] = $this->conf->err['e_msg'];
                $this->store->log->log($this->conf->err['e_log']);
                break;
            }
            /* save new config to session */
            $this->conf->save_conf_to_sess($new_config);

            $success_log = sprintf("Option82 setting was successfully added.(Circuit-ID: %s, Remote-ID: %s, Mac address: %s, Range: %s-%s)"
                ,$data['circuit_id'], $data['remote_id'], $data['mac_address'], $data['pool_start'], $data['pool_end']);
            $this->store->log->log($success_log);
            $this->conf->get_config(DHCPV4);
        }

        foreach ($all_tag as $value) {
            foreach ($value as $key => $val) {
                if (preg_match("/^e_/", $key) && $val != "") {
                    array_push($merge_tag_arr, $val);
                }
            }
        }

        if ($err_flag === 1) {
            $this->csv_err = $merge_tag_arr;
            /* Rollback to previous settings */
            $this->conf->save_conf_to_sess($before_config);

            /* Clear the operation history */
            $this->conf->delete_hist_from_sess(); 

            /* Restore original history */
            if (!is_null($before_hist)) {
                foreach ($before_hist as $one_hist) {
                    $this->conf->save_hist_to_sess($one_hist);
                }
            }
            
            $err_log = 'Rolled back due to failed add.';
            $this->store->log->log($err_log);
            
            return false;
        }

        /* If a pool exists for which no client class is defined, define a no-member class */
        $new_config = $this->conf->assign_nomember($new_config);

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $success_log = sprintf('Option82 setting was successfully added.(total: %s)', $total_num);
        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);
        $this->store->log->log($success_log);

        $this->msg_tag['success'] = _('Add successful!');

        return true;
    }

    /*************************************************************************
    * Method        : apply_csvfile_option82_del
    * args          : $mode
    * return        : true/false
    *************************************************************************/
    public function apply_csvfile_option82_del() {
        global $log_msg;
        $all_tag = [];
        $all_data = [];

        $line = 0;
        $err_flag = 0;
        $require_column = 2;

        /* check csv file */
        if ($_FILES["csvfile"]["tmp_name"] == "") {
            $this->store->log->output_log("Csv file is not selected.");
            $this->msg_tag['disp_msg'] = _("Please select csv file.");
            return false;
        }

        /* open csvfile */
        $fp = fopen($_FILES["csvfile"]["tmp_name"], 'r');
        if ($fp === false) {
            $this->store->log->output_log("Failed to open csvfile.("
                                         . $_FILES["csvfile"]["name"] . ")");
            $this->msg_tag['disp_msg'] = _("Failed to open csvfile.");
            return false;
        }

        while (($tmpline = fgets($fp)) !== false) {

            /* Count of rows */
            $line++;
            $all_tag[$line] = array();
            $this->tag_arr = [];

            /* Skip comments */
            if (substr($tmpline, 0, 1) === '#') {
                continue;
            }

            /* Skip first line */
            if (substr($tmpline, 0, 6) === 'subnet') {
                continue;
            }

            /* Separate by commas */
            $tmpline = rtrim($tmpline);
            $csvdata = str_getcsv($tmpline);

            $this->msg_tag = ['success'                => null,
                              'disp_msg'               => null,
                              'e_msg'                  => null];

            /* Check number of columns */
            if (count_array($csvdata) < 2) {
                $this->store->log->output_log("Invalid number of columns.(line: " . $line . ")");
                $this->tag_arr['e_csv_column'] = _("Invalid number of columns.") . sprintf(_('(line: %s)'), $line);

                $all_tag[$line] = $this->tag_arr;
                $err_flag = 1;
                continue;
            }

            /* Validation check */
            $all_data[$line] = [
                'subnet'            => $csvdata[0],
                'class_name'        => $csvdata[1],
            ];
        }

        /* If file is empty */
        if ($line == 0) {
            $this->store->log->output_log("The file content is empty.");
            $this->msg_tag['disp_msg'] = _("The file content is empty.");
            return false;
        }

        /* Keep pre-added settings for rollback */
        $before_config = $this->conf->get_conf_from_sess();
        $before_hist = $this->conf->get_hist_from_sess();
        $total_num = count_array($all_data);
        $merge_tag_arr = [];
        foreach ($all_data as $line => $data) {
            /* First check subnet */
            $ret = $this->validate_subnet($data, $line);
            if ($ret === false) {
                $all_tag[$line] = $this->msg_tag;
                $err_flag = 1;
                continue;
            }

            $ret = $this->validate_post_del_option82($data, $line);

            /* When errors occured, get log */
            if ($ret === false) {
                $err_flag = 1;
                $all_tag[$line] = $this->tag_arr;
                continue;
            }


            $new_config = $this->conf->delete_option82($data['class_name'], $data['subnet']); 

            /* save new config to session */
            $this->conf->save_conf_to_sess($new_config);
            $this->conf->get_config(DHCPV4);

            $success_log = sprintf('Option82 setting deleted successfully.(line: %s)', $line);
            $this->store->log->log($success_log);
        }

        foreach ($all_tag as $value) {
            foreach ($value as $key => $val) {
                if (preg_match("/^e_/", $key) && $val != "") {
                    array_push($merge_tag_arr, $val);
                }
            }
        }

        if ($err_flag === 1) {
            $this->csv_err = $merge_tag_arr;
            /* Rollback to previous settings */
            $this->conf->save_conf_to_sess($before_config);

            /* Clear the operation history */
            $this->conf->delete_hist_from_sess(); 

            /* Restore original history */
            if (!is_null($before_hist)) {
                foreach ($before_hist as $one_hist) {
                    $this->conf->save_hist_to_sess($one_hist);
                }
            }

            $err_log = 'Rolled back due to failed delete.';
            $this->store->log->log($err_log);

            return false;
        }

        $success_log = sprintf('Option82 setting deleted successfully.(total: %s)', $total_num);
        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);
        $this->store->log->log($success_log);

        $this->msg_tag['success'] = _('Option82 setting deleted successfully.');

        return true;
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
        $this->store->view->assign('config_type', $this->config_type);
        $this->store->view->assign('allowleased', $this->allowleased);
        $this->store->view->assign('mode', $this->mode);
        $this->store->view->render("bulkhost4.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
*  main
******************************************************************************/
$bh4 = new BulkHost4($store);

if ($bh4->check_conf === false) {
    $bh4->display();
    exit(1);
}

$apply = post('apply');

if (isset($apply)) {
    /************************************
    * apply section
    ************************************/
    $config_type = post('config_type');
    $mode = post('mode');

    /* Keep mode and config_type */    
    $bh4->mode = $mode;
    $bh4->config_type = $config_type;

    if ($config_type === 'host') {
        $bh4->apply_csvfile_host($mode);
    } else if ($config_type === 'option82') {
        switch ($mode) {
            case '0':
                $allowleased = post('allow_leased', 'false');
                $bh4->allowleased = $allowleased;
                $bh4->apply_csvfile_option82_add($allowleased);
                break;

            case '1':
                $bh4->apply_csvfile_option82_del();

            default:
                break;
        }
    }

    $bh4->display();
    exit;
}

/************************************
* default section
************************************/
$bh4->display();
