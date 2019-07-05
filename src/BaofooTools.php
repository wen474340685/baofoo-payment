<?php

namespace Baofoo;

/**
 * Class BaofooTools
 * @package app\Transaction\Baofu
 *
 * @author wenqiang<474340685@qq.com>
 * @create_date 2019.06.27
 */
class BaofooTools
{

    public function __construct()
    {
        if (!function_exists( 'bin2hex')) {
            throw new \Exception("bin2hex PHP5.4及以上版本支持此函数，也可自行实现！");
        }
    }

    /**
     * 公钥加密
     * @param   string      $originalData       原始数据
     * @param   string      $publicKeyPath      公钥地址
     * @param   integer     $blockSize          分段长度
     * @return  string
     * @throws \Exception
     */
    public static function encryptByCERFile($originalData, $publicKeyPath, $blockSize = 117)
    {
        $publicKeyObj = self::getPublicKey($publicKeyPath);
        $base64Data = base64_encode($originalData);
        $totalLen = strlen($base64Data);
        $encryptSubStarLen = 0;
        $encryptStr = '';
        $encryptTempData = '';
        while ($encryptSubStarLen < $totalLen){
            openssl_public_encrypt(substr($base64Data, $encryptSubStarLen, $blockSize), $encryptTempData, $publicKeyObj);
            $encryptStr .= bin2hex($encryptTempData);
            $encryptSubStarLen += $blockSize;
        }
        return $encryptStr;
    }

    /**
     * 私钥解密
     * @param   string      $encryptData        加密数据
     * @param   string      $privateKeyPath     私钥地址
     * @param   string      $privateKeyPwd      私钥密码
     * @return  bool|string
     * @throws \Exception
     */
    public static function decryptByPFXFile($encryptData, $privateKeyPath, $privateKeyPwd)
    {
        $privateKeyObj = self::getPrivateKey($privateKeyPath, $privateKeyPwd);
        $totalLen = strlen($encryptData);
        $blockSize = 256; //分段长度
        $encryptSubStarLen = 0;
        $decryptData = '';
        $decryptTempData = '';
        while ($encryptSubStarLen < $totalLen) {
            openssl_private_decrypt( hex2bin( substr($encryptData, $encryptSubStarLen, $blockSize) ), $decryptTempData, $privateKeyObj);
            $decryptData .= $decryptTempData;
            $encryptSubStarLen += $blockSize;
        }
        return base64_decode($decryptData);
    }

    /**
     * 私钥加密
     * @param   string      $originalData       原始数据
     * @param   string      $privateKeyPath     私钥地址
     * @param   string      $privatePwd         私钥密码
     * @param   integer     $blockSize          分段长度
     * @return  string
     * @throws \Exception
     */
    public static function encryptByPrivateKey($originalData, $privateKeyPath, $privatePwd, $blockSize = 32)
    {
        $base64Data = base64_encode($originalData);
        $privateKey = self::getPrivateKey($privateKeyPath, $privatePwd);
        $encryptStr = '';
        $totalLen = strlen($base64Data);
        $encryptTempData = 0;
        while ($encryptTempData < $totalLen){
            openssl_private_encrypt(substr($base64Data, $encryptTempData, $blockSize), $encryptData, $privateKey);
            $encryptStr .= bin2hex($encryptData);
            $encryptTempData += $blockSize;
        }
        return $encryptStr;
    }

    /**
     * 公钥解密
     * @param   string      $encryptData        加密数据
     * @param   string      $publicKeyPath      公钥路径
     * @param   int         $blockSize          分段长度
     * @return  bool|string
     * @throws \Exception
     */
    public static function decryptByPublicKey($encryptData, $publicKeyPath, $blockSize = 256)
    {
        $publicKey = self::getPublicKey($publicKeyPath);
        $decryptStr = '';
        $totalLen = strlen($encryptData);
        $decryptTempData = 0;
        while ($decryptTempData < $totalLen) {
            openssl_public_decrypt(hex2bin(substr($encryptData, $decryptTempData, $blockSize)), $decryptData, $publicKey);
            $decryptStr .= $decryptData;
            $decryptTempData += $blockSize;
        }
        return base64_decode($decryptStr);
    }


    /**
     * 获取信封中的key值
     * @param   string  $string     数字信封内容
     * @return  mixed
     * @throws \ErrorException
     */
    public static function getAesKey($string)
    {
        $keyArr = explode('|', $string);
        if( count($keyArr) == 2 ){
            if ( !empty( trim($keyArr[1]) ) ) {
                return $keyArr[1];
            } else {
                throw new \ErrorException('Key is Null!');
            }
        } else {
            throw new \ErrorException('Data format is incorrect!');
        }
    }

    /**
     * AEC 加密
     * @param   string      $data       待加密的数据
     * @param   string      $aesKey     对称加密Key
     * @return  string
     * @throws \Exception
     */
    public static function aesEncrypt($data, $aesKey)
    {
        if(!(strlen($aesKey) == 16)){
            throw new \Exception('AES密码长度固定为16位！当前KEY长度为：'.  strlen($aesKey));
        }

        $iv = $aesKey; //偏移量与key相同
        $data = base64_encode($data);
//        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $aesKey,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        $encrypted = openssl_encrypt($data, 'AES-128-CBC', $aesKey,true, $iv);
        $data = bin2hex($encrypted);
        return $data;
    }


    /**
     * AES 解密
     * @param   string      $data       需要解密的数据
     * @param   string      $aesKey     对称加密Key
     * @return  string
     * @throws \Exception
     */
    public static function aesDecrypt($data, $aesKey)
    {
        if ( strlen($aesKey) != 16 ) {
            throw new \Exception('AES密码长度固定为16位！当前KEY长度为：'.  strlen($aesKey));
        }
        $iv = $aesKey; //偏移量与key相同
        $data = hex2bin($data);
        $returnData = openssl_decrypt($data, "AES-128-CBC",$aesKey,OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv);
        return $returnData;
    }

    /**
     * RSA签名
     * @param   array       $params             原数据
     * @param   string      $privateKeyPath     私钥路径
     * @param   string      $privateKeyPwd      私钥密码
     * @return  string
     * @throws \Exception
     */
    public static function sign($params, $privateKeyPath, $privateKeyPwd)
    {
        // 生成key=value&key=value格式报文
        $params1 = self::sortAndOutString($params);

        // SHA-1计算
        $requestMessage = urldecode($params1);
        $requestMessage = openssl_digest($requestMessage, 'SHA1'); // SHA1摘要

        // RSA 签名
        $binarySignature = null;
        $pkey = self::getPrivateKey($privateKeyPath, $privateKeyPwd); // 读取密钥
        if ( openssl_sign($requestMessage, $binarySignature, $pkey, OPENSSL_ALGO_SHA1) ) {
            return bin2hex($binarySignature);
        } else {
            throw new \Exception('加签异常！');
        }
    }

    /**
     * md5 签名（余额查询接口使用）
     * @param   array       $params     生成签名所需的参数
     * @return  string
     */
    public static function md5Sign(array $params)
    {
        $paramsStr = strtoupper( md5( http_build_query($params) ) );
        return $paramsStr;
    }

    /**
     * 验证签名自己生成的是否正确
     * @param   array       $params         宝付返回的原文
     * @param   string      $publicKeyPath  公钥路径
     * @param   string      $signature      签名
     * @return  bool
     * @throws \Exception
     */
    public static function verifySign($params, $publicKeyPath, $signature)
    {
        // 生成key=value&key=value格式报文
        $responseMessage = self::sortAndOutString($params);

        // SHA-1计算
        $responseMessage = urldecode($responseMessage);
        $responseMessage = openssl_digest($responseMessage, 'SHA1'); // SHA1摘要

        // 获取公钥
        $publicKeyObj = self::getPublicKey($publicKeyPath);

        // 验签
        $res = openssl_verify($responseMessage, hex2bin($signature), $publicKeyObj);
        if ($res == 1) {
            return true;
        }
        return false;
    }


    /**
     * 生成请求报文
     * @param   array   $params     请求参数
     * @return  string
     */
    public static function sortAndOutString(array $params)
    {
        $messageArr = array();
        foreach ($params as $key => $value){
            if($value && trim($value)){
                $messageArr[$key] = $value;
            }
        }
        ksort($messageArr);//排序
        return http_build_query($messageArr);
    }

    /**
     * 读取公钥
     * @param   string      $path   公钥文件地址
     * @return  resource
     * @throws \Exception
     */
    private static function getPublicKey($path)
    {
        if (!file_exists($path)) {
            throw new \Exception('公钥文件不存在！路径：' . $path);
        }
        $publicKeyContent = file_get_contents($path);
        $publicKey = openssl_get_publickey($publicKeyContent);
        return $publicKey;
    }

    /**
     * 读取私钥
     * @param   string      $path       私钥文件地址
     * @param   string      $pwd        私钥密码
     * @return  mixed
     * @throws \Exception
     */
    private static function getPrivateKey($path, $pwd)
    {
        if (!file_exists($path)) {
            throw new \Exception('私钥文件不存在！路径：' . $path);
        }
        $privateKeyObj = file_get_contents($path);
        $privateKey = [];
        if(openssl_pkcs12_read($privateKeyObj, $privateKey, $pwd)){
            return $privateKey['pkey'];
        }else{
            throw new \Exception('私钥证书读取出错！原因[证书或密码不匹配]，请检查本地证书相关信息。');
        }
    }

    /**
     * xml数据格式转换成数组
     * @param   string      $xml        xml格式数据
     * @return  mixed
     */
    public static function xml2array($xml)
    {
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $array = json_decode( json_encode( simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA) ), true);
        return $array;
    }

    /**
     * 生产代付订单json数据格式
     * @param   array   $order_sn           商户订单号集合
     * @param   array   $transaction_money  代付金额结合，单位：分
     * @param   array   $name               收款人姓名集合
     * @param   array   $bankcard_no        银行卡号集合
     * @param   array   $bankcard_name      银行名称集合
     * @param   array   $mobile             收款人银行预留手机号集合
     * @param   array   $id_card            收款人身份证号集合
     * @return  array
     * @throws \Exception
     */
    public static function makePayrollOrderJson($order_sn, $transaction_money, $name, $bankcard_no, $bankcard_name, $mobile = [], $id_card = [])
    {
        $params = [];
        for ($i = 0; $i < count($order_sn); $i++) {
            if ($transaction_money[$i] <= 0) {
                throw new \Exception('订单金额参数不合法');
            }
            $params[$i] = [
                'trans_no' => $order_sn[$i],
                'trans_money' => round($transaction_money[$i] / 100, 2),
                'to_acc_name' => $name[$i],
                'to_acc_no' => $bankcard_no[$i],
                'to_bank_name' => $bankcard_name[$i]
            ];
            if ($mobile) {
                $params[$i]['trans_mobile'] = $mobile[$i];
            }
            if ($mobile) {
                $params[$i]['trans_card_id'] = $id_card[$i];
            }
        }

        $transactionContent = json_encode([
            'trans_content' => [
                'trans_reqDatas' => [
                    [
                        'trans_reqData' => $params
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        $transactionContent = str_replace("\\\"",'"', $transactionContent);
        return $transactionContent;
    }

    /**
     * 生产代付订单查询xml数据格式
     * @param   array       $order_sn       代付订单号集合
     * @param   array       $batch_id       代付订单号所属宝付批次号集合
     * @return  string
     */
    public static function makeQueryOrderXml($order_sn, $batch_id = [])
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" ?>';
        $xml .= '<trans_content>';
        $xml .= '<trans_reqDatas>';
        for ($i = 0; $i < count($order_sn); $i++) {
            $xml .= '<trans_reqData>';
            $xml .= '<trans_no>' . $order_sn[$i] . '</trans_no>';
            if ($batch_id) {
                $xml .= '<trans_batchid>' . $batch_id[$i] . '</trans_batchid>';
            }
            $xml .= '</trans_reqData>';
        }
        $xml .= '</trans_reqDatas>';
        $xml .= '</trans_content>';
        return $xml;
    }

    /**
     * 生产代付订单查询json数据格式
     * @param   array       $order_sn       代付订单号集合
     * @param   array       $batch_id       代付订单号所属宝付批次号集合
     * @return  mixed|string
     * @throws \Exception
     */
    public static function makeQueryOrderJson($order_sn, $batch_id = [])
    {
        $params = [];
        for ($i = 0; $i < count($order_sn); $i++) {
            $params[$i] = [
                'trans_no' => $order_sn[$i],
            ];
            if ($batch_id) {
                $params[$i]['trans_mobile'] = $batch_id[$i];
            }
        }

        $transactionContent = json_encode([
            'trans_content' => [
                'trans_reqDatas' => [
                    [
                        'trans_reqData' => $params
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE);
        $transactionContent = str_replace("\\\"",'"', $transactionContent);
        return $transactionContent;
    }

    /**
     * 生成报文流水号
     * @param   string      $scene      生成报文场景标识，最长10位
     * @return  string      返回拼接字符串后的流水号，最长32位
     */
    public static function createMsgId($scene = '')
    {
        if (strlen($scene) > 16){
            return '';
        }
        $microtimeStr = microtime(true);
        $microtimeArr = explode('.', $microtimeStr);
        $microtimeArr[1] = strlen($microtimeArr[1]) == 3 ? '0'.$microtimeArr[1] : $microtimeArr[1];
        return $scene . $microtimeArr[0] . $microtimeArr[1] . mt_rand(10, 99);
    }

}