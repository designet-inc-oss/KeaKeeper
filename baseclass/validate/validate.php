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


/******************************************************************************
 * Class: Validater
 *
 * [Description]
 *   This class validates the form.
 *****************************************************************************/
class Validater {
    public $err = array();

    /*************************************************************************
    * Method         : __construct
    * Description    : Analyze rule and Run validate class.
    * args           : $rules  - validate rules
    *                : $values - validate values
    * return         : None
    *************************************************************************/
    public function __construct($rules, $values, $convert = false)
    { 
        $this->err["result"] = true;

        /* Loop for extract rules array */
        foreach ($rules as $key => $validaters) {
            $methods = explode("|", $validaters["method"]);

            /* Processing methods and options for validation */
            $i = -1;
            foreach ($methods as $method_option) {
                /************************************************************
                * Initialized variables section
                *************************************************************/
                $i++;
                $tmp = [];
                $method = null;
                $moption = [];

                /* Split method and option in colon. For example..
                 *        rule: max:16
                 *  $tmp[0]   -> max (this is validate class)
                 *  $tmp[1-*] -> 16  (this is validate class options)
                 */
                $tmp = explode(":", $method_option, 2);
                $method = array_shift($tmp);
                if (count($tmp) !== 0) {
                    $moption = explode(":", $tmp[0]);
                }

                /* If there is no key, it can be filled with null */
                if (!isset($values[$key])) {
                    $values[$key] = null;
                }

                /* initialized variables */
                $errkey = "e_". $key;
                $this->err["keys"][$key] = $values[$key];
                $this->err["msg"][$errkey] = [];
                $this->err["log"][$errkey] = [];

                /************************************************************
                * validate option section
                *************************************************************/
                /* allowempty option */
                $ret = $this->_allowempty($rules[$key], $key, $values);
                if ($ret === true) {
                    break;
                }

                $continueonfail = $this->_continueonfail($rules[$key]);
                $skiponfail = $this->_skiponfail($rules[$key]);

                if ($skiponfail === true && $this->err["result"] === false) {
                    continue;
                }

                /************************************************************
                * validate section
                *************************************************************/
                /* make validation instance */
                $method = $method. "Validate";
                $validater = new $method();

                $validater->allval = $values;

                if (count($moption) === 0) {
                    $ret = $validater->run($values[$key]);
                } else {
                    $ret = $validater->run($values[$key], $moption);
                }

                unset($validater);

                /* validation OK */
                if ($ret === true) {
                    continue;
                }
                $this->err["result"] = false;

                /************************************************************
                * Error handling section
                *************************************************************/
                /* Set display errors */
                if (isset($rules[$key]["msg"][$i])) {
                    $this->err["msg"][$errkey][] = $rules[$key]["msg"][$i];
                } else {
                    $this->err["msg"][$errkey][] =
                                             "Undefined message.($key:$method)";
                }

                /* Set log errors */
                if (isset($rules[$key]["log"][$i])) {
                    $this->err["log"][$errkey][] = $rules[$key]["log"][$i];
                } else {
                    $this->err["log"][$errkey][] =
                                             "Undefined message.($key:$method)";
                }

                /* Specify continueonfail */
                if ($continueonfail === true) {
                    continue;
                }

                break;
            }
        }

        if ($convert === true) {
            $this->tags = $this->err2tag();
            $this->logs = $this->err2log();
        }

    }

    private function _continueonfail($ruleofkey)
    {
        if (isset($ruleofkey["option"]) &&
            in_array("continueonfail", $ruleofkey["option"])) {
            return true;
        }
        return false;
    }

    private function _skiponfail($ruleofkey)
    {
        if (isset($ruleofkey["option"]) &&
            in_array("skiponfail", $ruleofkey["option"])) {
            return true;
        }
        return false;
    }

    private function _allowempty($ruleofkey, $key, $values)
    {
        /* Specify allowempty */
        if (!isset($ruleofkey["option"])) {
            return false;
        }

        if (!in_array("allowempty", $ruleofkey["option"])) {
            return false;
        }

        if (isset($values[$key]) && $values[$key] !== NULL &&
                                    $values[$key] !== "") {
            return false;
        }
        return true;
    }

    /*************************************************************************
    * Method         : err2tag
    * Description    : convert display err message
    * args           : None
    * return         : None
    *************************************************************************/
    public function err2tag()
    { 
        /* old values */
        $tag = $this->err["keys"];

        /*  */
        foreach ($this->err["msg"] as $key => $msg) {
            $tag[$key] = implode("\n", $msg);
        }

        return $tag;
    }

    /*************************************************************************
    * Method         : err2log
    * Description    : convert log message
    * args           : None
    * return         : None
    *************************************************************************/
    public function err2log()
    { 
        $log = [];

        foreach ($this->err["log"] as $key => $msgarray) {
            foreach ($msgarray as $msg) {
                $log[] = $msg;
            }
        }

        return $log;
    }
}

/*************************************************************************
* Abstract Class : AbstractValidate
* Description    : Abstract class for validation
* args           : $val     - validate values
*                : $options - method options
* return         : None
*************************************************************************/
abstract class AbstractValidate {
    public $allval;
    public abstract function run($val, $option); 

    /*************************************************************************
    * Method         : masktobyte
    * Description    : Creates binary data of the same format as that
    *                   generated by inet_pton () from the specified subnet mask.
    * args           : $mask
    * return         : $binMask
    *************************************************************************/
    public function masktobyte($mask)
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
}

/************************************************************************* 
* Class          : existValidate
* Description    : Validation class that prohibits data empty and null
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class existValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (is_null($val) || $val === "") {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : boolValidate
* Description    : Validation class that boolean
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class boolValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_BOOLEAN);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : emailValidate
* Description    : Validation class that email
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class emailValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_EMAIL);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : floatValidate
* Description    : Validation class that float
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class floatValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_FLOAT);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : intValidate
* Description    : Validation class that int
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class intValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = is_numeric($val);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : minValidate
* Description    : Validation class that min number
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class minValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (count($option) === 0) {
            return false;
        }

        $len = strlen($val);
        if ($len < $option[0]) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : maxValidate
* Description    : Validation class that min number
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class maxValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (count($option) === 0) {
            return false;
        }

        $len = strlen($val);
        if ($len > $option[0]) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : intminValidate
* Description    : Validation class that min number
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class intminValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (count($option) === 0) {
            return false;
        }

        $range["options"] = ["min_range" => $option[0]];

        $ret = filter_var($val, FILTER_VALIDATE_INT, $range);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : maxValidate
* Description    : Validation class that max number
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class intmaxValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (count($option) === 0) {
            return false;
        }

        $range["options"] = ["max_range" => (int)$option[0]];

        $ret = filter_var($val, FILTER_VALIDATE_INT, $range);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : ipValidate
* Description    : Validation class that ipv4/ipv6 address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class ipValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_IP);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : ipv4Validate
* Description    : Validation class that ipv4 address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class ipv4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
        if ($ret === false) {
            return false;
        }

        $separated = explode('.', $val);
        
        if ($separated[0] == 0 || $separated[3] == 0) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : ipv6Validate
* Description    : Validation class that ipv6 address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class ipv6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($ret === false) {
            return false;
        }
/*
        if(preg_match('/::$/',$val)){
            return false;
        }
*/

        return true;
    }
}

/*************************************************************************
* Class          : ipv6PoolValidate
* Description    : Validation class that ipv6 address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class ipv6PoolValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = filter_var($val, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($ret === false) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : macaddrValidate
* Description    : Validation class that mac address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class macaddrValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $pattern = "[a-fA-F0-9]{2}";
        $all = "^$pattern:$pattern:$pattern:$pattern:$pattern:$pattern$";

        $ret = preg_match("/$all/", $val);
        if ($ret === 0) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : duidValidate
* Description    : Validation class that mac address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class duidValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $pattern = "^([a-fA-F0-9]{2}:)+[a-fA-F0-9]{2}$";
        $ret = preg_match("/$pattern/", $val);
        if ($ret === 0) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : circuitidValidate
* Description    : Validation class that mac address
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class circuitidValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $ret = preg_match('/[^a-fA-F0-9\-:]/', $val);
        if ($ret === 1) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : subnet6Validate
* Description    : Validation class for subnet
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class subnet6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $num = substr_count($val, "/");
        if ($num != 1) {
            return false;
        }

        list($addr, $mask) = explode("/", $val);

        $ret = filter_var($addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : insubnet4Validate
* Description    : Validation class that ip address in subnet
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class insubnet4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        list($addr, $mask) = explode('/', $option[0]);
        $addr_long = ip2long($addr) >> (32 - $mask);
        $ip_long   = ip2long($val) >> (32 - $mask);

        if ($addr_long !== $ip_long) {
            return false;
        }
        return true;
    }
}

/*****************************************************************************
* Class          : insubnet6Validate
* Description    : Validation class that ipv6 address in subnet
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class insubnet6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make str for subnet */
        $subnet = implode(":", $option);

        /* Separate into addresses and netmasks */
        list($net, $mask) = explode("/", $subnet);

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

/************************************************************************* 
* Class          : regexValidate
* Description    : Validation class that regexp
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class regexValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        if (count($option) === 0) {
            return false;
        }

        $options = implode(":", $option);

        $regexp["options"] = ["regexp" => $options];


        $ret = filter_var($val, FILTER_VALIDATE_REGEXP, $regexp);
        if ($ret === false) {
            return false;
        }
        return true;
    }
}

/************************************************************************* 
* Class          : dateValidate
* Description    : Validation class that date
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class dateValidate extends AbstractValidate {
    public function run($val, $option = array("Y/m/d"))
    {
        $format = implode(":", $option);
        $date = DateTime::createFromFormat($format, $val);
        if ($date === false) {
            return false;
        }

        if ($date->format($format) != $val) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : datecmpValidate
* Description    : Validation class that date
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class datecmpValidate extends AbstractValidate {
    public function run($val, $option = array())
    {
        $format = "Y/m/d";
        $cond = array_shift($option);
        $cmpkey = array_shift($option);
        if (count($option) !== 0) {
            $format = implode(":", $option);
        }

        if (empty($val) || empty($this->allval[$cmpkey]))  {
            return true;
        }

        $date1 = DateTime::createFromFormat($format, $val);
        if ($date1 === false) {
            return false;
        }

        $date2 = DateTime::createFromFormat($format, $this->allval[$cmpkey]);
        if ($date2 === false) {
            return false;
        }

        if ($cond == ">") {
            if ($date1 > $date2) {
                return true;
            }
            return false;
        }

        if ($cond == "<") {
            if ($date1 < $date2) {
                return true;
            }
            return false;
        }

        if ($cond == ">=") {
            if ($date1 >= $date2) {
                return true;
            }
            return false;
        }

        if ($cond == "<=") {
            if ($date1 <= $date2) {
                return true;
            }
            return false;
        }

        if ($cond == "==") {
            if ($date1 == $date2) {
                return true;
            }
            return false;
        }

        if ($cond == "!=") {
            if ($date1 != $date2) {
                return true;
            }
            return false;
        }

        return false;
    }
}

/************************************************************************* 
* Class          : eqValidate
* Description    : Class for checking the identity of two values
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class eqValidate extends AbstractValidate {
    public function run($val, $option = array(""))
    {
        $target = implode(":", $option);
        if ($val !== $this->allval[$target]) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : noteqValidate
* Description    : Class for checking nonidentity of two values
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class noteqValidate extends AbstractValidate {
    public function run($val, $option = array(""))
    {
        $target = implode(":", $option);
        if ($val === $this->allval[$target]) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : portValidate
* Description    : Validation class that port
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class portValidate extends AbstractValidate {
    public function run($val, $option = array(""))
    {
        $num = "0123456789";
        if (strspn($val, $num) != strlen($val)) {
            return false;
        }
    
        if (($val < 1) || ($val > 65535)) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : domainValidate
* Description    : Validation class that domain
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class domainValidate extends AbstractValidate {
    public function run($val, $option = array(""))
    {
        $len = strlen($val);
        if ($len > 255) {
          return false;
        }

        if ($len !== strspn($val, 'abcdefghijklmnopqrstuvwxyz'.
                                     'ABCEDFGHIJKLMNOPQRSTUVWXYZ'.
                                     '1234567890-.')) {
          return false;
        }

        $labels = explode('.', $val);
        foreach($labels as $label) {
            $len = strlen($label);
            if (!$len || $len > 63) {
              return false;
            }
        }

        $begin = preg_match('/^[\-\.]/', $val);
        $end   = preg_match('/[\-\.]$/', $val);
        if ($begin !== 0 || $end !== 0) {
            return false;
        }

        $allnum = ctype_digit($val);
        if ($allnum === true) {
            return false;
        }

        return true;
    }
}

/************************************************************************* 
* Class          : uniqintableValidate
* Description    : 
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class uniqintableValidate extends AbstractValidate {
    public function run($val, $option = array(""))
    {
        return true;
    }
}

/************************************************************************* 
* Class          : sharednameValidate
* Description    : Validation class that sharedname
* args           : $val     - validate values
*                : $options - method options
* return         : true or false
*************************************************************************/
class sharednameValidate extends AbstractValidate {
    public function run($val, $option = array(""))
    {
        $len = strlen($val);
        if ($len > 256) {
            return false;
        }

        if ($len !== strspn($val, 'abcdefghijklmnopqrstuvwxyz'.
                                  'ABCEDFGHIJKLMNOPQRSTUVWXYZ'.
                                  '1234567890-_.')) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
* Class          : greateripv4Validate
* Description    : Validation class that ip greater than other ip
* args           : $val
*                : $options - method options
* return         : true or false
*****************************************************************************/
class greateripv4Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make str for startpool */
        $start = implode(":", $option);

        $start_long = ip2long($start); 
        $end_long = ip2long($val);
        if ($end_long < $start_long) {
            return false;
        }

        return true;
    }
}

/*****************************************************************************
 * Class          : greateripv6Validate
 * Description    : Validation class that ip greater than other ip
 * args           : $val
 *                : $options - method options
 * return         : true or false
 *****************************************************************************/
class greateripv6Validate extends AbstractValidate {
    public function run($val, $option = array())
    {
        /* make str for startpool */
        $start = implode(":", $option);

        $start_ip = inet_pton($start);
        $end_ip   = inet_pton($val);
        if ($end_ip < $start_ip) {
            return false;
        }

        return true;
    }
}
