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

        $this->conf = new KeaConf(DHCPV4);
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
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
           'method' => 'exist|regex:/^[0-2]$/',
           'msg'    => [_('Please enter type.') . sprintf(_('(line: %s)'), $line),
                        _('Invalid type of identifier.') . sprintf(_('(line: %s)'), $line)],
           'log'    => ['Empty type of identifier.(line: ' . $line . ')',
                        'Invalid type of identifier('
                        . $values['dhcp_identifier_type'] . ').(line: ' . $line . ')']
          ];

        switch ($values['dhcp_identifier_type']) {
            case "MAC":
                $values['dhcp_identifier_type'] = 0;
                $id_format = 'macaddr';
                $id_num = 0;
                break;
            case"DUID":
                $values['dhcp_identifier_type'] = 1;
                $id_format = 'duid';
                $id_num = 1;
                break;
            case "Circuit-ID":
                $values['dhcp_identifier_type'] = 2;
                $id_format = 'circuitid';
                $id_num = 2;
                break;
            default:
                $id_format = '';
                $id_num = '';
                break;
        }

        if ($id_format !== "" || $id_num !== "") {

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

        $this->store->log->log($success_log, null);
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
    * Method        : apply_csvfile
    * args          : $fp
    *               : $mode
    * return        : true/false
    *************************************************************************/
    public function apply_csvfile($mode)
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
            if (count($csvdata) !== 11) {
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
                $ret = $this->validate_post_add($data, $line);

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
                $ret = $this->validate_post_del($data, $line);
            }

            /* Duplicate check in CSV file */
           if (in_array($data['ipv4_address'], $duplicate_arr['ipv4_address'])) {
               $duplicate_flag = 1;
                $this->store->log->output_log("ipv4_address is duplicated in registration data.(line: " . $line . ")");
               $this->tag_arr['e_csv_ipv4'] = _("IP address is duplicated in registration data.") . sprintf(_('(line: %s)'), $line);
            }

            /* When errors occured, get log */
            if ($ret === FALSE || $duplicate_flag === 1) {
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
    $mode = post('mode');
    $ret = $bh4->apply_csvfile($mode);

    $bh4->display();
    exit;
}

/************************************
* default section
************************************/
$bh4->display();
