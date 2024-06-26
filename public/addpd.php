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
 * Class          : AddPD
 * Description    : Class for add prefix delegation
 * args           : $store
 *****************************************************************************/
class AddPD
{
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
                           'e_subnet'        => null,
                           'e_prefix'        => null,
                           'e_prefix_len'    => null,
                           'e_delegated_len' => null,
                           'success'         => null,
                         ];

        $this->pre = ['prefix'     => "",
                      'prefix_len'   => "",
                      'delegated_len'    => "",
                     ];
        $this->subnet_val = [];
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

        $this->subnet_val['subnet'] = $params['subnet'];
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
        /* Inspect unavailable values */
        /* Temporary replacement to avoid splitting as an option */
        $subnet = str_replace(':', ';', $this->subnet_val['subnet']);

        $rules['prefix'] = [
           'method' => "exist|ipv6|check_other_subnet:$subnet|check_other_pd_pools",
           'msg'    => [
                         _('Please enter prefix.'),
                         _('Invalid prefix format.'),
                         _('Prefix overlaps with other subnets.'),
                         _('Prefix overlaps with other prefix delegation.')
                       ],
           'log'    => [
                         'Please enter prefix.',
                         sprintf('Invalid prefix format.(%s)', $values['prefix']),
                         sprintf('Prefix overlaps with other subnets.(%s)', $values['prefix']),
                         sprintf('Prefix overlaps with other subnets.(%s)', $values['prefix']),
                         sprintf('Prefix overlaps with other prefix delegation.(%s)', $values['prefix']),
                       ],
        ];

        $rules['prefix_len'] =
          [
           'method' => "exist|int|intmin:1|intmax:128",
           'msg'    => [
                          _('Please Enter prefix-len.'),
                          _('Invalid prefix-len format.'),
                          _('Invalid prefix-len format.'),
                          _('Invalid prefix-len format.'),
                       ],
           'log'    => [
                         'Please Enter prefix-len.',
                         'prefix-len is not an integer(' . $values['prefix_len']. ').',
                         'prefix-len is smaller than 1(' . $values['prefix_len']. ').',
                         'prefix-len is larger than 128(' . $values['prefix_len']. ').'
                       ],
          ];

        $rules['delegated_len'] =
          [
           'method' => "exist|int|intmin:1|intmax:128",
           'msg'    => [
                          _('Please Enter delegated-len.'),
                          _('Invalid delegated-len format.'),
                          _('Invalid delegated-len format.'),
                          _('Invalid delegated-len format.'),
                       ],
           'log'    => [
                         'Please Enter delegated-len.',
                         'delegated-len is not an integer(' . $values['delegated_len']. ').',
                         'delegated-len is smaller than 1(' . $values['delegated_len']. ').',
                         'delegated-len is larger than 128(' . $values['delegated_len']. ').'
                       ],
          ];

        /* input store into values */
        $values['store'] = $this->store;

        /* validate */
        $validater = new validater($rules, $values, true);

        $this->pre = $validater->err["keys"];

        /* input made message into property */
        $this->msg_tag = array_merge($this->msg_tag, $validater->tags);

        /* validation error, output log and return */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        $prefix_len = $values['prefix_len'];

        /* Inspect values that cannot be set */
        $rules['prefix'] = [
           'method' => "ipv6_prefreserve:$prefix_len|duplicate_pools:$prefix_len|duplicate_pd_pools:$prefix_len",
           'msg'    => [
                         _('Prefix/Prefix-len is in an invalid format.'),
                         _('Prefix/Prefix-len overlaps with existing subnet pool.'),
                         _('Prefix/Prefix-len overlaps with existing pd-pools.'),
                       ],
           'log'    => [
                         sprintf('Prefix/Prefix-len is in an invalid format.(%s/%s)', $values['prefix'], $prefix_len),
                         sprintf('Prefix/Prefix-len overlaps with existing subnet pool.(%s/%s)', $values['prefix'], $prefix_len),
                         sprintf('Prefix/Prefix-len overlaps with existing pd-pools.(%s/%s)', $values['prefix'], $prefix_len),
                       ],
        ];

        $rules['delegated_len'] = [
           'method' => "comparison:$prefix_len",
           'msg'    => [
                         _('Delegated-len must be greater than Prefix-len.'),
                       ],
           'log'    => [
                         'Delegated-len must be greater than Prefix-len.',
                       ],
        ];

        /* validate */
        $validater = new validater($rules, $values, true);

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
     * Method        : add_pd_pools
     * args          : none
     * return        : void
     *************************************************************************/
    public function add_pd_pools()
    {
        /* make insert variable */
        $params = [
            'prefix'        => $this->pre['prefix'],
            'prefix-len'    => intval($this->pre['prefix_len']),
            'delegated-len' => intval($this->pre['delegated_len'])
        ];

        $subnet = $this->subnet_val['subnet'];

        /* check subnet belong shared-network part */
        $ret = $this->conf->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* delete subnet in config */
        if ($ret === RET_SUBNET) {
            [$ret, $new_config] = $this->conf->add_pd_pools($subnet, $params, $pos_subnet);
        } else if ($ret === RET_SHNET) {
            [$ret, $new_config] = $this->conf->add_pd_pools($subnet, $params, $pos_subnet, $pos_shnet);
        }
        if ($ret === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Prefix delegation added.(subnet: %s prefix: %s/%s)";
        $success_log = sprintf($log_format, $subnet, $this->pre['prefix'], $this->pre['prefix_len']);

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log);

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
        $this->store->view->assign("subnet_val", $this->subnet_val);
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->render("addpd.tmpl", $errors);
    }

}

/******************************************************************************
 *  main
 ******************************************************************************/
$addpd = new AddPD($store);
if ($addpd->check_subnet === false) {
    $addpd->display();
    exit(1);
}

$subnet = get('subnet');
$condition = ['subnet' => $subnet];
$ret = $addpd->validate_subnet($condition);
if ($ret === false) {
    $addpd->display();
    exit(1);
}

/************************************
 * Insert section
 ************************************/
$addbtn = post('add');
/* if add button pressed */
if (isset($addbtn)) {

    $post = [
        'prefix' => post('prefix'),
        'prefix_len' => post('prefix_len'),
        'delegated_len' => post('delegated_len'),
    ];

    /* validate post */
    $ret = $addpd->validate_post($post);

    if ($ret === false) {
        $addpd->display();
        exit(1);
    }
    /* add pd-pools */
    $ret = $addpd->add_pd_pools();
    if ($ret === false) {
        $addpd->display();
        exit(1);
    }
    header("Location: listpd.php?subnet=$subnet&msg=add_ok");
    exit(0);
}

/************************************
 * Default section
 ************************************/
$addpd->display();
exit(0);
