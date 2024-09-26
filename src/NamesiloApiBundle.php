<?php
// src/AcmeBlogBundle.php
namespace Estratos\NamesiloApiBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class NamesiloApiBundle extends AbstractBundle

{
    
    private string $tokenkey;

    
    public const BASE_URL = 'https://www.namesilo.com/api/';


    public function getPrices() {

    $endpoint = self::BASE_URL . 'getPrices';
    $json = json_encode(['version'=>'1', 'type'=> 'json', 'key'=>$this->tokenkey]);

    ///// https://www.namesilo.com/api/getPrices?version=1&type=xml&key=12345
    $service = new RequestService($endpoint , null, $json, null, false);


    return $service->RequestApi();

    }

    public function registerDomain() {

    //// https://www.namesilo.com/api/registerDomain?version=1&type=xml&key=12345&domain=namesilo.com&years=2&private=1&auto_renew=1


    }


    public function renewDomain() {
     //// https://www.namesilo.com/api/renewDomain?version=1&type=xml&key=12345&domain=namesilo.com&years=2


    }




    /**
     * Get the value of tokenkey
     */ 
    public function getTokenkey()
    {
        return $this->tokenkey;
    }

    /**
     * Set the value of tokenkey
     *
     * @return  self
     */ 
    public function setTokenkey($tokenkey)
    {
        $this->tokenkey = $tokenkey;

        return $this;
    }
}