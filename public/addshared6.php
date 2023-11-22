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
* Class          : ListShared6
* Description    : Class for add & list shared-network6 information page
* args           : $store
*****************************************************************************/
class ListShared6 {
    public $sharednetworks = null;
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
        $this->msg_tag = ['e_msg'            => null,
                          'e_sharednetwork'  => null,
                          'success'          => null];

        $this->store = $store;

        /* call kea-dhcp6.conf class */
        $this->conf = new KeaConf(DHCPV6);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
        }
    }

    /*************************************************************************
    * Method        : msg_set
    * Description   : Method for when values are received.
    * args          : $mst
    * return        : None
    **************************************************************************/
    public function msg_set($msg)
    {
        if ($msg === "edit_ok") {
            $this->msg_tag["success"] =
                                _("Shared-network edited successfully.(%s)");
        } else if ($msg === "delete_ok") {
            $this->msg_tag["success"] =
                                _("Shared-network deleted successfully.(%s)");
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
        $rules['sharednetwork'] =
          [
           'method' => 'exist|sharedname|shared6exist',
           'msg'    => [
                          _('Please enter shared-network name.'),
                          _('Invalid shared-network validate.'),
                          _('Shared-network already exists.')
                       ],
           'log'    => [
                         'Please enter shared-network name.',
                         sprintf('Invalid shared-network name format.(%s)'
                                               ,$values['sharednetwork']),
                         sprintf('Shared-network name already exists.(%s)'
                                               ,$values['sharednetwork'])
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
    * Method        : insert_sharedname
    * args          : none
    * return        : true or false
    *************************************************************************/
    public function insert_sharedname()
    {
        /* replace variable */
        $params = $this->pre;

        /* get shared-network */
        $shared_data = [
            STR_NAME     => $params["sharednetwork"],
        ];

        /* add shared_name */
        $new_config = $this->conf->add_shared_name($shared_data);
        if ($new_config === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $log_format = "Shared-network added successfully.(%s)";
        $this->msg_tag['success'] = _('Shared-network added successfully.');
        $success_log = sprintf($log_format, $params["sharednetwork"]);
        
        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log, NULL);
        $this->pre = "";
        return true;
    }

    /*************************************************************************
    * Method        : init_disp
    * Description   : Method for display all shared-network data
    * args          : None
    * return        : true or false
    *************************************************************************/
    public function init_disp()
    {
        /* fetch all shared-network6 */
        $sharednetworks = $this->_get_shared6();

        /* failed to fetch shared-network6*/
        if ($sharednetworks === false) {
            if ($this->log !== "") {
                $this->store->log->log($this->log, null);
            }
            return false;
        }

        asort($sharednetworks);
        $this->sharednetworks = $sharednetworks;
        return true;
    }

    /*************************************************************************
    * Method        : _get_shared6
    * Description   : Method for get shared-network6 data
    * args          : $mode - init or others
    *                 $cond - search condition
    * return        : fetched $sharednetworks or false
    *************************************************************************/
    private function _get_shared6($cond = null)
    {
        /* get all shared-network name */
        $sharednetworks = $this->conf->search_shared6();

        /* failed to search shared-network */
        if ($sharednetworks === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->log = $this->conf->err['e_log'];
            return false;
        }

        return $sharednetworks;
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : $shared-network
    * return        : None
    *************************************************************************/
    public function display($sharednetworks = null)
    {
        if ($sharednetworks !== null) {
            $this->store->view->assign('item', $sharednetworks);
        }
        $this->store->view->assign('result', count($sharednetworks));
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->render("addshared6.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
*  main
******************************************************************************/
$shared6 = new ListShared6($store);

/* check read kea-dhcp6.conf result */
if ($shared6->conf->result === false) {
    $shared6->display();
    exit(1);
}

/************************************
 * message section
 ************************************/
$msg = get('msg');
if ($msg !== NULL) {
    $shared6->msg_set($msg);
}

/*************************************
* Add and display section
*************************************/
$apply = post('add');

if (isset($apply)) {
    /************************************
    * Add shared network information
    ************************************/
    $post = ['sharednetwork' => post('sharednetwork')];

    $ret = $shared6->validate_post($post);

    if (!$ret) {
        $shared6->init_disp();
        $shared6->display($shared6->sharednetworks);
        exit(1);
    }

    /* add shared-network name */
    $ret = $shared6->insert_sharedname();

    if ($ret === false) {
        $shared6->init_disp();
        $shared6->display($shared6->sharednetworks);
        exit(1);
    }

    /* read new conf */
    $shared6->conf->get_config(DHCPV6);

}

/*************************************
* Initial screen, display all shared-network6
*************************************/
$shared6->init_disp();
$shared6->display($shared6->sharednetworks);

exit();
