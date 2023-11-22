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
* Class:  SearchHost4
*
* [Description]
*   Class for searching information about hosts
*****************************************************************************/
class SearchHost4 {

    public  $msg_tag;
    public  $conf;
    private $store;
    private $err_tag;
    private $err_tag2;
    private $pre;
    private $subnet_val;
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
        /* Tag */
        $this->msg_tag =  ["ipaddr"     => "",
                           "identifier" => "",
                           "subnet_id"  => "",
                           "subnet"     => "",
                           "e_pool"     => "",
                           "host_id"    => "",
                           "disp_msg"   => "",
                          ];
        $this->pools = null;
        $this->err_tag =  ["e_page"     => "",
                           "e_all"      => "",
                          ];
        $this->err_tag2 = ["e_subnet"    => "",
                           "e_subnet_id" => "",
                          ];

        $this->result = null;
        $this->store  = $store;

        /* read keaconf */
        $this->read_keaconf();
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
    public function delete($host_id)
    {
        /* delete from dhcp4_options */
        $this->dbutil = new dbutils($this->store->db);
        /* make FROM statement */
        $this->dbutil->from('dhcp4_options');
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

        $log_msg = "Reservation IP deleted.";
        $this->store->log->output_log($log_msg);
        $this->msg_tag['disp_msg'] = _("Reservation IP deleted.");

    }

    /*************************************************************************
    * Method        : validate_subnet
    * Description   : Method for Checking subet and subnet_id in get value
    * args          : $params
    * return        : true/false
    **************************************************************************/
    public function validate_subnet($params)
    {
        $rules["subnet_id"] = ["method"=>"exist",
                               "msg"=>[_("Can not find a subnet id.")],
                               "log"=>["Can not find a subnet id in GET parameters."],
                              ];
        $rules["subnet"] = ["method"=>"exist",
                            "msg"=>[_("Can not find a subnet.")],
                            "log"=>["Can not find a subnet in GET parameters."],
                           ];

        $validater = new validater($rules, $params, true);
        $this->subnet_val = $validater->err["keys"];
        $this->err_tag2 = $validater->tags;

        /* When validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();

            return false;
        }

        $pools_arr = $this->conf->get_pools($this->subnet_val['subnet']);
        if ($pools_arr === false) {
            $this->msg_tag['e_pool'] = $this->conf->err['e_msg'];
            $this->store->log->log($this->conf->err['e_log'], null);
            $this->display();
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
    * Method        : check_keaconf
    * Description   : Method for Checking if there is a matching subnet
    *                                                            in keaconf
    * args          : $params
    * return        : true/false
    **************************************************************************/
    public function check_keaconf($params)
    {
        /* When reading of keaconf succeeded */
        /* get all subnet in config */
        $all_subnet_data = $this->conf->mk_arr_all_subnet($this->conf->dhcp4);

        foreach ($all_subnet_data as $shnet_name => $data_subnet) {

            /* When reading of keaconf succeeded */
            foreach ($data_subnet as $one) {

                if (array_key_exists('id', $one) &&
                                         $one['id'] == $params['subnet_id']) {
                    /* When matching subnet_id is in keaconf */
                    if (array_key_exists('subnet', $one)) {
                        if ($one['subnet'] == $params['subnet']) {

                            /* When a matching subnet is in keaconf */
                            return true;
                        }
                    }
                    /* When matching subnet is not in keaconf */
                    $tmp_msg = _("No such subnet(%s)");
                    $this->msg_tag['disp_msg'] = sprintf($tmp_msg, $params['subnet']);
                    $this->store->log->output_log("No such subnet(" . $params['subnet'] . ")");
                    $this->display();

                    return false;
                }
            }
        }

        /* When matching subnet_id is not in keaconf */
        $tmp_msg = _("No such subnet id(%s)");
        $this->msg_tag['disp_msg'] = sprintf($tmp_msg, $params['subnet_id']);
        $this->store->log->output_log("No such subnet id(" . $params['subnet_id'] . ")");
        $this->display();

        return false;

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
    * Description   : Method for search host4
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

        /* fetch the host4 data using WHERE statement */
        $tmp_data = $this->_fetch_host4($appini['search']['hostmax'],
                                       $this->datahead);

        $host4data = [];
        foreach ($tmp_data as $item) {
            $item['id'] = add_colon($item['id']);
            $item['type'] = $this->_check_type($item['type']);
            if ($item['hostname'] === NULL) {
                $item['hostname'] === "";
            }
            $host4data[] = $item;
        }

        /* display fetched data */
        $this->display($host4data);
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
        $this->dbutil->where('dhcp4_subnet_id', $conditions['subnet_id']);

        /* make where statement of ipaddr */
        if ($conditions['ipaddr'] != null) {
            $this->dbutil->like($this->store->db->inet_ntoa('ipv4_address'),
                                   $conditions['ipaddr'] . "%");
        }

        /* make where statement of identifier */
        if ($conditions['identifier'] != null) {
            $conditions['identifier'] = remove_colon($conditions['identifier']);
            $this->dbutil->where($this->store->db->hex('dhcp_identifier'),
                                    $conditions['identifier']);
        }

        $this->where = $this->dbutil->where_state;
    }

    /*************************************************************************
    * Method        : _fetch_host4
    * Description   : Method for search host4
    * args          : $dataperpage
    *               : $datahead
    * return        : fetched host4 data
    **************************************************************************/
    private function _fetch_host4($dataperpage, $datahead)
    {
        /* make SELECT statement */
        $select = [$this->store->db->hex('dhcp_identifier') . ' AS "id"',
                   $this->store->db->inet_ntoa('ipv4_address') . ' AS "ip"',
                   'hostname AS "hostname"',
                   'dhcp_identifier_type AS "type"',
                   'host_id AS "host_id"',
                  ];
        $this->dbutil->select($select);

        /* make FROM statement */
        $this->dbutil->from('hosts');

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
        $this->pageobj->source = 'SELECT COUNT(ipv4_address) from hosts WHERE ' .
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
        $this->store->view->assign('result', $this->result);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->assign('subnet_val', $this->subnet_val);
        $this->store->view->assign('paging', $this->pageobj);
        $this->store->view->render("searchhost4.tmpl", $array);
    }
}

/*************************************************************************
*  main
*************************************************************************/
$searchhost4_obj = new SearchHost4($store);

/* check read kea.conf result */
if ($searchhost4_obj->conf->result === false) {
    $searchhost4_obj->display();
    exit(1);
}

/************************************
* Default section
************************************/
$subnet_id = get('subnet_id');
$subnet    = get('subnet');

$subnet_params = [
    'subnet_id' => $subnet_id,
    'subnet'    => $subnet,
];

if ($searchhost4_obj->validate_subnet($subnet_params) === false) {
    exit(1);
}
if ($searchhost4_obj->check_keaconf($subnet_params) === false) {
    exit(1);
}

/* set hidden tag */
$searchhost4_obj->msg_tag['subnet_id'] = $subnet_id;
$searchhost4_obj->msg_tag['subnet'] = $subnet;

/**********************************
* Delete section
***********************************/
$del = get('del');
if (isset($del)) {

    /* check subnet_id and subnet in GET value */
    if ($searchhost4_obj->validate_subnet($subnet_params) === false) {
        exit(1);
    }

    $host_id = get('host_id');
    /* check subnet_id and subnet in GET value */
    if ($searchhost4_obj->check_hostid($host_id) === false) {
        exit(1);
    }

    $searchhost4_obj->delete($host_id);
}

/**********************************
* Search section
***********************************/
$search = get('search');
if (isset($search)) {
    /* check subnet_id and subnet in GET value */
    if ($searchhost4_obj->validate_subnet($subnet_params) === false) {
        exit(1);
    }

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
        'subnet_id'  => $subnet_id,
    ];

    if ($searchhost4_obj->validate_params($params) === false) {
        exit(1);
    }

    /* start search */
    $searchhost4_obj->search($conditions);
    exit(0);
}

/************************************
* Initial display
************************************/
$searchhost4_obj->display();
exit(0);
