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
* Class: Mysql
*
* [Description]
* Class to use MySQL.
*
******************************************************************************/
class Mysql {

    /* PDO instance */
    public $dbh;
    public $sth;

    /*************************************************************************
    * Method        : __construct
    * Description   : Connect to MySQL automatically.
    * args          : $db_host
    *               : $db_user
    *               : $db_pass
    *               : $db_name
    * return        : None
    **************************************************************************/
    public function __construct($db_host = "", $db_user = "",
                                $db_pass = "", $db_name = "")
    {
        global  $appini;

        /* If arguments are empty, use value of application.ini */
        if (empty($db_host) === true) {
            $db_host = $appini['db']['host'];
        }
        if (empty($db_name) === true) {
            $db_name = $appini['db']['database'];
        }

        if (empty($db_user) === true) {
            $db_user = $appini['db']['user'];
        }

        if (empty($db_pass) === true) {
            $db_pass = $appini['db']['password'];
        }

        try {
            $this->dbh = new PDO('mysql:host=' . $db_host . ';dbname=' . $db_name,
                                 $db_user,
                                 $db_pass,
                                 [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        } catch (PDOException $e) {
            /* throw message */
            $log_msg = "mysql_err(" . $e->getMessage() . ")";
            throw new SyserrException($log_msg);

        }
    }

    /*************************************************************************
    * Method        : exec
    * Description   : exec raw sql
    * args          : $statement
    * return        : none
    **************************************************************************/
    public function exec($statement)
    {
        try{
            $this->sth = $this->dbh->exec($statement);

        } catch (PDOException $e) {
            /* throw message */
            $log_msg = "mysql_err(" . $e->getMessage() . ")";
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : query
    * Description   : Prepare statement and execute.
    * args          : $statement
    *               : $value_arr
    * return        : none
    **************************************************************************/
    public function query($statement, $value_arr = [])
    {
        try{
            $this->sth = $this->dbh->prepare($statement,
                                       [PDO::ATTR_CURSOR =>
                                        PDO::CURSOR_FWDONLY]);
            $this->sth->execute($value_arr);

        } catch (PDOException $e) {
            /* throw message */
            $log_msg = "mysql_err(" . $e->getMessage() . ")";
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : fetch_all
    * Description   : Get data from MySQL.
    * args          : $statement
    *               : $value_arr
    * return        : $selected_arr(selected data)
    **************************************************************************/
    public function fetch_all($statement, $value_arr)
    {
        /* check statement */
        if (preg_match("/^SELECT/", $statement) === 0) {
            $log_msg = "How to use fetchAll method is incorrect.";
            $log_msg = "mysql_err(" . $log_msg . ")";
            throw new SyserrException($log_msg);
        }

        /* query */
        try {
            $this->query($statement, $value_arr);

        } catch (SyserrException $e) {
            throw new SyserrException($e->getMessage());
        }

        $selected_arr = $this->sth->fetchAll();
        return $selected_arr;
    }

    /*************************************************************************
    * Method        : row_count
    * Description   : Counting rows affected by SQL statement.
    * args          : none
    * return        : $rows_num
    **************************************************************************/
    public function row_count()
    {
        $rows = $this->sth->rowCount();
        return $rows_num;
    }

    /*************************************************************************
    * Method        : begin_transaction
    * Description   : Method to begin transaction.
    * args          : none
    * return        : none
    **************************************************************************/
    public function begin_transaction()
    {
        try {
            $ret = $this->dbh->beginTransaction();
            if ($ret === false) {
                $log_msg = "Failed to start database transaction.";
                $log_msg = "mysql_err(" . $log_msg . ")";
                throw new SyserrException($log_msg);
            }
        } catch (PDOException $e) {
            throw new SyserrException($e->getMessage());
        }

    }

    /*************************************************************************
    * Method        : commit
    * Description   : Method to commit to database.
    * args          : none
    * return        : none
    **************************************************************************/
    public function commit()
    {
        try {
            $ret = $this->dbh->commit();
            if ($ret === false) {
                $log_msg = "Failed to commit to database.";
                $log_msg = "mysql_err(" . $log_msg . ")";
                throw new SyserrException($log_msg);
            }
        } catch (PDOException $e) {
            throw new SyserrException($e->getMessage());
        }
    }

    /*************************************************************************
    * Method        : rollback
    * Description   : Method to rollback.
    * args          : none
    * return        : none
    **************************************************************************/
    public function rollback()
    {
        try {
            $ret = $this->dbh->rollback();
            if ($ret === false) {
                $log_msg = "Failed to rollback database transaction.";
                $log_msg = "mysql_err(" . $log_msg . ")";
                throw new SyserrException($log_msg);
            }
        } catch (PDOException $e) {
            throw new SyserrException($e->getMessage());
        }
    }

    /*************************************************************************
    * Method        : in_transaction
    * Description   : Method to check if the transaction is active.
    * args          : none
    * return        : true/false
    **************************************************************************/
    public function in_transaction()
    {
        $ret = $this->dbh->inTransaction();
        if ($ret === false) {
            return false;
        }
        return true;
    }

    /*************************************************************************
    * Method        : last_insertid 
    * Description   : Method to get last insert id.
    *               : (Note: before commit.)
    * args          : none
    * return        : $ret Insert id. 
    **************************************************************************/
    public function last_insertid()
    {
        try {
            $ret = $this->dbh->lastInsertId();
        } catch (PDOException $e) {
            throw new SyserrException($e->getMessage());
        }
        return $ret; 
    }

    /*************************************************************************
    * Method        : get_errorcode
    * Description   : Method to get error code. 
    * args          : none
    * return        : $ret error code. 
    **************************************************************************/
    public function get_errorcode()
    {
        return $this->sth->errorCode();
    }

    /*************************************************************************
    * Method        : get_errorinfo
    * Description   : Method to get error info. 
    * args          : none
    * return        : $ret error info(array). 
    **************************************************************************/
    public function get_errorinfo()
    {
        return $this->sth->errorInfo();
    }

    /*************************************************************************
    * Method        : inet_aton
    * Description   : Make strings for inet_aton.
    * args          : $ipaddr_str
    *               : $quote - flag for using quotation (default is false)
    * return        : $ret_str
    **************************************************************************/
    public function inet_aton($ipaddr_str, $quote = false)
    {
        if ($quote === true) {
            return "INET_ATON('" . $ipaddr_str . "')";
        }
        return "INET_ATON(" . $ipaddr_str . ")";
    }

    /*************************************************************************
    * Method        : inet_ntoa
    * Description   : Make strings for inet_ntoa.
    * args          : $ipaddr_str
    *               : $quote - flag for using quotation (default is false)
    * return        : $ret_str
    **************************************************************************/
    public function inet_ntoa($ipaddr_binary, $quote = false)
    {
        if ($quote === true) {
            return "INET_NTOA('" . $ipaddr_binary . "')";
        }
        return "INET_NTOA(" . $ipaddr_binary . ")";
    }

    /*************************************************************************
    * Method        : hex
    * Description   : Make strings for hex.
    * args          : $hex_str
    *               : $quote - flag for using quotation (default is false)
    * return        : $ret_str
    **************************************************************************/
    public function hex($hex_str, $quote = false)
    {
        if ($quote === true) {
            return "HEX('" . $hex_str . "')";
        }
        return "HEX(" . $hex_str . ")";
    }

    /*************************************************************************
    * Method        : unhex
    * Description   : Make strings for unhex.
    * args          : $unhex_str
    *               : $quote - flag for using quotation (default is false)
    * return        : $ret_str
    **************************************************************************/
    public function unhex($unhex_str, $quote = false)
    {
        if ($quote === true) {
            return "UNHEX('" . $unhex_str . "')";
        }
        return "UNHEX(" . $unhex_str . ")";
    }
}

