<?php

namespace Estratos\NamesiloApi\Service;



class RequestService
{

    private $endpoint;
    private $metodo;
    private $json;
    private $token;
    private $bearer;

    public function __construct( $endpoint, $metodo = null, $json = null, $token = null, $bearer = false)

    {
        $this->endpoint = $endpoint;
        $this->metodo = $metodo;
        $this->json = $json;
        $this->token = $token;
        $this->bearer = $bearer;

    }

    public function RequestApi() {
        $ch = curl_init($this->endpoint);
        if (isset($this->metodo))    {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->metodo);    
        }
        if (isset($this->json)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->json);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        if (isset( $this->token ) and ($this->bearer == false  )) {      
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Content-Length: ' . strlen($this->json), 'x-auth: ' . $this->token));
        }
        if (isset( $this->token)  and ( $this->bearer == true ))  {
            //Set your auth headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token
        ));

        curl_setopt($ch, CURLOPT_URL, $this->endpoint);

        }
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }
}