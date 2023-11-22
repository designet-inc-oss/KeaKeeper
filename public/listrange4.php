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
 * Class:  ListRange4
 *
 * [Description]
 *   Class for listting all range in subnet
 *****************************************************************************/
class ListRange4 {

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
        $this->msg_tag =  [
                           "subnet"     => null,
                           "e_pool"     => null,
                           "disp_msg"   => null,
                          ];
        $this->err_tag =  ["e_msg"     => null, 
                           "e_subnet"  => null,
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
     **************************************************************************/
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
     * Description   : Method for Checking subet and subnet_id in get value
     * args          : $params
     * return        : true/false
     **************************************************************************/
    public function validate_params($params)
    {
        $rules["subnet"] = ["method"=>"exist|subnet4exist:exist_true",
                            "msg"=>[
                                     _('Can not find a subnet.'),
                                     _('Subnet do not exit in config.'),
                                   ],
                            "log"=>[
                                     'Can not find a subnet in GET parameters.',
                                     sprintf('Subnet do not exist in config.(%s)', $params["subnet"]),
                                   ],
                           ];

        /* create object validater */
        $validater = new validater($rules, $params, true);

        /* set error tags */
        $this->err_tag2 = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();

            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : get_all_pools
     * Description   : get all pools in current subnet
     * args          : $params
     * return        : true/false
     **************************************************************************/
    public function get_all_pools($params)
    {
        /* get all pools of this subnet */
        $pools_arr = $this->conf->get_pools($params['subnet']);
        if ($pools_arr === false) {
            $this->msg_tag['e_pool'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        $pools = [];
        if (is_array($pools_arr)) {
            foreach ($pools_arr as $key => $value) {
                $pools[] = $value[STR_POOL];
            }
            $this->pools = $pools;
        }

        return true;
    }

    /*************************************************************************
     * Method        : validate_post
     * Description   : validation post data
     * args          : $postdata    postdata will check
     * return        : true/false
     **************************************************************************/
    public function validate_post($postdata)
    {
        $rules["pool"] = ["method"=>"exist",
                            "msg"=>[
                                     _('Delete target of range does not exist.'),
                                   ],
                            "log"=>[
                                     'Can not find a pool in GET parameters.',
                                   ],
                           ];

        /* create object validater */
        $validater = new validater($rules, $postdata, true);

        /* set error tag */
        $this->err_tag2 = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();
            return false;
        }

        /* get all pools of this subnet */
        $pools_arr = $this->get_all_pools($postdata);
        if ($pools_arr === false) {
            $this->msg_tag['e_pool'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : delete_range
     * Description   : delete range in subnet
     * args          : $subnet
     *               : $pool
     * return        : None
     **************************************************************************/
    public function delete_range($subnet, $pool)
    {
        /* delete pool in this subnet */
        $new_config = $this->conf->del_range($subnet, $pool);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Range deleted(%s)(%s).";
        $log_msg = sprintf($log_msg, $subnet, $pool);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = sprintf(_("Range deleted(%s)."), $pool);

        return;
    }

    /*************************************************************************
     * Method        : display
     * Description   : Method for displaying the template on the screen
     * args          : $host4data Search result on host
     * return        : None
     **************************************************************************/
    public function display($host4data = null)
    {
        /* If host4data exists, display the table */
        if ($host4data != null) {
            $this->store->view->assign('item', $host4data);
        }

        $array = array_merge($this->msg_tag, $this->err_tag, $this->err_tag2);
        $this->store->view->assign("pools", $this->pools);
        $this->store->view->render("listrange4.tmpl", $array);
    }
}

/*************************************************************************
 *  main
 *************************************************************************/
$objListRange = new ListRange4($store);

/* check read kea.conf result */
if ($objListRange->conf->result === false) {
    $objListRange->display();
    exit(1);
}

/************************************
 * Default section
 ************************************/
$subnet = get('subnet');
$subnet_params = [
    'subnet'    => $subnet,
];

/* validate subnet GET param */
if ($objListRange->validate_params($subnet_params) === false) {
    exit(1);
}

/**********************************
* Delete section
***********************************/
$del = get('del');
if (isset($del)) {

    $pooldata = [
                  'subnet' => $subnet,
                  'pool'   => get('pool')
                ];

    /* validate post data  */
    $ret = $objListRange->validate_post($pooldata);

    if ($ret === true) {
        /* delete range of subnet */
        $objListRange->delete_range($subnet, $pooldata['pool']);

        /* after delete, refresh config */
        $objListRange->conf->get_config(DHCPV4);
    }
}

/**********************************
* List section
***********************************/
$objListRange->get_all_pools($subnet_params);

/* set hidden tag */
$objListRange->msg_tag['subnet'] = $subnet;

/************************************
* Initial display
************************************/
$objListRange->display();
exit(0);
