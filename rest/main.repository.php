<?php

class MainRepository
{
    function __construct($table)
    {
        $this->table = $table;
        $this->entity = ucfirst($this->table);
        $this->db = null;

        $this->relations = [];
        $this->subRelations = [];
    }

    private function connect()
    {
        if ($this->db === null) {
            //Connexion à la DB
            $host = "localhost";
            $port = "3306";
            $dbName = "tdphpmvc_db";
            $dsn = "mysql:host=$host;port=$port;dbname=$dbName";
            $user = "root";
            $pass = "";
            $db = null;
            try {
                $db = new PDO(
                    $dsn,
                    $user,
                    $pass,
                    array(
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    )
                );
            } catch (PDOException $e) {
                die("Erreur de connexion à la base de données : $e->getMessage()");
            }
            $this->db = $db;
        }
        return $this->db;
    }

    function getAll($where = "1")
    {
        $sql = "SELECT * FROM $this->table WHERE $where";
        $resp = $this->connect()->query($sql);
        $rows = $resp->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $this->entity);
        if (count($rows) == 0) {
            return $rows;
        }
        foreach ($this->relations as $relation) {
            if ($relation['type'] == 'hasMany') {
                $repo = new MainRepository($relation['table']);
                foreach ($this->subRelations[$relation['name']] as $subRel) {
                    $repo->with($subRel);
                }
                $results = $repo->getAll();
                foreach ($rows as $row) {
                    $currentResults = array_filter($results, function ($item) use ($row, $relation) {
                        return $item->{$relation['foreignKey']} == $row->id;
                    });
                    $row->{$relation['attribute']} = $currentResults;
                }
            }
            if ($relation['type'] == 'hasOne') {
                $repo = new MainRepository($relation['table']);
                foreach ($this->subRelations[$relation['name']] as $subRel) {
                    $repo->with($subRel);
                }
                $results = $repo->getAll();
                foreach ($rows as $row) {
                    $currentResults = array_filter($results, function ($item) use ($row, $relation) {
                        return $item->id == $row->{$relation['foreignKey']};
                    });
                    $row->{$relation['attribute']} =
                        count($currentResults) == 1 ? array_shift($currentResults) : null;
                }
            }
            if ($relation['type'] == 'isOne') {
                $repo = new MainRepository($relation['table']);
                foreach ($this->subRelations[$relation['name']] as $subRel) {
                    $repo->with($subRel);
                }
                $results = $repo->getAll();
                foreach ($rows as $row) {
                    $currentResults = array_filter($results, function ($item) use ($row, $relation) {
                        return $item->{$relation['foreignKey']} == $row->id;
                    });
                    $row->{$relation['attribute']} =
                        count($currentResults) == 1 ? array_shift($currentResults) : null;
                }
            }
        }
        return $rows;
    }

    function getOne($id)
    {
        if ($id == null) {
            return null;
        }
        $sql = "SELECT * FROM $this->table WHERE id=$id";
        $resp = $this->connect()->query($sql);
        $rows = $resp->fetchAll(PDO::FETCH_CLASS| PDO::FETCH_PROPS_LATE, $this->entity);
        $row = count($rows) == 1 ? $rows[0] : null;
        if ($row == null) {
            return null;
        }

        foreach ($this->relations as $relation) {
            if ($relation['type'] == 'hasMany') {
                $repo = new MainRepository($relation['table']);
                foreach ($this->subRelations[$relation['name']] as $subRel) {
                    $repo->with($subRel);
                }
                $results = $repo->getAll($relation['foreignKey']." = $row->id");
                $row->{$relation['attribute']} = $results;
            }
            if ($relation['type'] == 'hasOne') {
                $repo = new MainRepository($relation['table']);
                foreach ($this->subRelations[$relation['name']] as $subRel) {
                    $repo->with($subRel);
                }
                $result = $repo->getOne($row->{$relation['foreignKey']});
                $row->{$relation['attribute']} = $result;
            }
            if ($relation['type'] == 'isOne') {
                $repo = new MainRepository($relation['table']);
                foreach ($this->subRelations[$relation['name']] as $subRel) {
                    $repo->with($subRel);
                }
                $results = $repo->getAll($relation['foreignKey']." = $row->id");
                $row->{$relation['attribute']} =
                    count($results) == 1 ? array_shift($results) : null;
            }
        }

        return $row;
    }

    function insertOne($fields = [])
    { 
        $columns = "";
        $values = "";
        if (isset($fields['id'])) {
            unset($fields['id']);
        }
        $valuesToBind = array();
        foreach ($fields as $k => $v) {
            $columns .= $k . ",";
            $values .= "?,";
            array_push($valuesToBind, $v);
        }
        $columns = trim($columns, ',');
        $values = trim($values, ',');
        $sql = "INSERT INTO $this->table ($columns) VALUES ($values)";
        $statment = $this->connect()->prepare($sql);
        $result = $statment->execute($valuesToBind);
        $test = $statment->rowCount() == 1;
        if ($result && $test) {
            $insertedId = $this->db->lastInsertId();
            $fields['id'] = $insertedId;
            $entityClass = $this->entity;
            $entity = new $entityClass($fields);
            return $entity;
        }
        return false;
    }

    public function insertMany($rows)
    {
        $firstRow = true;
        $globalArray = [];
        $columns = "";
        $values = "";
        foreach ($rows as $fields) {
            $values .= "(";
            if (isset($fields['id'])) {
                unset($fields['id']);
            }
            $valuesToBind = [];
            foreach ($fields as $k => $v) {
                if ($firstRow) {
                    $columns .= $k . ",";
                }
                $values .= "?,";
                array_push($valuesToBind, $v);
            }
            $values = trim($values, ',') . "),";
            //array_push($globalArray, $valuesToBind);
            $globalArray = array_merge($globalArray, $valuesToBind);
            $firstRow = false;
        }
        $columns = trim($columns, ',');
        $values = trim($values, ',');
        $sql = "INSERT INTO $this->table ($columns) VALUES $values";
        $statment = $this->connect()->prepare($sql);
        $result = $statment->execute($globalArray);
        $test = $statment->rowCount() == count($rows);
        if ($result && $test) {
            // $insertedId = $this->db->lastInsertId();
            // $fields['id'] = $insertedId;
            // $entityClass = $this->entity;
            // $entity = new $entityClass($fields);
            // return $entity;
            return true;
        }
        return false;
    }

    function updateOne($fields)
    { //TODO updateWhere
        $set = "";
        $valuesToBind = array();
        $id = $fields['id'];
        unset($fields['id']);
        foreach ($fields as $k=>$v) {
            $set .= $k."=?,";
            array_push($valuesToBind, $v);
        }
        $set = trim($set, ",");
        $where = "id = ?";
        array_push($valuesToBind, $id);
        $sql = "UPDATE $this->table SET $set WHERE $where";
        $statment = $this->connect()->prepare($sql);
        $result = $statment->execute($valuesToBind);
        $test = $statment->rowCount() == 1;
        if ($result && $test) {
            $entityClass = $this->entity;
            $fields['id'] = $id;
            $entity = new $entityClass($fields);
            return $entity;
        }
        return false;
    }

    function with($name, $withArray = [])
    {
        $relationToAdd = $this->entity::$relations[$name];
        $relationToAdd['name'] = $name;
        array_push($this->relations, $relationToAdd);
        $this->subRelations[$name] = $withArray;
        return $this;
    }   

    private function describe()
    {
        $sql = "DESCRIBE $this->table";
        $resp = $this->connect()->query($sql);
        $results = $resp->fetchAll(PDO::FETCH_ASSOC);
        $columns = [];
        foreach ($results as $result) {
            $columns[$result['Field']] = $result;
        }
        return $columns;
    }

    function validate(&$inputs)
    {
        $errors = [];
        $columns = $this->describe();
        foreach ($inputs as $k => $v) {
            $value = filter_var($v, FILTER_SANITIZE_STRING);
            $column = $columns[$k] ?? null;
            if (!isset($column) || $value == 'null') {
                unset($inputs[$k]);
                continue;
            }
            $type = $column['Type'];
            $type = explode('(', $type)[0];
            if (in_array($type, ["float"])) {
                $filtered = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                if ($filtered != $value || empty($filtered)) {
                    $errors[$k] = true;
                }
            }
            if (in_array($type, ["int"])) {
                $filtered = filter_var($value, FILTER_SANITIZE_NUMBER_INT);
                if ($filtered != $value || empty($filtered)) {
                    $errors[$k] = true;
                }
            }
            $name = $column['Field'];
            if ($name == 'login') {
                $filtered = filter_var($value, FILTER_VALIDATE_EMAIL);
                if ($filtered != $value || empty($filtered)) {
                    $errors[$k] = true;
                }
            }
        }
        return $errors;
    }
}
