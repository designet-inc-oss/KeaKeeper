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
* Class          : ListPD
* Description    : Class for list prefix delegation information page
* args           : $store
*****************************************************************************/
class ListPD {
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
            'e_prefix_del' => null,
       ];
        $this->pd_pools = [];
        $this->subnet = [];

        $this->store = $store;

        /* call kea.conf class */
        $this->conf = new KeaConf(DHCPV6);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
        }
    }

    /*************************************************************************
     * Method        : validate_subnet
     * Description   : Method for validate GET parameter
     * args          : $params
     * return        : true or false
     *************************************************************************/
    public function validate_subnet($params) {
        /* define rules  */
        $rules['subnet'] = [
                'method' => 'exist|subnet6exist:exist_true',
                'msg' => [
                            _('Subnet does not exist in config.'),
                            _('Subnet does not exist in config.')],
                'log' => [
                            'Subnet does not exist in config.',
                            'Subnet does not exist in config.']
        ];

        /* input store into values */
        $params['store'] = $this->store;

        /* validate passed value */
        $this->validater = new validater($rules, $params, true);

        /* keep validated value and messages */
        $this->msg_tag = array_merge($this->msg_tag, $this->validater->tags);

        /* when validation error */
        if ($this->validater->err['result'] === false) {
            $this->store->log->output_log_arr($this->validater->logs);
            return false;
        }
        
        $this->subnet['subnet'] = $params['subnet'];
        return true;
    }



    /*************************************************************************
     * Method        : validate_delete
     * Description   : Method for validate GET parameter
     * args          : $params
     * return        : true or false
     *************************************************************************/
    public function validate_delete($params)
    {
        /* define rules */
        $rules['subnet'] = [
            'method' => 'exist|subnet6format|subnet6exist:exist_true',
            'msg' => [_('Subnet does not exist in config.'),
                      _('Invalid subnet validate.'),
                      _('Subnet does not exist in config.')],
            'log' => ['Subnet does not exist in config.',
                      'Invalid subnet validate.',
                      'Subnet does not exist in config.',]
        ];

        /* Temporary replacement to avoid splitting as an option */
        $subnet = str_replace(':', ';', $params['subnet']);

        $rules['prefix_del'] = [
             'method' => "exist|ipv6|existprefix:$subnet",
             'msg' => [
                 _('Prefix delete does not exist.'),
                 _('Invalid prefix validate.'),
             ],
             'log' => [
                 'Deleting prefix does not exist.',
                 'Invalid prefix(' . $params['prefix_del'] . ').',
             ]
        ];

        /* input store into values */
        $params['store'] = $this->store;

        /* validate passed value */
        $this->validater = new validater($rules, $params, true);

        /* keep validated value and messages */
        $this->msg_tag = array_merge($this->msg_tag, $this->validater->tags);

        /* when validation error */
        if ($this->validater->err['result'] === false) {
            $this->store->log->output_log_arr($this->validater->logs);
            return false;
        }

        $this->subnet['subnet'] = $params['subnet'];
        return true;
    }

    /*************************************************************************
     * Method        : delete_prefix
     * Description   : Method for delete pd-pools data
     * args          : $subnet
     * return        : true or false
     *************************************************************************/
    public function delete_prefix($subnet, $prefix)
    {
        /* check subnet belong shared-network part */
        $ret = $this->conf->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* delete subnet in config */
        if ($ret === RET_SUBNET) {
            [$ret, $new_config] = $this->conf->del_pd_pools($subnet, $prefix, $pos_subnet);
        } else if ($ret === RET_SHNET) {
            [$ret, $new_config] = $this->conf->del_pd_pools($subnet, $prefix, $pos_subnet, $pos_shnet);
        }
        if ($ret === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Prefix delegation deleted successfully.(subnet: %s, prefix: %s)";
        $success_log = sprintf($log_format, $subnet, $prefix);

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log);
        $msg = _('Prefix delegation deleted successfullly.(%s)');
        $this->msg_tag['success'] = sprintf($msg, $prefix);

        return true;
    }

    /*************************************************************************
    * Method        : create_pd_list
    * Description   : Method for search pd-pools data
    * args          : $conditions
    * return        : true or false
    *************************************************************************/
    public function create_pd_list($subnet)
    {
        /* search subnet6 by passed condition */
        [$ret, $subnetdata] = $this->conf->get_one_subnet($subnet);

        /* failed to fetch subnet6 */
        if ($ret === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err);
            return false;
        }

        if (empty($subnetdata['pd-pools'])) {
            $err_tag['e_msg'] = _('Prefix delegation setting does not exist.');
            $err_log = 'Prefix delegation setting does not exist.';
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            return false;
        }

        foreach ($subnetdata['pd-pools'] as $value) {
            $pd_pools = [];
            $pd_pools['prefix'] = $value['prefix'];
            $pd_pools['prefix_len'] = $value['prefix-len'];
            $pd_pools['delegated_len'] = $value['delegated-len'];

            $this->pd_pools[] = $pd_pools;
        }
        
        return true;
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : $subnet
    * return        : None
    *************************************************************************/
    public function display($pd_pools = null)
    {
        if ($pd_pools !== null) {
            $this->store->view->assign('item', $pd_pools);
        }
        $this->store->view->assign('subnet_val', $this->subnet);
        $this->store->view->assign('result', count_array($pd_pools));
        $this->store->view->render("listpd.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
 *  main
 ******************************************************************************/
$listpd = new ListPD($store);

/* check read kea.conf result */
if ($listpd->conf->result === false) {
    $listpd->display();
    exit;
}

/************************************
 * message section
 ************************************/
$msg = get('msg');
if ($msg === 'add_ok') {
    $listpd->msg_tag["success"] = _("Prefix delegation added successfully.");
} else if ($msg === 'edit_ok') {
    $listpd->msg_tag["success"] = _("Prefix delegation edited successfully.");
}

/**********************************
 * Delete
 ***********************************/
$delete = get('delete');

if (isset($delete)) {

    $del_action = true;

    $params = [
                'subnet'     => get('subnet'),
                'prefix_del' => get('delete'),
              ];

    /* check params of GET */
    $ret = $listpd->validate_delete($params);

    /* validation error */
    if ($ret === false) {
        $listpd->display();
        exit(1);
    }

    /* delete subnet */
    $ret = $listpd->delete_prefix($params['subnet'], $params['prefix_del']);
    if ($ret === true) {
        /* refesh config */
        $listpd->conf->get_config(DHCPV6);
    }
}


/*************************************
* Initial screen, display all subnet6
*************************************/
$subnet = get('subnet');
$condition = ['subnet' => $subnet];
$ret = $listpd->validate_subnet($condition);
if ($ret === false) {
    $listpd->display();
    exit(1);
}

$ret = $listpd->create_pd_list($subnet);
if ($ret === false) {
    $listpd->display();
    exit(1);
}

$listpd->display($listpd->pd_pools);
exit(0);
