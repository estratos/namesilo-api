<?php
// src/AcmeBlogBundle.php
namespace Estratos\NamesiloApi\NamesiloApiBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class NamesiloApiBundle extends AbstractBundle

{

    private string $tokenkey;


    public const BASE_URL = 'https://www.namesilo.com/api/';


    public function getPrices()
    {

        $endpoint = self::BASE_URL . 'getPrices';
        $json = json_encode(['version' => '1', 'type' => 'json', 'key' => $this->tokenkey]);

        ///// https://www.namesilo.com/api/getPrices?version=1&type=xml&key=12345
        $service = new RequestService($endpoint, null, $json, null, false);


        return $service->RequestApi();
    }

    public function registerDomain()
    {

        $endpoint = self::BASE_URL . 'registerDomain';
        $json = json_encode(['version' => '1', 'type' => 'json', 'key' => $this->tokenkey]);

        //// https://www.namesilo.com/api/registerDomain?version=1&type=xml&key=12345&domain=namesilo.com&years=2&private=1&auto_renew=1
        $service = new RequestService($endpoint, null, $json, null, false);

        return $service->RequestApi();
    }


    public function renewDomain()

    {   $endpoint = self::BASE_URL . 'renewDomain';
        $json = json_encode(['version' => '1', 'type' => 'json', 'key' => $this->tokenkey]);
        //// https://www.namesilo.com/api/renewDomain?version=1&type=xml&key=12345&domain=namesilo.com&years=2
        $service = new RequestService($endpoint, null, $json, null, false);

        return $service->RequestApi();
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
