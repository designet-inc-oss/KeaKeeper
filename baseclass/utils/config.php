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
* Class: Config
*
* [Description]
* Class for read config.
*
******************************************************************************/
class Config {

    public $appini;

    /*************************************************************************
    * Method        : __construct
    * Description   : Read configure file.
    * args          : None
    * return        : None
    **************************************************************************/
    public function __construct()
    {
        /* read conf */
        if (is_readable(APPINI) === false) {
            /* syserr */
            $msg = "Cannot read the file.(" . APPINI . ")";
            throw new SyserrException($msg);
        }

        $this->appini = parse_ini_file(APPINI, true);
        if ($this->appini === false) {
            /* syserr */
            $msg = "Cannot get value from the file.(" . APPINI . ")";
            throw new SyserrException($msg);
        }

    }
}
