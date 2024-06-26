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
* Class:  EditShared6
* Description    : Class for edit shared-network6 page
* args           : $store
*****************************************************************************/
class EditShared6 {

    public  $msg_tag;
    public  $conf;
    private $store;
    private $pre;
    private $validater;
    private $log;
    private $othersubnet;
    private $sharedsubnet;

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
                           "e_msg"             => null,
                           "e_shared_subnet"   => null,
                           "e_shared_name"     => null,
                           "e_old_shared_name" => null,
                           "e_interface"       => null,
                           "e_interfaceid"     => null,
                           "e_relayagent"      => null,
                           "success"           => null
                          ];

        $this->pre = ['sharednetwork' => "",
                      'interface'     => "",
                      'interfaceid'   => "",
                      'relayagent'    => "",
                      'interfacelist' => [],];

        $this->store  = $store;

        /* call kea-dhcp6.conf class */
        $this->conf = new KeaConf(DHCPV6);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
        }
    }

    /*************************************************************************
    * Method        : validate_params
    * Description   : Method for Checking shared_name in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    public function validate_params($params)
    {
        $rules["shared_name"] = 
           [
            "method"=>"exist|shared6exist:exist_true",
            "msg"=>[
                     _('Please enter shared-network name.'),
                     _('Shared-network does not exists.'),
                   ],
            "log"=>[
                     'Can not find a shared-network name in GET parameters.',
                     sprintf('Shared-network does not exists.(%s)', 
                                                     $params["shared_name"]),
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
        $sharedconfig = $this->conf->get_shared_params($params['shared_name']);
        $this->pre['interface'] = isset($sharedconfig['interface']) ? $sharedconfig['interface'] : "";
        $this->pre['interfaceid'] = isset($sharedconfig['interface-id']) ? $sharedconfig['interface-id'] : "";
        $this->pre['relayagent'] = isset($sharedconfig['relay']['ip-addresses'][0]) ? $sharedconfig['relay']['ip-addresses'][0] : "";

        return true;
    }
    /*************************************************************************
    * Method        : validate_post
    * args          : $values - POST values
    * return        : true or false
    *************************************************************************/
    public function validate_post($values)
    {

        $new_name = $values['shared_name'];
        $old_name = $values['old_shared_name'];
        $interface = $values['interface'];
        $interfaceid = $values['interfaceid'];

        /*  define rules */
        $rules['shared_name'] =
           [
            'method' => "exist|sharedname|shared6exist:$new_name:$old_name",
            'msg'    => [
                           _('Please enter shared-network name.'),
                           _('Invalid shared-network validate.'),
                           _('Shared-network already exists.')
                        ],
            'log'    => [
                          'Please enter shared-network name.',
                          sprintf('Invalid shared-network name format.(%s)'
                                            ,$values['shared_name']),
                          sprintf('Shared-network name already exists.(%s)'
                                            ,$values['shared_name']),
                        ],
          ];

        $rules['old_shared_name'] =
          [
           'method' => 'shared6exist:exist_true',
            "msg"=>[
                     _('Shared-network does not exists.'),
                   ],
            "log"=>[
                     sprintf('Shared-network does not exists.(%s)',
                                                $values["old_shared_name"]),
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

        $rules['shared_subnet'] =
          [
           'method' => "existinterface|existinterfaceid|existrelay",
           'msg'    => [],
           'log'    => [],
           'option' => ['continueonfail',
                        'continueonfail',],
          ];

        /* input store into values */
        $values['store'] = $this->store;

        /* validate */
        $validater = new validater($rules, $values, true);

        /* keep validated value into property */
        $this->pre = $validater->err["keys"];
        $this->sharedsubnet = $values["shared_subnet"];
        $this->othersubnet = $values["other_subnet"];

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);
        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs, null);
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : delete_shared
    * Description   : Delete shared-network
    * args          : $delete_shared
    * return        : true or false
    *************************************************************************/
    public function delete_shared($delete_shared)
    {
        /* delete shared-network */
        $new_config = $this->conf->delete_shared_network($delete_shared);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Shared-network deleted successfully.(%s)";
        $log_msg = sprintf($log_msg, $delete_shared);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->log($log_msg);

        return true;
    }

    /*************************************************************************
    * Method        : check_del_shared
    * Description   : check whether shared-network can delete
    * args          : $values - POST values
    * return        : true or false
    *************************************************************************/
    public function check_del_shared($values)
    {
        /* check shared_subnet */
        /* get all shared-network subnet */
        $shared_subnet = $this->conf->get_shared_subnet
                                            ($values["old_shared_name"]);
        if (!empty($shared_subnet)) {
            $this->pre['sharednetwork'] = $values["old_shared_name"];
            $this->sharedsubnet = $values["shared_subnet"];
            $this->othersubnet = $values["other_subnet"];
            $this->msg_tag['e_msg'] = _("Subnet exists in shared-network.");

            $log_msg = "Subnet exists in shared-network.";
            $this->store->log->log(sprintf($log_msg));
            return false;
        }
        return true;

    }

    /*************************************************************************
    * Method        : check_exist_shared
    * Description   : check whether shared-network can delete
    * args          : $values - POST values
    * return        : true or false
    *************************************************************************/
    public function check_exist_shared($values)
    {

        /* check shared_network */
        $rules['old_shared_name'] =
          [
           'method' => 'shared6exist:exist_true',
            "msg"=>[
                     _('Shared-network does not exists.'),
                   ],
            "log"=>[
                     sprintf('Shared-network does not exists.(%s)',
                                                $values["old_shared_name"]),
                   ],
          ];

        /* input store into values */
        $values['store'] = $this->store;

        /* validate */
        $validater = new validater($rules, $values, true);

        /* keep validated value into property */
        $this->pre = $validater->err["keys"];
        $this->pre["shared_name"] = $values["old_shared_name"];
        $this->pre["interface"] = $values["interface"];
        $this->pre["interfaceid"] = $values["interfaceid"];
        $this->pre["relayagent"] = $values["relayagent"];
        $this->sharedsubnet = $values["shared_subnet"];
        $this->othersubnet = $values["other_subnet"];

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs, null);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : edit_shared
     * Description   : Method for editing the shared-network
     * args          : $postdata
     * return        : true or false
     *************************************************************************/
    public function edit_shared($postdata)
    {
        /* edit shared_network */
        $new_config = $this->conf->edit_shared_network($postdata);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Shared-network edited successfully.(%s)";
        $log_msg = sprintf($log_msg, $postdata["shared_name"]);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->log($log_msg);

        return true;
    }

    /*************************************************************************
    * Method        : init_disp
    * Description   : Method for display all shared-network data
    * args          : None
    * return        : true or false
    *************************************************************************/
    public function init_disp($sharedname)
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

        /* validate shared name GET param */
        /* fetch all get subnet data */
        $subnet_data = $this->_get_subnet_part($sharedname);
        /* failed to fetch other subnet*/
        if ($subnet_data === false) {
            if ($this->log !== "") {
                $this->store->log->log($this->log);
            }
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : _get_subnet_part
    * Description   : Method for get shared-network6 data
    * args          : $sharedname
    * return        : true or false
    *************************************************************************/
    private function _get_subnet_part($sharedname)
    {
        /* get all other subnet */
        $other_subnet = $this->conf->get_other_subnet();

        if ($other_subnet === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->log = $this->conf->err['e_log'];
            return false;
        }
        $this->othersubnet = $other_subnet;

        /* get all shared-network subnet */
        $shared_subnet = $this->conf->get_shared_subnet($sharedname);
        if ($shared_subnet === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->log = $this->conf->err['e_log'];
            return false;
        }

        $this->sharedsubnet = $shared_subnet;

        return true;
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen
    * args          : $shared-network name
    * return        : None
    **************************************************************************/
    public function display($shared_name = null)
    {
        if ($shared_name !== null) {
            $this->store->view->assign('shared_name', $shared_name);
        }

        $this->store->view->assign('shareditem', $this->sharedsubnet);
        $this->store->view->assign('otheritem', $this->othersubnet);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("editshared6.tmpl", $this->msg_tag);
    }
}

/*************************************************************************
*  main
*************************************************************************/
$objEditShared6 = new EditShared6($store);

/* check current config  */
if ($objEditShared6->conf->result === false) {
    $objEditShared6->display();
    exit(1);
}

/************************************
* Default section
************************************/
$editbtn = post('edit');
$delbtn = post('delete');



/**********************************
* Edit section
***********************************/
if (isset($editbtn)) {

    $postdata = [
        'old_shared_name'  => post('old_shared_name'),
        'shared_name'      => post('shared_name'),
        'shared_subnet'    => post('selectleft'),
        'other_subnet'     => post('selectright'),
        'interface'        => post('interface'),
        'interfaceid'      => post('interfaceid'),
        'relayagent'       => post('relayagent')
        ];

    $ret = $objEditShared6->validate_post($postdata);
    if (!$ret) {
        $objEditShared6->init_disp($postdata["old_shared_name"]);
        $objEditShared6->display($postdata["old_shared_name"]);
        exit(1);
    }

    /* make edit data */
    $relayagent['ip-addresses'] = [];
    if (!empty($postdata['relayagent'])) {
        $relayagent['ip-addresses'] = array($postdata['relayagent']);
    }
    $editdata = ['old_shared_name' => post('old_shared_name'),
                 'shared_name'     => post('shared_name'),
                 'shared_subnet'   => post('selectleft'),
                 'other_subnet'    => post('selectright'),
                 'interface'       => post('interface'),
                 'interface-id'    => post('interfaceid'),
                 'relay'           => $relayagent,
                ];

    $ret = $objEditShared6->edit_shared($editdata);
    if ($ret === false) {
        $objEditShared6->init_disp($postdata["old_shared_name"]);
        $objEditShared6->display($editdata["old_shared_name"]);
        exit(1);
    }

    header('Location: addshared6.php?msg=edit_ok');
    exit(0);

/**********************************
* Delete section
***********************************/
} else if (isset($delbtn)) {

    /* deleting option name target */
    $postdata = [
        'old_shared_name'  => post('old_shared_name'),
        'shared_name'      => post('shared_name'),
        'shared_subnet'    => post('selectleft'),
        'other_subnet'     => post('selectright'),
        'interface'        => post('interface'),
        'interfaceid'      => post('interfaceid'),
        'relayagent'       => post('relayagent'),
        ];

    /* check exist shared_network */
    $ret = $objEditShared6->check_exist_shared($postdata);
    /* validation error */
    if ($ret === false) {
        $objEditShared6->init_disp($postdata["old_shared_name"]);
        $objEditShared6->display($postdata["old_shared_name"]);
        exit(1);
    }

    /* check no exist shared_subnet */
    $ret = $objEditShared6->check_del_shared($postdata);
    if ($ret === false) {
        $objEditShared6->init_disp($postdata["old_shared_name"]);
        $objEditShared6->display($postdata["old_shared_name"]);
        exit(1);
    }

    /* delete shared-network */
    $ret = $objEditShared6->delete_shared($postdata["old_shared_name"]);
    if ($ret === false) {
        $objEditShared6->init_disp($postdata["old_shared_name"]);
        $objEditShared6->display($postdata["old_shared_name"]);
        exit(1);
    }

    header('Location: addshared6.php?msg=delete_ok');
    exit(0);

/**********************************
* First section
***********************************/
} else {

    $sharedname = get('shared_name');
    $shared_params = [
        'shared_name'    => $sharedname,
    ];

    if ($objEditShared6->validate_params($shared_params) === false) {
        $objEditShared6->display();
        exit(1);
    }
}

/************************************
* Initial display
************************************/
$objEditShared6->init_disp($sharedname);
$objEditShared6->display($sharedname);
exit(0);
