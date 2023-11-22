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
* Class          : Searchsubnet6
* Description    : Class for search subnet6 information page
* args           : $store
*****************************************************************************/
class SearchSubnet6 {
    public $subnets = null;
    public $conf;
    private $store;
    private $pre;
    private $validater;
    private $log;

    /*************************************************************************
    * Method        : __construct
    * Description   : Method for setting tags automatically
    * args          : $store
    *************************************************************************/
    public function __construct($store)
    {
        $this->msg_tag = [
            'subnet'       => null,
            'e_msg'        => null,
            'no_result'    => null,
            'e_subnet'     => null,
            'e_subnet_del' => null,
            'success'      => null,
       ];

        $this->store = $store;

        /* call kea.conf class */
        $this->conf = new KeaConf(DHCPV6);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
        }
    }

    /*************************************************************************
    * Method        : _validate
    * Description   : Method for validate GET parameter
    * args          : $conditions
    * return        : true or false
    *************************************************************************/
    private function _validate($condition)
    {
        /* define rules */
        $rules['subnet'] = [
                            'method' => 'exist',
                            'msg' => [_('Please enter subnet.')],
                            'log' => ['No search subnet condition.'],
                           ];

        /* validate passed value */
        $this->validater = new validater($rules, $condition, true);

        /* keep validated value and messages */
        $this->pre = $this->validater->err["keys"];
        $this->msg_tag = array_merge($this->msg_tag, $this->validater->tags);

        /* when validation error */
        if ($this->validater->err['result'] === false) {
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : _validate_params
     * Description   : Method for validate GET parameter
     * args          : $params
     * return        : true or false
     *************************************************************************/
    public function _validate_params($params)
    {
        /* define rules */
        $rules['subnet'] = [
            'method' => 'exist',
            'msg' => [''],
            'log' => [''],
            'option' => ['allowempty']
        ];
        $rules['subnet_del'] = [
            'method' => 'exist|subnet6format',
             'msg' => [
                 _('Subnet delete do not exist.'),
                 _('Invalid subnet validate.'),
             ],
             'log' => [
                 'Deleting subnet do not exist.',
                 'Invalid subnet(' . $params['subnet_del'] . ').',
             ]
        ];

        /* input store into values */
        $params['store'] = $this->store;

        /* validate passed value */
        $this->validater = new validater($rules, $params, true);

        /* keep validated value and messages */
        $this->pre = $this->validater->err["keys"];
        $this->msg_tag = array_merge($this->msg_tag, $this->validater->tags);

        /* when validation error */
        if ($this->validater->err['result'] === false) {
            $this->store->log->output_log_arr($this->validater->logs);
            $this->display();
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : check_subnet_del
     * Description   : check whether subnet can delete
     * args          : $subnet
     * return        : true or false
     *************************************************************************/
     public function check_subnet_del($params)
     {
        $flg_found = false;
        $subnet = $params["subnet_del"];

        /* get subnet part only */
        $conf_all_subnet = $this->conf->mk_arr_all_subnet($this->conf->dhcp6);

        /* loop all subnet in config */
        foreach ($conf_all_subnet as $shnet => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {
                if(isset($one_subnet[STR_SUBNET])) {
                    if ($one_subnet[STR_SUBNET] === $subnet) {
                        $subnet_id = $one_subnet[STR_ID];
                        $flg_found = true;
                        break;
                    }
                }
            }
        }

        /* save input data */
        $this->pre = $params;

        /* deletion target do not exist in config */
        if (!$flg_found) {
            $log_format = "Subnet delete target do not exist in config(%s).";
            $log_msg = sprintf($log_format, $subnet);
            $this->store->log->log($log_msg, null);
            $msg = _('Subnet delete target do not exist in config(%s).');
            $this->msg_tag['e_subnet_del'] = sprintf($msg, $subnet);
            return false;
        }

        /* create dbutil */
        $dbutil = new dbutils($this->store->db);

        /* make query for check exist */
        $cond = ['dhcp6_subnet_id' => $subnet_id];

        $dbutil->select('COUNT(host_id)');
        $dbutil->from('hosts');
        $dbutil->where($cond);

        /* fetch COUNT query's result */
        $ret = $dbutil->get();


        /* greater than 0, already exists */
        if (max($ret[0]) > 0) {
            $log_format = "Since the host remains in the subnet, can not delete(%s).";
            $log_msg = sprintf($log_format, $subnet);
            $this->store->log->log($log_msg, null);
            $msg = _('Since the host remains in the subnet, can not delete(%s).');
            $this->msg_tag['e_subnet_del'] = sprintf($msg, $subnet);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : delete_subnet
     * Description   : Method for delete subnet4 data
     * args          : $subnet
     * return        : true or false
     *************************************************************************/
    public function delete_subnet($subnet)
    {
        /* delete subnet in config */
        $new_config = $this->conf->del_subnet($subnet);
        if ($new_config === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Subnet deleted successfully.(subnet: %s)";
        $success_log = sprintf($log_format, $subnet);

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log, null);
        $msg = _('Subnet deleted successfullly.(%s)');
        $this->msg_tag['success'] = sprintf($msg, $subnet);

        return true;
    }
    /*************************************************************************
    * Method        : init_disp
    * Description   : Method for display all subnet6 data
    * args          : None
    * return        : true or false
    *************************************************************************/
    public function init_disp()
    {
        /* fetch all subnet6 */
        $subnets = $this->_get_subnet6();

        /* failed to fetch subnet6 */
        if ($subnets === false) {
            $this->store->log->log($this->log, null);
            return false;
        }

        foreach ($subnets as $key => $value) {
            list($addr, $mask) = explode("/", $subnets[$key]['subnet']);
            $addr = inet_ntop(inet_pton($addr));
            $subnets[$key]['subnet'] = $addr . "/" . $mask;
        }

        $this->subnets = $subnets;
        return true;
    }

    /*************************************************************************
    * Method        : search_disp
    * Description   : Method for search subnet6 data
    * args          : $conditions
    * return        : true or false
    *************************************************************************/
    public function search_disp($condition, $del_action)
    {
        if ($del_action === false) {
            /* validate search condition */
            $ret = $this->_validate($condition);

            /* validation error */
            if ($ret === false) {
                $this->store->log->output_log_arr($this->validater->logs);
                return false;
            }
        }

        /* search subnet6 by passed condition */
        $subnets = $this->_get_subnet6($condition, $del_action);

        /* failed to fetch subnet6 */
        if ($subnets === false) {
            $this->store->log->log($this->log, null);
            return true;
        }

        foreach ($subnets as $key => $value) {                                              
            list($addr, $mask) = explode("/", $subnets[$key]['subnet']);
            $addr = inet_ntop(inet_pton($addr));
            $subnets[$key]['subnet'] = $addr . "/" . $mask;
        }

        /* keep searched data */
        $this->subnets = $subnets;
        return true;
    }

    /*************************************************************************
    * Method        : _get_subnet6
    * Description   : Method for get subnet6 data
    * args          : $cond - search condition
    *                 $del_action - deleting subnet mode
    * return        : fetched $subnets or false
    *************************************************************************/
    private function _get_subnet6($cond = null, $del_action = false)
    {
        /* decide whether to all or search subnet */
        if ($cond === null) {
            $subnets = $this->conf->search_subnet6();
        } else {
            $subnets = $this->conf->search_subnet6($cond['subnet'], 'foward');
        }

        /* failed to search subnet */
        if ($subnets === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->log = $this->conf->err['e_log'];
            return false;
        }

        /* sort subnets by id */
        foreach ($subnets as $i => $val) {
            /* adjus format of pool */
            if (isset($subnets[$i][STR_POOLS]) && is_array($subnets[$i][STR_POOLS])) {
                $pool_data = [];
                foreach ($subnets[$i][STR_POOLS] as $pool) {
                    if (isset($pool[STR_POOL])) {
                        list($pool_min, $pool_max) = get_kea_pool_v6($pool[STR_POOL]);
                        $pool_data[] =  inet_ntop(inet_pton($pool_min)). '-'.
                                        inet_ntop(inet_pton($pool_max));
                    }
                }

                $subnets[$i][STR_POOLS] = $pool_data;
            }

            $sort[$i] = $val[STR_SUBNET];
        }
        array_multisort($sort, SORT_ASC, SORT_NATURAL, $subnets);

        return $subnets;
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : $subnet
    * return        : None
    *************************************************************************/
    public function display($subnets = null)
    {
        if ($subnets !== null) {
            $this->store->view->assign('item', $subnets);
        }
        $this->store->view->assign('result', count($subnets));
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("searchsubnet6.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
 *  main
 ******************************************************************************/
$del_action = false;

$sub6 = new SearchSubnet6($store);

/* check read kea.conf result */
if ($sub6->conf->result === false) {
    $sub6->display();
    exit;
}

/**********************************
 * Delete
 ***********************************/
$delete = get('delete');

if (isset($delete)) {

    $del_action = true;

    $params = [
                'subnet'     => get('subnet'),
                'subnet_del' => get('delete'),
              ];

    /* check params of GET */
    $ret = $sub6->_validate_params($params);

    /* validation error */
    if ($ret === false) {
        return false;
    }

    /* check exist subnet */
    $ret = $sub6->check_subnet_del($params);
    /* validation error */
    if ($ret === true) {
        /* delete subnet */
        $ret = $sub6->delete_subnet($delete);
        if ($ret === true) {
            /* refesh config */
            $sub6->conf->get_config(DHCPV6);
        }
    }
}

/*************************************
* Search and display section
*************************************/
$search = get('search');

if (isset($search)) {
    /************************************
    * Search subnet information
    ************************************/
    $condition = ['subnet' => get('subnet')];

    $ret = $sub6->search_disp($condition, $del_action);
    if ($ret === false) {
        $ret = $sub6->init_disp();
    }

    $sub6->display($sub6->subnets);
    exit;
}

/*************************************
* Initial screen, display all subnet6
*************************************/
$ret = $sub6->init_disp();

$sub6->display($sub6->subnets);
