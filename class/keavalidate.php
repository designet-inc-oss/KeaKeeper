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


/*****************************************************************************
* Class          : v6type
* Description    : Investigate whether subnet is in keaconf(DHCPv4)
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class v6typeValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if ($val == 'ip' || $val == 'prefix') {
            return true;
        }
        return false;
    }
}

/*****************************************************************************
* Class          : subnetinconf4
* Description    : Investigate whether subnet is in keaconf(DHCPv4)
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnetinconf4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $conf = new KeaConf(DHCPV4);
        $ret = $conf->check_subnet4($val);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : subnetinconf6
* Description    : Investigate whether subnet is in keaconf(DHCPv6)
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnetinconf6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $conf = new KeaConf(DHCPV6);
        $ret = $conf->check_subnet6($val);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : checkexistipv4Validate
* Description    : Investigate whether the IP address exists in the database
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class checkexistipv4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $cond = ["ipv4_address" => $val];

        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select('COUNT(ipv4_address)');
        $dbutil->from('hosts');
        $dbutil->where("ipv4_address = INET_ATON('" . $val . "')");

        /* fetch COUNT query's result */
        $ret = $dbutil->get();

        /* greater than 0, already exists */
        if (max($ret[0]) > 0) {
            return true;
        }
        return false;
    }
}

/*****************************************************************************
* Class          : checkexistipv6Validate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class checkexistipv6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select($this->allval['store']->db->inet6_ntoa('address') . 'AS address,prefix_len,type');
        $dbutil->from('ipv6_reservations');

        /* fetch COUNT query's result */
        $ret = $dbutil->get();

        foreach ($ret as $key => $value) {
            if ($value['type'] == 0) {
                /* compare ipv6 address from db */
                $db_addr = inet_pton($value['address']);
                $post_addr = inet_pton($val);

                /* Compare addresses */
                if ($db_addr == $post_addr) {
                    return true;
                }

            } else if ($value['type'] == 2) {
                /* compare range of ipv6 address from db */
                $db_addr = $value['address'];
                $post_prefix = $value['prefix_len'];

                $binPrefix = $this->masktobyte($post_prefix);
                $db_addr_min = inet_pton($db_addr);
                $db_addr_max = inet_pton($db_addr) | ~$binPrefix;
                $post_addr = inet_pton($val);

                /* Compare addresses */
                if ($post_addr >= $db_addr_min && $post_addr <= $db_addr_max) {
                    return true;
                }
            }
        }
        return false;
    }
}

/*****************************************************************************
* Class          : checkexistprefreserveValidate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class checkexistprefreserveValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select($this->allval['store']->db->inet6_ntoa('address') . 'AS address, prefix_len, type');
        $dbutil->from('ipv6_reservations');

        /* fetch COUNT query's result */
        $ret = $dbutil->get();
        foreach ($ret as $key => $value) {
            if ($value['type'] == 0) {
                /* compare ipv6 address from db */
                $db_addr = inet_pton($value['address']);

                /* range of ipv6 address from post */
                $binPrefix = $this->masktobyte($option[0]);
                $post_addr_max = inet_pton($val) | ~$binPrefix;
                $post_addr_min = inet_pton($val);

                /* Compare addresses */
                if ($db_addr >= $post_addr_min && $db_addr <= $post_addr_max) {
                    return true;
                }

            } else if ($value['type'] == 2) {
                /* compare range of ipv6 address from db */
                $db_addr = $value['address'];
                $post_prefix = $value['prefix_len'];

                $binPrefix = $this->masktobyte($post_prefix);
                $db_addr_min = inet_pton($db_addr);
                $db_addr_max = inet_pton($db_addr) | ~$binPrefix;

                /* range of ipv6 address from post */
                $binPrefix = $this->masktobyte($option[0]);
                $post_addr_max = inet_pton($val) | ~$binPrefix;
                $post_addr_min = inet_pton($val);

                /* Compare addresses */
                if ($post_addr_min <= $db_addr_max && $db_addr_min <= $post_addr_max) {
                    return true;
                }
            }
        }
        return false;
    }
}

/*****************************************************************************
* Class          : equaltosubnetValidate
* Description    : Validation class that ip address in subnet pool
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class equaltosubnetValidate extends AbstractValidate {
    public function run($val, $option = array())
    {

        $subnet = implode(":", $option);
        list($addr, $mask) = explode('/', $subnet);

        if ($val == $mask) {
            return true;
        }
        return false;
    }
}

/*****************************************************************************
* Class          : outpoolValidate
* Description    : Validation class that ip address in subnet pool
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class outpoolValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $subnet = $option[0];

        $conf = new KeaConf(DHCPV4);
        [$ret, $pools] = $conf->get_pools($subnet);

        /* Returns true if there is no pool */
        if (is_array($pools) === false) {
            return true;
        }

        foreach ($pools as $pool) {
            list($min, $max) = explode('-', $pool['pool']);

            $ip_long  = ip2long($val);
            $min_long = ip2long($min);
            $max_long = ip2long($max);

            if ($ip_long >= $min_long && $ip_long <= $max_long) {
                return false;
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : outpool_prefreserveValidate
* Description    : Validation class that ip address in subnet pool
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class outpool_prefreserveValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make str for subnet and prefix */
        $prefix = array_pop($option);
        $subnet = implode(":", $option);

        $conf = new KeaConf(DHCPV6);
        [$ret, $pools] = $conf->get_pools6($subnet);

        if ($ret === false) {
            return false;
        }

        /* Returns true if there is no pool */
        if (is_array($pools) === false) {
            return true;
        }

        foreach ($pools as $pool) {
            list($min, $max) = explode('-', $pool['pool']);

            /* Convert hexadecimal number to binary number */
            $val_addr = inet_pton($val);

            $ret = filter_var($min, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($ret === false) {
                return false;
            }
            $ret = filter_var($max, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($ret === false) {
                return false;
            }

            $min      = inet_pton($min);
            $max      = inet_pton($max);

            /* Convert mask value to bytes */
            $binPrefix = $this->masktobyte($prefix);

            /* Convert IPv6 address to byte and mask it */
            $val_max = inet_pton($val) | ~$binPrefix;

            /* Compare addresses */
            if ($val_addr <= $max && $min <= $val_max) {
                return false;
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : outpool6Validate
* Description    : Validation class that ip address in subnet pool
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class outpool6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $conf = new KeaConf(DHCPV6);
        $subnet = implode(":", $option);
        [$ret, $pools] = $conf->get_pools6($subnet);
        if ($ret === false) {
            return false;
        }

        /* Convert hexadecimal number to binary number */
        $val = inet_pton($val);

        /* Returns true if there is no pool */
        if (is_array($pools) === false) {
            return true;
        }

        foreach ($pools as $pool) {

            list($min, $max) = explode('-', $pool['pool']);

            /* Check the format of the pool */
            $ret = filter_var($min, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($ret === false) {
                return false;
            }
            $ret = filter_var($max, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($ret === false) {
                return false;
            }

            $min = inet_pton($min);
            $max = inet_pton($max);

            /* Compare addresses */
            if ($val >= $min && $val <= $max) {
                return false;
            }
        }
        return true;
    }
}

/*************************************************************************
* Class          : ipv6_prefreserveValidate
* Description    : Validation class that ipv6 address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class ipv6_prefreserveValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($ret === false) {
            return false;
        }

        $prefix = $option[0];
        if ($prefix === NULL) {
            return false;
        }

        /* Convert mask value to bytes */
        $binPrefix = $this->masktobyte($prefix);

        /* Mask by applying logical AND */
        $val_orig = inet_pton($val);
        $val_mask = inet_pton($val) & $binPrefix;

        /* For example, when the prefix is 112,
          an error occurs if the end does not end with:0000 */
        if ($val_orig !== $val_mask) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : insubnet_prefreserveValidate
* Description    : Validation class that ip address in subnet
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class insubnet_prefreserveValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make str for subnet and prefix */
        $prefix = array_pop($option);
        $subnet = implode(":", $option);

        /* Separate into addresses and netmasks */
        list($net, $mask) = explode("/", $subnet);

        /* When the value of the subnet mask is larger than the prefix */
        if ($mask > $prefix) {
            return false;
        }

        /* Convert mask value to bytes */
        $binMask = $this->masktobyte($mask);

        /* Mask by applying logical AND */
        $maskNet = inet_pton($net) & $binMask; // Mask by applying logical AND
        /* Convert IPv6 address to byte and mask it */
        $maskipv6 = inet_pton($val) & $binMask;

        /* Compare the masked IP part with the masked IPv6 entry */
        /* Since it is out of range unless it is the same, an error */
        if ($maskNet != $maskipv6) {
            return FALSE;
        }
        return TRUE;
    }
}

/*****************************************************************************
* Class          : ipaddrs4Validate
* Description    : Validation class that servers
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class ipaddrs4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (strlen($val) > 256) {
            return false;
        }

        $separated = [];
        if (strpos($val, ',')) {
            $separated = explode(',', $val);
        } else {
            $separated[] = $val;
        }

	$ipv4_validater = new ipv4Validate();
        foreach ($separated as $host) {
            $ipaddr = $ipv4_validater->run($host);

            if ($ipaddr === false) {
                return false;
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : serversValidate
* Description    : Validation class that servers
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class serversValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (strlen($val) > 256) {
            return false;
        }

        $separated = [];
        if (strpos($val, ',')) {
            $separated = explode(',', $val);
        } else {
            $separated[] = $val;
        }

	$ipv4_validater = new ipv4Validate();
	$domain_validater = new domainValidate();
        foreach ($separated as $host) {
            $ipaddr = $ipv4_validater->run($host);
            $host   = $domain_validater->run($host);

            if ($ipaddr === false && $host === false) {
                return false;
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : ipaddrs6Validate
* Description    : Validation class that servers
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class ipaddrs6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (strlen($val) > 256) {
            return false;
        }

        $separated = [];
        if (strpos($val, ',')) {
            $separated = explode(',', $val);
        } else {
            $separated[] = $val;
        }

	$ipv6_validater = new ipv6Validate();
        foreach ($separated as $host) {
            $ipaddr = $ipv6_validater->run($host);

            if ($ipaddr === false) {
                return false;
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : servers6Validate
* Description    : Validation class that servers
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class servers6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (strlen($val) > 256) {
            return false;
        }

        $separated = [];
        if (strpos($val, ',')) {
            $separated = explode(',', $val);
        } else {
            $separated[] = $val;
        }

	$ipv6_validater = new ipv6Validate();
	$domain_validater = new domainValidate();
        foreach ($separated as $host) {
            $ipaddr = $ipv6_validater->run($host);
            $host   = $domain_validater->run($host);

            if ($ipaddr === false && $host === false) {
                return false;
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicateValidate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicateValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (count_array($option) > 1) {
            $val = $option[1]($val);
        }

        /* make query for check duplicate */
        if (count_array($option) > 2) {
            $cond = [$option[0] => $val, 'dhcp_identifier_type' => $option[2]];
        } else {
            $cond = [$option[0] => $val];
        }

        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select('COUNT(' . $option[0] . ')');
        $dbutil->from('hosts');
        $dbutil->where($cond);

        /* fetch COUNT query's result */
        $ret = $dbutil->get();

        /* greater than 0, already exists */
        if (max($ret[0]) > 0) {
            return false;
        }

        /* Inspect for the same MAC address in the configuration file */
        return true;
    }
}

/*****************************************************************************
* Class          : duplicate6Validate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select($this->allval['store']->db->inet6_ntoa('address') . ' AS address,prefix_len,type');
        $dbutil->from('ipv6_reservations');

        /* fetch COUNT query's result */
        $ret = $dbutil->get();

        foreach ($ret as $key => $value) {
            if ($value['type'] == 0) {
                /* compare ipv6 address from db */
                $db_addr = inet_pton($value['address']);
                $post_addr = inet_pton($val);

                /* Compare addresses */
                if ($db_addr == $post_addr) {
                    return false;
                }

            } else if ($value['type'] == 2) {
                /* compare range of ipv6 address from db */
                $db_addr = $value['address'];
                $post_prefix = $value['prefix_len'];

                $binPrefix = $this->masktobyte($post_prefix);
                $db_addr_min = inet_pton($db_addr);
                $db_addr_max = inet_pton($db_addr) | ~$binPrefix;
                $post_addr = inet_pton($val);

                /* Compare addresses */
                if ($post_addr >= $db_addr_min && $post_addr <= $db_addr_max) {
                    return false;
                }
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicate_prefreserveValidate
* Description    : Validation class that check duplication
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate_prefreserveValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select($this->allval['store']->db->inet6_ntoa('address') . ' AS address,prefix_len,type');
        $dbutil->from('ipv6_reservations');

        /* fetch COUNT query's result */
        $ret = $dbutil->get();
        foreach ($ret as $key => $value) {
            if ($value['type'] == 0) {
                /* compare ipv6 address from db */
                $db_addr = inet_pton($value['address']);

                /* range of ipv6 address from post */
                $binPrefix = $this->masktobyte($option[0]);
                $post_addr_max = inet_pton($val) | ~$binPrefix;
                $post_addr_min = inet_pton($val);

                /* Compare addresses */
                if ($db_addr >= $post_addr_min && $db_addr <= $post_addr_max) {
                    return false;
                }

            } else if ($value['type'] == 2) {
                /* compare range of ipv6 address from db */
                $db_addr = $value['address'];
                $post_prefix = $value['prefix_len'];

                $binPrefix = $this->masktobyte($post_prefix);
                $db_addr_min = inet_pton($db_addr);
                $db_addr_max = inet_pton($db_addr) | ~$binPrefix;

                /* range of ipv6 address from post */
                $binPrefix = $this->masktobyte($option[0]);
                $post_addr_max = inet_pton($val) | ~$binPrefix;
                $post_addr_min = inet_pton($val);

                /* Compare addresses */
                if ($post_addr_min <= $db_addr_max && $db_addr_min <= $post_addr_max) {
                    return false;
                }
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : subnet4formatValidate
* Description    : check format of subnet4
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnet4formatValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $arr_item = explode('/', $val);
        if (count_array($arr_item) !=  2) {
            return false;
        }

        /* check ipaddress */
        $ret = filter_var($arr_item[0], FILTER_VALIDATE_IP);
        if ($ret === false) {
            return false;
        }
   
        /* check netmask */
        if (!chec_str($arr_item[1], "0123456789")) {
            return false;
        }

        if ($arr_item[1] < 1 || $arr_item[1] > 32) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : subnet6formatValidate
* Description    : check format of subnet6
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnet6formatValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $arr_item = explode('/', $val);
        if (count_array($arr_item) !=  2) {
            return false;
        }

        /* check ipaddress */
        $ret = filter_var($arr_item[0], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($ret === false) {
            return false;
        }

        /* check netmask */
        if (!chec_str($arr_item[1], "0123456789")) {
            return false;
        }

        if ($arr_item[1] < 1 || $arr_item[1] > 128) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
 * Class          : subnet4existValidate
 * Description    : check subnet4 overlap with other subnet in config
 * args           : $val
 *                : $options - method options
 * return         : true or false
 *****************************************************************************/
class subnet4existValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* create config */
        $conf = new KeaConf(DHCPV4);

        if (isset($option[0]) && ($option[0] === 'exist_true')) {
            $exist_true = true;
        }

        /* get subnet part only */
        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp4);

        foreach ($conf_all_subnet as $conf_subnet) {
            /* loop all subnet */
            foreach ($conf_subnet as $one_subnet) {

                /* if existed subnet in config */
                if (isset($one_subnet[STR_SUBNET])) {
                    /* if subnet exist*/
                    if ($val === $one_subnet[STR_SUBNET]) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

/*****************************************************************************
 * Class          : subnet6existValidate
 * Description    : check subnet4 overlap with other subnet in config
 * args           : $val
 *                : $options - method options
 * return         : true or false
 *****************************************************************************/
class subnet6existValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* create config */
        $conf = new KeaConf(DHCPV6);

        if (isset($option[0]) && ($option[0] === 'exist_true')) {
            $exist_true = true;
        }

        /* get subnet part only */
        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);

        foreach ($conf_all_subnet as $conf_subnet) {
            /* loop all subnet in config */
            foreach ($conf_subnet as $one_subnet) {
                /* if existed subnet */
                if (isset($one_subnet[STR_SUBNET])) {
                    if ($val === $one_subnet[STR_SUBNET]) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

/*****************************************************************************
* Class          : subnetoverldap4Validate
* Description    : check subnet4 overlap with other subnet in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnetoverldap4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* create config */
        $conf = new KeaConf(DHCPV4);

        if (isset($option[0]) && ($option[0] === 'exist_true')) {
            $exist_true = true;
        }

        /* get subnet part only */
        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp4);

        /* get range ip of this subnet */
        list($val_min_str, $val_max_str)  = get_range_ipaddr_v4($val);
        $val_min_long = ip2long($val_min_str);
        $val_max_long = ip2long($val_max_str);

        foreach ($conf_all_subnet as $shnet => $conf_subnet) {
            /* loop in subnet config */
            foreach ($conf_subnet as $one_subnet) {

                /* if existed subnet */
                if (isset($one_subnet[STR_SUBNET])) {
 
                    /* get range ip of this subnet */
                    list($conf_min_str, $conf_max_str) = 
                          get_range_ipaddr_v4($one_subnet[STR_SUBNET]);
    
                    $conf_min_long = ip2long($conf_min_str);
                    $conf_max_long = ip2long($conf_max_str);

                    /* subnet overlap with other subnet */
                    if ((($val_min_long >= $conf_min_long) &&
                         ($conf_max_long <= $conf_max_str)) ||
                        (($val_max_long >= $conf_min_long) &&
                         ($val_max_long <= $conf_max_long))) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}

/*****************************************************************************
* Class          : subnetoverldap6Validate
* Description    : check subnet6 overlap with other subnet in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnetoverldap6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* create config */
        $conf = new KeaConf(DHCPV6);

        /* get subnet part only */
        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);

        /* calculate range of subnet want checkt */
        list($in_addr, $in_postPrefix) = explode('/', $val);
        $in_binPrefix = $this->masktobyte($in_postPrefix);
        $in_ip_min = inet_pton($in_addr);
        $in_ip_max = inet_pton($in_addr) | ~$in_binPrefix;

        foreach ($conf_all_subnet as $shnet => $conf_subnet) {

            /* loop all subnet in config */
            foreach ($conf_subnet as $one_subnet) {

                /* calculate range of subnet in config */
                list($sub_addr, $sub_postPrefix) = explode('/', $one_subnet[STR_SUBNET]);
                $sub_binPrefix = $this->masktobyte($sub_postPrefix);
                $sub_addr_min = inet_pton($sub_addr);
                $sub_addr_max = inet_pton($sub_addr) | ~$sub_binPrefix;
    
                /* if do not overlap then continue */
                if ((($in_ip_min >= $sub_addr_min) && ($in_ip_min <= $sub_addr_max)) ||
                     (($in_ip_max >= $sub_addr_min) && ($in_ip_max <= $sub_addr_max))) {
                    return false;
                }
            }
        }

        return true;
    }
}

/*****************************************************************************
* Class          : ipv4overlapValidate
* Description    : check ipv4 overlap with pools in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class ipv4overlapValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* current pool */
        $editing_pool = null;
        if (isset($option[0])) {
            $editing_pool = $option[0];
        }

        $val_long = ip2long($val);

        /* create config */
        $conf = new KeaConf(DHCPV4);

        /* get subnet part only */
        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp4);;

        /* loop all subnet in config */
        foreach ($conf_all_subnet as $shname => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {

                /* if subnet have pools */
                if (isset($one_subnet[STR_POOLS])) {
                    /* loop all pools in the subnet */
                    foreach ($one_subnet[STR_POOLS] as $one_pool) {
                        if (isset($one_pool[STR_POOL])) {

                            /* get pool of config */ 
                            list($conf_pool_min, $conf_pool_max) =
                                  get_kea_pool_v4($one_pool[STR_POOL]);

                            if (($editing_pool === $conf_pool_min) || 
                                ($editing_pool === $conf_pool_max)) {
                                continue;
                            }

                            $conf_min_long = ip2long($conf_pool_min);
                            $conf_max_long = ip2long($conf_pool_max);

                             /* check new pool */
                            if (($val_long >= $conf_min_long) &&
                                  ($val_long <= $conf_max_long)) {
                                return false;
                            }
                        }
                    }
                }
            }
        }

        return true;
    }
}

/*****************************************************************************
 * Class          : ipv6overlapValidate
 * Description    : check ipv6 overlap with other pools in config
 * args           : $val
 *                : $options - method options
 * return         : true or false
 *****************************************************************************/
class ipv6overlapValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $editing_pool = null;
        if (isset($option[0])) {
            $editing_pool = implode(":", $option);
            if ($editing_pool === false) {
                return false;
            }
        }

        $val = inet_pton($val);

        /* create config */
        $conf = new KeaConf(DHCPV6);

        /* get subnet part only */
        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);;

        /* loop all subnets in config */
        foreach ($conf_all_subnet as $shname => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {
                /* if subnet have pool  */
                if (isset($one_subnet[STR_POOLS])) {
                    foreach ($one_subnet[STR_POOLS] as $one_pool) {
                        if (isset($one_pool[STR_POOL])) {

                            list($conf_pool_min, $conf_pool_max) =
                                  get_kea_pool_v6($one_pool[STR_POOL]);

                            /* if pool is editting pool then next */
                            if (($editing_pool === $conf_pool_min) || 
                                 ($editing_pool === $conf_pool_max)) {
                                continue;
                            }

                            $min = inet_pton($conf_pool_min);
                            $max = inet_pton($conf_pool_max);
                            /* Compare addresses */
                            if ($val >= $min && $val <= $max) {
                                return false;
                            }
                        }
                    }
                }
            }
        }

        return true;
    }
}

/*****************************************************************************
* Class          : shared4existValidate
* Description    : check shared4 overlap with other shared in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class shared4existValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $exist_flg = false;
        $exist_true = false;

        /* create config */
        $conf = new KeaConf(DHCPV4);

        /* no change shared_name */
        if (isset($option[0]) && isset($option[1]) &&
                                 ($option[0] === $option[1])) {
            return true;
        }

        /* exist is true */
        if (isset($option[0]) && ($option[0] === 'exist_true')) {
            $exist_true = true;
        }

        /* get shared part only */
        $conf_shared = $conf->get_shared_part();


        /* loop all shared in config */
        foreach ($conf_shared as $one_shared) {

            /* if existed shared */
            if (isset($one_shared[STR_NAME])) {
 
                /* shared-network overlap with other shared-network */
                if ($val === $one_shared[STR_NAME]) {
                    $exist_flg = true;
                    break;
                }
            }
        }

        /* exist is true  */
        if ($exist_true) {
            return $exist_flg;

        /* exist is false  */
        } else {
            return !$exist_flg;
        }
    }
}
/*****************************************************************************
* Class          : shared6existValidate
* Description    : check shared6 overlap with other shared in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class shared6existValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $exist_flg = false;
        $exist_true = false;

        /* create config */
        $conf = new KeaConf(DHCPV6);

        /* no change shared_name */
        if (isset($option[0]) && isset($option[1]) && 
                                 ($option[0] === $option[1])) {
            return true;
        }

        /* exist is true */
        if (isset($option[0]) && ($option[0] === 'exist_true')) {
            $exist_true = true;
        }

        /* get shared part only */
        $conf_shared = $conf->get_shared_part();


        /* loop all shared in config */
        foreach ($conf_shared as $one_shared) {

            /* if existed shared */
            if (isset($one_shared[STR_NAME])) {

                /* shared-network overlap with other shared-network */
                if ($val === $one_shared[STR_NAME]) {
                    $exist_flg = true;
                    break;
                }
            }
        }

        /* exist is true  */
        if ($exist_true) {
            return $exist_flg;

        /* exist is false  */
        } else {
            return !$exist_flg;
        }
    }
}

/*****************************************************************************
* Class          : interfaceValidate
* Description    : check interface in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class interfaceValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        /* create config */
        $conf = new Keaconf(DHCPV6);
        [$ret, $interfaces] = $conf->get_interfaces();
        if ($ret === false) {
            return false;
        }

        if (!in_array($val, $interfaces)) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : interfaceidValidate
* Description    : check interfaceid format
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class interfaceidValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        $ret = preg_match('/[^a-zA-Z0-9!#\$%&\'\(\)\*\+,\-\.\/:;<=>\?@\[\]\^_`\{\|\}~]/', $val);
        if ($ret === 1 || $ret === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicateifidValidate
* Description    : check only exist interface or interfaceid
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicateifidValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if (!empty($val) && !empty($option[0] )) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : existinterfaceValidate
* Description    : check exist interface in shared-subnets
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class existinterfaceValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if(empty($val)) {
            return true;
        }
        $this->errmsg = [];
        $this->errlog = [];
        $allow_add = true;
        $conf = new KeaConf(DHCPV6);
        foreach ($val as $add_subnet) {
            foreach ($conf->all[STR_DHCP6][STR_SUBNET6] as $new_conf ) {
                if ($new_conf[STR_SUBNET] === $add_subnet) {
                    if (!empty($new_conf[STR_INTERFACE])) {
                        $this->errmsg[] = sprintf(_('Subnet has Interface setting.(%s)'), $add_subnet); 
                        $this->errlog[] = sprintf('Subnet has Interface setting.(%s)', $add_subnet);
                        $allow_add = false;
                        break;
                    }
                }
            }
        }
        if ($allow_add === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : existinterfaceidValidate
* Description    : check exist interface-id in shared-subnets
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class existinterfaceidValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if(empty($val)) {
            return true;
        }

        $this->errmsg = [];
        $this->errlog = [];
        $allow_add = true;
        $conf = new KeaConf(DHCPV6);
        foreach ($val as $add_subnet) {
            foreach ($conf->all[STR_DHCP6][STR_SUBNET6] as $new_conf ) {
                if ($new_conf[STR_SUBNET] === $add_subnet) {
                    if (!empty($new_conf[STR_INTERFACEID])) {
                        $this->errmsg[] = sprintf(_('Subnet has Interface-ID setting.(%s)'), $add_subnet);
                        $this->errlog[] = sprintf('Subnet has Interface0ID setting.(%s)', $add_subnet);
                        $allow_add = false;
                        break;
                    }
                }
            }
        }
        if ($allow_add === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : existrelayValidate
* Description    : check exist interface in shared-subnets
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class existrelayValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if(empty($val)) {
            return true;
        }
        $this->errmsg = [];
        $this->errlog = [];
        $allow_add = true;
        $conf = new KeaConf(DHCPV6);
        foreach ($val as $add_subnet) {
            foreach ($conf->all[STR_DHCP6][STR_SUBNET6] as $new_conf ) {
                if ($new_conf[STR_SUBNET] === $add_subnet) {
                    if (!empty($new_conf[STR_RELAY][STR_AGENT])) {
                        $this->errmsg[] = sprintf(_('Subnet has RelayAgent setting.(%s)'), $add_subnet);
                        $this->errlog[] = sprintf('Subnet has RelayAgent setting.(%s)', $add_subnet);
                        $allow_add = false;
                        break;
                    }
                }
            }
        }
        if ($allow_add === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : existrelayValidate
* Description    : check exist interface in shared-subnets
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class belongshared6Validate extends AbstractValidate {
    function run ($val, $option = array()) {
        $errmsg = "";
        $errlog = "";
        $conf = new KeaConf(DHCPV6);
    
        /* check subnet belong to subnet part or shared-network part */
        $ret = $conf->check_subnet_belongto($val, $pos_subnet, $pos_shnet);

        /* subnet do not exist in config */
        if ($ret === RET_NOTFOUND) {
            $this->errmsg = sprintf(_("Subnet does not exist(%s)."), $val);
            $this->errlog = sprintf('Subnet does not exist(%s).', $val);
            return false;
        }

        /* subnet in shared-networks */
        if ($ret === RET_SHNET) {
            $this->errmsg = sprintf(_("Subnet belongs to a shared network.(%s)"), $val);
            $this->errlog = sprintf('Subnet belongs to a shared network.(%s)', $val);
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : existprefixValidate
* Description    : check exist prefix in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class existprefixValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        $this->errmsg = "";
        $this->errlog = "";
        $conf = new KeaConf(DHCPV6);

        $flg_found = false;

        /* Undo Temporary Replace */
        $subnet = str_replace(';', ':', $option[0]);

        /* get subnet part only */
        [$ret, $subnetdata] = $conf->get_one_subnet($subnet);

        if ($ret === false) {
            return false;
        }
        if (empty($subnetdata['pd-pools'])) {
            $this->errmsg = _('Prefix delegation setting does not exist.');
            $this->errlog = 'Prefix delegation setting does not exist.';
            return false;
        }

        foreach ($subnetdata['pd-pools'] as $value) {
            if ($value['prefix'] === $val) {
                $flg_found = true;
            }
        }

        /* deletion target do not exist in config */
        if (!$flg_found) {
            $this->errmsg = sprintf(_('Prefix delegation target does not exist in config(%s).'), $val);
            $this->errlog = sprintf("Prefix delegation target does not exist in config(%s).", $val);
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : check_other_subnetValidate
* Description    : check exist prefix in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class check_other_subnetValidate extends AbstractValidate {
    function run ($val, $option = array()) {

        $flg_found = false;
        $conf = new KeaConf(DHCPV6);

        /* Undo Temporary Replace */
        $belong_subnet = str_replace(';', ':', $option[0]);

        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);
        foreach ($conf_all_subnet as $shname => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {
                /* Allow overlap with the subnet to which you belong. */
                if ($one_subnet['subnet'] === $belong_subnet) {
                    continue;
                }
                $subnet = explode('/', $one_subnet['subnet'])[0];
                if ($val === $subnet) {
                    $flg_found = true;
                }
            }
        }

        if ($flg_found === true) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : check_other_pd_poolsValidate
* Description    : check exist pd-pools in config
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class check_other_pd_poolsValidate extends AbstractValidate {
    function run ($val, $option = array()) {

        $flg_found = false;
        $conf = new KeaConf(DHCPV6);

        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);
        foreach ($conf_all_subnet as $shname => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {
                foreach ($one_subnet['pd-pools'] as $pd_pools) {
                    if ($pd_pools['prefix'] === $val) {
                        $flg_found = true;
                    }
                }
            }
        }

        if ($flg_found === true) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : comparisonValidate
* Description    : compare val > option
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class comparisonValidate extends AbstractValidate {
    function run ($val, $option = array()) {

        if ($val < $option[0]) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicate_poolsValidate
* Description    : Check for duplicate pd-pools and pools
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate_poolsValidate extends AbstractValidate {
    function run ($val, $option = array()) {

        /* get pools */
        $all_pools = [];
        $conf = new KeaConf(DHCPV6);

        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);
        foreach ($conf_all_subnet as $shname => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {
                foreach ($one_subnet['pools'] as $pools) {
                    $all_pools[] = $pools['pool'];
                }
            }
        }

        if (empty($all_pools)) {
            return true;
        }

        /* Compare prefix/prefix-len with existing pools */
        foreach ($all_pools as $pool) {
            list($min, $max) = explode('-', $pool);

            $binPrefix = $this->masktobyte($option[0]);
            $pool_min = inet_pton($min);
            $pool_max = inet_pton($max);
            $prefix_min = inet_pton($val);
            $prefix_max = inet_pton($val)  | ~$binPrefix;

            /* Compare addresses */
            if ($prefix_min <= $pool_max && $prefix_max >= $pool_min) {
                return false;
            }
        }

        return true;
    }
}

/*****************************************************************************
* Class          : duplicate_pd_poolsValidate
* Description    : Check pd-pools range for duplicates
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate_pd_poolsValidate extends AbstractValidate {
    function run ($val, $option = array()) {

        $flg_exist = false;

        /* get pd-pools */
        $all_pd_pools = [];
        $conf = new KeaConf(DHCPV6);

        $conf_all_subnet = $conf->mk_arr_all_subnet($conf->dhcp6);
        foreach ($conf_all_subnet as $shname => $conf_subnet) {
            foreach ($conf_subnet as $one_subnet) {
                foreach ($one_subnet['pd-pools'] as $pd_pools) {
                    $all_pd_pools[] = [$pd_pools['prefix'], $pd_pools['prefix-len']];
                }
            }
        }

        if (empty($all_pd_pools)) {
            return true;
        }

        /* Compare prefix/prefix-len with existing pools */
        foreach ($all_pd_pools as $pd_pool) {
            list($prefix, $length) = $pd_pool;

            /* Check if it is within the scope of existing pd-pools */
            $exist_binprefix = $this->masktobyte($length);
            $exist_min = inet_pton($prefix);
            $exist_max = $exist_min | ~$exist_binprefix;

            $add_binprefix = $this->masktobyte($option[0]);
            $add_min = inet_pton($val);
            $add_max = $add_min | ~$add_binprefix;

            /* Compare addresses */
            /* Within existing pd-pools */
            if ($add_min >= $exist_min && $add_min <= $exist_max) {
                $flg_exist = true;
                break;
            }
            if ($add_max >= $exist_min && $add_max <= $exist_max) {
                $flg_exist = true;
                break;
            }

            /* Include existing pd-pools */
            if ($exist_min >= $add_min && $exist_min <= $add_max) {
                $flg_exist = true;
                break;
            }

            if ($exist_max >= $add_min && $exist_max <= $add_max) {
                $flg_exist = true;
                break;
            }
        }

        if ($flg_exist === true) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : equalprefixValidate
* Description    : compare prefix old_prefix
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class equalprefixValidate extends AbstractValidate {
    function run ($val, $option = array()) {

        /* Undo Temporary Replace */
        $old_prefix = str_replace(';', ':', $option[0]);

        if ($val !== $old_prefix) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : option82formatValidate
* Description    : Check if the class name begins with opt82_.
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class option82formatValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if (preg_match('/^opt82_/', $val) === 1) {
            return true;
        }
        return false;
    }
}

/*****************************************************************************
* Class          : classexistValidate
* Description    : Check if the class name begins with opt82_.
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class classexistValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        $flg_exist = false;

        if ($option[0] === 'dhcp4') {
            $conf = new KeaConf(DHCPV4);
        } else if ($option[0] === 'dhcp6') {
            $conf = new KeaConf(DHCPV6);
        } else {
            return false;
        }

        [$ret, $classdata] = $conf->get_client_class('all');
        if ($ret === false) {
            return false;
        }

        foreach ($classdata as $class) {
            if($class['name'] === $val) {
                $flg_exist = true;
            }
        }

        if ($flg_exist === true) {
            return true;
        }

        return false;
    }
}

/*****************************************************************************
* Class          : invalid_charsValidate
* Description    : Check for the inclusion of characters that cannot be used
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class invalid_charsValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if (strpos($val, '"') !== false || strpos($val, "'") !== false || strpos($val, ',') !== false || strpos($val, '\\') !== false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : duplicate_payout_conditionValidate
* Description    : Inspect for duplicate payout conditions
* args           : $val
*                : $options - method options
                    0 - dhcp4 or dhcp6
                    1 - String to be inspected (If no, inspect $val)
* return         : true or false
*****************************************************************************/
class duplicate_payout_conditionValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if (isset($option[1])){
            $val = $option[1];
        }

        $flg_found = false;
        if ($option[0] === 'dhcp4') {
            $conf = new KeaConf(DHCPV4);
        } else if ($option[0] === 'dhcp6') {
            $conf = new KeaConf(DHCPV6);
        }

        [$ret, $classdata] = $conf->get_client_class('all');
        if ($ret === false) {
            return true;
        }

        foreach ($classdata as $class) {
            if ($class[STR_TEST] === "$val") {
                $flg_found = true;
                break;
            }
        }

        if ($flg_found === true) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : alreadyleased4Validate
* Description    : Check whether the IPv4 address to be leased is already leased.
* args           : $val
*                : $options - method options
                    0 - pool_start
                    1 - pool_end
                    2 - allowleased (true or false)
                    3 - subnet
* return         : true or false
*****************************************************************************/
class alreadyleased4Validate extends AbstractValidate {
    function run ($val, $option = array()) {
        $flg_found = false;
        /* If flg_pass is true, do not check */
        if ($option[2] === "true") {
            return true;
        }

        /* get subnet id */
        $conf = new KeaConf(DHCPV4);
        $subnet_id = $conf->get_subnet_id($option[3]);

        /* make query for check duplicate */
        $dbutil = new dbutils($this->allval['store']->db);
        $dbutil->select('INET_NTOA(address) as address');
        $dbutil->from('lease4');
        $dbutil->where("subnet_id = " . $subnet_id . " AND address BETWEEN INET_ATON('" . $option[0] . "') AND INET_ATON('" . $option[1] . "')");

        /* fetch COUNT query's result */
        $ret = $dbutil->get();
        if ($ret === false) {
            return false;
        }
        if (!empty($ret)) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : duplicate_option82_mac_Validate
* Description    : Inspect for duplicate MAC addresses used in option82 setting.
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class duplicate_option82_macValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        $flg_found = false;
        $conf = new KeaConf(DHCPV4);

        [$ret, $classdata] = $conf->get_client_class('option82');
        if ($ret === false) {
            return true;
        }

        foreach ($classdata as $class) {
            [$ret, $exists_mac] = $conf->format_test_value($class[STR_TEST], 'mac_address');
            if (strtolower($val) === strtolower($exists_mac)) {
                $flg_found = true;
                break;
            } 
        }

        if ($flg_found === true) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : includepoolValidate
* Description    : Inspect for existing pools within range
* args           : $val
*                : $options - method options
                   0 - pool_start
                   1 - subnet
                   2 - ignore_pool_start
                   3 - ignore_pool_end
* return         : true or false
*****************************************************************************/
class includepoolValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        $start_long  = ip2long($option[0]);
        $end_long = ip2long($val);
        $subnet = $option[1];

        $conf = new KeaConf(DHCPV4);
        [$ret, $pools] = $conf->get_pools($subnet, 'all');

        /* Returns true if there is no pool */
        if (is_array($pools) === false) {
            return true;
        }
        foreach ($pools as $pool) {
            list($min, $max) = explode('-', $pool['pool']);
            if (!empty($option[2]) && $option[2] === $min) {
                continue;
            }

            if (!empty($option[3]) && $option[3] === $max) {
                continue;
            }

            $min_long = ip2long($min);
            $max_long = ip2long($max);

            if ($start_long >= $min_long && $start_long <= $max_long) {
                return false;
            }
            if ($end_long >= $min_long && $end_long <= $max_long) {
                return false;
            }
            if ($min_long >= $start_long && $min_long <= $end_long) {
                return false;
            }
            if ($max_long >= $start_long && $max_long <= $end_long) {
                return false;
            }
        }
        return true;        
    }
}

/*****************************************************************************
* Class          : true_or_falseValidate
* Description    : Judge true or false
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class true_or_falseValidate extends AbstractValidate {
    function run ($val, $option = array()) {
        if ($val === "") {
            return true;
        }
        if ($val === 'true' || $val === 'false') {
            return true;
        }
        return false;
    }
}

/*****************************************************************************
* Class          : includepool6Validate
* Description    : Inspect for existing pools within range (DHCPv6)
* args           : $val
*                : $options - method options
                   0 - pool_start
                   1 - subnet
                   2 - ignore_pool_start
                   3 - ignore_pool_end
* return         : true or false
*****************************************************************************/
class includepool6Validate extends AbstractValidate {
    function run ($val, $option = array()) {
        $start_long  = inet_pton($option[0]);
        $end_long = inet_pton($val);
        $subnet = $option[1];

        $conf = new KeaConf(DHCPV6);
        [$ret, $pools] = $conf->get_pools6($subnet);

        /* Returns true if there is no pool */
        if (is_array($pools) === false) {
            return true;
        }
        foreach ($pools as $pool) {
            list($min, $max) = explode('-', $pool['pool']);
            if (!empty($option[2]) && $option[2] === $min) {
                continue;
            }

            if (!empty($option[3]) && $option[3] === $max) {
                continue;
            }

            $min_long = inet_pton($min);
            $max_long = inet_pton($max);

            if ($start_long >= $min_long && $start_long <= $max_long) {
                return false;
            }
            if ($end_long >= $min_long && $end_long <= $max_long) {
                return false;
            }
            if ($min_long >= $start_long && $min_long <= $end_long) {
                return false;
            }
            if ($max_long >= $start_long && $max_long <= $end_long) {
                return false;
            }
        }
        return true;
    }
}
