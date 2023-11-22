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
 * Class:  AddRange6
 *
 * [Description]
 *   Class for adding range to subnet
*****************************************************************************/
class AddRange6 {

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
    private $subnet_val;

    /*************************************************************************
     * Method        : __construct
     * Description   : Method for setting tags automatically
     * args          : $store
     * return        : None
     **************************************************************************/
    public function __construct($store)
    {
        /* tag */
        $this->subnet_val = ['subnet_id' => null,
                             'subnet'    => null];
        $this->msg_tag =  [
                           "e_poolstart" => null,
                           "e_poolend"   => null,
                           "subnet"      => null,
                           "e_pool"      => null,
                           "disp_msg"    => null,
                          ];
        $this->err_tag =  ["e_msg"       => null,
                          ];
        $this->err_tag2 = [];
        $this->pools = null;
        $this->result = null;
        $this->store  = $store;

        /* read running configuration */
        $this->read_keaconf();
    }

    /*************************************************************************
     * Method        : read_keaconf
     * Description   : Method for reading running configuration
     * args          : None
     * return        : true/false
     **************************************************************************/
    public function read_keaconf()
    {
        $this->conf = new KeaConf(DHCPV6); 
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
     * Description   : validate GET params
     * args          : $params
     * return        : true/false
     **************************************************************************/
    public function validate_params($params)
    {
        $rules["subnet"] = [
            "method"=>"exist|subnet6exist:exist_true",
            "msg"=>[
                _('Can not find a subnet.'),
                _('Subnet do not exit in config.'),
            ],
            "log"=>[
                'Can not find a subnet in GET parameters.',
                sprintf('Subnet do not exist in config.(%s)', $params["subnet"]),
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
     * args          : $values    - POST data
     * return        : true/false
     *************************************************************************/
    public function validate_post($values)
    {
        $subnet = $values["subnet"];
        $start  = $values["poolstart"];

        $rules["poolstart"] = [
            "method"=>"exist|ipv6|insubnet6:$subnet|ipv6overlap",
            "msg"=>[
                _(AddRange6::MSG_IPSTART_EMPTY),
                _(AddRange6::MSG_IPSTART_INVALID),
                _(AddRange6::MSG_IPSTART_OUT_RANGE),
                _(AddRange6::MSG_IPSTART_OVERLAP),
            ],
            "log"=>[
                AddRange6::LOG_IPSTART_EMPTY,
                sprintf(AddRange6::LOG_IPSTART_INVALID, $values['poolstart']),
                sprintf(AddRange6::LOG_IPSTART_OUT_RANGE, $values['poolstart']),
                sprintf(AddRange6::LOG_IPSTART_OVERLDAP, $values['poolstart']),
            ],
        ];
        $rules["poolend"] = [
            "method"=>"exist|ipv6|insubnet6:$subnet|ipv6overlap|greateripv6:$start",
            "msg"=>[
                _(AddRange6::MSG_IPEND_EMPTY),
                _(AddRange6::MSG_IPEND_INVALID),
                _(AddRange6::MSG_IPEND_OUT_RANGE),
                _(AddRange6::MSG_IPEND_OVERLDAP),
                _(AddRange6::MSG_IPEND_SMALLER),
            ],
            "log"=>[
                AddRange6::LOG_IPEND_EMPTY,
                sprintf(AddRange6::LOG_IPEND_INVALID, $values['poolend']),
                sprintf(AddRange6::LOG_IPEND_OUT_RANGE, $values['poolend']),
                sprintf(AddRange6::LOG_IPEND_OVERLDAP, $values['poolend']),
               sprintf(AddRange6::LOG_IPEND_SMALLER, $values['poolend'], $values['poolend']),
            ],
        ];

        /* crearte object validater */
        $validater = new validater($rules, $values, true);

        /* keep validated value into property */
        $this->pre = $validater->err["keys"];

        /* keep subnet */
        $this->msg_tag['subnet'] = $subnet;
        $this->err_tag2 = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : add_range
     * Description   : Add new range to subnet
     * args          : $subnet     subnet will add pool
     *                 $pooldata   data of pool
     * return        : None
    **************************************************************************/
    public function add_range($subnet, $pooldata)
    {
        /* create new pool data */
        $new_pool[STR_POOL] = $pooldata["poolstart"]. "-". $pooldata["poolend"];

        /* delete pool in this subnet */
        $new_config = $this->conf->add_range($subnet, $new_pool);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Range added.(%s)(%s)";
        $log_msg = sprintf($log_msg, $subnet,
                           $pooldata["poolstart"]. "-". $pooldata["poolend"]);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);
  
        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Range added.");

        return true;
    }

    /*************************************************************************
     * Method        : display
     * Description   : Method for displaying the template on the screen
     * args          : None
     * return        : None
     **************************************************************************/
    public function display()
    {
        $array = array_merge($this->msg_tag, $this->err_tag, $this->err_tag2);
        $this->store->view->assign("pools", $this->pools);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("addrange6.tmpl", $array);
    }
}

/*************************************************************************
 *  main
 *************************************************************************/
$objAddRange = new AddRange6($store);
/* check current config  */
if ($objAddRange->conf->result === false) {
    $objAddRange->display();
    exit(1);
}

/************************************
 * Default section
 ************************************/
$subnet = get('subnet');
$subnet_params = [
    'subnet'    => $subnet,
];

/* validate GET param */
if ($objAddRange->validate_params($subnet_params) === false) {
    exit(1);
}

/**********************************
 * Add section
 **********************************/
$addbtn = post('add');
/* if add button was pressed */
if (isset($addbtn)) {

    $postdata = [
        'subnet'    => post('subnet'),
        'poolstart' => post('poolstart'),
        'poolend'   => post('poolend'),
    ];

    /* validate post data */
    $ret = $objAddRange->validate_post($postdata);
 
    if ($ret === true) {
        /* add range to subnet */
        $objAddRange->add_range($subnet, $postdata);
    }
}

/************************************
 * Initial display
 ************************************/
$objAddRange->display();
