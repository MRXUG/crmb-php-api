<?php

namespace app\controller\api\coupon;

use app\common\repositories\applet\WxAppletRepository;
use app\common\repositories\coupon\StockProductRepository;
use crmeb\basic\BaseController;



class BestOfferCoupon extends BaseController
{
    /**
     * 商品推优
     *
     * @param $id
     * @param StockProductRepository $productRepository
     *
     * @return mixed
     * @author  wanglei <wanglei@vchangyi.com>
     * @date    2023/3/7 16:29
     */
    public function productBestOffer($id, StockProductRepository $productRepository)
    {
        $params = $this->request->params(['mer_id']);

        return app('json')->success($productRepository->productBestOffer($id, $params['mer_id']));
    }
}