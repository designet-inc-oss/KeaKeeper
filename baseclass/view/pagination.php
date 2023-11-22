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
* Class:  Pagination
*
* [Description]
*   Class to keep and make values for pagination.
*   Usage is call this class with data type(e.g. "mysql"),
*   and put necessary values into variables in this class from the caller,
*   finally, use "run" method.
******************************************************************************/
class Pagination {
    public $currentpage;
    public $totaldata;
    public $totalpage;
    public $dataperpage;
    public $linknum;
    public $datahead;
    public $source;
    public $dbobj;
    public $disppage;
    public $disppagetype;
    public $db_host;
    public $db_user;
    public $db_pass;
    public $db_name;

    /*************************************************************************
    * Method         : __construct
    * Description    : Receive data type.
    * args           : $type : type of data. (default is array)
    * return         : None
    **************************************************************************/
    public function __construct($type = "array")
    {
        $this->type = $type;
        $this->currentpage = 1;
        $this->disppage = [];
        $this->disppagetype = "leftfix";
        $this->linknum = 5;
        $this->dataperpage = 5;
        $this->source = null;

        $this->db_host = "";
        $this->db_user = "";
        $this->db_pass = "";
        $this->db_name = "";
    }

    /**************************************************************************
    * Method         : _getcurrenthead
    * Description    : Check current page, and calcurate data head.
    * args           : None
    * return         : None
    * note           : $datahead is calcurated for MySQL's OFFSET
    **************************************************************************/
    private function _getcurrenthead()
    {
        if ($this->totalpage == 0) {
            $this->totalpage = 1;
        }

        /* Check current page and replace valid number */
        if ($this->currentpage <= 0 || !isset($this->currentpage)) {
            $this->currentpage = 1;
        } else if ($this->currentpage > $this->totalpage) {
            $this->currentpage = $this->totalpage;
        }

        /* Calculate current page's data head */
        $this->datahead = ($this->currentpage - 1) * $this->dataperpage;
    }

    /**************************************************************************
    * Method         : run
    * Description    : Check necessary variables, and call other method.
    * args           : None
    * return         : None
    **************************************************************************/
    public function run()
    {
        /* Check the type  */
        $sourcetypelist = [
            "array",
            "mysql",
            //"file", #note: add later
        ];
        $ret = in_array($this->type, $sourcetypelist);
        if ($ret === false) {
            throw new Exception("Invalid data type");
        }

        /* Check the display page number type  */
        $pagetypelist = [
            "leftfix",
        ];

        $ret = in_array($this->disppagetype, $pagetypelist);
        if ($ret === false) {
            throw new Exception("Invalid paging type");
        }

        /* Check the necessary values */
        if (empty($this->dataperpage)) {
            throw new Exception("Max data number per a page is empty");
        }
        if (empty($this->linknum)) {
            throw new Exception("To display link number is empty");
        }

        /* Call counting method according to type */
        $func = "_".$this->type;
        $this->$func();
        $this->_getcurrenthead();

        /* Call make displayed page number list method according to type */
        $func = "_".$this->disppagetype;
        $this->$func();
    }

    /**************************************************************************
    * Method         : _makepagelist
    * Description    : Make an array containing displayed page number.
    *                  Current page is put to left end.
    * args           : None
    * return         : None
    **************************************************************************/
    private function _leftfix()
    {
        /* caliculate data */
        $remain   = $this->totalpage - $this->currentpage;
        $rightend = $this->currentpage + $this->linknum - 1;
        $leftend  = $this->totalpage - $this->linknum + 1;

        /* total page number is less than link number */
        if ($this->totalpage <= $this->linknum) {
            $this->disppage = range(1, $this->totalpage);

        /* current page is left end 
           when remainig page is more than link page number */
        } else if ($remain >= $this->linknum) {
            $this->disppage = range($this->currentpage, $rightend);

        /* current page is not left end 
           when remainig page is less than link page number */
        } else {
            $this->disppage = range($leftend, $this->totalpage);
        }
    }


    /**************************************************************************
    * Method         : _mysql
    * Description    : Count rows from mysql, and calcurate total page number.
    *                : This method needs database object as $this->dbobj.
    * args           : None
    * return         : None
    **************************************************************************/
    private function _mysql()
    {
        /* Check passed source and object for database */
        if (empty($this->source)) {
            throw new Exception("SQL statement for count data is empty");
        }

        /* Connect the mysql */
        $this->dbobj = new Mysql($this->db_host, $this->db_user,
                                 $this->db_pass, $this->db_name);

        /* execute counting SQL in $source and get the data */
        $ret = $this->dbobj->fetch_all($this->source, null);

        /* Get the max data from result of count query */
        $this->totaldata = max($ret[0]);

        /* Get total page number */
        $this->totalpage = ceil($this->totaldata / $this->dataperpage);
    }

    /**************************************************************************
    * Method         : _array
    * Description    : Count array, and calcurate total page number.
    * args           : None
    * return         : None
    **************************************************************************/
    private function _array()
    {
        /* Check passed source */
        if (empty($this->source)) {
            throw new Exception("Array is not passed");
        } else if (is_array($this->source) === false) {
            throw new Exception("Passed data is not array");
        }

        /* Count array data */
        $this->totaldata = count($this->source);

        /* Get total page number */
        $this->totalpage = ceil($this->totaldata / $this->dataperpage);
    }
}
