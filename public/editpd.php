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
 * Class          : EditPD
 * Description    : Class for edit prefix delegation
 * args           : $store
 *****************************************************************************/
class EditPD
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
                           'e_old_prefix'    => null,
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
     * Method        : validate_get_params
     * Description   : Method for validate GET parameter
     * args          : $params
     * return        : true or false
     *************************************************************************/
    public function validate_get_params($params) {
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

        /* Temporary replacement to avoid splitting as an option */
        $subnet = str_replace(':', ';', $params['subnet']);

        /* define rules  */
        $rules['old_prefix'] = [
                'method' => "exist|existprefix:$subnet",
                'msg' => [_('Prefix does not exist in config.')],
                'log' => ['Prefix does not exist in config.']
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
 
        $this->subnet_val = [
                                'subnet'     => $params['subnet'],
                                'old_prefix' => $params['old_prefix']
                            ];
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
        $old_prefix = str_replace(':', ';', $this->subnet_val['old_prefix']); 
        $prefix_len = $values['prefix_len'];
        $rules['prefix'] = [
           'method' => "exist|equalprefix:$old_prefix",
           'msg'    => [
                         _('Please enter prefix.'),
                         _('Prefix cannot be edited.'),
                       ],
           'log'    => [
                         'Please enter prefix.',
                         'Prefix cannot be edited.'
                       ],
        ];

        $rules['prefix_len'] =
          [
           'method' => "exist",
           'msg'    => [
                          _('Please Enter prefix-len.'),
                       ],
           'log'    => [
                         'Please Enter prefix-len.',
                       ],
          ];

        $rules['delegated_len'] =
          [
           'method' => "exist|int|intmin:1|intmax:128|comparison:$prefix_len",
           'msg'    => [
                          _('Please Enter delegated-len.'),
                          _('Invalid delegated-len format.'),
                          _('Invalid delegated-len format.'),
                          _('Invalid delegated-len format.'),
                          _('Delegated-len must be greater than Prefix-len.'),
                       ],
           'log'    => [
                         'Please Enter delegated-len.',
                         'delegated-len is not an integer(' . $values['delegated_len']. ').',
                         'delegated-len is smaller than 1(' . $values['delegated_len']. ').',
                         'delegated-len is larger than 128(' . $values['delegated_len']. ').',
                         'Delegated-len must be greater than Prefix-len.',
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

        return true;
    }

    /*************************************************************************
     * Method        : edit_pd_pools
     * args          : none
     * return        : void
     *************************************************************************/
    public function edit_pd_pools()
    {
        /* make insert variable */
        $params = [
            'delegated-len' => intval($this->pre['delegated_len'])
        ];

        $subnet = $this->subnet_val['subnet'];

        /* Get the array number to be edited */
        $pos_pd_pools = "";
        [$ret, $subnetdata] = $this->conf->get_one_subnet($subnet);
        foreach ($subnetdata['pd-pools'] as $key => $value) {
            if ($value['prefix'] === $this->subnet_val['old_prefix']) {
                $pos_pd_pools = $key;
            }
        }

        /* check subnet belong shared-network part */
        $ret = $this->conf->check_subnet_belongto($subnet, $pos_subnet, $pos_shnet);

        /* delete subnet in config */
        if ($ret === RET_SUBNET) {
            [$ret, $new_config] = $this->conf->edit_pd_pools($subnet, $params, $pos_pd_pools, $pos_subnet);
        } else if ($ret === RET_SHNET) {
            [$ret, $new_config] = $this->conf->edit_pd_pools($subnet, $params, $pos_pd_pools, $pos_subnet, $pos_shnet);
        }
        if ($ret === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Prefix delegation edited.(subnet: %s prefix: %s/%s)";
        $success_log = sprintf($log_format, $subnet, $this->pre['prefix'], $this->pre['prefix_len']);

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log);

        return true;
    }

    /*************************************************************************
     * Method        : get_existing_pd_poools
     * args          : none
     * return        : void
     *************************************************************************/
    public function get_existing_pd_poools()
    {
        /* Get the setting to be edited */
        [$ret, $subnetdata] = $this->conf->get_one_subnet($this->subnet_val['subnet']);

        foreach ($subnetdata['pd-pools'] as $value) {
            if ($value['prefix'] === $this->subnet_val['old_prefix']) {
                $this->pre['prefix'] = $value['prefix'];
                $this->pre['prefix_len'] = $value['prefix-len'];
                $this->pre['delegated_len'] = $value['delegated-len'];
            }
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
        $errors = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign("subnet_val", $this->subnet_val);
        $this->store->view->assign("pre", $this->pre);
        $this->store->view->render("editpd.tmpl", $errors);
    }

}

/******************************************************************************
 *  main
 ******************************************************************************/
$editpd = new EditPD($store);
if ($editpd->check_subnet === false) {
    $editpd->display();
    exit(1);
}

$subnet = get('subnet');
$old_prefix = get('prefix');
$condition = [
                'subnet'     => $subnet,
                'old_prefix' => $old_prefix
             ];
$ret = $editpd->validate_get_params($condition);
if ($ret === false) {
    $editpd->display();
    exit(1);
}

/************************************
 * Insert section
 ************************************/
$editbtn = post('edit');
/* if edit button pressed */
if (isset($editbtn)) {

    $post = [
        'prefix' => post('prefix'),
        'prefix_len' => post('prefix_len'),
        'delegated_len' => post('delegated_len'),
    ];

    /* validate post */
    $ret = $editpd->validate_post($post);

    if ($ret === false) {
        $editpd->display();
        exit(1);
    }
    /* edit pd-pools */
    $ret = $editpd->edit_pd_pools();
    if ($ret === false) {
        $editpd->display();
        exit(1);
    }
    header("Location: listpd.php?subnet=$subnet&msg=edit_ok");
    exit(0);
}

/************************************
 * Default section
 ************************************/
$editpd->get_existing_pd_poools();
$editpd->display();
exit(0);
