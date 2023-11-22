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
 * Class          : AddSubnet6
 * Description    : Class for add subnet
 * args           : $store
 *****************************************************************************/
class AddSubnet6
{
    /*
     * constant message 
     */
     const MSG_SUBNET_EMPTY    = 'Please enter subnet.';
     const MSG_SUBNET_INVALID  = 'Invalid subnet validate.';
     const MSG_SUBNET_OVERLDAP = 'Subnet overlapping.';

    /*
     * constant log
     */
     const LOG_SUBNET_EMPTY    = 'Empty subnet.';
     const LOG_SUBNET_INVALID  = 'Invalid subnet.(%s)';
     const LOG_SUBNET_OVERLDAP = 'Subnet overlapping.(%s)';
 
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
                           'e_subnet' => null,
                           'success'  => null,
                         ];
        $this->err_tag = ['e_msg'     => null];
        $this->store = $store;

        /* get running Configuration*/
        $this->conf = new KeaConf(DHCPV6);
        if ($this->conf->result === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            $this->check_subnet = false;
            return;
        }
    }

    /*************************************************************************
     * Method        : validate_post
     * args          : $values - POST values
     * return        : true or false
     *************************************************************************/
    public function validate_post($values)
    {
        /*  define rules */
        $rules['subnet'] = [
           'method' => 'exist|subnet6format|subnetoverldap6:exist_false',
           'msg'    => [
                         _(AddSubnet6::MSG_SUBNET_EMPTY),
                         _(AddSubnet6::MSG_SUBNET_INVALID),
                         _(AddSubnet6::MSG_SUBNET_OVERLDAP)
                       ],
           'log'    => [
                         AddSubnet6::LOG_SUBNET_EMPTY,
                         sprintf(AddSubnet6::LOG_SUBNET_INVALID, $values['subnet']),
                         sprintf(AddSubnet6::LOG_SUBNET_OVERLDAP, $values['subnet']),
                       ],
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
     * Method        : add_subnet
     * args          : none
     * return        : void
     *************************************************************************/
    public function add_subnet() 
    {
        /* replace variable */
        $params = $this->pre;

        /* create subnetid */
        $subnet_id = $this->conf->get_max_subnetid();

        /* get subnet */
        $subnet = $params["subnet"];

        $subnet_data = [
            STR_ID     => $subnet_id,
            STR_SUBNET => $subnet,
        ];

        /* add subnet */
        $new_config = $this->conf->add_subnet($subnet_data);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            $this->check_subnet = false;
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Subnet added.(subnet id: %s subnet: %s)";
        $success_log = sprintf($log_format, $subnet_id, $subnet);

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log, null);
        $this->msg_tag['success'] = _('Subnet added.');

        return true;
    }

    /*************************************************************************
     * Method        : display
     * args          : none
     * return        : void
     *************************************************************************/
    public function display()
    {
        $errors = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->render("addsubnet6.tmpl", $errors);
    }

}

/******************************************************************************
 *  main
 ******************************************************************************/
$objASubnet = new AddSubnet6($store);
if ($objASubnet->check_subnet === false) {
    $objASubnet->display();
    exit(1);
}

/************************************
 * Insert section
 ************************************/
$addbtn = post('add');
/* if add button pressed */
if (isset($addbtn)) {

    $post = [
        'subnet' => post('subnet'),
    ];

    /* validate post */
    $ret = $objASubnet->validate_post($post);
    
    if ($ret === true) {
        /* add subnet */
        $objASubnet->add_subnet();
    }
}

/************************************
 * Default section
 ************************************/
$objASubnet->display();
exit;
