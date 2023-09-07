<?php

namespace app\controller\merchant\store\shipping;

use think\App;
use crmeb\basic\BaseController;
use app\validate\merchant\PostageTemplateValidate;
use app\common\repositories\store\shipping\PostageTemplateRepository;

class ShippingTemplateController extends BaseController
{
    protected $repository;

    /**
     * ShippingTemplate constructor.
     * @param App $app
     * @param PostageTemplateRepository $repository
     */
    public function __construct(App $app, PostageTemplateRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * @api mer/store/shippingTemplate/list
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function list()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['type','name']);
        return app('json')->success($this->repository->list($this->request->merId(),$where, $page, $limit));
    }

    /**
     * 简化模板 用新表数据
     * @return mixed
     */
    public function allList()
    {
        $list = $this->repository->getList($this->request->merId());
        return app('json')->success($list);
    }

    /**
     * @api mer/store/shippingTemplate/create
     * @param PostageTemplateValidate $validate
     * @return mixed
     */
    public function create(PostageTemplateValidate $validate)
    {
        $data = $this->checkParams($validate);
        $data['mer_id'] = $this->request->merId();
        $this->repository->create($data);
        return app('json')->success('添加成功');
    }

    /**
     * @api mer/store/shippingTemplate/detail
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function detail($id)
    {
        if(!$this->repository->merExists($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        return app('json')->success($this->repository->detail($id));
    }

    /**
     * @api mer/store/shippingTemplate/update
     * @param $id
     * @param PostageTemplateValidate $validate
     * @return mixed
     * @throws \think\db\exception\DbException
     */
    public function update($id,PostageTemplateValidate $validate)
    {
        $data = $this->checkParams($validate);
        if(!$this->repository->merExists($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        $data['mer_id'] = $this->request->merId();
        $this->repository->update($id,$data);

        return app('json')->success('编辑成功');
    }

    /**
     * @api mer/store/shippingTemplate/delete
     * @param $id
     * @return mixed
     * @throws \think\db\exception\DbException
     */
    public function delete($id)
    {
        if(!$this->repository->merExists($this->request->merId(),$id))
            return app('json')->fail('数据不存在');
        if($this->repository->getProductUse($this->request->merId(),$id))
            return app('json')->fail('模板使用中，不能删除');
        $this->repository->delete($id);
        return app('json')->success('删除成功');
    }

    /**
     * @param PostageTemplateValidate $validate
     * @return array
     */
    private function checkParams(PostageTemplateValidate $validate)
    {
        $data = $this->request->params(['name','type','rules']);
        $validate->check($data);
        return $data;
    }
}
