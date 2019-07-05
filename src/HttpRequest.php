<?php

namespace Baofoo;

/**
 * HTTP请求
 * Class HttpRequest
 * @package app\Transaction
 *
 * @author wenqiang<474340685@qq.com>
 * @create_date 2019.06.27
 */
class HttpRequest
{
    private static $instance = null;

    /**
     * Http请求Header头设置
     * @contentType 'Content-Type:application/json;charset=utf-8'
     * @contentType 'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
     * @contentType 'Content-Type:application/xml;charset=utf-8'
     * @contentType 'Content-Type:multipart/form-data;charset=utf-8'
     *
     * @var array
     */
    private static $header = [
        'Content-Type:application/x-www-form-urlencoded;charset=utf-8'
    ];

    private function __construct(){}

    public static function getInstance()
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Http请求，支持GET、POST，Header头可以自定义，满足不同的接口类型
     * @param string $url               请求URL
     * @param array|string $params      请求参数
     * @param string $type              请求类型
     * @param array $header             Http请求头信息，默认为：Content-Type: application/json; charset=utf-8
     * @return mixed
     */
    public static function http($url, $params, $type = 'get', $header = [])
    {
        $type = strtolower($type);
        return self::$type($url, $params, $header);
    }

    /**
     * 通过CURL发送HTTP POST请求
     * @param string        $url        请求URL
     * @param array|string  $params     请求参数
     * @param array         $header     Http请求头
     * @return mixed|string
     */
    private static function post($url, $params, $header)
    {
        if ( empty($header) ) {
            $header = self::$header;
        }

        $postFields = null;
        foreach ($header as $k=>$v){
            if ( preg_match('/application\/json/', $v) ){
                $postFields = json_encode($params);
                break;
            } elseif ( preg_match('/x-www-form-urlencoded/', $v) ) {
                $postFields = http_build_query($params);
                break;
            } elseif ( $k == (count($header) - 1) ) {
                $postFields = $params;
            }
        }

        $ch = curl_init ();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_TIMEOUT,60);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);

        if (false == $ret) {
            $result = curl_error($ch);
        } else {
            $rsp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . '' . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close($ch);
        return $result;
    }

    /**
     * 通过CURL发送HTTP GET请求
     * @param string        $url        请求URL
     * @param array|string  $params     请求参数
     * @param array         $header     Http请求头
     * @return mixed|string
     */
    private static function get($url, $params, $header = [])
    {
        if (empty($header)) {
            $header = self::$header;
        }

        if (is_array($params)) {
            $params = http_build_query($params);
        }

        $url = $params ? $url . '?' . $params : $url;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $ret = curl_exec($ch);
        if (false == $ret) {
            $result = curl_error(  $ch);
        } else {
            $rsp = curl_getinfo( $ch, CURLINFO_HTTP_CODE);
            if (200 != $rsp) {
                $result = "请求状态 ". $rsp . " " . curl_error($ch);
            } else {
                $result = $ret;
            }
        }
        curl_close($ch);
        return $result;
    }

    private function __clone(){}
}