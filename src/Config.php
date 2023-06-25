<?php

namespace Nece\Brawl\Pns\Axb\Huawei;

use Nece\Brawl\ConfigAbstract;

class Config extends ConfigAbstract
{
    public function buildTemplate()
    {
        $this->addTemplate(true, 'appKey', 'appKey', 'AXB应用 APP_KEY');
        $this->addTemplate(true, 'appSecret', 'appSecret', 'AXB应用 APP_SECRET');
        $this->addTemplate(true, 'relationNum', '中间号码', '指定已申请到的X号码进行绑定。');
        $this->addTemplate(false, 'duration', '绑定关系保持时间', '单位为秒。取值范围：0~7776000（90天）。绑定关系过期后会被系统自动解除。如果不携带该参数或携带为0，系统默认永不过期。');
        $this->addTemplate(false, 'callDirection', '允许的呼叫方向', '0：bidirectional，表示callerNum和calleeNum都可以通过X号码呼叫对方。 1：caller to callee，表示只允许callerNum通过X号码呼叫calleeNum。2：callee to caller，表示只允许calleeNum通过X号码呼叫callerNum。');
        $this->addTemplate(false, 'recordFlag', '通话录音', '0：表示不录音1：表示录音。该参数仅当客户添加应用时申请开通了录音功能才有效。');
    }
}
