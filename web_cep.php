<?php

$url = 'https://apps.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl';
$options = [
    'soap_version'   => SOAP_1_1,
    'trace' => true
];
$client = new SoapClient($url, $options);
var_dump($client->__getFunctions());
var_dump($client->consultaCEP(['cep'=>'73050146']));
