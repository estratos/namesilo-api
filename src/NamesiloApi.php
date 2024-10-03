<?php
// src/AcmeBlogBundle.php
namespace Estratos\NamesiloApi;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Estratos\NamesiloApi\Service\RequestService;

class NamesiloApi extends AbstractBundle

{

    private string $tokenkey;


    public const BASE_URL = 'https://www.namesilo.com/api/';


    public function getPrices()
    {

        $endpoint = self::BASE_URL . 'getPrices';
        $endpoint = $endpoint . $this->getParams();

        ///// https://www.namesilo.com/api/getPrices?version=1&type=xml&key=12345
        $service = new RequestService($endpoint, null, null, null, false);


        return $service->RequestApi();
    }

    public function registerDomain()
    {

        $endpoint = self::BASE_URL . 'registerDomain';
        $endpoint = $endpoint . $this->getParams();

        //// https://www.namesilo.com/api/registerDomain?version=1&type=xml&key=12345&domain=namesilo.com&years=2&private=1&auto_renew=1
        $service = new RequestService($endpoint, null, null, null, false);

        return $service->RequestApi();
    }


    public function renewDomain()

    {
        $endpoint = self::BASE_URL . 'renewDomain';
        $endpoint = $endpoint . $this->getParams();

        //// https://www.namesilo.com/api/renewDomain?version=1&type=xml&key=12345&domain=namesilo.com&years=2
        $service = new RequestService($endpoint, null, null, null, false);

        return $service->RequestApi();
    }


    public function listDomains()
    {


        $endpoint = self::BASE_URL . 'listDomains';
        $endpoint = $endpoint . $this->getParams();
        // https://www.namesilo.com/api/listDomains?version=1&type=xml&key=12345&withBid=1&pageSize=10

        $service = new RequestService($endpoint, null, null, null, false);

        return $service->RequestApi();
    }

    public function getAccountBalance()
    {

        $endpoint = self::BASE_URL . 'getAccountBalance';
        $endpoint = $endpoint . $this->getParams();
        //https: //www.namesilo.com/api/getAccountBalance?version=1&type=xml&key=12345

        $service = new RequestService($endpoint);

        return $service->RequestApi();
    }


    public function addAccountFunds(float $amount, int $paymentid)
    {

        /// validate amount
        if (!is_float($amount)) return 'amount must be float type';
        /// we may limit to 2 digit precision

        $endpoint = self::BASE_URL . 'addAccountFunds';
        $endpoint = $endpoint . $this->getParams() . '&amount=' . (string)$amount . '&paiment_id' . (string)$paymentid;
        //  https://www.namesilo.com/api/addAccountFunds?version=1&type=xml&key=12345&amount=65.43&payment_id=123


        $service = new RequestService($endpoint);

        return $service->RequestApi();
    }


    private function getParams()
    {

        return '?' . 'version=1' . '&' . 'type=json' . '&' . 'key=' . $this->tokenkey;
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
