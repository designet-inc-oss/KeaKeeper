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
* Class          : UpdateConfig4
* Description    : Class for update config file information page
* args           : $store
*****************************************************************************/
class UpdateConfig4 {
    public $conf;
    private $store;
    private $log;

    /*************************************************************************
    * Method        : __construct
    * Description   : Method for setting tags automatically
    * args          : $store
    *************************************************************************/
    public function __construct($store)
    {
        $this->msg_tag = ['e_msg'            => null,
                          'success'          => null];

        $this->store = $store;

        /* call kea-dhcp4.conf class */
        $this->conf = new KeaConf(DHCPV4);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
        }

    }

    /*************************************************************************
    * Method        : update_config_file
    * args          : none
    * return        : None
    *************************************************************************/
    public function update_config_file()
    {
        global $appini;

        /* If set log message , update config file */
        $ret = $this->conf->config_reflect();
        if ($ret === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log'], null);
            return;
        }

        /* delete log message from session */
        $this->conf->delete_hist_from_sess();
        $log_format = "Config file update successfully.(%s)";
        $success_log = sprintf($log_format, $appini["conf"]["pathdhcp4"]);
        $this->msg_tag['success'] = _('Config file update successfully.');
        $this->store->log->log($success_log, null);
        return;
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : $shared-network
    * return        : None
    *************************************************************************/
    public function display()
    {

        /* Get log message from session */
        $arr_history = $this->conf->get_hist_from_sess();

        if ($arr_history !== null) {
            $this->store->view->assign('item', $arr_history);
        } else {
            $this->store->view->assign('item', "");
        }

        $this->store->view->render("updateconfig4.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
*  main
******************************************************************************/
$upconfig4 = new UpdateConfig4($store);

/* check read kea-dhcp4.conf result */
if ($upconfig4->conf->result === false) {
    $upconfig4->display();
    exit(1);
}

/*************************************
* Update and display section
*************************************/
$update = post('update');

if (isset($update)) {

    /* Update config file */
    $upconfig4->update_config_file();
    $upconfig4->display();

    exit(0);

}

/*************************************
* Check history and display section
*************************************/
$upconfig4->display();

exit();
