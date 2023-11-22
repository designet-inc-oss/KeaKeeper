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

/* path to lockfile */
define("LOCK_FILE", "lock/kea-keakeeper.lock");
define("LOCK_LINE", "%s %s %s");
define("RET_LOGIN_OK",  1);
define("RET_LOGIN_NG",  0);
define("RET_LOGIN_ERR", -1);


/*****************************************************************************
* function        : remove_hyphen
* Description     : Remove hyphen from identifier
* args            : $id_hyphen
* return          : $removed_id - identifier removed colon
*****************************************************************************/
function remove_hyphen ($id_hyphen) {
    $removed_id = str_replace("-", "", $id_hyphen);
    return $removed_id;
}

/*****************************************************************************
* function        : remove_both
* Description     : Remove colon from identifier
* args            : $id_colon
* return          : $removed_id - identifier removed colon
*****************************************************************************/
function remove_colon ($id_colon) {
    $removed_id = str_replace(":", "", $id_colon);
    return $removed_id;
}

/*****************************************************************************
* function        : remove_both
* Description     : Remove hyphen anb colon from identifier
* args            : $id
* return          : $removed_id - identifier removed colon and hyphen
*****************************************************************************/
function remove_both ($id) {
    $tmp_id = remove_hyphen($id);
    $removed_id = remove_colon($tmp_id);
    return $removed_id;
}

/*****************************************************************************
* function        : add_colon
* Description     : add colon to identifier
* args            : $id_nocolon
* return          : $added_id - identifier added colon
*****************************************************************************/
function add_colon ($id_nocolon) {
    /* cannot separate by 2 letters, return without process */
    if (strlen($id_nocolon) < 2) {
        return $id_nocolon;
    }

    $added_id = wordwrap($id_nocolon, 2, ':', true);
    return $added_id;
}

/*****************************************************************************
* function        : check_ipv4_in_range
* Description     : check whether ip in range
* args            : $check_ip
*                   $start_ip
*                   $end_ip
* return          : true or false
*****************************************************************************/
function check_ipv4_in_range($ip, $start, $end)
{
    $ip_long = ip2long($ip);
    $start_long = ip2long($start);
    $end_long = ip2long($end);

    if ($ip_long >= $start_long && $ip_long <= $end_long) {
        return true;
    }

    return false;
}

/*****************************************************************************
 * function        : chec_str
 * Description     : check string
 * args            : $val        string will check  
 *                   $allow_str  string allow
 * return          : true or false
 *****************************************************************************/
function chec_str($val, $allow_str)
{
    $len = strlen($val);
    if ($len !== strspn($val, $allow_str)) {
        return false;
    }
    return true;
}

/*****************************************************************************
 * function        : reindex_numeric
 * Description     : re-index array
 * args            : $arr
 * return          : new_arr
 *****************************************************************************/
function reindex_numeric($arr)
{
    $i = 0;
    $new_arr = [];
    foreach ($arr as $key => $val) {
        $new_arr[$i++] = $val;
    }
    return $new_arr;
}

/*****************************************************************************
 * function        : reindex_numeric
 * Description     : re-index array
 * args            : $arr
 * return          : new_arr
 *****************************************************************************/
function convert_str_ascii($str)
{
    $new_str = "";
    $len = strlen($str);
    $idx = 0;
    while ($idx < $len) {
        $tmp_str = substr( $str, $idx, 2);
        $tmp_str = hexdec($tmp_str);
        $tmp_str = chr($tmp_str);
        $new_str = $new_str. $tmp_str;
        $idx = $idx + 2;
    }

    return $new_str;
}

/*****************************************************************************
 * function        : masktobyte_v6
 * Description     :
 *
 * args            : $cidr
 * return          : true or false
 *****************************************************************************/
function masktobyte_v6($mask)
{
    /* Represent mask as 32 digit hexadecimal number */
    /* Set f */
    $binMask = str_repeat('f', $mask / 4);
    /* Set not f */
    switch ($mask % 4) {
        case 1:
            $binMask .= "8";
            break;
        case 2:
            $binMask .= "c";
            break;
        case 3:
            $binMask .= "e";
            break;
    }
    /* Fill in the digits */
    $binMask = str_pad($binMask, 32, '0');
    /* Pack in binary string in hexadecimal format (H *) */
    $binMask = pack("H*", $binMask);

    return $binMask;
}

/*****************************************************************************
 * function        : get_range_ipaddr_v4
 * Description     : get pool from config
 * args            : get range ip address from cidr
 * return          : [min_ip,max_ip]
 *****************************************************************************/
function get_range_ipaddr_v4($cidr)
{
    list($ip, $mask) = explode('/', $cidr);

    /* net mask binary string */
    $mask_bin_str =str_repeat("1", $mask ) . str_repeat("0", 32 - $mask );

    /* inverse mask */
    $inverse_mask_bin_str = str_repeat("0", $mask ). 
                            str_repeat("1",  32 - $mask );

    $ip_long = ip2long($ip);
    $ip_mask_long = bindec($mask_bin_str);
    $inverse_ip_mask_long = bindec($inverse_mask_bin_str);
    $net_work = $ip_long & $ip_mask_long;

    /* ignore network ID(eg: 192.168.1.0) */
    $start = $net_work + 1;

    /* ignore brocast IP(eg: 192.168.1.255) */
    $end = ($net_work | $inverse_ip_mask_long) - 1 ;

    /*
     * input:        192.168.1.65-192.168.1.65
     * pool of kea : 192.168.1.65/32
     */
    if($net_work == ($end + 1)) {
        $start = $net_work;
        $end = $net_work;
    }

    return [long2ip($start), long2ip($end)];
}

/*****************************************************************************
 * function        : get_range_ipaddr_v6
 * Description     : get pool from config
 * args            : get range ip address from cidr
 * return          : [min_ip,max_ip]
 *****************************************************************************/
function get_range_ipaddr_v6($cidr)
{
    list($addr, $mask) = explode('/', $cidr);
    $binPrefix   = masktobyte_v6($mask);
    $db_addr_min = inet_pton($addr);
    $db_addr_max = inet_pton($addr) | ~$binPrefix;

    return [inet_ntop($db_addr_min), inet_ntop($db_addr_max)];
}

/*****************************************************************************
 * function        : get_kea_pool_v4
 * Description     : get pool v4 from config
 *                   There are two formats of pool
 *                    1) 10.1.1.1-10.1.1.10
 *                    2) 192.168.2.9/29
 * args            : $pool_str   
 * return          : min_pool/max_pool
 *****************************************************************************/
function get_kea_pool_v4($pool_str)
{
    /* find the position of the first occurrence of / in pool_str */
    if (strpos($pool_str, '/')) {
        list($min_pool, $max_pool) = get_range_ipaddr_v4($pool_str);
        
    } else {
        list($min_pool, $max_pool) = explode("-", $pool_str);
    }

    return [$min_pool, $max_pool];
}

/*****************************************************************************
 * function        : get_kea_pool_v6
 * Description     : get pool v6 from config
 *                   There are two formats of pool
 *                    1) 10.1.1.1-10.1.1.10
 *                    2) 192.168.2.9/29
 * args            : $pool_str
 * return          : min_pool/max_pool
 *****************************************************************************/
function get_kea_pool_v6($pool_str)
{
    /* find the position of the first occurrence of / in pool_str */
    if (strpos($pool_str, '/')) {
        list($min_pool, $max_pool) = get_range_ipaddr_v6($pool_str);

    } else {
        list($min_pool, $max_pool) = explode("-", $pool_str);
    }

    return [$min_pool, $max_pool];
}

/*****************************************************************************
 * function        : get_kea_pool
 * Description     : get pool v4, v6 from config
 *                   There are two formats of pool
 *                    1) 10.1.1.1-10.1.1.10
 *                    2) 192.168.2.9/29
 * args            : $version 
 * return          : min_pool/max_pool
 *****************************************************************************/
function get_kea_pool($version, $pool_str)
{
    if ($version === STR_DHCP4) {
        list($min_pool, $max_pool) = get_kea_pool_v4($pool_str);
    } else {
        list($min_pool, $max_pool) = get_kea_pool_v6($pool_str);
    }

    return [$min_pool, $max_pool];
}

/*****************************************************************************
 * function        : double_login_check
 * Description     : check double login
 * args            : $login_user logining ID
 *                   $lock_time  lock time
 *                   &$errmsg    error message
 * return          : RET_LOGIN_OK    login allow
 *                   RET_LOGIN_ERR   occur system error
 *                   RET_LOGIN_NG    double login (login deny)
 *****************************************************************************/
function double_login_check($login_user, $lock_time, &$errmsg)
{
    $lock_filepath = APP_ROOT. '/'. LOCK_FILE;
   
    /* if lock file existed */ 
    if (file_exists($lock_filepath)) {
        /* check lock file */
        $ret = check_lockfile($login_user, $lock_filepath, 
                              $lock_time, $errmsg);
        if ($ret !== RET_LOGIN_OK) {
            return $ret;
        }
    }

    /* make lock file */
    $ret = make_lockfile($login_user, $lock_filepath, $errmsg);
    if ($ret === FALSE) {
        return RET_LOGIN_ERR;
    }

    return RET_LOGIN_OK;
}

/*****************************************************************************
 * function        : make_lockfile
 * Description     : create lock file
 * args            : $login_user     logining ID
 *                   $lock_filepath  path to lock file
 *                   &$errmsg    error message
 * return          : TRUE
 *                   FALSE
 *****************************************************************************/
function make_lockfile($login_user, $lock_filepath, &$errmsg)
{
    $data = sprintf(LOCK_LINE, $login_user, $_SERVER["REMOTE_ADDR"], time());

    $fp = fopen($lock_filepath, "w");
    if ($fp === FALSE) {
        $errmsg = "Failed to open file.($lock_filepath)"; 
        return FALSE;
    }

    $ret = fwrite($fp, $data);
    fclose($fp);
    if ($ret === FALSE) {
        $errmsg = "Failed to write to file.($lock_filepath)($data)"; 
        return FALSE;
    }

    return TRUE;
}

/*****************************************************************************
 * function        : check_lockfile
 * Description     : check lock file
 * args            : $login_user     logining ID
 *                   $lock_filepath  path to lock file
 *                   $lock_time      lock time
 *                   &$errmsg        error message
 * return          : RET_LOGIN_OK    login allow
 *                   RET_LOGIN_ERR   occur system error
 *                   RET_LOGIN_NG    double login (login deny)
 *****************************************************************************/
function check_lockfile($login_user, $lock_filepath, $lock_time, &$errmsg)
{
    $filedata = read_lockfile($lock_filepath, $errmsg);
    if ($filedata === FALSE) {
        return RET_LOGIN_ERR;
    }

    /* data of file is invalid*/
    if ($filedata === NULL) {
        return RET_LOGIN_OK;
    }

    $time_diff = time() - $filedata[2];

    /* the lock time is over then destroy old file and create new lock file */
    if ($time_diff > $lock_time) {
        return RET_LOGIN_OK;
    }

    if (($filedata[0] != $login_user) ||
        ($filedata[1] != $_SERVER["REMOTE_ADDR"])) {
        $errmsg = "Double login.($login_user)";
        return RET_LOGIN_NG;
    }

    return RET_LOGIN_OK;
}

/*****************************************************************************
 * function        : read_lockfile
 * Description     : read lock file
 * args            : $lock_filepath  path to lock file
 *                   &$errmsg        error message
 * return          : TRUE/FALSE
 *****************************************************************************/
function read_lockfile($lock_filepath, &$errmsg)
{
    $fp = fopen($lock_filepath, "r");
    if ($fp === FALSE) {
        $errmsg = "Failed to open file.($lock_filepath)"; 
        return FALSE;
    }

    $buffer = fgets($fp);
    fclose($fp);
    if ($buffer === FALSE) {
        $errmsg = "Failed to read file.($lock_filepath)"; 
        return FALSE;
    }

    $filedata = explode(" ", $buffer, 3);
    if ($filedata === FALSE) {
        $errmsg = "Failed to explode data.($lock_filepath)($buffer)"; 
        return NULL;
    } elseif (!isset($filedata[2])) {
        $errmsg = "File data is invalid.($lock_filepath)($buffer)"; 
        return NULL;
    }

    return $filedata;
}

/*****************************************************************************
 * function        : delete_lockfile
 * Description     : read lock file
 * args            : $login_user     logining ID
 *                   &$errmsg        error message
 * return          : 1     normal
 *                   0     lock file do not exist
 *                   2     can not read lock file
 *                   3     invalid login_id or remote ip address
 *                   -1    can not delele lock file               
 *****************************************************************************/
function delete_lockfile($login_user, &$errmsg)
{
    $lock_filepath = APP_ROOT. '/'. LOCK_FILE;
    $errmsg = NULL;

    /* if lock file do not exist */
    if (file_exists($lock_filepath) === FALSE) {
        return 0;
    } 

    /* read lock file */
    $filedata = read_lockfile($lock_filepath, $errmsg);
    if ($filedata === FALSE) {
        return 2;
    }

    /* check login_user and remote ip address*/
    if (($filedata[0] !== $login_user) || (
         $filedata[1] !== $_SERVER["REMOTE_ADDR"])) {
        $errmsg = "Invalid loginser.($login_user)". "(". $_SERVER["REMOTE_ADDR"]. ")";
        return 3;
    }

    /* delete lock file */
    $ret = unlink($lock_filepath);
    if ($ret === FALSE) {
        $errmsg = "Failed to delete lockfile.($login_user)($lock_filepath)";
        /* even if it can not be deleted lock file, transit to login screen */
        return -1;
    }

    return 1;    
}

?>
