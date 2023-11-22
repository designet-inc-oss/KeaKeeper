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
 * Class:  EditRange4
 *
 * [Description]
 *   Class for searching information about hosts
 ****************************************************************************/
class EditRange4 {

    /*
     * constant message
     */
    const MSG_IPSTART_EMPTY     = 'Please enter Start IP address.';
    const MSG_IPSTART_INVALID   = 'Invalid Start IP address.';
    const MSG_IPSTART_OUT_RANGE = 'Start IP address out of subnet range.';
    const MSG_IPSTART_OVERLAP   = 'Start IP address overlaps.';

    const MSG_IPEND_EMPTY       = 'Please enter End IP address.';
    const MSG_IPEND_INVALID     = 'Invalid End IP address.';
    const MSG_IPEND_OUT_RANGE   = 'End IP address out of subnet range.';
    const MSG_IPEND_OVERLDAP    = 'End IP address overlaps.';
    const MSG_IPEND_SMALLER     = 'Start IP address greater then End IP address.';

    /*
     * constant log
     */
    const LOG_IPSTART_EMPTY     = 'Empty Start IP address.';
    const LOG_IPSTART_INVALID   = 'Invalid Start IP address(%s)';
    const LOG_IPSTART_OUT_RANGE = 'Start IP address out of subnet range(%s)';
    const LOG_IPSTART_OVERLDAP  = 'Start IP address overlaps.(%s)';

    const LOG_IPEND_EMPTY       = 'Empty End IP address.';
    const LOG_IPEND_INVALID     = 'Invalid End IP address(%s)';
    const LOG_IPEND_OUT_RANGE   = 'End IP address out of subnet range(%s)';
    const LOG_IPEND_OVERLDAP    = 'End IP address overlaps.(%s)';
    const LOG_IPEND_SMALLER     = 'Start IP address greater then End IP address(%s)(%s).';

    /*
     * properties
     */
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
     *************************************************************************/
    public function __construct($store)
    {
        $this->msg_tag =  [
                            "subnet"     => "",
                            "e_pool"     => "",
                            "disp_msg"   => "",
                          ];
        $this->err_tag =  [
                            "e_msg"     => "",
                            "e_poolstart" => null,
                            "e_poolend"   => null,
                          ];
        $this->err_tag2 = []; 

        $this->pools  = null;
        $this->result = null;
        $this->store  = $store;

        /* read keaconf */
        $this->read_keaconf();
    }

    /*************************************************************************
     * Method        : read_keaconf
     * Description   : Method for reading keaconf
     * args          : None
     * return        : true/false
     *************************************************************************/
    public function read_keaconf()
    {
        $this->conf = new KeaConf(DHCPV4); 
        /* If an error is found by checking keaconf */
        if ($this->conf->result === false) {
            $this->msg_tag['disp_msg'] = $this->conf->err['e_msg'];
            $this->store->log->output_log($this->conf->err['e_log']);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : validate_params
     * Description   : validate post data
     * args          : $params - POST data
     * return        : true/false
     *************************************************************************/
    public function validate_params($params)
    {
        $rules["subnet"] = [
            "method"=>"exist|subnet4exist:exist_true",
            "msg"=>[
                _('Can not find a subnet.'),
                _('Subnet do not exit in config.'),
             ],
             "log"=>[
                'Can not find a subnet in GET parameters.',
                sprintf('Subnet do not exist in config.(%s)', $params["subnet"]),
            ],
        ];
        $rules["range"] = [
            "method"=>"exist",
            "msg"=>[
                 _('Editing range does not exist.'),
             ],
             "log"=>[
                 'Can not find a range in GET parameters.',
             ],
        ];

        $validater = new validater($rules, $params, true);
        /* keep validated value into property */
        $this->pre = $validater->err["keys"];
        $this->err_tag2 = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();

            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : validate_post
     * Description   : validate post data
     * args          : $values - POST data
     * return        : true/false
     *************************************************************************/
    public function validate_post($values)
    {
        $subnet = $values["subnet"];
        $start  = $values["poolstart"];

        $edit_pool_start = $this->pools["poolstart"];
        $edit_pool_end   = $this->pools["poolend"];

        $rules["poolstart"] = [
            "method"=>"exist|ipv4|insubnet4:$subnet|ipv4overlap:$edit_pool_start",
            "msg"=>[
                _(EditRange4::MSG_IPSTART_EMPTY),
                _(EditRange4::MSG_IPSTART_INVALID),
                _(EditRange4::MSG_IPSTART_OUT_RANGE),
                _(EditRange4::MSG_IPSTART_OVERLAP),
            ],
            "log"=>[
                EditRange4::LOG_IPSTART_EMPTY,
                sprintf(EditRange4::LOG_IPSTART_INVALID, $values['poolstart']), 
                sprintf(EditRange4::LOG_IPSTART_OUT_RANGE, $values['poolstart']),
                sprintf(EditRange4::LOG_IPSTART_OVERLDAP, $values['poolstart']),
            ],
       ];
        $rules["poolend"] = [
            "method"=>"exist|ipv4|insubnet4:$subnet|ipv4overlap:$edit_pool_end|greateripv4:$start",
            "msg"=>[
                 _(EditRange4::MSG_IPEND_EMPTY),
                 _(EditRange4::MSG_IPEND_INVALID),
                 _(EditRange4::MSG_IPEND_OUT_RANGE),
                 _(EditRange4::MSG_IPEND_OVERLDAP),
                 _(EditRange4::MSG_IPEND_SMALLER),
            ],
            "log"=>[
                 EditRange4::LOG_IPEND_EMPTY,
                 sprintf(EditRange4::LOG_IPEND_INVALID, $values['poolend']),
                 sprintf(EditRange4::LOG_IPEND_OUT_RANGE, $values['poolend']),
                 sprintf(EditRange4::LOG_IPEND_OVERLDAP, $values['poolend']),
                 sprintf(EditRange4::LOG_IPEND_SMALLER, $values['poolstart'], $values['poolend']),
            ],
        ];

        $validater = new validater($rules, $values, true);
        /* keep validated value into property */
        $this->pre = $validater->err["keys"];
        /* keep subnet */
        $this->msg_tag['subnet'] = $subnet;
        $this->err_tag2 = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();

            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : set_range
     * Description   : set range 
     * args          : $range_data - pool data will set
     * return        : None
     *************************************************************************/
    public function set_range($range_data) 
    {
        /* split range */
        $arr_range = explode("-", $range_data["range"]);

        if (count($arr_range) != 2) {
            return false;
        }

        $this->pools["poolstart"] = $arr_range[0];
        $this->pools["poolend"] = $arr_range[1];

        return true;
    }

    /*************************************************************************
     * Method        : delete_pool
     * Description   : update pool of current subnet
     * args          : $subnet - current subnet
     *               : $pooldata - pool data will update
     * return        : None
     *************************************************************************/
    public function edit_range($subnet, $pooldata)
    {
        /* range is editting */
        $editing_range = $this->pools["poolstart"]. "-".  $this->pools["poolend"];

        /* create new pool data */
        $new_pool[STR_POOL] = $pooldata["poolstart"]. "-". $pooldata["poolend"];

        /* delete pool in this subnet */
        $new_config = $this->conf->edit_range($subnet, $editing_range, $new_pool);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Range edited.(%s)(%s)";
        $log_msg = sprintf($log_msg, $subnet,
                           $pooldata["poolstart"]. "-". $pooldata["poolend"]);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Range edited.");

        return;
    }

    /*************************************************************************
     * Method        : display
     * Description   : Method for displaying the template on the screen
     * args          : $host4data Search result on host
     * return        : None
    **************************************************************************/
    public function display()
    {
        $array = array_merge($this->msg_tag, $this->err_tag, $this->err_tag2);
        $this->store->view->assign("pools", $this->pools);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("editrange4.tmpl", $array);
    }
}

/*************************************************************************
 *  main
 *************************************************************************/
$editrange_inst = new EditRange4($store);

/* check current config  */
if ($editrange_inst->conf->result === false) {
    $editrange_inst->display();
    exit(1);
}

/**********************************
 * Default section
 **********************************/
$subnet = get('subnet');
$range  = get('range');

$subnet_params = [
    'subnet'    => $subnet,
    'range'     => $range,
];

/* validate subnet GET param */
if ($editrange_inst->validate_params($subnet_params) === false) {
    exit(1);
}

/* set data to initial display */
$editrange_inst->set_range($subnet_params);

/**********************************
 * Edit section
 **********************************/
$editbtn = post('edit');

/* if press edit button */
if (isset($editbtn)) {

   /* get post data */
    $pooldata = [
        'subnet'    => post('subnet'),
        'poolstart' => post('poolstart'),
        'poolend'   => post('poolend'),
    ];

    /* validate post data */
    if ($editrange_inst->validate_post($pooldata) === false) {
        exit(1);
    }

    /* edit range of subnet */
    $editrange_inst->edit_range($subnet, $pooldata);
}

/************************************
 * Initial display
 ************************************/
$editrange_inst->display();
exit(0);
