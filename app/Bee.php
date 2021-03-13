<?php

namespace App;

use GuzzleHttp\Client;

class Bee
{
    private $user;

    private $password;

    private $client;

    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
        $this->client = new Client([
            'base_uri' => 'http://vibish.com',
            'timeout'  => 20.0,
            'cookies' => true,
            'allow_redirects' => [
                'max'             => 10,        // allow at most 10 redirects.
                'strict'          => true,      // use "strict" RFC compliant redirects.
                'referer'         => true,      // add a Referer header
                'track_redirects' => true
            ],
        ]);
    }

    public function up(){
        $response = $this->client->request('GET', '/');
        $csrfBody = $response->getBody();

        $logonData = $this->getLogonData($csrfBody);

        $response = $this->client->request('POST', '/', [
            'form_params' => $logonData,
        ]);

        if($response->getStatusCode() !== 200){
            throw new \Exception('Something went wrong');
        }

        $response = $this->client->request('POST', '/myanket', []);
        $myanketBody = $response->getBody();

        $results = [];
        foreach($this->getActiveMyanketUrls($myanketBody) as $anketEditUrl){
            $response = $this->client->request('GET', $anketEditUrl);
            $anketBody = $response->getBody();
            $editUrl = $this->getEditUrl($anketBody);

            $response = $this->client->request('GET', $editUrl);
            $formBody = $response->getBody();
            $formData = $this->parseFormData($formBody);
            $formAction = $this->parseFormAction($formBody);
            if($formAction){
                $response = $this->client->request('POST', $formAction, ['multipart'=>$formData]);
            }

            $results[] = $response->getStatusCode();
        }
        return $results;
    }

    private function getActiveMyanketUrls($html){
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR);
        $selector = new \DOMXpath($dom);
        $formXpathElements = "//li[@class='item']//span[@style='background:green; color:#fff']/../../..//a[@itemprop='url']";
        $urls = $selector->query($formXpathElements);
        $result =[];
        foreach ($urls as $url){
            $result[] = $url->getAttribute('href') . $url->tagName;
        }
        unset($dom);
        unset($selector);
        return $result;
    }

    private function getEditUrl($html){
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR);
        $selector = new \DOMXpath($dom);
        $formXpathElements = "//div[@class='change-profile_link']//a";
        $editUrl = $selector->query($formXpathElements)->item(0);
        unset($dom);
        unset($selector);
        return $editUrl->getAttribute('href');
    }

    private function getLogonData($html){
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR);
        $selector = new \DOMXpath($dom);
        $formXpathElements = "//input[@type='hidden']";
        $logonData = [
            'username' => $this->user,
            'password' => $this->password,
        ];
        foreach ($selector->query($formXpathElements) as $item){
            $logonData[$item->getAttribute('name')] = $item->getAttribute('value');
        }
        unset($dom);
        unset($selector);
        return $logonData;
    }

    private function getItem($name, $value){
        if($name === 'config[unique]'){
            return [
                'name' => $name,
                'contents' => 'seblod_form_ankets',
            ];
        }
        if($name === 'task'){
            return [
                'name' => $name,
                'contents'=> 'save2view'
            ];
        }
        return [
            'name' => $name,
            'contents'=> $value,
            ];
    }

    private function getItemImg($url){
        $fileName = array_reverse(explode('/', $url))[0];
        if(!file_exists($fileName)){
            $response = $this->client->request('GET', $url);
            file_put_contents($fileName, $response->getBody()->getContents());
        }
        return fopen($fileName, "r");
    }

    private function parseFormData($html){
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR);
        $selector = new \DOMXpath($dom);
        $formXpathElements = "//form[@id='seblod_form']//input[@type='checkbox'][@checked='checked']|" .
            "//form[@id='seblod_form']//input[@type='hidden']|" .
            "//form[@id='seblod_form']//input[@type='radio'][@checked='checked']|" .
            "//form[@id='seblod_form']//input[@type='text']|" .
            "//form[@id='seblod_form']//select//option[@selected='selected']|" .
            "//form[@id='seblod_form']//textarea|" .
            "//form[@id='seblod_form']//select";
        $form = $selector->query($formXpathElements);
        $formParams = [];
        foreach($form as $element){
            if($element->tagName === 'textarea'){
                $formParams[] = $this->getItem($element->getAttribute('name'), $element->textContent);

            }
            if($element->tagName === 'input'){
                $formParams[] = $this->getItem($element->getAttribute('name'), $element->getAttribute('value'));
            }
            if($element->tagName === 'option'){
                $formParams[] = $this->getItem($element->parentNode->getAttribute('name'), $element->getAttribute('value'));
            }
        }

        $formXpathElementsFile = "//form[@id='seblod_form']//input[@type='file']|" .
            "//a[@id='colorBox5030']";
        $form = $selector->query($formXpathElementsFile);
        $multipart = [];
        foreach($form as $key => $element){
            if($element->tagName === 'input'){
                $multipart[$key] = ['name' => $element->getAttribute('name'), 'filename' => "", 'contents' => null];
            }
            if($element->tagName === 'a' && $multipart[$key -1] ?? false){
                $multipart[$key -1]['contents'] = $this->getItemImg($element->getAttribute('href'));
            }

        }
        unset($dom);
        unset($selector);
        return array_merge($formParams, $multipart);
    }

    private function parseFormAction($html){
        $dom = new \DOMDocument();
        $dom->loadHTML($html, LIBXML_NOERROR);
        $selector = new \DOMXpath($dom);
        $formXpathElements = "//form[@id='seblod_form']";
        $form = $selector->query($formXpathElements)->item(0);
        return $form ? $form->getAttribute('action') : null;
    }

}