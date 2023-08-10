<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------


namespace crmeb\services\easywechat\certficates;


use crmeb\exceptions\WechatException;
use crmeb\services\easywechat\BaseClient;
use EasyWeChat\Core\AbstractAPI;
use think\exception\InvalidArgumentException;
use think\facade\Cache;

class Client extends BaseClient
{
    public function get()
    {
        $driver = Cache::store('file');
        $cacheKey = '_wx_new_v3' . $this->app['config']['service_payment']['serial_no'];
        $cacheKeyOld = '_wx_v3' . $this->app['config']['service_payment']['serial_no'];
        if ($driver->has($cacheKey)) {
            return $driver->get($cacheKey);
        }
        $certficates = $this->getCertficates();
        $driver->set($cacheKey, $certficates, 3600 * 24 * 30);
        $driver->set($cacheKeyOld, $certficates, 3600 * 24 * 30);
        return $certficates;
    }

    /**
     * get certficates.
     *
     * @return array
     */
    public function getCertficates()
    {
        $response = $this->request('/v3/certificates', 'GET', [], false);
        if (isset($response['code']))  throw new WechatException($response['message']);
        $certificates = $response['data'][0];
        $certificates['certificates'] = $this->decrypt($certificates['encrypt_certificate'],1);
        unset($certificates['encrypt_certificate']);
        return $certificates;
    }

    public static function parseCertificateSerialNo($certificate)
    {
        $info = \openssl_x509_parse($certificate);
        if (!isset($info['serialNumber']) && !isset($info['serialNumberHex'])) {
            throw new \InvalidArgumentException('证书格式错误');
        }

        $serialNo = '';
        // PHP 7.0+ provides serialNumberHex field
        if (isset($info['serialNumberHex'])) {
            $serialNo = $info['serialNumberHex'];
        } else {
            // PHP use i2s_ASN1_INTEGER in openssl to convert serial number to string,
            // i2s_ASN1_INTEGER may produce decimal or hexadecimal format,
            // depending on the version of openssl and length of data.
            if (\strtolower(\substr($info['serialNumber'], 0, 2)) == '0x') { // HEX format
                $serialNo = \substr($info['serialNumber'], 2);
            } else { // DEC format
                $value = $info['serialNumber'];
                $hexvalues = ['0','1','2','3','4','5','6','7',
                    '8','9','A','B','C','D','E','F'];
                while ($value != '0') {
                    $serialNo = $hexvalues[\bcmod($value, '16')].$serialNo;
                    $value = \bcdiv($value, '16', 0);
                }
            }
        }

        return \strtoupper($serialNo);
    }
}
