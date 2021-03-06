<?php

include_once 'DbManager.php';
include_once 'Argon2.php';

class BaseController {

    function __construct($routeParts){

        $this->method = $_SERVER["REQUEST_METHOD"];
        
        $firstParam = $routeParts[0] ?? null;
        $id = isset($firstParam) ? intval($firstParam) : 0;

        $lastParamIndex = count($routeParts) - 1;
        $lastParam = $routeParts[$lastParamIndex];
        
        //$test = substr($lastParam, 0, strlen('?')) === '?';
        if(substr($lastParam, 0, strlen('?')) === '?'){
            $getParams = array_pop($routeParts);
            if($firstParam == $lastParam){
                $firstParam = null;
            }
        }
        
        $this->body = $this->method == 'GET' ? $_GET : json_decode(file_get_contents('php://input'), true);
        
        $this->action = null;
        switch($this->method){

            case 'GET':
                $this->action = 
                    $id > 0 ? "getOne" : ($firstParam == "all" ? "getAll" : $firstParam);
                break;

            case 'POST':
                $this->action = 
                    $firstParam != null ? $firstParam : "create";
                break;

            case 'PUT':
                $this->action = "update";
                break;

            case 'PATCH':
                $this->action = "softDelete";
                break;

            case 'DELETE':
                $this->action = "hardDelete";
                break;
            
        }

        if (!isset($this->action) || !method_exists(get_called_class(), $this->action))
            die(json_encode(false));

        $this->table = $this->table ?? str_replace("Controller","",lcfirst(get_called_class()));

        //TODO Authorization

        $this->db = new DbManager($this->table);

        $this->jsonResponse = json_encode($this->{$this->action}($routeParts));
  
    }

    public function sendResponse(){
        echo $this->jsonResponse;
    }

    protected function getAll($params){

        array_shift($params);//remove 'all'
        $where = "";
        // foreach($params as $param){
        foreach($this->body as $param){
            $param = urldecode($param);
            $where .= " $param AND";
        }
        $where = substr($where, 0, -3);
        if(!$where) $where = "1";
        
        return $this->db->getAll($where);
    }

    protected function getOne($params){
        $id = array_shift($params);
        $where = "";
        // foreach($params as $param){
        foreach($this->body as $param){
            $param = urldecode($param);
            $where .= " $param AND";
        }
        $where = substr($where, 0, -3);
        if(!$where) $where = "1";

        return $this->db->getOne($id, $where);
    }

    protected function create(){
        return "create an  $this->table";
    }

    protected function update(){
        return "update an  $this->table";
    }

    protected function softDelete(){
        return "softDelete an  $this->table";
    }

    protected function hardDelete(){
        return "hardDelete an  $this->table";
    }


}