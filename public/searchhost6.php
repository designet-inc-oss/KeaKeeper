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
* Class          : allemptyValidate
* Description    : Validation class that validate searchng condition
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class allemptyValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if ($this->allval['ipaddr'] == ''
                                    && $this->allval['identifier'] == '') {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class:  SearchHost6
*
* [Description]
*   Class for searching information about hosts
*****************************************************************************/
class SearchHost6 {

    public  $msg_tag;
    public  $conf;
    public  $check_subnet;
    public  $subnet_val;
    private $store;
    private $err_tag;
    private $err_tag2;
    private $pre;
    private $where;
    private $pageobj;
    private $dbutil;

    /*************************************************************************
    * Method        : __construct
    * Description   : Method for setting tags automatically
    * args          : $store
    * return        : None
    **************************************************************************/
    public function __construct($store)
    {
        $this->subnet_val = ['subnet_id' => get('subnet_id'),
                             'subnet'    => get('subnet')];

        $this->pools = null;

        $this->msg_tag =  ["ipaddr"     => "",
                           "identifier" => "",
                           "subnet_id"  => "",
                           "subnet"     => "",
                           "host_id"    => "",
                           'e_pool'     => null,
                           "disp_msg"   => "",
                          ];
        $this->err_tag =  ["e_page"     => "",
                           "e_all"      => "",
                          ];
        $this->err_tag2 = ["e_subnet"    => "",
                           "e_subnet_id" => "",
                          ];

        $this->result = null;
        $this->store  = $store;

        /* read keaconf */
        $this->conf = new KeaConf(DHCPV6);
        if ($this->conf->result === false) {
            $this->msg_tag['disp_msg'] = $this->conf->err['e_msg'];
            $this->store->log->output_log($this->conf->err['e_log']);
            $this->check_subnet = false;
            return;
        }

        /* check subnet and subnet_id existsnce in GET */
        $valid = $this->_validate_subnet();
        if ($valid === false) {
            $this->check_subnet = false;
            return;
        }

        /* check subnet and subnet_id existsnce in config file */
        $exist = $this->conf->check_id_subnet6($this->subnet_val['subnet_id'],
                                               $this->subnet_val['subnet']);
        if ($exist === false) {
            $this->msg_tag['disp_msg'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            $this->check_subnet = false;
            return;
        }
    }

    /*************************************************************************
    * Method        : check_hostid
    * Description   : Method for Checking host id in get value
    * args          : $host_id
    * return        : true/false
    **************************************************************************/
    public function check_hostid($host_id)
    {
        $this->dbutil = new dbutils($this->store->db);

        /* When the host_id is not included in GET value */
        if ($host_id === NULL || $host_id === "") {
            $log_msg = "Can not find host id.";
            $this->store->log->output_log($log_msg);
            $this->msg_tag['disp_msg'] = _("Can not find a host id.");
            $this->display();
            return false;
        }

        /* Search the database for a matching host_id */

        /* make where statement of subnet_id */
        $this->dbutil->where('host_id', $host_id);
        /* make select statement */
        $this->dbutil->select('host_id');
        /* make FROM statement */
        $this->dbutil->from('hosts');

        /* result */
        $count_ret = count($this->dbutil->get());

        /* Cannot get data from database */
        if ($count_ret === 0) {
            $log_msg = "Reservation IP has already been deleted.";
            $this->store->log->output_log($log_msg);
            $this->msg_tag['disp_msg'] = _("Reservation IP has already been deleted.");
            $this->display();
            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : delete
    * Description   : Method for deleting the selected host
    * args          : $host_id
    * return        : None
    **************************************************************************/
    public function delete($host_id, $reserv_id)
    {
        /* delete from dhcp6_options */
        $this->dbutil = new dbutils($this->store->db);
        /* make FROM statement */
        $this->dbutil->from('dhcp6_options');
        /* make where statement of subnet_id */
        $this->dbutil->where('host_id', $host_id);
        $this->dbutil->delete();

        /* delete from hosts */
        $this->dbutil = new dbutils($this->store->db);
        /* make FROM statement */
        $this->dbutil->from('hosts');
        /* make where statement of subnet_id */
        $this->dbutil->where('host_id', $host_id);
        $this->dbutil->delete();

        /* delete from hosts */
        $this->dbutil = new dbutils($this->store->db);
        /* make FROM statement */
        $this->dbutil->from('ipv6_reservations');
        /* make where statement of subnet_id */
        $this->dbutil->where('reservation_id', $reserv_id);
        $this->dbutil->delete();

        $log_msg = "Delete successful(host id: " . $host_id . ").";
        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Reservation IP deleted.");

    }

    /*************************************************************************
    * Method        : _validate_subnet
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    private function _validate_subnet()
    {
        $rules["subnet_id"] = ["method"=>"exist",
                               "msg"=>[_("Can not find a subnet id.")],
                               "log"=>["Can not find a subnet id in GET parameters."],
                              ];
        $rules["subnet"] = ["method"=>"exist|subnet6",
                            "msg"=>[_("Can not find a subnet."),
                                    _("Invalid subnet.")],
                            "log"=>["Can not find a subnet in GET parameters."],
                                   ["Invalid subnet in GET parameters."]];

        $validater = new validater($rules, $this->subnet_val, true);
        $this->subnet_val = $validater->err["keys"];
        $this->err_tag2 = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        list($addr, $mask) = explode("/", $this->subnet_val['subnet']);
        $addr = inet_ntop(inet_pton($addr));
        $this->subnet_val['subnet'] = $addr . "/" . $mask;

        /* get pools */
        $pools_arr = $this->conf->get_pools6($this->subnet_val['subnet']);
        if ($pools_arr === false) {
            $this->msg_tag['e_pool'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            return false;
        }

        $pools = [];
        if (is_array($pools_arr)) {
            foreach ($pools_arr as $key => $value) {
                $pools[] = $value['pool'];
            }
            $this->pools = $pools;
        }

        return true;
    }

    /*************************************************************************
    * Method        : validate_params
    * Description   : Method for checking the value of the search condition
    * args          : $params
    * return        : true/false
    **************************************************************************/
    public function validate_params($params)
    {
        /* Check validation */
        $rules['ipaddr'] = [
                            'method' => 'exist',
                            'msg' => [''],
                            'log' => [''],
                            'option' => ['allowempty']
                           ];
        $rules['identifier'] = [
                                'method' => 'exist',
                                'msg' => [''],
                                'log' => [''],
                                'option' => ['allowempty']
                               ];

        $rules["page"] = [
                          'method'=>'intmin:1',
                          'msg'=>[_('Invalid page number.')],
                          'log'=>['Invalid page number.(' . $params['page'] . ")"],
                         ];

        $rules['all'] = [
                         'method' => 'allempty',
                         'msg' => [_('Please enter search conditions.')],
                         'log' => ['Empty search conditions.'],
                        ];

        $validater = new validater($rules, $params, true);
        $this->pre = $validater->err["keys"];
        $this->err_tag = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();

            return false;
        }

        return true;
    }

    /*************************************************************************
    * Method        : search
    * Description   : Method for search host6
    * args          : $conditions
    * return        : None
    **************************************************************************/
    public function search($conditions)
    {
        global $appini;
        $this->dbutil = new dbutils($this->store->db);

        /* make statement */
        $this->_makewhere($conditions);
        $this->_pagenation();

        /* fetch the host6 data using WHERE statement */
        $tmp_data = $this->_fetch_host6($appini['search']['hostmax'],
                                       $this->datahead);

        $host6data = [];
        foreach ($tmp_data as $item) {
            $item['id'] = add_colon($item['id']);
            $item['type'] = $this->_check_type($item['type']);
            if ($item['hostname'] === NULL) {
                $item['hostname'] === "";
            }
            $host6data[] = $item;
        }

        /* display fetched data */
        $this->display($host6data);
    }

    /*************************************************************************
    * Method        : _check_type
    * Description   : Method for converting dhcp_identifier_type
    * args          : $type_num
    * return        : $str
    **************************************************************************/
    private function _check_type($type_num)
    {
        switch ($type_num) {
            case 0:
                return "MAC";
            case 1:
                return "DUID";
            case 2:
                return "Circuit-ID";
        }
    }
    /*************************************************************************
    * Method        : _makewhere
    * Description   : Method for make WHERE statement
    * args          : $conditions
    * return        : None
    **************************************************************************/
    private function _makewhere($conditions)
    {
        /* make where statement of subnet_id */
        $this->dbutil->where('hosts.dhcp6_subnet_id', $conditions['subnet_id']);

        /* make where statement of ipaddr */
        if ($conditions['ipaddr'] != null) {
            $this->dbutil->like('ipv6_reservations.address', $conditions['ipaddr'] . "%");
        }

        /* make where statement of identifier */
        if ($conditions['identifier'] != null) {
            $conditions['identifier'] = remove_colon($conditions['identifier']);
            $this->dbutil->where($this->store->db->hex('hosts.dhcp_identifier'),
                                    $conditions['identifier']);
        }

        $this->where = $this->dbutil->where_state;
    }

    /*************************************************************************
    * Method        : _fetch_host6
    * Description   : Method for search host6
    * args          : $dataperpage
    *               : $datahead
    * return        : fetched host6 data
    **************************************************************************/
    private function _fetch_host6($dataperpage, $datahead)
    {
        /* make SELECT statement */
        $select = [$this->store->db->hex('hosts.dhcp_identifier') . ' AS "id"',
                   'ipv6_reservations.address' . ' AS "ip"',
                   'hosts.hostname AS "hostname"',
                   'hosts.dhcp_identifier_type AS "type"',
                   'hosts.host_id AS "host_id"',
                   'ipv6_reservations.reservation_id AS "reservation_id"',
                  ];
        $this->dbutil->select($select);

        /* make FROM statement */
        $this->dbutil->from('hosts JOIN ipv6_reservations ON hosts.host_id=ipv6_reservations.host_id');

        /* make LIMIT statement */
        $this->dbutil->limit($dataperpage, $datahead);

        return $this->dbutil->get();

    }

    /*************************************************************************
    * Method        : _pagenation
    * Description   : Method for make page link
    * args          : None
    * return        : None
    *************************************************************************/
    private function _pagenation()
    {
        global $appini;
        $this->pageobj = new Pagination('mysql');
        $this->pageobj->currentpage = get('page', 1);
        $this->pageobj->linknum     = 5;
        $this->pageobj->dataperpage = $appini['search']['hostmax'];
        $this->pageobj->source = 'SELECT COUNT(ipv6_reservations.address) from hosts JOIN ipv6_reservations ON hosts.host_id=ipv6_reservations.host_id WHERE ' .
                                  $this->where;

        $this->pageobj->run();
        $this->msg_tag['result'] = $this->pageobj->totaldata;
        $this->datahead = $this->pageobj->datahead;

        /* Check result and if there is no result, output log */
        if ($this->pageobj->totaldata == 0) {
            $this->msg_tag['no_result'] = _('No result.');
            $this->store->log->output_log('No result.');
        }
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen
    * args          : $host6data Search result on host
    * return        : None
    **************************************************************************/
    public function display($host6data = null)
    {

        /* If host6data exists, display the table */
        if ($host6data != null) {
            $this->store->view->assign('item', $host6data);
        }

        $array = array_merge($this->msg_tag, $this->err_tag, $this->err_tag2);
        $this->store->view->assign('result', $this->result);
        $this->store->view->assign("pools", $this->pools);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->assign('subnet_val', $this->subnet_val);
        $this->store->view->assign('paging', $this->pageobj);
        $this->store->view->render("searchhost6.tmpl", $array);
    }
}

/*************************************************************************
*  main
*************************************************************************/
$searchhost6_obj = new SearchHost6($store);

if ($searchhost6_obj->check_subnet === false) {
    $searchhost6_obj->display();
    exit(1);
}

/**********************************
* Delete section
***********************************/
$del = get('del');
if (isset($del)) {

    $host_id = get('host_id');
    $reserv_id = get('reserv_id');
    /* check subnet_id and subnet in GET value */
    if ($searchhost6_obj->check_hostid($host_id) === false) {
        exit(1);
    }

    $searchhost6_obj->delete($host_id, $reserv_id);
}

/**********************************
* Search section
***********************************/
$search = get('search');
if (isset($search)) {

    /* check search condition */
    $ipaddr = get('ipaddr');
    $identifier = get('identifier');
    $page_num = get('page', 1);

    $params  = [
        'ipaddr'     => $ipaddr,
        'identifier' => $identifier,
        'all'        => '',
        'page'       => $page_num,
    ];

    $conditions  = [
        'ipaddr'     => $ipaddr,
        'identifier' => $identifier,
        'subnet_id'  => $searchhost6_obj->subnet_val['subnet_id'],
    ];

    if ($searchhost6_obj->validate_params($params) === false) {
        exit(1);
    }

    /* start search */
    $searchhost6_obj->search($conditions);
    exit(0);
}

/************************************
* Initial display
************************************/
$searchhost6_obj->display();
exit(0);
