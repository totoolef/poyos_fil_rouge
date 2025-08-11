<?php
    class sqlCmd
    {
        public $m_fields = array();
        public $m_values = array();
        public $m_type = array(); //s=string,n=numeric,d=date,l=litteral,b=bool
        protected $m_command;

        protected function formated($n)
        {
            $v = $this->m_values[$n];
            $t = $this->m_type[$n];
            switch ($t) {
                case "n":
                    $v = str_replace(["\'","'"], "", $v); //pas de guillemets
                    if (strlen($v) === 0 || $v === "NaN") {
                        return 'null';
                    }
                    return str_replace(',', '.', $v);
                case "d":
                    $v = str_replace(["\'","'"], "", $v); //pas de guillemets
                    if (strlen($v) == 0) {
                        return "null";
                    }
                    return "'" . $v . "'";
                case "b":
                    $v = str_replace(["\'","'"], "", $v); //pas de guillemets
                    if (strlen($v) == 0) {
                        return "null";
                    } else {
                        if ($v == "t" || $v == "Oui" || $v == "on" || $v == "true" || $v == "TRUE" || $v === '1') {
                            return "TRUE";
                        }
                    }
                    return "FALSE";
                case "l":
                    return $v;
            }
            return "'" . str_replace("'", "''", str_replace("\'", "'", $v.'')) . "'";
        }

        public function Add($tuple, $valeur, $type = "")
        {
            $this->m_fields[] = $tuple;
            $this->m_values[] = $valeur;
            if ($type == "") { //auto
                if (is_numeric($valeur)) {
                    $this->m_type[] = "n";
                } else {
                    $this->m_type[] = "s";
                }
            } else {
                $this->m_type[] = $type;
            }
            return count($this->m_type) - 1;
        }

        public function AddNull($tuple)
        {
            $this->m_fields[] = $tuple;
            $this->m_values[] = "null";
            $this->m_type[] = "l";
            return count($this->m_type) - 1;
        }

        public function MakeUpdateQuery($table, $sqlwhere)
        {
            $this->m_command = "UPDATE $table SET \n";
            for ($n = 0; $n < count($this->m_fields); $n++) {
                $f = $this->m_fields[$n];
                if ($n > 0) {
                    $this->m_command .= ",\n";
                }
                $this->m_command .= "$f=" . $this->formated($n);
            }
            $this->m_command .= " WHERE $sqlwhere";
            return $this->m_command;
        }

        public function MakeInsertQuery($table)
        {
            $this->m_command = "INSERT INTO $table (\n";
            for ($n = 0; $n < count($this->m_fields); $n++) {
                if ($n > 0) {
                    $this->m_command .= ",";
                }
                $this->m_command .= $this->m_fields[$n];
            }
            $this->m_command .= ") VALUES (";
            for ($n = 0; $n < count($this->m_values); $n++) {
                if ($n > 0) {
                    $this->m_command .= ",";
                }
                $this->m_command .= $this->formated($n);
            }

            $this->m_command .= ")\n";
            return $this->m_command;
        }

        public function execute($db) {
            global $dbConnection;
            $conn = $db ?: $dbConnection;
            if ($conn) {
                $result = $conn->sql_query($this->GetSQL());
                if ($result === false) {
                    return false;
                }
                $this->Clear();
                return true;
            }
            return false;
        }

        public function Clear()
        {
            unset($this->m_fields);
            unset($this->m_commands);
            unset($this->m_type);
            unset($this->m_command);
            $this->m_fields = array();
            $this->m_values = array();
            $this->m_type = array();
            $this->m_command = "";
        }

        public function GetSQL()
        {
            return $this->m_command;
        }
    }

    interface sqlResult
    {
        public function sql_num_rows();
        public function sql_fetch_array($r = null);
        public function sql_fetch_assoc($r = null);
        public function sql_fetch_all();
        public function sql_num_fields();
        public function sql_free_result();
    }

    interface sqlInterface
    {
        public function sql_connect($host, $user, $password, $database);
        public function sql_error();
        public function sql_close();
        public function sql_query($query);
        public function sql_result($query); //return sqlResult
        public function sql_num_rows($result);
        public function sql_fetch_array($result, $r = null);
        public function sql_fetch_assoc($result, $r = null);
        public function sql_fetch_all($result);
        public function sql_fetch_result($result, $row, $field);
        public function sql_num_fields($result);
        public function sql_free_result($result);
        public function sql_start_transaction();
        public function sql_commit();
        public function sql_rollback();
    }

    class pgsqlResult implements sqlResult
    {
        public $dbRresult = null;

        public function __construct($r)
        {
            $this->dbRresult = $r;
        }

        public function __destruct()
        {
            if ($this->dbRresult != null) {
                pg_free_result($this->dbRresult);
                $this->dbRresult = null;
            }
        }

        public function sql_num_rows()
        {
            return pg_num_rows($this->dbRresult);
        }

        public function sql_fetch_array($r = null)
        {
            return pg_fetch_array($this->dbRresult, $r);
        }

        public function sql_fetch_assoc($r = null)
        {
            return pg_fetch_assoc($this->dbRresult, $r);
        }
        public function sql_fetch_all()
        {
            return pg_fetch_all($this->dbRresult);
        }

        public function sql_fetch_result($row, $field)
        {
            return pg_fetch_result($this->dbRresult, $row, $field);
        }

        public function sql_num_fields()
        {
            return pg_num_fields($this->dbRresult);
        }

        public function sql_free_result()
        {
            pg_free_result($this->dbRresult);
            $this->dbRresult = null;
        }

        public function forEachRow($f)
        {
            $index = 0;
            while ($row = pg_fetch_assoc($this->dbRresult)) {
                $f($row, $index++);
            }
        }
    }

    class pgsqlInterface implements sqlInterface
    {
        public $dbLink = null;

        public function sql_connect($host, $user, $password, $database)
        {
            $this->dbLink = pg_connect("host=$host dbname=$database user=$user password=$password ");
            return $this->dbLink;
        }

        public function sql_error()
        {
            return pg_last_error($this->dbLink);
        }

        public function sql_close()
        {
            pg_close($this->dbLink);
            $this->dbLink = null;
        }

        public function sql_query($query)
        {
            return pg_query($this->dbLink, $query);
        }

        public function sql_result($query)
        {
            $r = pg_query($this->dbLink, $query);
            if ($r) {
                return new pgsqlResult($r);
            } else {
                return false;
            }
        }

        public function sql_num_rows($result)
        {
            return pg_num_rows($result);
        }

        public function sql_fetch_array($result, $r = null)
        {
            return pg_fetch_array($result, $r);
        }

        public function sql_fetch_assoc($result, $r = null)
        {
            return pg_fetch_assoc($result, $r);
        }
        public function sql_fetch_all($result)
        {
            return pg_fetch_all($result);
        }

        public function sql_fetch_result($result, $row, $field)
        {
            return pg_fetch_result($result, $row, $field);
        }

        public function sql_num_fields($result)
        {
            return pg_num_fields($result);
        }

        public function sql_free_result($result)
        {
            pg_free_result($result);
        }

        public function sql_start_transaction()
        {
            $this->sql_query("START TRANSACTION");
        }

        public function sql_commit()
        {
            $this->sql_query("COMMIT");
        }

        public function sql_rollback()
        {
            $this->sql_query("ROLLBACK");
        }
    }
