<?php

namespace Baofoo;

use Baofoo\BaofooTools;
use Baofoo\HttpRequest;

/**
 * Class BaofooApi
 * @package app\Transaction\Baofu
 *
 * @author moyang<474340685@qq.com>
 * @create_date 2019.06.27
 */
class BaofooApi
{

    private $api_version = '4.0.0.0'; // 银行卡操作接口，协议支付接口版本号

    private $api_version_loan = '4.0.0'; // 代付接口版本号

    private $api_version_query_balance = '4.0'; // 余额查询接口版本号

    private $protocol_pay_url = 'https://public.baofoo.com/cutpayment/protocol/backTransRequest'; // 协议支付生产地址

    private $merchant_id = ''; // 商户号

    private $terminal_id = ''; // 终端号

    private $public_key_path = ''; // 生产公钥证书路径

    private $private_key_path = ''; // 私钥证书路径

    private $private_key_pwd = ''; // 私钥证书密码

    private $balance_terminal_id = ''; // 余额终端号

    private $balance_secret = ''; // 余额查询密钥

    private $request_data_type = 'json'; // 请求接口数据类型

    public static $industryCategory = [
        '01' => '电商',
        '02' => '互金消金',
        '03' => '航旅',
        '04' => '酒店',
        '05' => '保险',
        '06' => '游戏',
        '07' => '大宗'
    ];

    public static $transaction_type = [
        'pre_bind_card' => '01', //协议支付预绑卡类交易
        'bind_card' => '02', // 协议支付确认绑卡类交易
        'bind_query' => '03', // 查询绑定关系类交易
        'cancel_bind_card' => '04', // 协议支付解除绑卡类交易
        'pre_protocol_pay' => '05', // 协议支付预支付类交易
        'sure_protocol_pay' => '06', // 协议支付确认支付类交易
        'protocol_pay_query' => '07', // 协议支付订单查询类交易
        'protocol_pay' => '08', // 协议支付直接支付类
    ];

    private static $instance = null;

    /**
     * 宝付Api构造函数
     * BaofuApi constructor.
     * @param $merchantConfig
     */
    private function __construct($merchantConfig)
    {
		$this->api_version = $merchantConfig['api_version'] ?? $this->api_version;
		$this->api_version_loan = $merchantConfig['api_version_loan'] ?? $this->api_version_loan;
		$this->api_version_query_balance = $merchantConfig['api_version_query_balance'] ?? $this->api_version_query_balance;
		$this->protocol_pay_url = $merchantConfig['protocol_pay_url'] ?? $this->protocol_pay_url;
		
        $this->merchant_id = $merchantConfig['merchant_id'];
        $this->terminal_id = $merchantConfig['terminal_id'];
        $this->balance_terminal_id = $merchantConfig['balance_terminal_id'];
        $this->public_key_path = $merchantConfig['public_key_path'];
        $this->private_key_path = $merchantConfig['private_key_path'];
        $this->private_key_pwd = $merchantConfig['private_key_pwd'];
        $this->balance_secret = $merchantConfig['balance_secret'];
    }

    /**
     * 获取宝付支付接口实例
     *
     * @param $merchantConfig
     * @return BaofuApi|null
     */
    public static function getInstance($merchantConfig)
    {
        if(!(self::$instance instanceof self)){
            self::$instance = new self($merchantConfig);
        }
        return self::$instance;
    }

    /**
     * 防止对象被复制
     */
    public function __clone(){
        trigger_error('Clone is not allowed !');
    }

    /**
     * 防止反序列化后创建对象
     */
    private function __wakeup(){
        trigger_error('Unserialized is not allowed !');
    }

    // --------------------------------- 这是一条分割线：Protocol pay from here ---------------------------------

    // + -------------------------------------------------------------------------------------- +
    // | 银行卡操作接口、协议支付接口、协议支付订单状态查询接口 响应公共参数解释                |
    // |     [                                                                                  |
    // |         [member_id] => 100025773                      // 商户号                        |
    // |         [terminal_id] => 200001173                    // 终端号                        |
    // |         [version] => 4.0.0.0                          // 报文编号/版本号               |
    // |         [send_time] => 2019-07-04 14:06:16            // 报文发送日期时间              |
    // |         [msg_id] => 135380ebf04f44038c564b2824152505  // 应答报文流水号                |
    // |         [txn_type] => 07                              // 交易类型                      |
    // |         [resp_code] => S                              // 应答码                        |
    // |         [biz_resp_code] => 0000                       // 业务返回码                    |
    // |         [biz_resp_msg] => 交易成功                    // 业务返回说明                  |
    // |     ]                                                                                  |
    // + -------------------------------------------------------------------------------------- +

    /**
     * 宝付预绑卡
     * @param   string      $card_type              卡类型
     * @param   string      $id_card_type           证件类型
     * @param   string      $bank_card_no           银行卡号
     * @param   string      $name                   持卡人姓名
     * @param   string      $papers_no              证件号
     * @param   string      $mobile                 手机号
     * @param   string      $req_reserved1          商户预留字段，自定义传值，接口同步返回此值
     * @param   string      $user_id                商户系统用户唯一标识
     * @param   string      $card_security_code     银行卡安全码
     * @param   string      $card_validity          银行卡有效期
     * @return  string
     * @throws \Exception
     */
    public function preBindCard(string $card_type, string $id_card_type, string $bank_card_no, string $name, string $papers_no, string $mobile, $req_reserved1 = '', $user_id = '', $card_security_code = '', $card_validity = '')
    {

        // 接口参数构造 - 数字证书
        $aesKey = str_random();
        $dgtl_envlp = '01|' . $aesKey;
        $dgtl_envlp = BaofooTools::encryptByCERFile($dgtl_envlp, $this->public_key_path); //公钥加密

        // 接口参数构造 - 账户信息
        $acc_info = $bank_card_no . '|' . $name . '|' . $papers_no . '|' . $mobile . '|' . $card_security_code . '|' . $card_validity;
        $acc_info = BaofooTools::aesEncrypt($acc_info, $aesKey);

        // 接口参数构造 - 报文流水号
        $msgId = BaofooTools::createMsgId('BFYB');

        // 接口参数构造 - 签名参数
        $params = [
            'version' => $this->api_version,
            'member_id' => $this->merchant_id,
            'send_time' => date('Y-m-d H:i:s'), // 报文发送日期时间，必填，发送方发出本报文时的机器日期时间，如 2017-12-19 20:19:19
            'msg_id' => $msgId, // 报文流水号，必填，商户流水号
            'terminal_id' => $this->terminal_id, // 终端号
            'txn_type' => self::$transaction_type['pre_bind_card'], // 交易类型
            'dgtl_envlp' => $dgtl_envlp, // 数字信封格式：01|对称密钥，01代表AES，加密方式：Base64转码后使用宝付的公钥加密
            'card_type' => $card_type, // 卡类型
            'id_card_type' => $id_card_type, // 证件类型
            'acc_info' => $acc_info, // 账户信息，格式：银行卡号|持卡人姓名|证件号|手机号|银行卡安全码|银行卡有效期。加密方式：Base64转码后，使用数字信封指定的方式和密钥加密
            'req_reserved1' => $req_reserved1,
            'user_id' => $user_id
        ];

        // RSA签名 - 生成接口必须的签名参数
        $sign = BaofooTools::sign($params, $this->private_key_path, $this->private_key_pwd);

        // 接口参数构造
        $params['signature'] = $sign;

        // 请求接口
        try {

            $result = HttpRequest::http($this->protocol_pay_url, $params, 'post');

            // 检查接口返回是否异常，包括验签
            $returnDataArr = $this->checkBaofuResponse($result);

            $decryptStr = BaofooTools::decryptByPFXFile($returnDataArr['dgtl_envlp'], $this->private_key_path, $this->private_key_pwd);
            $returnAesKey = BaofooTools::getAesKey($decryptStr); //获取返回的AESkey
            $uniqueCode = base64_decode(BaofooTools::aesDecrypt($returnDataArr['unique_code'], $returnAesKey));
            $returnDataArr['unique_code_decrypt'] = $uniqueCode;
            return $returnDataArr;

        } catch (\Exception $e) {

            return $e->getMessage();

        }

    }

    /**
     * 确认绑卡
     * @param   string      $unique_code            唯一码
     * @param   string      $sms_code               短信验证码
     * @param   string      $req_reserved1          商户预留字段1
     * @return  mixed|string
     * @throws \Exception
     */
    public function bindCard(string $unique_code, string $sms_code, $req_reserved1 = '')
    {
        // 请求地址
        $testUrl = 'https://vgw.baofoo.com/cutpayment/protocol/backTransRequest';
        $prodUrl = 'https://public.baofoo.com/cutpayment/protocol/backTransRequest';

        // 接口参数构造 - 数字证书
        $aesKey = str_random();
        $dgtl_envlp = '01|' . $aesKey;
        $dgtl_envlp = BaofooTools::encryptByCERFile($dgtl_envlp, $this->public_key_path); //公钥加密

        // 接口参数构造 - 预签约唯一码
        $unique_code .=  '|' . $sms_code;
        $unique_code_aes = BaofooTools::aesEncrypt($unique_code, $aesKey); //公钥加密

        // 接口参数构造 - 签名参数
        $params = [
            'version' => $this->api_version,
            'member_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id, // 终端号
            'send_time' => date('Y-m-d H:i:s'), // 报文发送日期时间，必填，发送方发出本报文时的机器日期时间，如 2017-12-19 20:19:19
            'msg_id' => BaofooTools::createMsgId('BFBK'), // 报文流水号，必填，商户流水号
            'txn_type' => self::$transaction_type['bind_card'], // 交易类型
            'dgtl_envlp' => $dgtl_envlp, // 数字信封格式：01|对称密钥，01代表AES，加密方式：Base64转码后使用宝付的公钥加密
            'unique_code' => $unique_code_aes, // 预签约唯一码，加密方式：Base64转码后，使用数字信封指定的方式和密钥加密
            'req_reserved1' => $req_reserved1
        ];

        // RSA签名 - 生成接口必须的签名参数
        $sign = BaofooTools::sign($params, $this->private_key_path, $this->private_key_pwd);

        // 接口参数构造
        $params['signature'] = $sign;

        // 请求接口
        try {

            $result = HttpRequest::http($this->protocol_pay_url, $params, 'post');

            // 检查接口返回是否异常
            $returnDataArr = $this->checkBaofuResponse($result);

            // protocol_no 签约协议号解密
            $decryptStr = BaofooTools::decryptByPFXFile($returnDataArr['dgtl_envlp'], $this->private_key_path, $this->private_key_pwd);
            $returnAesKey = BaofooTools::getAesKey($decryptStr); //获取返回的AESkey
            $protocolNo = base64_decode(BaofooTools::aesDecrypt($returnDataArr['protocol_no'], $returnAesKey));
            $returnDataArr['protocol_no_decrypt'] = $protocolNo;
            return $returnDataArr;

        } catch (\Exception $e) {

            return $e->getMessage();

        }

    }

    /**
     * 直接支付（协议支付）
     * @param   string      $protocol_no            预签约唯一码
     * @param   string      $order_sn               商户订单号，唯一订单号
     * @param   string      $transaction_money      交易金额，单位：分
     * @param   array       $risk_item              风控参数
     * @param   string      $return_url             交易成功通知地址，最多填写三个地址，不同的地址用‘|’连接
     * @param   string      $user_id                商户系统用户ID
     * @param   string      $card_info
     * @return  mixed|string
     * @throws \Exception
     */
    public function protocolPay($protocol_no, $order_sn, $transaction_money, $risk_item, $return_url = '',  $user_id = '', $card_info = '')
    {
        // 校验风控参数
        $riskItemJson = $this->checkRiskItem($risk_item);

        // 接口参数校验 - 商户订单号
        if ( !preg_match('/^[a-zA-Z0-9]{8,50}$/', $order_sn) ) {
            throw new \Exception('参数trans_id不合法');
        }

        // 请求地址
        $testUrl = 'https://vgw.baofoo.com/cutpayment/protocol/backTransRequest';
        $prodUrl = 'https://public.baofoo.com/cutpayment/protocol/backTransRequest';

        // 接口参数构造 - 数字证书
        $aesKey = str_random();
        $dgtl_envlp = '01|' . $aesKey;
        $dgtl_envlp = BaofooTools::encryptByCERFile($dgtl_envlp, $this->public_key_path); // 公钥加密

        // 接口参数构造 - 签约协议号
        $protocol_no_aes = BaofooTools::aesEncrypt($protocol_no, $aesKey); // AES对称加密

        // 接口参数构造 - 信用卡信息
        $card_info_aes = '';
        if ($card_info) {
            $card_info_aes = BaofooTools::aesEncrypt($card_info, $aesKey);
        }

        // 接口参数构造 - 签名参数
        $params = [
            'version' => $this->api_version,
            'member_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id, // 终端号
            'send_time' => date('Y-m-d H:i:s'), // 报文发送日期时间，必填，发送方发出本报文时的机器日期时间，如 2017-12-19 20:19:19
            'msg_id' => BaofooTools::createMsgId('BFZF'), // 报文流水号，必填，商户流水号
            'dgtl_envlp' => $dgtl_envlp, // 数字信封格式：01|对称密钥，01代表AES，加密方式：Base64转码后使用宝付的公钥加密
            'txn_type' => self::$transaction_type['protocol_pay'], // 交易类型
            'protocol_no' => $protocol_no_aes, // 预签约唯一码，加密方式：Base64转码后，使用数字信封指定的方式和密钥加密
            'trans_id' => $order_sn, // 商户订单号，唯一订单号，8-50 位字母和数字,未支付成功的订单号可重复提交
            'txn_amt' => $transaction_money, // 交易金额，单位：分
            'risk_item' => $riskItemJson, // 风控参数
            'return_url' => $return_url, // 交易成功通知地址，最多填写三个地址，不同的地址用‘|’连接
            'card_info' => $card_info_aes, // 当使用信用卡支付时，需上传。格式：信用卡有效期|安全码 加密方式：Base64转码后，使用数字信封指定的方式和密钥加密
            'user_id' => $user_id
        ];

        // RSA签名 - 生成接口必须的签名参数
        $sign = BaofooTools::sign($params, $this->private_key_path, $this->private_key_pwd);

        // 接口参数构造
        $params['signature'] = $sign;

        // 请求接口
        try {

            $result = HttpRequest::http($this->protocol_pay_url, $params, 'post');

            // 检查接口返回是否异常
            $returnDataArr = $this->checkBaofuResponse($result);

            return $returnDataArr;

        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * 协议支付回调内容处理（内含基本校验，验签）
     * @param   string      $result     宝付请求元数据
     * @return  mixed       将宝付请求数据解析成数组返回
     * @throws \Exception
     */
    public function parseProtocolPayNotify($result)
    {
        // 检查接口返回是否异常
        $returnDataArr = $this->checkBaofuResponse($result);
        return $returnDataArr;
    }

    /**
     * 查询协议支付订单状态
     * @param   string      $order_sn               商户订单号
     * @param   string      $order_trans_date       订单提交日期  YYYY-mm-dd HH:ii:ss
     * @return mixed|string
     * @throws \Exception
     */
    public function queryProtocolPayOrder(string $order_sn, string $order_trans_date)
    {
        // 接口参数构造 - 签名参数
        $params = [
            'version' => $this->api_version,
            'member_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id, // 终端号
            'send_time' => date('Y-m-d H:i:s'), // 报文发送日期时间，必填，发送方发出本报文时的机器日期时间，如 2017-12-19 20:19:19
            'msg_id' => BaofooTools::createMsgId('BFQO'), // 报文流水号，必填，商户流水号
            'txn_type' => self::$transaction_type['protocol_pay_query'], // 交易类型
            'orig_trans_id' => $order_sn, // 商户提交的标识支付的唯一原订单号，最长50位
            'orig_trade_date' => $order_trans_date // 格式：yyyy-MM-dd HH:mm:ss，如：2017-12-19 20:19:19
        ];

        // RSA签名 - 生成接口必须的签名参数
        $sign = BaofooTools::sign($params, $this->private_key_path, $this->private_key_pwd);

        // 接口参数构造
        $params['signature'] = $sign;

        // 请求接口
        try {

            $result = HttpRequest::http($this->protocol_pay_url, $params, 'post');

            // 检查接口返回是否异常
            $returnDataArr = $this->checkBaofuResponse($result);

            return $returnDataArr;

        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }


    /**
     * 接口返回公共校验
     * @param   string      $result     接口返回结果
     * @return  mixed                   校验通过，返回传入参数的数组类型
     * @throws \Exception
     */
    private function checkBaofuResponse($result)
    {
        if (!$result) {
            throw new \Exception('没有返回任何结果');
        }

        parse_str($result,$returnDataArr); // 参数解析

        if ( !$returnDataArr['resp_code'] ) {
            throw new \Exception('缺少resp_code参数');
        }
        if ( $returnDataArr['resp_code'] == 'F' || isset($returnDataArr['resp_code']) && $returnDataArr['resp_code'] == 'FF') {
            $biz_resp_code = isset($returnDataArr['biz_resp_code']) ? $returnDataArr['biz_resp_code'] : '';
            $biz_resp_msg = isset($returnDataArr['biz_resp_msg']) ? $returnDataArr['biz_resp_msg'] : '';
            throw new \Exception('失败：' . $biz_resp_msg . '(' . $biz_resp_code .')');
        }
        if ( !$returnDataArr['signature'] ) {
            throw new \Exception('缺少验签参数');
        }

        // 验签
        $returnSign = $returnDataArr['signature']; // 响应签名
        unset($returnDataArr['signature']); // 删除签名字段，得到签名所需的参数
        if ( ! BaofooTools::verifySign($returnDataArr, $this->public_key_path, $returnSign) ) {
            throw new \Exception('验签失败');
        }

        return $returnDataArr;
    }

    /**
     * 风控参数校验
     * @param   array       $risk_item      风控数据集合
     * @return  string
     * @throws \Exception
     */
    private function checkRiskItem(array $risk_item)
    {
        // 校验参数 - 风控参数
        if (!$risk_item || empty($risk_item)) {
            throw new \Exception('缺失必填参数' . 'risk_item');
        }

        // 校验参数 - 风控参数 - 行业类目
        if ( !$risk_item['goods_category'] ) {
            throw new \Exception('缺失风控必填参数goods_category');
        }

        if ( !in_array( $risk_item['goods_category'], array_keys(self::$industryCategory) ) ) {
            throw new \Exception('风控参数goods_category不合法');
        }

        // 接口参数构造 - 风控参数
        $riskItem = array();
        /* ------------- 风控基础参数 ------------- */
        $riskItem['goodsCategory' ] = $risk_item['goods_category']; // 商品类目 详见附录《商品类目》
        $riskItem['userLoginId' ] = isset($risk_item['user_login_id']) ? $risk_item['user_login_id'] : ''; // 用户在商户系统中的登陆名（手机号、邮箱等标识）
        $riskItem['userEmail' ] = isset($risk_item['user_email']) ? $risk_item['user_email'] : ''; // 用户邮箱，用户在商户系统中注册的 邮箱
        $riskItem['userMobile' ] = isset($risk_item['user_mobile']) ? $risk_item['user_mobile'] : ''; // 用户手机号，商户系统中绑定手机号，如有，需要传送
        $riskItem['registerUserName' ] = isset($risk_item['register_user_name']) ? $risk_item['register_user_name'] : ''; // 用户注册姓名
        $riskItem['identifyState' ] = isset($risk_item['identify_state']) ? $risk_item['identify_state'] : ''; // 用户在商户系统是否已实名，1：是 ；0：不是
        $riskItem['userIdNo' ] = isset($risk_item['id_card']) ? $risk_item['id_card'] : ''; // 用户身份证号
        $riskItem['registerTime' ] = isset($risk_item['register_date']) ? $risk_item['register_date'] : ''; // 注册时间，格式为：YYYYMMDDHHMMSS
        $riskItem['registerIp' ] = isset($risk_item['register_ip']) ? $risk_item['register_ip'] : ''; // 用户在商户端注册时留存的IP
        $riskItem['chName' ] = isset($risk_item['card_name']) ? $risk_item['card_name'] : ''; // 持卡人姓名
        $riskItem['chIdNo' ] = isset($risk_item['card_id_card']) ? $risk_item['card_id_card'] : ''; // 持卡人身份证号
        $riskItem['chCardNo' ] = isset($risk_item['card_id']) ? $risk_item['card_id'] : ''; // 持卡人银行卡号
        $riskItem['chMobile' ] = isset($risk_item['card_mobile']) ? $risk_item['card_mobile'] : ''; // 持卡人手机
        $riskItem['chPayIp' ] = isset($risk_item['pay_ip']) ? $risk_item['pay_ip'] : '127.0.0.1'; // 持卡人在支付时的IP地址；如支付场景中，无法获取有 效的持卡人 IP，请直接传参 127.0.0.1 即可
        $riskItem['deviceOrderNo' ] = isset($risk_item['device_order_no']) ? $risk_item['device_order_no'] : ''; // 生成设备指纹的订单号(用 于快捷)，如果和支付订单 号一致，传相同的值
        /* ------------- 风控行业参数 ------------- */
//        $riskItem['game_name'] = '15821798636'; // 充值游戏名称
//        $riskItem['game_prod_type'] = '02'; // 游戏商品类型，01：点券类、02：金币类、03：装备道具类、04：其他
//        $riskItem['game_acct_id'] = ''; // 游戏账户ID
//        $riskItem['game_login_time'] = '20'; // 游戏登录次数，累计最近一个月

        $riskItemJson = json_encode($riskItem); // 加入风控参数(固定为JSON字串)
        return $riskItemJson;
    }

    // --------------------------------- 这是一条分割线：Payroll from here ---------------------------------------------------

    /**
     * 代付
     * @param   array   $order_sn           商户订单号集合
     * @param   array   $transaction_money  代付金额集合，单位：分
     * @param   array   $name               收款人姓名集合
     * @param   array   $bankcard_no        银行卡号集合
     * @param   array   $bankcard_name      银行名称集合
     * @param   array   $mobile             收款人银行预留手机号集合
     * @param   array   $id_card            收款人身份证号集合
     * @return  array
     * @throws \Exception
     */
    public function payroll(array $order_sn, array $transaction_money, array $name, array $bankcard_no, array $bankcard_name, $mobile = [], $id_card = [])
    {
        // 请求地址
        $url = 'https://public.baofoo.com/baofoo-fopay/pay/BF0040001.do'; // 生产
        $url = 'https://paytest.baofoo.com/baofoo-fopay/pay/BF0040001.do'; // 测试

        // 校验条数
        if (count($order_sn) > 5) {
            throw new \Exception('交易请求记录条数超过上限');
        }

        // 接口参数构造 - 请求报文（证书加密）
        $transactionContent = BaofooTools::makePayrollOrderJson($order_sn, $transaction_money, $name, $bankcard_no, $bankcard_name, $mobile, $id_card);
        $dataContent = BaofooTools::encryptByPrivateKey($transactionContent, $this->private_key_path, $this->private_key_pwd);

        // 接口参数构造
        $params = [
            'version' => $this->api_version_loan,
            'member_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id,
            'data_type' => $this->request_data_type,
            'data_content' => $dataContent
        ];

        try {
            // 请求接口
            $result = HttpRequest::http($url, $params, 'post');

            // 接口返回处理
            if (!$result) {
                throw new \Exception('没有返回任何结果');
            }
            if ( count( explode('trans_content', $result) ) > 1) { // 基本信息处理（明文返回）
                $decryptDataArr = json_decode($result, true);
            } else { // 业务逻辑信息处理（密文返回）
                $decryptData = BaofooTools::decryptByPublicKey($result, $this->public_key_path);
                if (! $decryptData) {
                    throw new \Exception('返回内容解密错误');
                }
                $decryptDataArr = json_decode($decryptData, true);
            }

            return $this->apiReturnFormatting(
                $decryptDataArr['trans_content']['trans_head']['return_code'],
                $decryptDataArr['trans_content']['trans_head']['return_msg'],
                isset($decryptDataArr['trans_content']['trans_reqDatas'][0]['trans_reqData']) ? $decryptDataArr['trans_content']['trans_reqDatas'][0]['trans_reqData'] : []
            );

        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * 代付订单回调数据处理
     * @param   string      $notifyXml      宝付回调报文加密串
     * @return  array
     * @throws \Exception
     */
    public function parsePayrollNotify($notifyXml)
    {
        // 返回数据解密，返回统一格式数据
        $decryptData = BaofooTools::decryptByPublicKey($notifyXml, $this->public_key_path);
        if (! $decryptData) {
            throw new \Exception('返回数据解密后为空');
        }

        $returnData = BaofooTools::xml2array($decryptData); // 处理xml数据

        return $this->apiReturnFormatting(
            $returnData['trans_head']['return_code'],
            $returnData['trans_head']['return_msg'],
            isset($returnData['trans_reqDatas']['trans_reqData']) ? $returnData['trans_reqDatas']['trans_reqData'] : []
        );
    }

    /**
     * 查询代付订单
     * @param   array       $order_sn       代付订单号集合
     * @param   array       $batch_id       代付订单号所属宝付批次号集合
     * @return  array|string
     * @throws \Exception
     */
    public function queryPayrollOrder(array $order_sn, array $batch_id = [])
    {
        // 请求地址
        $url = 'https://public.baofoo.com/baofoo-fopay/pay/BF0040002.do'; // 生产
        $url = 'https://paytest.baofoo.com/baofoo-fopay/pay/BF0040002.do'; // 测试

        $this->request_data_type = 'xml';

        // 校验条数
        if (count($order_sn) > 5) {
            throw new \Exception('交易请求记录条数超过上限');
        }

        // 接口参数构造 - 请求报文（证书加密）
        if ($this->request_data_type == 'json') {
            $transactionContent = BaofooTools::makeQueryOrderJson($order_sn, $batch_id); // 生成json格式数据
        } else if($this->request_data_type == 'xml') {
            $transactionContent = BaofooTools::makeQueryOrderXml($order_sn, $batch_id); // 生成xml格式数据
        } else {
            throw new \Exception('请求数据类型参数不正确');
        }
        $dataContent = BaofooTools::encryptByPrivateKey($transactionContent, $this->private_key_path, $this->private_key_pwd);

        // 接口参数构造
        $params = [
            'version' => $this->api_version_loan,
            'member_id' => $this->merchant_id,
            'terminal_id' => $this->terminal_id,
            'data_type' => $this->request_data_type,
            'data_content' => $dataContent
        ];

        // 请求接口
        try {
            $result = HttpRequest::http($url, $params, 'post');

            if (! $result) {
                throw new \Exception('没有返回任何结果');
            }

            if( count( explode('trans_content', $result) ) > 1){
                // 基本信息处理（明文返回）
                $returnData = json_decode($result, true);
            }else{
                // 返回数据解密，返回统一格式数据
                $decryptData = BaofooTools::decryptByPublicKey($result, $this->public_key_path);
                if (! $decryptData) {
                    throw new \Exception('返回数据解密后为空');
                }
                if ($this->request_data_type == 'json') {
                    $returnData = json_decode($decryptData, true);
                } else {
                    $xml2arrData = BaofooTools::xml2array($decryptData); // 处理xml数据
                    $returnData = [
                        'trans_content' => $xml2arrData
                    ];
                }

            }

            return $this->apiReturnFormatting(
                $returnData['trans_content']['trans_head']['return_code'],
                $returnData['trans_content']['trans_head']['return_msg'],
                isset($returnData['trans_content']['trans_reqDatas']['trans_reqData']) ? $returnData['trans_content']['trans_reqDatas']['trans_reqData'] : []
            );

        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    // --------------------------------- 这是一条分割线：Balance query from here ---------------------------------------------------

    /**
     * 商户余额查询
     * @param   int             $account_type       账户类型，默认0，查询所有账户数据
     * @return  array|string
     */
    public function queryMerchantBalance($account_type = 0)
    {
        // 请求地址
        $prodUrl = 'https://public.baofoo.com/open-service/query/service.do';

        // 接口参数构造
        $params = [
            'member_id' => $this->merchant_id,
            'terminal_id' => $this->balance_terminal_id,
            'return_type' => $this->request_data_type,
            'trans_code' => 'BF0001',
            'version' => $this->api_version_query_balance,
            'account_type' => $account_type,
        ];

        // MD5签名 - 生成接口必须的签名参数
        $md5Params = $params;
        $md5Params['key'] = $this->balance_secret;
        $sign = BaofooTools::md5Sign($md5Params, $this->balance_secret);

        // 接口参数构造
        $params['sign'] = $sign;

        // 请求接口
        try {
            $result = HttpRequest::http($prodUrl, $params, 'post');

            if (! $result) {
                throw new \Exception('没有返回任何结果');
            }

            $returnData = json_decode($result, true);

            // TODO：此处应该交由调用业务层处理
            return $this->apiReturnFormatting(
                $returnData['trans_content']['trans_head']['return_code'],
                $returnData['trans_content']['trans_head']['return_msg'],
                isset($returnData['trans_content']['trans_reqDatas']['trans_reqData']) ? $returnData['trans_content']['trans_reqDatas']['trans_reqData'] : []
            );

        } catch (\Exception $e) {
            return $e->getMessage();
        }

    }

    /**
     * 统一返回格式，只有正确的时候才可以调用
     * @param   string      $code       宝付错误码
     * @param   string      $msg        宝付错误信息
     * @param   array       $data       宝付返回数据（处理后的格式）
     * @return array
     */
    private function apiReturnFormatting($code, $msg, array $data = [])
    {
        return [
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ];
    }

}