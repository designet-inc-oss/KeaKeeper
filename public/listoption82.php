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
 * Class:  ListOption82
 *
 * [Description]
 *   Class for add, update, edit options of subnet
 *****************************************************************************/
class ListOption82 {

    public  $msg_tag;
    public  $conf;
    private $store;
    private $err_tag;
    private $pre;
    private $pageobj;

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
                            "e_msg"           => null,
                            "e_subnet"        => null,
                            "e_class_name"    => null,
                            "e_keyword"       => null,
                            "e_ipaddr"        => null,
                            "success"         => null,
                          ];
        $this->err_tag =  [
                            "e_msg"           => null,
                          ];

        /* conditions */
        $this->pre = [
            'keyword' => '',
            'ipaddr' => ''
        ];

        /* search */
        $this->subnet = "";
        $this->retrieval_data = [];

        /* result */
        $this->total = 0;
        $this->export_data = [];
        $this->result = [];
        $this->store  = $store;

        /* read keaconf */
        $this->store = $store;

        /* call kea.conf class */
        $this->conf = new KeaConf(DHCPV4);

        /* check config error */
        if ($this->conf->result === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err['e_log']);
        }
    }

    /*************************************************************************
     * Method        : validate_subnet
     * Description   : Method for Checking subet in get value
     * args          : $params - GET data
     * return        : true/false
     *************************************************************************/
    public function validate_subnet($params)
    {
        $rules["subnet"] = [
            "method"=>"exist|subnet4exist:exist_true",
            "msg"=> [
                 _('Can not find a subnet.'),
                 _('Subnet does not exist in config.'),
             ],
             "log"=> [
                 'Can not find a subnet in GET parameters.',
                 sprintf('Subnet does not exist in config.(%s)', $params["subnet"]),
            ],
        ];

        $validater = new validater($rules, $params, true);
        /* keep validated value into property */
        $this->pre = $validater->err["keys"];
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        /* keep subnet value */
        $this->subnet = $params['subnet'];
        return true;
    }

    /*************************************************************************
     * Method        : validate_class_name
     * Description   : Method for Checking class_name in get value
     * args          : $params - GET data
     * return        : true/false
     *************************************************************************/
    public function validate_class_name($params) {
        /*  define rules */
        $rules["class_name"] = [
            "method"=>"exist|option82format|classexist:dhcp4",
            "msg"=> [
                 _('Can not find a class name.'),
                 _('Class name must begin with opt82_.'),
                 _('Class name does not exist in config.'),
             ],
             "log"=> [
                 'Can not find a class name in GET parameters.',
                 sprintf('Class name must begin with opt82_.(%s)', $params["class_name"]),
                 sprintf('Class name does not exist in config.(%s)', $params["class_name"]),
            ],
        ];

        $validater = new validater($rules, $params, true);
        $this->err_tag = $validater->tags;

        /* validation check fails */
        if ($validater->err['result'] === false) {
            $this->store->log->output_log_arr($validater->logs);
            return false;
        }

        return true;
    }

    /*************************************************************************
     * Method        : delete_option82_setting
     * Description   : Delete option82 setting
     * args          : $class_name
     * return        : true
     *************************************************************************/
    public function delete_option82_setting($class_name) {
        /* delete option82 setting */
        $new_config = $this->conf->delete_option82($class_name, $this->subnet);
        
        /* save new config to session */
        $this->conf->save_conf_to_sess($new_config);

        $success_log = "Option82 setting deleted successfully.";

        /* save log to session history */
        $this->conf->save_hist_to_sess($success_log);

        $this->store->log->log($success_log);
        $this->msg_tag['success'] = _('Option82 setting deleted successfully.');

        return true;
    }


    /*************************************************************************
     * Method        : validate_conditions
     * Description   : check conditions
     * args          : $params- conditions(keyword and ipaddr)
     * return        : true/false
     *************************************************************************/
    public function validate_conditions($params)
    {
        /*  define rules */
        $rules['ipaddr'] = [
            'method' => "exist|ipv4|insubnet4:$this->subnet",
            'msg'    => [
                _(''),
                _('Invalid Pool IP address format.'),
                _('Pool IP address is outside the subnet range.'),
            ],
            'log'    => [
                '',
                'Invalid Pool IP address format.',
                'Pool IP address is outside the subnet range.',
            ],
            'option' => [ 'allowempty' ]
        ];

        /* input store into params */
        $params['store'] = $this->store;

        /* validate */
        $validater = new validater($rules, $params, true);
        $this->pre = $validater->err["keys"];
        $this->pre['keyword'] = $params['keyword'];

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
     * Method        : search_option82
     * Description   : Method for searching option82 setting
     * args          : subnet
     * return        : true or false
     *************************************************************************/
    public function search_option82($conditions) {

        $ret = $this->create_retrieval_data();
        if ($ret === false) {
            return false;
        }

        /* If there are no search criteria, display all data */
        if ($conditions['keyword'] === '' && $conditions['ipaddr'] === '') {
            /* If there are no search criteria, display all data */
            $this->result = $this->retrieval_data;
            $this->_pagination();
            return true;
        }

        /* Search Result Variables */
        $ret_keyword = false;
        $ret_ipaddr = false;

        foreach ($this->retrieval_data as $data) {
            /* When the search condition is only a KEYWORD */
            if ($conditions['keyword'] !== '' && $conditions['ipaddr'] === '') {
                $ret_keyword = $this->part_match_option82($data, $conditions['keyword']);
                if ($ret_keyword === true) {
                    $this->result[] = $data;
                }
            /* When the search condition is IP address only */
            } else if ($conditions['keyword'] === '' && $conditions['ipaddr'] !== '') {
                $ret_ipaddr = $this->check_inpool_ipaddr($data, $conditions['ipaddr']);
                if ($ret_ipaddr === true) {
                    $this->result[] = $data;
                }
            /* When the search condition is both KEYWORD and IP address */
            } else if ($conditions['keyword'] !== '' && $conditions['ipaddr'] !== '') {
                /* If no keywords match, process the next set of data */
                $ret_keyword = $this->part_match_option82($data, $conditions['keyword']);
                if ($ret_keyword === false) {
                    continue;
                }

                $ret_ipaddr = $this->check_inpool_ipaddr($data, $conditions['ipaddr']);
                /* Store only data that also matches the IP address */
                if ($ret_ipaddr === true) {
                    $this->result[] = $data;
                }
            }
        }

        if (empty($this->result)) {
            $err_tag['e_msg'] = _('No result.');
            $err_log = 'No result.';
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);

            return false;
        }
        
        $this->_pagination();
        return true;
    }

    /*************************************************************************
     * Method        : _pagination
     * Description   : Paging process for search results.
     * args          : none
     * return        : none
     *************************************************************************/
    private function _pagination() {
        global $appini;

        $this->total = count_array($this->result);
        /* Keep all data for export */
        $this->export_data = $this->result;

        $this->pageobj = new Pagination('array');
        $this->pageobj->currentpage = get('page', 1);
        $this->pageobj->linknum = $appini['search']['pagelinks'];
        $this->pageobj->dataperpage = $appini['search']['poolmax'];
        $this->pageobj->source = $this->result;
        $this->pageobj->run();


        $this->result = array_slice($this->result, $this->pageobj->datahead, $this->pageobj->dataperpage);
    }

    /*************************************************************************
     * Method        : create_retrieval_data
     * Description   : Methods to create data for search
     * args          : subnet
     * return        : true or false
     *************************************************************************/
     public function create_retrieval_data() {

        $classdata = [];
        /* Obtain data from the client class */
        [$ret, $classdata] = $this->conf->get_client_class('option82');
        if ($ret === false) {
            $err_tag['e_msg'] = _('Client class for option82 does not exist.');
            $err_log = 'Client class for option82 does not exist.';
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            return false;
        }

        /* Obtain subnet information for search target */
        [$ret, $subnetdata] = $this->conf->get_one_subnet($this->subnet);
        if ($ret === false) {
            $this->msg_tag = array_merge($this->msg_tag, $this->conf->err);
            $this->store->log->log($this->conf->err);
            return false;
        }

        if (empty($subnetdata[STR_POOLS])) {
            $err_tag['e_msg'] = _('No pool exists on the subnet.');
            $err_log = 'No pool exists on the subnet.';
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            return false;
        }

        $pooldata = [];
        /* Get only the pool for option82 */
        [$ret, $pooldata] = $this->conf->survey_option82_pools($subnetdata[STR_POOLS], 'include');
        if ($ret === false) {
            $err_tag['e_msg'] = _('No pool of option82 exists in the subnet.');
            $err_log = 'No pool of option82 exists in the subnet.';
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            return false;
        }

        /* Combine class and pool data using name as a key */
        $retrieval_data = [
            'subnet'           => '',
            'class_name'       => '',
            'pool_start'       => '',
            'pool_end'         => '',
            'circuit_id'       => '',
            'circuit_id_hexed' => '',
            'remote_id'        => '',
            'remote_id_hexed'  => '',
            'mac_address'      => '',
            'advanced_setting' => ''
        ];

        $flg_err = false;
        foreach ($classdata as $class) {
            $no_hex_circuit = '';
            $no_hex_remote= '';
            $no_hex_mac = '';
            /* The class name is used as is. */
            $class_name = $class[STR_NAME];
            if (!isset($pooldata[$class_name])) {
                continue;
            }
            $pool = $pooldata[$class_name];

            /* Shaping of pool */
            list($pool_start, $pool_end) = get_kea_pool_v4($pool[STR_POOL]);

            if (preg_match('/^opt82_bas_/', $class_name) === 1) {
                /* Formatting of test values */
                /* get circuit-id */
                [$ret, $circuit_id, $no_hex_circuit] = $this->conf->format_test_value($class[STR_TEST], 'circuit_id');
                if ($ret === false) {
                    $err_tag['e_msg'] = _('Failed to get Circuit-ID.');
                    $err_log = 'Failed to get Circuit-ID.';
                    $flg_err = true;
                    break;
                }

                /* get remote-id */
                [$ret, $remote_id, $no_hex_remote] = $this->conf->format_test_value($class[STR_TEST], 'remote_id');
                if ($ret === false) {
                    $err_tag['e_msg'] = _('Failed to get Remote-ID.');
                    $err_log = 'Failed to get Remote-ID.';
                    $flg_err = true;
                    break;
                }

                /* get mac address */
                [$ret, $mac_address, $no_hex_mac] = $this->conf->format_test_value($class[STR_TEST], 'mac_address');
                if ($ret === false) {
                    $err_tag['e_msg'] = _('Failed to get MAC address.');
                    $err_log = 'Failed to get MAC address.';
                    $flg_err = true;
                    break;
                }

                /* For basic settings, advanced_setting is empty. */
                $advanced_setting = '';
            } else if (preg_match('/^opt82_adv_/', $class_name) === 1) {
                /* For advanced settings, circuit_id, remote_id, and mac_address are empty. */
                $circuit_id = '';
                $remote_id = '';
                $mac_address = '';
                
                /* Use the value of test as it is. */
                $advanced_setting = $class[STR_TEST];
            }

            $retrieval_data = [
                'subnet'              => $this->subnet,
                'class_name'          => $class_name,
                'pool_start'          => $pool_start,
                'pool_end'            => $pool_end,
                'circuit_id'          => $circuit_id,
                'circuit_id_not_hex'  => $no_hex_circuit,
                'remote_id'           => $remote_id,
                'remote_id_not_hex'   => $no_hex_remote,
                'mac_address'         => $mac_address,
                'advanced_setting'    => $advanced_setting
            ];

            $this->retrieval_data[] = $retrieval_data;
        }

        if ($flg_err === true) {
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            return false;
        }

        return true; 
    }

    /*************************************************************************
     * Method        : part_match_option82
     * Description   : Partial match search for option82 setting
     * args          : array
                       condition
     * return        : true or false
     *************************************************************************/
    private function part_match_option82($data, $condition) {
        $flg_match = false;
        if ($data['advanced_setting'] === '') {
            if (strpos($data['circuit_id'], $condition) !== false) {
                $flg_match = true;
            } else if (strpos($data['remote_id'], $condition) !== false) {
                $flg_match = true;
            /* MAC address is not case sensitive */
            } else if (stripos($data['mac_address'], $condition) !== false) {
                $flg_match = true;
            }
        } else {
            if (strpos($data['advanced_setting'], $condition) !== false) {
                $flg_match = true;
            }
        }

        if ($flg_match === true) {
            return true;
        }

        return false;
    }

    /*************************************************************************
     * Method        : check_inpool_ipaddr
     * Description   : Check if the IP address is within the range of the pool
     * args          : array
                       condition
     * return        : true or false
     *************************************************************************/
    private function check_inpool_ipaddr($data, $condition) {
        /* Mold data for comparison */
        $src_addr = ip2long($condition);
        $pool_start = ip2long($data['pool_start']);
        $pool_end = ip2long($data['pool_end']);

        if ($src_addr >= $pool_start && $src_addr <= $pool_end) {
            return true;
        }
        return false;
    }

    /*************************************************************************
     * Method        : export_search_result
     * Description   : Exporting search results
     * args          : None
     * return        : true or false
     *************************************************************************/
    public function export_search_result() {
        global $appini;
        $flg_success = true;
        $tmpdir = APP_ROOT . '/tmp/';
        $filename = 'option82list_' . date("Ymd_His") . '.csv';
        $filepath = $tmpdir . $filename;

        if (is_dir($tmpdir) === false) {
            $ret = mkdir($tmpdir);
            if ($ret === false) {
                $err_tag['e_msg'] = sprintf(_('Failed to create temporary file placement directory.(%s)'), $tmpdir);
                $err_log = sprintf('Failed to create temporary file placement directory.(%s)', $tmpdir);
                $this->msg_tag = array_merge($this->msg_tag, $err_tag);
                $this->store->log->log($err_log);
                return false;
            }
        }

        $file = fopen($filepath, 'w');
        if ($file === false) {
            $err_tag['e_msg'] = sprintf(_('Failed to create CSV file.(%s)'), $filepath);
            $err_log = sprintf('Failed to create CSV file.(%s)', $filepath);
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            return false;
        }
        $ret = fwrite($file, implode(',', array_keys($this->export_data[0])) . "\n");
        if ($ret === false) {
            $err_tag['e_msg'] = sprintf(_('Failed to create CSV file.(%s)'), $filepath);
            $err_log = sprintf('Failed to create CSV file.(%s)', $filepath);
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            fclose($file);
            unlink($filepath);
            return false;
        }
        foreach ($this->export_data as $data) {
            $ret = fwrite($file, implode(',', $data) . "\n");
            if ($ret === false) {
                $err_tag['e_msg'] = sprintf(_('Failed to create CSV file.(%s)'), $filepath);
                $err_log = sprintf('Failed to create CSV file.(%s)', $filepath);
                $this->msg_tag = array_merge($this->msg_tag, $err_tag);
                $this->store->log->log($err_log);
                $flg_success = false;
                break;
            }
        }
        fclose($file);

        if ($flg_success === false) {
            unlink($filepath);
            return false;
        }

        /* export */
        $ret = download_file($filepath, 'text/csv');
        if ($ret === false) {
            $err_tag['e_msg'] = _('Export of search results failed.');
            $err_log = 'Export of search results failed.';
            $this->msg_tag = array_merge($this->msg_tag, $err_tag);
            $this->store->log->log($err_log);
            unlink($filepath);
            return false;
        }

        $this->store->log->log('Search results were successfully exported.');
        unlink($filepath);
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
        if (!empty($this->result)) {
            $this->result = $this->convert_result($this->result);
            $this->store->view->assign('item', $this->result);
        }
 
        $array = array_merge($this->msg_tag, $this->err_tag);
        $this->store->view->assign('subnet', $this->subnet);
        $this->store->view->assign('pre', $this->pre);
        $this->store->view->assign('result', $this->total);
        $this->store->view->assign('paging', $this->pageobj);
        $this->store->view->render("listoption82.tmpl", $array);
    }

    /*************************************************************************
     * Method        : convert_result
     * Description   : Method for displaying the template on the screen
     * args          : array
     * return        : None
     *************************************************************************/
    public function convert_result($result)
    {
        $converted_result = [];

        foreach ($result as $one_result) {
            /* Add the actual notation being set to the resulting array */
            if ($one_result['circuit_id'] !== '') {
                if ($one_result['circuit_id_not_hex'] === 'false') {
                    $one_result['org_circuit_id'] = '0x' . bin2hex($one_result['circuit_id']);
                } else {
                    $one_result['org_circuit_id'] = $one_result['circuit_id'];
                }
            } else {
                $one_result['org_circuit_id'] = '';
            }

            if ($one_result['remote_id'] !== '') {
                if ($one_result['remote_id_not_hex'] === 'false') {
                    $one_result['org_remote_id'] = '0x' . bin2hex($one_result['remote_id']);
                } else {
                    $one_result['org_remote_id'] = $one_result['remote_id'];
                }
            } else {
                $one_result['org_remote_id'] = '';
            }

            /* MAC address only takes a colon */
            if ($one_result['mac_address'] !== '') {
                $one_result['org_mac_address'] = '0x' . remove_colon($one_result['mac_address']);
            } else {
                $one_result['org_mac_address'] = '';
            }

            $converted_result[] = $one_result;
        }

        return $converted_result;
        
    }
}

/*************************************************************************
 *  main
 *************************************************************************/
$objListOption82 = new ListOption82($store);

/* check current config  */
if ($objListOption82->conf->result === false) {
    $objListOption82->display();
    exit(1);
}

/************************************
 * message section
 ************************************/
$msg = get('msg');
if ($msg === 'add_ok') {
    $objListOption82->msg_tag["success"] = _("Option82 setting was successfully added.");
}

/************************************
 * Initial display
 ************************************/
$subnet = get('subnet');
$params = ['subnet' => $subnet];
$ret = $objListOption82->validate_subnet($params);
if ($ret === false) {
    $objListOption82->display();
    exit(1);
}

/************************************
 * delete section
 ************************************/
$delete = get('delete');
if (isset($delete)) {
    /* validate delete data */
    $params = [ 'class_name' => get('class_name') ];
    $ret = $objListOption82->validate_class_name($params);
    if ($ret === true) {
        /* delete option82 setting */
        $objListOption82->delete_option82_setting($params['class_name']);

        /* refesh config */
        $objListOption82->conf->get_config(DHCPV4);
    }
}

/************************************
 * search section
 ************************************/
$export = get('export');
$conditions = [
    'keyword' => get('keyword', ''),
    'ipaddr'  => get('ipaddr', '')
];

$ret = $objListOption82->validate_conditions($conditions);
if ($ret === false) {
    $objListOption82->display();
    exit(1);
}

$ret = $objListOption82->search_option82($conditions);
if ($ret === false) {
    $objListOption82->display();
    exit(1);
}

if (isset($export)) {
    $ret = $objListOption82->export_search_result();
    if ($ret === false) {
        $objListOption82->display();
        exit(1);
    }
    exit(0);
}

$objListOption82->display();
exit(0);
