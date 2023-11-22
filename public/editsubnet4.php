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
 * Class:  EditSubnet4
 *
 * [Description]
 *   Class for add, update, edit options of subnet
 *****************************************************************************/
class EditSubnet4 {

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
        /* Tag */
        $this->msg_tag =  [
                            "subnet"          => null,
                            "extra_data"      => null,
                            "disp_msg"        => null,
                            "e_subnet"        => null,
                            "e_extra_name"    => null,
                            "e_extra_value"   => null,
                            "e_routers"       => null,
                            "e_dnsserveraddr" => null,
                            "display_ext_fmt" => "none",
                            "extra_success"   => null,
                          ];
        $this->err_tag =  [
                            "e_msg"           => null,
                            "e_msg_extra"     => null,
                          ];
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
     * Description   : Method for Checking subet and subnet_id in get value
     * args          : $params - GET data
     * return        : true/false
     *************************************************************************/
    public function validate_params($params)
    {
        $rules["subnet"] = [
            "method"=>"exist|subnet4exist:exist_true",
            "msg"=> [
                 _('Can not find a subnet.'),
                 _('Subnet do not exit in config.'),
             ],
             "log"=> [
                 'Can not find a subnet in GET parameters.',
                 sprintf('Subnet do not exist in config.(%s)', $params["subnet"]),
            ],
        ];

        $validater = new validater($rules, $params, true);
        /* keep validated value into property */
        $this->pre = $validater->err["keys"];
        $this->pre['extra_name']  = null;
        $this->pre['extra_value'] = null;
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : validate_post
     * Description   : check postdata
     * args          : $values - POST data
     * return        : true/false
     *************************************************************************/
    public function validate_post($values)
    {
        $valid_flg = true;
        $subnet        = $values["subnet"];
        $router_str    = $values['routers'];
        $dnsserver_str = $values['dnsserveraddr'];

        if (strpos($router_str, ',') !== false) {
            $arr_router = explode(',', $router_str);
        } else {
            $arr_router[0] = $router_str;
        }

        if (strpos($dnsserver_str, ',') !== false) {
            $arr_dnsserver = explode(',', $dnsserver_str);
        } else {
            $arr_dnsserver[0] = $dnsserver_str;
        }

        foreach ($arr_router as $router) {

            $data_check['routers'] = $router;

            $rules["routers"] = ["method"=>"exist|ipv4|insubnet4:$subnet",
                                    "msg"=>[
                                       _('Please enter routers.'),
                                       _('Invalid format of routers.'),
                                       _('Router out of subnet range.'),
                                    ],
                                    "log"=>[
                                      'Empty routers.',
                                      'Invalid routers(' . $router_str. ').', 
                                      'Router out of subnet range(' . $dnsserver_str. ').',
                                    ],
                               ];

            $validater = new validater($rules, $data_check, true);
            /* keep validated value into property */
            $this->pre = $validater->err["keys"];
            $this->pre['routers'] = $router_str;
            $this->pre['dnsserveraddr'] = $dnsserver_str;
            $this->pre['extra_name'] = null;
            $this->pre['extra_value'] = null;

            /* keep subnet */
            $this->msg_tag['subnet'] = $subnet;
            $this->err_tag = $validater->tags;

            /* validation check fails */
            if ($validater->err['result'] === false) {
                $valid_flg = false;
                break;
            }
        }

        foreach ($arr_dnsserver as $dnsserver) {

            $data_check['dnsserveraddr'] = $dnsserver;

            $rules["dnsserveraddr"] = ["method"=>"exist|ipv4|insubnet4:$subnet",
                                         "msg"=>[
                                         _('Please enter DNS Server Address.'),
                                         _('Invalid DNS Server Address.'),
                                         _('DNS Server Address out of subnet range.'),
                                      ],
                                        "log"=>[
                                          'Empty DNS Server Address.',
                                          'Invalid DNS Server Address.(' . $dnsserver_str . ')', 
                                          'DNS Server Address. out of subnet range(' . $dnsserver_str . ').',
                                         ],
                                     ];
            $validater = new validater($rules, $data_check, true);
            /* keep validated value into property */
            $this->pre = $validater->err["keys"];
            $this->pre['routers'] = $router_str;
            $this->pre['dnsserveraddr'] = $dnsserver_str;
            $this->pre['extra_name'] = null;
            $this->pre['extra_value'] = null;

            /* keep subnet */
            $this->msg_tag['subnet'] = $subnet;
            $this->err_tag = $validater->tags;

            /* validation check fails */
            if ($validater->err['result'] === false) {
                $valid_flg = false;
                break;
            }
        }

        if (!$valid_flg) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : validate_extra_post
     * Description   : check postdata
     * args          : $values - POST data
     * return        : true/false
     *************************************************************************/
    public function validate_extra_post($values)
    {
        $subnet = $values["subnet"];

        $rules["extra_name"] = ["method"=>"exist",
                                "msg"=>[
                                   _('Please enter option name.'),
                                ],
                                "log"=>[
                                  'Empty option name.',
                                ],
                          ];
        $rules["extra_value"] = ["method"=>"exist",
                                     "msg"=>[
                                 _('Please enter option value.'),
                               ],
                               "log"=>[
                                 'Empty option value',
                               ],
                            ];

        /* create object validater */
        $validater = new validater($rules, $values, true);

        /* keep validated value into property */
        $this->pre = $validater->err["keys"];

        /* keep subnet */
        $this->msg_tag['subnet'] = $subnet;
        $this->msg_tag['display_ext_fmt'] = 'block';
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : get_options
     * Description   : Add option data to subnet
     * args          : $subnet - current subnet
     * return        : None
     *************************************************************************/
    public function get_options($subnet) 
    { 
        $keaopt = new KeaOption(DHCPV4);

        /* display in extra option part only */
        $extra_option = [];     

        $optiondata = $this->conf->get_options($subnet);

        foreach ($optiondata as $optdata) {
            if ($optdata['name'] === 'routers') {
                if (strpos($optdata['data'], '.')) {
                    $this->pre['routers'] = $optdata['data'];
                } else {
                    // TODO must fix if have to multi IP
                    $this->pre['routers'] = long2ip(hexdec($optdata['data']));
                }
            } else if ($optdata['name'] === 'domain-name-servers') {
                if (strpos($optdata['data'], '.')) {
                    $this->pre['dnsserveraddr'] = $optdata['data'];
                } else {
                    // TODO must fix if have to multi IP
                    $this->pre['dnsserveraddr'] = long2ip (hexdec($optdata['data']));
                }
            } else {
                $extra_option[] =  $optdata;
            }
        }

        /* edit data to display */
        $disp_extra_option = $keaopt->edit_data_opt_v4($extra_option);

        $this->msg_tag['extra_data'] = $disp_extra_option;
    }

    /*************************************************************************
     * Method        : add_options
     * Description   : Add option data to subnet
     * args          : $subnet    - current subnet
     *                 $postdata  - POST data
     * return        : None
     *************************************************************************/
    public function add_options($subnet, $postdata)
    {
        $new_opt_data = [
           0 => [
                  STR_OPT_NAME  => 'routers',
                  STR_OPT_VALUE => $postdata['routers'],
                ],
           1 => [
                  STR_OPT_NAME   => 'domain-name-servers',
                  STR_OPT_VALUE  => $postdata['dnsserveraddr'],
                ],
         ];

        /* add option data to current config */
        $new_config = $this->conf->add_option($subnet, $new_opt_data);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Option added.(%s)(routers: %s)(domain-name-servers: %s)";
        $log_msg = sprintf($log_msg, $subnet,
                                     $postdata["routers"],
                                     $postdata["dnsserveraddr"]);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Option added.");

        return true;
    }

    /*************************************************************************
     * Method        : add_extra_options
     * Description   : Add option data to subnet
     * args          : $subnet
     *               : $postdata
     * return        : true or false
     *************************************************************************/
    public function add_extra_options($subnet, $postdata)
    {
        $new_opt_data = [
           0 => [
                  STR_OPT_NAME  => $postdata['extra_name'],
                  STR_OPT_VALUE => $postdata['extra_value'],
                ],
         ];

        /* add option data to current config */
        $new_config = $this->conf->add_option($subnet, $new_opt_data);
        if ($new_config === false) {
            $this->err_tag['e_msg_extra'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Extra Option added.(%s)(name: %s)(value: %s)";
        $log_msg = sprintf($log_msg, $subnet,
                                     $postdata["extra_name"],
                                     $postdata["extra_value"]);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->output_log($log_msg);
        $this->msg_tag['extra_success'] = _("Extra Option added.");

        return true;
    }

    /*************************************************************************
     * Method        : del_options
     * Description   : Delete option data of subnet
     * args          : $subnet
     * return        : true or false
     **************************************************************************/
    public function del_options($subnet)
    {
        $array_opt_del = ['domain-name-servers', 'routers'];

        /* add option data to current config */
        $new_config = $this->conf->del_option($subnet, $array_opt_del);
        if ($new_config === false) {
            $this->err_tag = array_merge($this->err_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Option deleted.(%s)(%s)";
        $log_msg = sprintf($log_msg,
                           $subnet, implode(',', $array_opt_del));

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);       
 
        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Option deleted.");

        return true;
    }

    /*************************************************************************
     * Method        : del_extra_options
     * Description   : Delete extra option data of subnet
     * args          : $subnet
     *               : $optionname
     * return        : true or false
     **************************************************************************/
    public function del_extra_options($subnet, $optionname)
    {
        /* display extra option */
        $this->msg_tag['display_ext_fmt'] = 'block';

        $array_opt_del = [$optionname];

        /* add option data to current config */
        $new_config = $this->conf->del_option($subnet, $array_opt_del);
        if ($new_config === false) {
            $this->err_tag['e_msg_extra'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_msg = "Extra Option deleted.(subnet: %s)(optionname: %s)";
        $log_msg = sprintf($log_msg, $subnet, $optionname);

        /* save log to session history */
        $this->conf->save_hist_to_sess($log_msg);

        $this->store->log->output_log($log_msg);
        $this->msg_tag['extra_success'] = sprintf(_("Extra Option deleted.(%s)"), $optionname);

        return true;
    }

    /*************************************************************************
     * Method        : display
     * Description   : Method for displaying the template on the screen
     * args          : None
     * return        : None
     *************************************************************************/
    public function display()
    {
        $array = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("editsubnet4.tmpl", $array);
    }
}

/*************************************************************************
 *  main
 *************************************************************************/
$objEditSubnet4 = new EditSubnet4($store);

/* check current config  */
if ($objEditSubnet4->conf->result === false) {
    $objEditSubnet4->display();
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
if ($objEditSubnet4->validate_params($subnet_params) === false) {
    exit(1);
}

/**********************************
 * Edit section
 **********************************/
$editbtn = post('edit');
if (isset($editbtn)) {

    $postdata = [
        'subnet'        => post('subnet'),
        'routers'       => post('routers'),
        'dnsserveraddr' => post('dnsserveraddr'),
    ];

    /* check postdata */
    if ($objEditSubnet4->validate_post($postdata) === true) {
        /* add pool to subnet */
        $ret = $objEditSubnet4->add_options($subnet, $postdata);
        if ($ret === true) {
            /* refesh config */
            $objEditSubnet4->conf->get_config(DHCPV4);
        }
    }
}

/**********************************
 * Add Extra section
 **********************************/
$editbtn = post('add_extra');
if (isset($editbtn)) {
    
    $postdata = [
        'subnet'      => post('subnet'),
        'extra_name'  => post('extra_name'),
        'extra_value' => post('extra_value'),
    ];

    /* check postdata */
    $ret = $objEditSubnet4->validate_extra_post($postdata);
    if ($ret === true) {
        /* add extra option to subnet */
        $ret = $objEditSubnet4->add_extra_options($subnet, $postdata);
        if ($ret === true) {
            /* refesh config */
            $objEditSubnet4->conf->get_config(DHCPV4);
        }
    }
}

/**********************************
 * Delete section
 **********************************/
/* delete add option */
$delbtn = get('delete');
if (isset($delbtn)) {

    /* deleting option name target */
    $optionname = get('name');

    /* delete extra option to subnet */
    $ret = $objEditSubnet4->del_extra_options($subnet, $optionname);

    if ($ret === true) {
        /* refesh config */
        $objEditSubnet4->conf->get_config(DHCPV4);
    }
}

/* delete option */
$delbtn = get('del');
if (isset($delbtn)) {

    /* delete option to subnet */
    $ret = $objEditSubnet4->del_options($subnet);

    if ($ret === true) {
        /* refesh config */
        $objEditSubnet4->conf->get_config(DHCPV4);
    }
}

/* get current options */
$objEditSubnet4->get_options($subnet);

/************************************
 * Initial display
 ************************************/
$objEditSubnet4->display();
exit(0);
