<?php

namespace Nece\Brawl\Pns\Axb\Huawei;

use GuzzleHttp\Client;
use Nece\Brawl\ClientAbstract;
use Nece\Brawl\Pns\Axb\AxbInterface;
use Nece\Brawl\Pns\Axb\BindResult;
use Nece\Brawl\Pns\PhoneNumber;
use Nece\Brawl\Pns\PnsException;
use Throwable;

/**
 * 华为AXB模式
 *
 * @Author nece001@163.com
 * @DateTime 2023-06-25
 */
class Axb extends ClientAbstract implements AxbInterface
{
    /**
     * API地址
     *
     * @var string
     * @author gjw
     * @created 2022-07-23 14:42:00
     */
    private $base_uri = 'https://rtcpns.cn-north-1.myhuaweicloud.com';

    /**
     * 获取客户端
     *
     * @var Client
     * @Author nece001@163.com
     * @DateTime 2023-06-25
     */
    private $client;

    /**
     * 获取客户端
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-25
     *
     * @return Client
     */
    public function getClient()
    {
        if (!$this->client) {

            $conf = array(
                'base_uri' => $this->base_uri,
                'timeout' => 10
            );

            $this->client = new Client($conf);
        }
        return $this->client;
    }


    /**
     * 绑定
     * 文档：https://support.huaweicloud.com/api-PrivateNumber/privatenumber_02_0002.html
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-28
     *
     * @param PhoneNumber $a
     * @param PhoneNumber $b
     * @param array $custom_data
     *
     * @return BindResult
     */
    public function bind(PhoneNumber $a, PhoneNumber $b, array $custom_data = array()): BindResult
    {
        // 请求Body
        $data = array(
            'callerNum' => $a->toNumber(),
            'calleeNum' => $b->toNumber()
        );

        $relationNum = $this->getConfigValue('relationNum', '');
        if ($relationNum) {
            $data['relationNum'] = $relationNum;
        }

        $duration = $this->getConfigValue('duration');
        if (!is_null($duration)) {
            $duration = intval($duration);
            $duration = $duration > 7776000 ? 7776000 : $duration;
            $data['duration'] = $duration;
        }

        $record = $this->getConfigValue('recordFlag');
        if (!is_null($record)) {
            $data['recordFlag'] = $record ? true : false;
        }

        $callDirection = $this->getConfigValue('callDirection');
        if (!is_null($callDirection)) {
            $data['callDirection'] = intval($callDirection);
        }

        if ($custom_data) {
            $data['userData'] = json_encode($custom_data, JSON_UNESCAPED_UNICODE);
        }

        $uri = '/rest/caas/relationnumber/partners/v1.0';
        try {
            $options = $this->buildRequestOptions($data);
            $response = $this->getClient()->post($uri, $options);
            $json = $response->getBody()->getContents();
        } catch (Throwable $e) {
            throw new PnsException('华AXB绑定异常：'.$e->getMessage(), $e->getCode());
        }

        $data = json_decode($json, JSON_UNESCAPED_UNICODE);
        if (!$data) {
            throw new PnsException('华为云AXB结果解析失败：' . json_last_error_msg(), json_last_error());
        }

        if ($data['resultcode'] == '0') {
            $result = new BindResult();
            $result->setBindId($data['subscriptionId']);
            $result->setPhoneX($data['relationNum']);
            return $result;
        } else {
            throw new PnsException('华为云AXB绑定失败：' . $data['resultdesc'], $data['resultcode']);
        }
    }

    /**
     * 解绑
     * 文档：https://support.huaweicloud.com/api-PrivateNumber/privatenumber_02_0003.html
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-25
     *
     * @param string $bind_id 绑定ID（传空解绑所有与x号码全部现有绑定）
     *
     * @return bool
     */
    public function unbind(string $bind_id): bool
    {
        // 请求Body
        if ($bind_id) {
            $data = array('subscriptionId' => $bind_id);
        } else {
            $data = array('relationNum' => $this->getConfigValue('relationNum', ''));
        }

        $uri = '/rest/caas/relationnumber/partners/v1.0';

        try {
            $options = $this->buildRequestOptions($data);
            $this->getClient()->delete($uri, $options);
            return true;
        } catch (Throwable $e) {
            throw new PnsException('华为云AXB绑定失败：' . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * 构建请求参数
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-25
     *
     * @param array $data
     *
     * @return array
     */
    protected function buildRequestOptions(array $data)
    {
        $data = array(
            'headers' => $this->buildWsseHeader(),
            'json' => $data
        );

        $proxy = array();
        $http_proxy = $this->getConfigValue('http_proxy');
        $https_proxy = $this->getConfigValue('https_proxy');
        if ($http_proxy) {
            $proxy['http'] = $http_proxy;
        }
        if ($https_proxy) {
            $proxy['https'] = $http_proxy;
        }

        if ($proxy) {
            $data['proxy'] = $proxy;
        }

        return $data;
    }

    /**
     * 构建X-WSSE值
     *
     * @param string $appKey
     * @param string $appSecret
     * @return string
     */
    protected function buildWsseHeader()
    {
        $appKey = $this->config['appKey'];
        $appSecret = $this->config['appSecret'];

        $dateUtc = new \DateTime("now", new \DateTimeZone("UTC"));
        $Created = $dateUtc->format('Y-m-d\TH:i:s\Z');
        $nonce = uniqid(); //Nonce
        $base64 = base64_encode(hash('sha256', ($nonce . $Created . $appSecret), TRUE)); //PasswordDigest

        $wsse = sprintf("UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"", $appKey, $base64, $nonce, $Created);

        $header = [
            'Accept: application/json',
            'Content-Type: application/json;charset=UTF-8',
            'Authorization: WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
            'X-WSSE: ' . $wsse
        ];

        return $header;
    }
}
