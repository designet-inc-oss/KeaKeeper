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


require "../bootstrap.php";

/*****************************************************************************
 * Class:  AddOption82
 *
 * [Description]
 *   Class for add, update, edit options of subnet
 *****************************************************************************/
class AddOption82 {

    public  $msg_tag;
    public  $conf;
    private $store;
    private $err_tag;
    private $pre;

    /*************************************************************************
     * Method        : __construct
     * Description   : Method for setting tags automatically
     * args          : $store
     * return        : None
     **************************************************************************/
    public function __construct($store)
    {
        /* Tag */
        $this->msg_tag =  [
                            "e_msg"                 => null,
                            "e_subnet"              => null,
                            "e_pool_start"          => null,
                            "e_pool_end"            => null,
                            "e_circuit_id"          => null,
                            "e_remote_id"           => null,
                            "e_mac_address"         => null,
                            "e_advanced_setting"    => null,
                            "e_alreadyleased"       => null,
                            "success"               => null,
                          ];
        $this->err_tag =  [
                            "e_msg"                 => null,
                          ];

        /* conditions */
        $this->pre = [
            'pool_start'       => '',
            'pool_end'         => '',
            'is_advanced'      => 'false',
            'circuit_id'       => '',
            'no_hex_circuit'   => 'false',
            'remote_id'        => '',
            'no_hex_remote'    => 'false',
            'mac_address'      => '',
            'advanced_setting' => '',
            'alreadyleased'    => 'false',
            'allowleased'      => 'false',
        ];

        $this->subnet = "";
        $this->store  = $store;

        /* read keaconf */
        $this->store = $store;

        /* call kea.conf class */
        $this->conf = new KeaConf(DHCPV4);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
        }
    }

    /*************************************************************************
     * Method        : validate_subnet
     * Description   : Method for Checking subet in get value
     * args          : $params - GET data
     * return        : true/false
     *************************************************************************/
    public function validate_subnet($params)
    {
        $rules["subnet"] = [
            "method"=>"exist|subnet4exist:exist_true",
            "msg"=> [
                 _('Can not find a subnet.'),
                 _('Subnet does not exist in config.'),
             ],
             "log"=> [
                 'Can not find a subnet in GET parameters.',
                 sprintf('Subnet does not exist in config.(%s)', $params["subnet"]),
            ],
        ];

        $validater = new validater($rules, $params, true);
        /* keep validated value into property */
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        /* keep subnet value */
        $this->subnet = $params['subnet'];
        return true;
    }

    /*************************************************************************
     * Method        : validate_post
     * Description   : Method for Checking post values
     * args          : $params - POST data
     * return        : true/false
     *************************************************************************/
    public function validate_post($params) {

        /* Not advanced settings, but from all Circuit IDs, Remote IDs, and MAC addresses. */
        if ($params['is_advanced'] === 'false' && $params['circuit_id'] === '' && $params['remote_id'] === '' && $params['mac_address'] === '') {
            $this->msg_tag['e_msg'] = _('If Advanced Settings is not checked, one of Circuit-ID, Remote-ID, or MAC Address must be entered.');
            $err_log = 'If Advanced Settings is not checked, one of Circuit-ID, Remote-ID, or MAC Address must be entered.';
            $this->store->log->log($err_log);

            $this->pre = $params;
            return false;
        }

        /*  define rules */
        /* Parameter stand-alone inspection */
        $rules["pool_start"] = [
            "method"=>"exist|ipv4|insubnet4:$this->subnet|ipv4overlap",
            "msg"=> [
                _('Please enter Pool IP address range(start).'),
                _('Invalid Pool IP address range(start).'),
                _('Pool IP address range(start) is outside the range of the subnet.'),
                _('Pool IP address range(start) already exists.')
             ],
             "log"=> [
                'Please enter Pool IP address range(start).',
                sprintf('Invalid Pool IP address range(start).(%s)', $params['pool_start']),
                sprintf('Pool IP address range(start) is outside the range of the subnet.(%s)', $params['pool_start']),
                sprintf('Pool IP address range(start) already exists.(%s)', $params['pool_start']),
            ],
        ];

        $rules["pool_end"] = [
            "method"=>"exist|ipv4|insubnet4:$this->subnet|ipv4overlap",
            "msg"=> [
                _('Please enter Pool IP address range(end).'),
                _('Invalid Pool IP address range(end).'),
                _('Pool IP address range(end) is outside the range of the subnet.'),
                _('Pool IP address range(end) already exists.')
             ],
             "log"=> [
                'Please enter Pool IP address range(end).',
                sprintf('Invalid Pool IP address range(end).(%s)', $params['pool_end']),
                sprintf('Pool IP address range(end) is outside the range of the subnet.(%s)', $params['pool_end']),
                sprintf('Pool IP address range(end) already exists.(%s)', $params['pool_end']),
            ],
        ];


        if ($params['is_advanced'] === 'true') {
            $rules["advanced_setting"] = [
                "method" => "exist|duplicate_payout_condition:dhcp4",
                'msg'    => [
                    _('If the advance setting is enabled, please describe the payout conditions in the free description.'),
                    _('Same condition is already exists.'),
                ],
                'log'    => [
                    sprintf('If the advance setting is enabled, please describe the payout conditions in the free description.'),
                    sprintf('Same condition is already exists.(%s)', $params['advanced_setting']),
                ],
            ];
        } else {
            $rules["circuit_id"] = [
                "method"=>"exist|invalid_chars",
                "msg"=> [
                    _(''),
                    _('Circuit-ID contains characters that cannot be used.'),
                 ],
                 "log"=> [
                    '',
                    sprintf('Circuit-ID contains characters that cannot be used.(%s)', $params['circuit_id']),
                ],
                "option" => [ 'allowempty'],
            ];

            $rules["remote_id"] = [
                "method"=>"exist|invalid_chars",
                "msg"=> [
                    _(''),
                    _('Remote-ID contains characters that cannot be used.'),
                 ],
                 "log"=> [
                    '',
                    sprintf('Remote-ID contains characters that cannot be used.(%s)', $params['remote_id']),
                ],
                "option" => [ 'allowempty'],
            ];

            $rules["mac_address"] = [
                "method"=>"exist|macaddr|max:64|duplicate:HEX(dhcp_identifier):remove_both:0|duplicate_option82_mac",
                'msg'    => [
                    _(''),
                    _('MAC address format is incorrect.'),
                    _('MAC address format is incorrect.'),
                    _('MAC address already exists.'),
                    _('MAC address already exists.')
                ],
                'log'    => [
                    '',
                    sprintf('MAC address format is incorrect.(%s)', $params['mac_address']),
                    sprintf('MAC address format is incorrect.(%s)', $params['mac_address']),
                    sprintf('MAC address already exists.(%s)', $params['mac_address']),
                    sprintf('MAC address already exists.(%s)', $params['mac_address']),
                ],
                "option" => [ 'allowempty'],
            ];
        }

        /* input store into values */
        $params['store'] = $this->store;

        $validater = new validater($rules, $params, true);
        $this->err_tag = $validater->tags;
        $this->pre = $validater->err["keys"];
        $this->pre['is_advanced'] = $params['is_advanced'];
        $this->pre['no_hex_circuit'] = $params['no_hex_circuit'];
        $this->pre['no_hex_remote'] = $params['no_hex_remote'];
        $this->pre['alreadyleased'] = $params['alreadyleased'];
        $this->pre['allowleased'] = $params['allowleased'];
        if ($this->pre['is_advanced'] === 'true') {
            $this->pre['circuit_id'] = $params['circuit_id'];
            $this->pre['remote_id'] = $params['remote_id'];
            $this->pre['mac_address'] = $params['mac_address'];
        } else {
            $this->pre['advanced_setting'] = $params['advanced_setting'];
        }

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
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
            "method"=>"greateripv4,$pool_start\\includepool,$pool_start,$this->subnet",
            "msg"=> [
                _('Pool IP address range(start) greater then Pool IP address range(end).'),
                _('Pool IP address range includes used pools.'),
             ],
             "log"=> [
                sprintf('Pool IP address range(start) greater then Pool IP address range(end).(%s-%s)', $params['pool_start'], $params['pool_end']),
                sprintf('Pool IP address range includes used pools.(%s-%s)', $params['pool_start'], $params['pool_end']),
            ],
        ];

        /* Variables for creating conditions */
        $circuit_id = null;
        $remote_id = null;
        $mac_address = null;

        /* basic setting */
        if ($params['is_advanced'] === 'false') {
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
                    _('Same condition is already exists.'),
                 ],
                 "log"=> [
                    sprintf('Same condition is already exists.(%s)', $basic_condition),
                ],
            ];
        }

        /* Separators are defined by prohibited characters */
        $validater = new validater($rules, $params, true, "\\", ',');
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        /* Check for the existence of leased IP addresses within the range of IP addresses on loan */
        $rules = [];
        $pool_end = $params['pool_end'];
        $allow_leased = $params['allowleased'];
        $rules["alreadyleased"] = [
            "method"=>"alreadyleased4:$pool_start:$pool_end:$allow_leased:$this->subnet",
            "msg"=> [
                _('Leased IP addresses exist within the Pool IP address range.To continue the process, click the Add button.'),
             ],
             "log"=> [
                sprintf('Leased IP addresses exist within the Pool IP address range.(%s-%s)', $pool_start, $pool_end),
            ],
        ];

        $validater = new validater($rules, $params, true);
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->pre['alreadyleased'] = true;
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : add_option82_setting
     * Description   : Add option82 setting
     * args          : $params
     * return        : true
     *************************************************************************/
    public function add_option82_setting($params) {
        /* add option82 setting */
        [$ret, $new_config] = $this->conf->add_option82($params, $this->subnet);
        if ($ret === false) {
            $this->msg_tag['e_msg'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        /* If a pool exists for which no client class is defined, define a no-member class */
        $new_config = $this->conf->assign_nomember($new_config);

        /* Check if the added settings are applicable */
        /* delete hash value */
        unset($new_config['hash']);
        $jsondata = json_encode($new_config);
        $kea_api = new KeaAPI('dhcp4');
        $ret = $kea_api->dg_config_test($jsondata);
        if ($ret === false) {
            $this->msg_tag['e_msg'] = _('This payout condition cannot be used.');
            $this->store->log->log('This payout condition cannot be used.');
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        if ($this->pre['is_advanced'] === 'true') {
            $success_log = sprintf("Option82 setting was successfully added.(Condition: %s, Range: %s-%s)"
                ,$this->pre['advanced_setting'], $this->pre['pool_start'], $this->pre['pool_end']);
        } else {
            $success_log = sprintf("Option82 setting was successfully added.(Circuit-ID: %s, Remote-ID: %s, Mac address: %s, Range: %s-%s)"
                ,$this->pre['circuit_id'], $this->pre['remote_id'], $this->pre['mac_address'], $this->pre['pool_start'], $this->pre['pool_end']);
        }

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);
        $this->store->log->log($success_log);

        return true;
    }

    /*************************************************************************
     * Method        : display
     * Description   : Method for displaying the template on the screen
     * args          : None
     * return        : None
     *************************************************************************/
    public function display()
    {
        $array = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign('subnet', $this->subnet);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("addoption82.tmpl", $array);
    }
}

/*************************************************************************
 *  main
 *************************************************************************/
$objAddOption82 = new AddOption82($store);

/* check current config  */
if ($objAddOption82->conf->result === false) {
    $objAddOption82->display();
    exit(1);
}

/************************************
 * Initial display
 ************************************/
$subnet = get('subnet');
$params = ['subnet' => $subnet];
$ret = $objAddOption82->validate_subnet($params);
if ($ret === false) {
    $objAddOption82->display();
    exit(1);
}

/************************************
 * add section
 ************************************/
$add = post('add');
if (isset($add)) {
    /* validate add data */
    $params = [ 
        'pool_start'          => post('pool_start'),
        'pool_end'            => post('pool_end'),
        'is_advanced'         => post('is_advanced', 'false'),
        'circuit_id'          => post('circuit_id', ''),
        'no_hex_circuit'      => post('no_hex_circuit', 'false'),
        'remote_id'           => post('remote_id', ''),
        'no_hex_remote'       => post('no_hex_remote', 'false'),
        'mac_address'         => post('mac_address', ''),
        'advanced_setting'    => post('advanced_setting', ''),
        'alreadyleased'       => post('alreadyleased', 'false'),
        'allowleased'         => post('allowleased', 'false'),
    ];

    $ret = $objAddOption82->validate_post($params);
    if ($ret === true) {
        /* add option82 setting */
        $ret = $objAddOption82->add_option82_setting($params);
        if ($ret === false) {
            $objAddOption82->display();
            exit(1);
        }
        header("Location: listoption82.php?subnet=$subnet&msg=add_ok");
        exit(0);
    }
}

$objAddOption82->display();
exit(0);
