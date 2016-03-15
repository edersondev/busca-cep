<?php

include 'connectiondb.php';

class Wscep
{
    public $cep;
    public $strOutput;
    public $outputFormat;
    private $db;
    private $uf;
    
    public function __construct($cep = null, $outputFormat = null)
    {
        $this->db = new Database();
        $er = '/^[0-9]{5,5}([- ]?[0-9]{3,3})?$/';
        if ( isset($cep) && !empty($cep) && preg_match($er, $cep) ) {
            $this->cep = $cep;
            $this->formatarCep();
        }
        
        if ( !empty($outputFormat) ){
            $this->outputFormat = $outputFormat;
        }
    }
    
    private function pesquisarLogIndex()
    {
        $cep5 = substr($this->cep, 0, 5);
        $this->db->query("SELECT * FROM `cep_log_index` where `cep5` = :cep5");
        $this->db->bind(':cep5', $cep5);
        $result = $this->db->fetch();
        if ( $result ){
            $this->uf = $result['uf'];
        } else {
            $this->pesquisarCepUnico();
        }
    }
    
    private function pesquisarCepUnico()
    {
        $this->db->query("SELECT `Nome` as logradouro, `UF` as uf FROM `cep_unico` where `Cep` = :cep");
        $this->db->bind(':cep', $this->cep);
        
        $result = $this->db->fetch();
        if ( $result ){
            $result['resultado'] = 1;
            $result['resultado_txt'] = 'sucesso - cep completo';
            $this->strOutput = $result;
        } else {
            throw new Exception('Cep não encontrado');
        }
    }
    
    private function getAddress()
    {
        if (empty($this->cep)) {
            throw new Exception('Cep invalido');
        }
        
        $this->pesquisarLogIndex();
        
        if ( $this->uf && empty($this->strOutput) ){            
            $this->db->query("SELECT `cidade`, `logradouro`, `bairro`, `cep`, `tp_logradouro` FROM `{$this->uf}` where `cep` = :cep");
            $this->db->bind(':cep', $this->cep);
            $result = $this->db->fetch();
            if ( $result ){
                $result['logradouro'] = ( $result['logradouro'] > 2 ? $result['logradouro'] : $result['tp_logradouro'] . ' ' . $result['logradouro']);
                $result['uf'] = strtoupper($this->uf);
                $result['resultado'] = 1;
                $result['resultado_txt'] = 'sucesso - cep completo';
                $this->strOutput = $result;
            } else {
                throw new Exception('Endereço não encontrado');
            }
        }
    }
    
    private function mascaraCep($mascara,$string)
    {
        $str = str_replace(" ","",$string);
        for($i=0;$i<strlen($str);$i++){
            $mascara[strpos($mascara,"#")] = $str[$i];
        }
        return $mascara;
    }
    
    private function formatarCep()
    {
        $pos = strpos($this->cep, '-');
        if ($pos === false) {
            $this->cep = $this->mascaraCep('#####-###', $this->cep);
        }
    }
    
    private function setOutputFormat($arrData)
    {
        $output = '';
        switch ($this->outputFormat){
            case 'json':
                $output = json_encode($arrData);
            break;
            case 'xml':
                $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><webservicecep></webservicecep>');
                $arrFlip = array_flip($this->encodeUtf8($arrData));
                array_walk_recursive($arrFlip, array ($xml, 'addChild'));
                $output = $xml->asXML();
            break;
            // query_string
            default:
                $output = http_build_query($arrData);
            break;
        }
        return $output;
    }
    
    public function output()
    {
        header('Content-Type: text/html; charset=utf-8');
        
        try {
            $this->getAddress();
            $output = $this->setOutputFormat($this->strOutput);
            return $output;
        } catch (Exception $exc) {
            $arrData = array(
                'resultado' => 0,
                'resultado_txt' => $exc->getMessage()
            );
            $output = $this->setOutputFormat($arrData);
            return $output;
        }
    }
}

$getCep = filter_input(INPUT_GET, 'cep');
$getFormat = filter_input(INPUT_GET, 'format');
$objWscep = new Wscep($getCep, $getFormat);
echo $objWscep->output();
