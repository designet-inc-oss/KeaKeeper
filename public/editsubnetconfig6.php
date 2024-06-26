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
 * Class          : EditSubnetConfig6
 * Description    : Class for edit subnet config
 * args           : $store
 *****************************************************************************/
class EditSubnetConfig6
{
    /*
     * constant message 
     */
     const MSG_SUBNET_MISSING  = 'Subnet does not exists.(%s)';

    /*
     * constant log
     */
     const LOG_SUBNET_MISSING  = 'Subnet does not exists.(%s)';
 
    /*
     * properties
     */
    public  $conf;
    private $pre;
    private $exist = [];
    private $store;
    private $msg_tag;
    private $err_tag;
    public  $check_subnet;

    /************************************************************************
     * Method        : __construct
     * args          : None
     * return        : None
     *************************************************************************/
    public function __construct($store)
    {
        $this->msg_tag = [
                           'e_subnet'      => null,
                           'e_interface'   => null,
                           'e_interfaceid' => null,
                           'e_relayagent'  => null,
                           'success'       => null,
                         ];

        $this->pre = ['subnet'        => "",
                      'interface'     => "",
                      'interfaceid'   => "",
                      'relayagent'    => "",
                      'interfacelist' => [],
                     ];
        
        $this->err_tag = ['e_msg'     => null];
        $this->store = $store;

        /* get running Configuration*/
        $this->conf = new KeaConf(DHCPV6);
        if ($this->conf->result === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            $this->check_subnet = false;
            return;
        }
    }

    /*************************************************************************
     * Method        : validate_param
     * args          : $param
     * return        : true or false
     *************************************************************************/
    public function validate_param($params)
    {
        $rules["subnet"] =
           [
            "method"=>"exist|subnet6exist:exist_true|belongshared6",
            "msg"=>[
                     _('Can not find a subnet'),
                     sprintf(_(EditSubnetConfig6::MSG_SUBNET_MISSING), $params['subnet']),
                   ],
            "log"=>[
                     'Can not find a subnet in GET parameters.',
                     sprintf(_(EditSubnetConfig6::LOG_SUBNET_MISSING), $params['subnet']),
                   ],
           ];

        /* input store into values */
        $values['store'] = $this->store;

        /* validate */
        $validater = new validater($rules, $params, true);

        /* keep validated value into property */
        $this->pre = $validater->err["keys"];

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs, null);
            return false;
        }

        /* completion of input fields */
        [$ret, $subnetdata] = $this->conf->get_one_subnet($params['subnet']);
        $this->pre['subnet'] = $subnetdata['subnet'];
        $this->pre['interface'] = isset($subnetdata['interface']) ? $subnetdata['interface'] : "";
        $this->pre['interfaceid'] = isset($subnetdata['interface-id']) ? $subnetdata['interface-id'] : "";
        $this->pre['relayagent'] = isset($subnetdata['relay']['ip-addresses'][0]) ? $subnetdata['relay']['ip-addresses'][0] : "";

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
        $interface = $values['interface'];
        $interfaceid = $values['interfaceid'];

        $rules["subnet"] =
           [
            "method"=>"exist|subnet6exist:exist_true|belongshared6",
            "msg"=>[
                     _('Can not find a subnet'),
                     sprintf(_(EditSubnetConfig6::MSG_SUBNET_MISSING), $values['subnet']),
                   ],
            "log"=>[
                     'Can not find a subnet in GET parameters.',
                     sprintf(_(EditSubnetConfig6::LOG_SUBNET_MISSING), $values['subnet']),
                   ],
           ];

        $rules['interface'] =
          [
           'method' => "interface|duplicateifid:$interfaceid",
           'msg'    => [
                          _('No such Interface.'),
                          _('Interface and InterfaceID cannot be used together.'),
                       ],
           'log'    => [
                         sprintf('No such Interface.(%s)'
                                               ,$values['interface']),
                         'Interface and InterfaceID cannot be used together.',
                       ],
          ];

        $rules['interfaceid'] =
          [
           'method' => "interfaceid",
           'msg'    => [
                          _('Invalid InerfaceID format.'),
                       ],
           'log'    => [
                         sprintf('Invalid InerfaceID format.(%s)'
                                               ,$values['interfaceid']),
                       ],
          ];

        $rules['relayagent'] =
          [
           'method' => 'exist|ipv6',
           'msg'    => [
                          '',
                          _('Invalid  RelayAgent format.'),
                       ],
           'log'    => [
                         '',
                         sprintf('Invalid RelayAgent format.(%s)'
                                               ,$values['relayagent']),
                       ],
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
     * Method        : edit_subnet_config
     * args          : none
     * return        : void
     *************************************************************************/
    public function edit_subnet_config() 
    {
        /* replace variable */
        $params = $this->pre;

        /* get subnet */
        $subnet = $params["subnet"];

        /* get subnet id */
        $subnet_id = $this->conf->get_subnet_idv6($subnet);

        /* get subnet position */
        $this->conf->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* get relay */
        $relayagent['ip-addresses'] = [];
        if (!empty($params['relayagent'])) {
            $relayagent['ip-addresses'] = array($params['relayagent']);
        }

        $subnet_data = [
            STR_INTERFACE => $params["interface"],
            STR_INTERFACEID => $params["interfaceid"],
            STR_RELAY => $relayagent,
        ];

        /* edit subnet */
        $new_config = $this->conf->edit_subnet_config($subnet, $pos_subnet, $subnet_data);

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Subnet config edited.(subnet id: %s subnet: %s)";
        $success_log = sprintf($log_format, $subnet_id, $subnet);

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log);
        $this->msg_tag['success'] = _('Subnet edited.');

        return true;
    }

    /*************************************************************************
    * Method        : init_disp
    * Description   : Method for display all subnet data
    * args          : None
    * return        : true or false
    *************************************************************************/
    public function init_disp($subnet)
    {
        /* get network-interfaces */
        [$ret, $interfaces] = $this->conf->get_interfaces();
        if ($ret === false)
        {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->log = $this->conf->err['e_log'];
            return false;
        }
        $this->pre['interfacelist'] = $interfaces;

        /* validate subnet GET param */
        /* get subnet data */
        $subnet_data = $this->conf->check_subnet6($subnet);
        if ($subnet_data === false) {
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : display
     * args          : none
     * return        : void
     *************************************************************************/
    public function display()
    {
        /* get network-interfaces */
        [$ret, $interfaces] = $this->conf->get_interfaces();
        if ($ret === false)
        {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
        }
        $this->pre['interfacelist'] = $interfaces;

        $errors = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->render("editsubnetconfig6.tmpl", $errors);
    }

}

/******************************************************************************
 *  main
 ******************************************************************************/
$objESubnetConfig = new EditSubnetConfig6($store);
if ($objESubnetConfig->check_subnet === false) {
    $objESubnetConfig->display();
    exit(1);
}

/************************************
 * Insert section
 ************************************/
$editbtn = post('edit');
/* if edit button pressed */
if (isset($editbtn)) {

    $post = [
        'subnet' => post('subnet'),
        'interface' => post('interface'),
        'interfaceid' => post('interfaceid'),
        'relayagent' => post('relayagent'),
    ];

    /* validate post */
    $ret = $objESubnetConfig->validate_post($post);
    
    if ($ret === false) {
        $objESubnetConfig->init_disp($post['subnet']);
        $objESubnetConfig->display();
        exit(1);
    }
    
    /* edit subnet */
    $ret = $objESubnetConfig->edit_subnet_config();
    if( $ret === false) {
        $objESubnetConfig->init_disp($post['subnet']);
        $objESubnetConfig->display();
        exit(1);
    }

    header('Location: searchsubnet6.php?msg=edit_ok');
    exit(0);
}

/************************************
 * Default section
 ************************************/
$subnet = get('subnet');
$params = [
    'subnet' => $subnet
];

if ($objESubnetConfig->validate_param($params) === false) {
    $objESubnetConfig->display();
    exit(1);
}
$objESubnetConfig->init_disp($subnet);
$objESubnetConfig->display();
exit(0);
