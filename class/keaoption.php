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
* Class:  KeaOption
*
* [Description]
*   Class to 
*****************************************************************************/
class KeaOption {

    /**
     * properties
     */
    public $extra_opt_4; 
    public $extra_opt_6; 

    /************************************************************************
    * Method         : __construct
    * Description    : constructor
    * args           : $this->dhcpver    ipv4 or ipv6
    * return         : None
    ************************************************************************/
    public function __construct($dhcpver)
    {
        $this->init_options4();
    }

    public function read_option_rule($filepath)
    {
        $data_rule = [];

        $fp = @fopen($filepath, "r");
        if ($fp === false) {
            return $data_rule;
        }

        while (feof($fp) === false) {

            $buf = fgets($fp);
            if ($buf === false) {
                continue;
            }

            $buf = rtrim($buf);        

            if (strlen($buf) == 0) {
                continue;
            }

            $arr_opt = explode("\t", $buf);

            $opt_rule = [
                'name'     => $arr_opt[0],
                'type'     => $arr_opt[2],
                //TODO
                #'array'    => $arr_opt[3],
                #'required' => $arr_opt[4],
            ];

            $data_rule[] = $opt_rule;
        }
        fclose($fp);
 
        return $data_rule;
    }

    /************************************************************************
    * Method         : init_options4
    * Description    : initinial options v4
    * args           : $this->dhcpver    ipv4 or ipv6
    * return         : None
    ************************************************************************/
    private function init_options4()
    {
        $this->extra_opt_4 = $this->read_option_rule('../inc/options_v4.inc');
        $this->extra_opt_6 = $this->read_option_rule('../inc/options_v6.inc');
    }

    public function edit_data_opt_v4($extra_option)
    {
        $disp_extra_option = $extra_option;

        foreach ($extra_option as $idx => $option) {
            /* get type of option */
            $type = $this->get_type($option, DHCPV4);
            switch($type) {
                case 'ipv4-address':
                    if (strpos($option[STR_OPT_VALUE], ':')) {
                        $disp_extra_option[$idx][STR_OPT_VALUE] = 
                                             $option[STR_OPT_VALUE];
                    } else {
                        // TODO
                    }
                    break;
                case 'uint8':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                case 'uint16':
                     $disp_extra_option[$idx][STR_OPT_VALUE] =
                                              $option[STR_OPT_VALUE];
                    break;
                case 'uint32':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                case 'string':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                case  'fqdn':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                // example: 2001 0DB8 0001 0000 0000 0000 0000 CAFE
                case  'hex':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                default:
                    $disp_extra_option[$idx][STR_OPT_VALUE] = 
                                             $option[STR_OPT_VALUE];
                    break;
            }
        }
        return $disp_extra_option;
    }

    public function edit_data_opt_v6($extra_option)
    {
        $disp_extra_option = $extra_option;

        foreach ($extra_option as $idx => $option) {
            /* get type of option */
            $type = $this->get_type($option, DHCPV6);
            switch($type) {
                case 'ipv6-address':
                    if (strpos($option[STR_OPT_VALUE], ':')) {
                        $disp_extra_option[$idx][STR_OPT_VALUE] = 
                                             $option[STR_OPT_VALUE];
                    } else {
                        // TODO
                    }
                    break;
                case 'uint8':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                case 'uint16':
                     $disp_extra_option[$idx][STR_OPT_VALUE] =
                                              $option[STR_OPT_VALUE];
                    break;
                case 'uint32':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                case 'string':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                case  'fqdn':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                // example: 2001 0DB8 0001 0000 0000 0000 0000 CAFE
                case  'hex':
                    $disp_extra_option[$idx][STR_OPT_VALUE] =
                                             $option[STR_OPT_VALUE];
                    break;
                default:
                    $disp_extra_option[$idx][STR_OPT_VALUE] = 
                                             $option[STR_OPT_VALUE];
                    break;
            }
        }
        return $disp_extra_option;
    }

    public function get_type($opt, $version = null) 
    {
        if ($version === DHCPV6) {
            $extra_opt = $this->extra_opt_6;
        } else {
            $extra_opt = $this->extra_opt_4;
        }

        foreach ($extra_opt as $ext_opt) {
            if ($ext_opt["name"] === $opt["name"]) {
                return $ext_opt["type"];
            }
        }
        return null;
    }

}
