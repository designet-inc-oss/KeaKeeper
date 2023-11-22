<?php
/******************************************************************************
* Class: dbutils
*
* [Description]
* Class to make SQL statement.
******************************************************************************/
class dbutils {

    /* PDO instance */
    public $db;
    public $select_state = '*';
    public $from_state;
    public $where_state;
    public $order_state;
    public $limit_state;
    public $set_state;
    public $into_state;

    /*************************************************************************
    * Method        : __construct
    * Description   : constructer
    * args          : $db - dbdriver
    **************************************************************************/
    public function __construct($db)
    {
        $this->db = $db;
    }

    /*************************************************************************
    * Method        : select
    * Description   : make SELECT statement
    * args          : $column - array, string, integer or double
    * return        : None
    **************************************************************************/
    public function select($column)
    {
        switch (gettype($column)) {
            /* string, int or double is input select_state without process */
            case 'string':
            case 'integer':
            case 'double':
                $statement = $column;
                break;

            /* array is input select_state with process */
            case 'array':
                /*check empty element */
                if (in_array('', $column)) {
                    $log_msg = "argument of the select method includes empty.";
                    throw new SyserrException($log_msg);
                }

                /* combine using comma */
                $statement = $this->_comma($column);
                break;
        }

        /* if select_state is initial value, write over */
        if ($this->select_state == '*') {
            $this->select_state = $statement;

        /* if select_state is not initial value, add with comma */
        } else {
            $this->select_state = $this->select_state . ', ' . $statement;
        }
    }

    /*************************************************************************
    * Method        : set
    * Description   : make UPDATE set statement
    * args          : $column - array, string, integer or double
    * return        : None
    **************************************************************************/
    public function set($key_values)
    {
        foreach ($key_values as $set) {
            $key = array_keys($set);
            $val = $set[$key[0]];
        switch (gettype($val)) {
            case 'integer':
                $sets[] = "$key[0] = $val";
            break;

            case 'string':
            default:
                $sets[] = "$key[0] = $val";
            break;
        }

        } 
        $statement = $this->_comma($sets);

        $this->set_state = $statement;
    }

    /*************************************************************************
    * Method        : into
    * Description   : make INSERT INTO statement
    * args          : $column - array or string
    *               : $table  - string
    * return        : None
    **************************************************************************/
    public function into($column)
    {
        if (gettype($column) === 'array') {
            $cols = [];
            $vals = [];
            foreach ($column as $col => $val) {
                switch (gettype($val)) {
                    case 'integer':
                    case 'double':
                        break;
                    case 'string':
                        if ($val === '') {
                            $val = "\"\"";
                        }
                        break;
                    case 'NULL':
                        $val = 'NULL';
                        break;
                    case 'boolean':
                        if ($val === false) {
                            $val = 0;
                        }
                        break;
                    default:
                        $log_msg = "invalid values for into method.";
                        throw new SyserrException($log_msg);
                }

                $cols[] = $col;
                $vals[] = $val;
            }
            $cols = $this->_comma($cols);
            $vals = $this->_comma($vals);

            $this->into_state = "($cols)" . ' VALUES ' . "($vals)";

        } else {
            $log_msg = "invalid type of argument for into method.";
            throw new SyserrException($log_msg);
        }
    }

    /*************************************************************************
    * Method        : from
    * Description   : make FROM statement
    * args          : $table
    * return        : None
    **************************************************************************/
    public function from($from)
    {
        $this->from_state = $from;
    }

    /*************************************************************************
    * Method        : where
    * Description   : make WHERE statement
    * args          : $column     - associative array or string
    *                 $condition - search condition for first argument
    * return        : None
    **************************************************************************/
    public function where($column, $condition = null)
    {
        switch (gettype($column)) {
            case 'string':
            case 'integer':
            case 'double':
                /* second argument is null input where_state without process */
                if ($condition === null) {
                    $statement = $column;

                /* second argument is not null input where_state with format */
                } else {
                    $condition = $this->db->dbh->quote($condition);

                    /* check operator */
                    if ($this->_check_operator($column)) {
                        $statement = $column . " $condition";
                    } else if (preg_match("/\sNOT IN$/i", $column) == 1) {
                        $statement = $column . " ($condition)";
                    } else {
                        $statement = $column . ' = ' . $condition;
                    }
                }

                break;

            case 'array':
                /* loop $column array, $column's value is searching condition */
                foreach ($column as $key => $condition) {
                    $condition = $this->db->dbh->quote($condition);

                    /* check operator */
                    if ($this->_check_operator($key)) {
                        $format = "%s %s";
                    } else {
                        $format = "%s = %s";
                    }

                    $tmp_state = sprintf($format, $key, $condition);
                    $tmp_list[] = $tmp_state;
                }
                $statement = $this->_and($tmp_list);
                break;
        }

        /* if where_state is initial value, write over */
        if (empty($this->where_state)) {
            $this->where_state = $statement;

        /* if where_state is not initial value, add with AND */
        } else {
            $this->where_state = $this->where_state . ' AND ' . $statement;
        }
    }

    /*************************************************************************
    * Method        : like
    * Description   : make LIKE statement and write to WHERE statement
    * args          : $column    - associative array or string
    *                 $condition - search condition for first argument
    *                 $type      - place where put % for second argument
    * return        : None
    **************************************************************************/
    public function like($column, $condition = null)
    {
        switch (gettype($column)) {
            case 'string':
            case 'integer':
            case 'double':
                /* second argument is null input where_state without process */
                if ($condition === null) {
                    $statement = $column;

                /* second argument is not null input where_state with parse */
                } else {
                    /* check wildcard */
                    if ($this->_check_wildcard($condition)) {
                        $tmp_cond = $condition;

                    } else {
                        $tmp_cond = "%$condition%";
                    }
                }

                $quoted = $this->db->dbh->quote($tmp_cond);
                $statement = "$column LIKE $quoted";
                break;

            case 'array':
                foreach ($column as $key => $condition) {
                    /* check operator */
                    if ($this->_check_wildcard($condition)) {
                        $tmp_cond = $condition;
                    } else {
                        $tmp_cond = "%$condition%";
                    }

                    $quoted = $this->db->dbh->quote($tmp_cond);
                    $tmp_state = "$key LIKE $quoted";
                    $tmp_list[] = $tmp_state;
                }

                $statement = $this->_and($tmp_list);
                break;
        }

        /* if where_state is initial value, write over */
        if (empty($this->where_state)) {
            $this->where_state = $statement;

        /* if where_state is not initial value, add with AND */
        } else {
            $this->where_state = $this->where_state . ' AND ' . $statement;
        }
    }

    /*************************************************************************
    * Method        : _check_operator
    * Description   : Check stirng whether operator is contained
    * args          : $state - array containing infomation for conditions
    * return        : true or false
    **************************************************************************/
    private function _check_operator($state)
    {
        $pattern = "/\s=$|\s<=>$|\s<>$|\s!=$|\s<$|\s<=$|\s>$|\s>=$|\sIS$/i";
        if (preg_match($pattern, $state) == 1) {
            return true;
        }
        return false;
    }

    /*************************************************************************
    * Method        : _check_wildcard
    * Description   : Check stirng whether wildcard(%) is contained
    * args          : $state - array containing infomation for conditions
    * return        : true or false
    **************************************************************************/
    private function _check_wildcard($state)
    {
        if (preg_match("/^%|%$/", $state) == 1) {
            return true;
        }
        return false;
    }

    /*************************************************************************
    * Method        : escape_wildcard
    * Description   : Escape wildcard(% and _)
    * args          : $cond
    * return        : $esc_cond - escaped condition passed as $cond
    **************************************************************************/
    public function escape_wildcard($cond)
    {
        $wildcards = ["%", "_"];
        $escaped = ["\\%", "\\_"];

        $esc_cond = str_replace($wildcards, $escaped, $cond);

        return $esc_cond;
    }

    /*************************************************************************
    * Method        : _and
    * Description   : Combine array's item with " AND "
    * args          : $stats_list - array containing infomation for conditions
    * return        : $statement
    **************************************************************************/
    private function _and($states_list)
    {
        $statement = implode(' AND ', $states_list);
        return $statement;
    }

    /*************************************************************************
    * Method        : _comma
    * Description   : Combine array's item with ", "
    * args          : $stats_list - array containing infomation for conditions
    * return        : $statement
    **************************************************************************/
    private function _comma($states_list)
    {
        $statement = implode(', ', $states_list);
        return $statement;
    }

    /*************************************************************************
    * Method        : order
    * Description   : Make ORDER BY statement for MySQL
    * args          : $key  - the key for sorting
    *                 $type - how to sort. default is "DESC"
    * return        : $statement
    **************************************************************************/
    public function order($key, $type = 'desc')
    {
        $list_type = ['desc', 'asc'];
        if (!in_array($type, $list_type)) {
            $log_msg = "invalid type for order method(use lower case for type).";
            throw new SyserrException($log_msg);
        }

        $statement = "ORDER BY $key $type";
        $this->order_state =  $statement;
    }

    /*************************************************************************
    * Method        : limit
    * Description   : Make LIMIT statement for MySQL
    * args          : $offset - OFFSET data number  default is 0
    *                 $number - how much to get data
    * return        : true or false
    **************************************************************************/
    public function limit($number, $offset = 0)
    {
        if (!is_numeric($offset) || !is_numeric($number)) {
            $log_msg = "invalid argument for limit method(only numbers).";
            throw new SyserrException($log_msg);
        }

        if ($offset == 0) {
            $statement = "LIMIT $number";
        } else {
            $statement = "LIMIT $offset, $number";
        }
        $this->limit_state =  $statement;
    }

    /*************************************************************************
    * Method        : compile_select
    * Description   : Make SELECT SQL using properties
    * args          : None
    * return        : $sql
    **************************************************************************/
    public function compile_select()
    {
        $sql = "SELECT $this->select_state ";
        $sql = $sql . "FROM $this->from_state ";
        if (!empty($this->where_state)) {
            $sql = $sql . "WHERE $this->where_state ";
        }
        $sql = $sql . "$this->order_state ";
        $sql = $sql . "$this->limit_state ";
        return $sql;
    }

    /*************************************************************************
    * Method        : compile_update
    * Description   : Make UPDATE SQL using properties
    * args          : None
    * return        : $sql
    **************************************************************************/
    public function compile_update()
    {
        $sql = "UPDATE ";
        $sql = $sql . "$this->from_state ";
        $sql = $sql . "SET $this->set_state ";
        $sql = $sql . "WHERE $this->where_state ";
        return $sql;
    }

    /*************************************************************************
    * Method        : compile_insert
    * Description   : Make INSERT SQL using properties
    * args          : None
    * return        : $sql
    **************************************************************************/
    public function compile_insert()
    {
        $sql = "INSERT INTO ";
        $sql = $sql . "$this->from_state ";
        $sql = $sql . $this->into_state;
        return $sql;
    }

    /*************************************************************************
    * Method        : compile_delete
    * Description   : Make DELETE SQL using properties
    * args          : None
    * return        : $sql
    **************************************************************************/
    public function compile_delete()
    {
        $sql = "DELETE ";
        $sql = $sql . "FROM $this->from_state ";
        $sql = $sql . "WHERE $this->where_state ";
        return $sql;
    }

    /*************************************************************************
    * Method        : get
    * Description   : fetch all data using statement properties
    * args          : $table
    *                 $offset
    *                 $limit
    * return        : None
    **************************************************************************/
    public function get($table = null, $limit = null, $offset = 0)
    {
        /* combine put statements */
        $sql = "SELECT $this->select_state ";

        /* use arguments preferentially for table */
        if ($table === null) {
            $sql = $sql . "FROM $this->from_state ";
        } else {
            $sql = $sql . "FROM $table ";
        }

        if (!empty($this->where_state)) {
            $sql = $sql . "WHERE $this->where_state ";
        }

        $sql = $sql . "$this->order_state ";

        if ($limit === null) {
            $sql = $sql . "$this->limit_state ";
        } else {
            $this->limit($limit, $offset);
            $sql = $sql . "$this->limit_state ";
        } 

        return $this->db->fetch_all($sql, null);
    }

    /*************************************************************************
    * Method        : update
    * Description   : update table
    * args          : $table
    * return        : None
    **************************************************************************/
    public function update($table = null)
    {
        /* use arguments preferentially for table */
        if ($table !== null) {
            $this->from($table);
        }

        $sql = $this->compile_update();
        $this->db->exec($sql);

        return true;
    }

    /*************************************************************************
    * Method        : delete
    * Description   : delete table
    * args          : $table
    * return        : None
    **************************************************************************/
    public function delete($table = null)
    {
        /* use arguments preferentially for table */
        if ($table !== null) {
            $this->from($table);
        }

        $sql = $this->compile_delete();
        $this->db->exec($sql);

        return true;
    }

    /*************************************************************************
    * Method        : insert
    * Description   : insert data
    * args          : $table
    * return        : None
    **************************************************************************/
    public function insert($table = null)
    {
        if ($table !== null) {
            $this->from = $table;
        }

        $sql = $this->compile_insert();
        $this->db->exec($sql);

        return true;
    }
}
