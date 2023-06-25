<?php

namespace Nece\Brawl\Pns\Axb\Huawei;

use GuzzleHttp\Client;
use Nece\Brawl\ClientAbstract;
use Nece\Brawl\Pns\Axb\AxbInterface;
use Nece\Brawl\Pns\Axb\BindParameter;
use Nece\Brawl\Pns\Axb\BindResult;
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
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-25
     *
     * @param BindParameter $bindParameter
     *
     * @return BindResult
     */
    public function bind(BindParameter $bindParameter): BindResult
    {
        // 请求Body
        $data = array(
            'callerNum' => $this->buildNumber($bindParameter->getPhoneA()),
            'calleeNum' => $this->buildNumber($bindParameter->getPhoneB()),
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
            $data['callDirection'] = $callDirection;
        }

        $uri = '/rest/caas/relationnumber/partners/v1.0';
        try {
            $options = $this->buildRequestOptions($data);
            $response = $this->getClient()->post($uri, $options);
            $json = $response->getBody()->getContents();
        } catch (Throwable $e) {
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
     *
     * @Author nece001@163.com
     * @DateTime 2023-06-25
     *
     * @param string $bind_id 绑定ID（传空解绑所有与x号码全部现有绑定）
     *
     * @return void
     */
    public function unbind(string $bind_id)
    {
        // 请求Body
        if ($bind_id) {
            $data = array('subscriptionId' => $bind_id);
        } else {
            $data = array('relationNum' => $this->buildNumber($this->getConfigValue('relationNum', '')));
        }

        $uri = '/rest/caas/relationnumber/partners/v1.0';

        try {
            $options = $this->buildRequestOptions($data);
            $this->getClient()->delete($uri, $options);
        } catch (Throwable $e) {
        }
    }

    /**
     * 构建号码格式
     *
     * @author gjw
     * @created 2022-07-23 15:02:59
     *
     * @param string $mobile
     * @return string
     */
    protected function buildNumber($mobile)
    {
        return '+86' . $mobile;
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
        return array(
            'headers' => $this->buildWsseHeader(),
            'json' => $data
        );
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
