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

define('LEASE', '(expire - INTERVAL valid_lifetime SECOND)');

/*****************************************************************************
* Class          : conditionValidate
* Description    : Validation class that validate searchng condition
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class allemptyValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        foreach($this->allval as $condition) {
            if ($condition != '') {
                $this->conditions = $this->allval;
                return true;
            }
        }
        return false;
    }
}

/*****************************************************************************
* Class          : Searchlease4
* Description    : Class for search lease4 information page
* args           : $store
*****************************************************************************/
class Searchlease4 {
    private $where;
    public $store;
    public $pageobj;
    public $search;
    private $datahead;
    private $msg_tag;
    private $result;
    private $pre;
    private $dbutil;

    /*************************************************************************
    * Method        : __construct
    * Description   : Method for setting tags automatically
    * args          : None
    * return        : None
    *************************************************************************/
    public function __construct($store)
    {
        $this->msg_tag = ['e_ldate1'  => null,
                          'e_ldate2'  => null,
                          'e_edate1'  => null,
                          'e_edate2'  => null,
                          'e_all'     => null,
                          'no_result' => null];

        $this->result = null;
        $this->store  = $store;
        $this->dbutil = new dbutils($this->store->db);
    }

    /*************************************************************************
    * Method        : search
    * Description   : Method for validate and search lease4
    * args          : $conditions
    * return        : None
    *************************************************************************/
    public function search($conditions)
    {
        global $appini;
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
        $rules['ldate1'] = [
                            'method' => 'date:Y-m-d|datecmp:<=:ldate2:Y-m-d',
                            'msg' => 
                             [_('Please check the format of start lease date.'), 
                                      _('Please check the lease date range.')],
                            'log' => [
                                 'Invalid lease date format(start lease date: '.
                                 $conditions['ldate1']. ')',
                                 'Invalid lease date range('.
                                 $conditions['ldate1']. ' - '. 
                                 $conditions['ldate2']. ')'],
                            'option' => ['allowempty']
                           ];

        $rules['ldate2']  = [
                             'method' => 'date:Y-m-d',
                             'msg' => 
                              [_('Please check the format of end lease date.')],
                             'log' => [
                                   'Invalid lease date format(end lease date: '. 
                                   $conditions['ldate2']. ')'],
                             'option' => ['allowempty']
                            ];

        $rules['edate1'] = [
                            'method' => 'date:Y-m-d|datecmp:<=:edate2:Y-m-d',
                            'msg' => 
                            [_('Please check the format of start expire date.'), 
                             _('Please check the expire date range.')],
                            'log' => 
                              ['Invalid start expire date format(expire date: '.
                               $conditions['edate1']. ')', 
                               'Invalid expire date range('.
                               $conditions['edate1']. ' - '.
                               $conditions['edate2']. ')'],
                            'option' => ['allowempty']
                           ];

        $rules['edate2'] = [
                            'method' => 'date:Y-m-d',
                            'msg' => 
                             [_('Please check the format of end expire date.')],
                            'log' => 
                                ['Invalid end expire date format(expire data: '. 
                                 $conditions['edate2']. ')'],
                            'option' => ['allowempty']
                           ];

        $rules['all'] = [
                         'method' => 'allempty',
                         'msg' => [_('Please enter search conditions.')],
                         'log' => ['Empty search conditions.'],
                        ];

        /* validation passed value */
        $validater = new validater($rules, $conditions, true);

        /* keep validated value and message */
        $this->pre = $validater->err["keys"];
        $this->msg_tag = $validater->tags;

        /* when validation error */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            $this->display();
            return false;
        }

        /* make WHERE statement and pagenate */
        $this->_makewhere($conditions);
        $this->pagenation();

        /* fetch the lease4 data using WHERE statement */
        $tmp_data = $this->_fetch_lease4($appini['search']['leasemax'],
                                         $this->datahead);

        /* format identifier adding colon */
        $lease4data = [];
        foreach ($tmp_data as $item) {
            $item['id'] = add_colon($item['id']);

            if (strptime($item['lease'], '%Y-%m-%d %H:%M:%S') === false) {
                $item['lease'] = '';
            }

            if (strptime($item['expire'], '%Y-%m-%d %H:%M:%S') === false) {
                $item['expire'] = '';
            }

            $lease4data[] = $item;
        }

        /* display fetched data */
        $this->display($lease4data);
        return true;
    }

    /*************************************************************************
    * Method        : pagenation
    * Description   : Method for make page link.
    * args          : $page_info
    * return        : None
    *************************************************************************/
    public function pagenation()
    {
        global $appini;
        $this->pageobj = new Pagination('mysql');
        $this->pageobj->currentpage = get('page', 1);
        $this->pageobj->linknum     = 5;
        $this->pageobj->dataperpage = $appini['search']['leasemax'];
        $this->pageobj->source = 'SELECT COUNT(address) from lease4 WHERE ' .
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
    * Method        : _makewhere
    * Description   : Method for make WHERE statement.
    * args          : $conditions
    * return        : None
    *************************************************************************/
    private function _makewhere($conditions)
    {
        /* make where statement of address */
        if ($conditions['ipaddr'] != null) {
            $esc = $this->dbutil->escape_wildcard($conditions['ipaddr']);
            $this->dbutil->like($this->store->db->inet_ntoa('address'), 
                                $esc . "%");
        }

        /* make where statement of identifier */
        if ($conditions['identifier'] != null) {
            $conditions['identifier'] = remove_colon($conditions['identifier']);
            $this->dbutil->where($this->store->db->hex('hwaddr'),
                                 $conditions['identifier']);
        }

        /* make where statement of lease date */
        if ($conditions['ldate1'] != null) {
            $this->dbutil->where(LEASE . " >=", $conditions['ldate1']);
        }
        
        if ($conditions['ldate2'] != null) {
            $this->dbutil->where(LEASE . " <=",
                                 $conditions['ldate2'] . " 23:59:59");
        }

        /* make where statement of expire date */
        if ($conditions['edate1'] != null) {
            $this->dbutil->where("expire >=", $conditions['edate1']);
        }
        
        if ($conditions['edate2'] != null){
            $this->dbutil->where("expire <=",
                                 $conditions['edate2'] . " 23:59:59");
        }

        $this->where = $this->dbutil->where_state;
    }

    /*************************************************************************
    * Method        : _fetch_lease4
    * Description   : Method for fetch lease4 data.
    * args          : $dataperpage
    *                 $datahead
    * return        : fetched lease4 data
    *************************************************************************/
    private function _fetch_lease4($dataperpage, $datahead)
    {
        /* make SELECT statement */
        $select = [$this->store->db->hex('hwaddr') . ' AS "id"',
                   $this->store->db->inet_ntoa('address') . ' AS "ip"',
                   LEASE . ' AS "lease"', 'expire'];
        $this->dbutil->select($select);

        /* make FROM statement */
        $this->dbutil->from('lease4');

        /* make ORDER BY statement */
        $this->dbutil->order(LEASE);

        /* make LIMIT statement */
        $this->dbutil->limit($dataperpage, $datahead);

        /* fetch lease4 information using made statement */
        return $this->dbutil->get();
    }

    /*************************************************************************
    * Method        : display
    * Description   : Method for displaying the template on the screen.
    * args          : $store
    * return        : None
    *************************************************************************/
    public function display($lease4data = null)
    {
        if ($lease4data != null) {
            $this->store->view->assign('item', $lease4data);
        }
        $this->store->view->assign('result', $this->result);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->assign('paging', $this->pageobj);
        $this->store->view->render("searchlease4.tmpl", $this->msg_tag);
    }
}

/******************************************************************************
*  main
******************************************************************************/
$lease4_obj = new Searchlease4($store);

/************************************
* Search and display section
************************************/
$search = get('search');

if (isset($search)) {
    /************************************
    * Fetch lease4 data
    ************************************/
    $conditions  = [
        'ipaddr'     => get('ip'),
        'identifier' => get('id'),
        'ldate1'     => get('ldate1'),
        'ldate2'     => get('ldate2'),
        'edate1'     => get('edate1'),
        'edate2'     => get('edate2'),
        'all'        => '',
    ];
    
    $ret = $lease4_obj->search($conditions);
    exit;

}

/************************************
* Initial display
************************************/
$lease4_obj->display();
