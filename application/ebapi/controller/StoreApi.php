<?php
namespace app\ebapi\controller;


use app\admin\model\system\SystemAttachment;
use app\core\model\routine\RoutineCode;//待完善
use app\ebapi\model\article\Banner;
use app\ebapi\model\store\StoreCategory;
use app\ebapi\model\store\StoreMerchant;
use app\ebapi\model\store\StoreOrderCartInfo;
use app\ebapi\model\store\StoreProduct;
use app\ebapi\model\store\StoreProductAttr;
use app\ebapi\model\store\StoreProductRelation;
use app\ebapi\model\store\StoreProductReply;
use app\core\util\GroupDataService;
use service\JsonService;
use app\core\util\SystemConfigService;
use service\UploadService;
use service\UtilService;
use app\core\util\MiniProgramService;
use think\Cache;
use think\Db;

/**
 * 小程序产品和产品分类api接口
 * Class StoreApi
 * @package app\ebapi\controller
 *
 */
class StoreApi extends AuthController
{

    public static function whiteList()
    {
        return [
            'banner_list',
            'goods_search',
            'get_routine_hot_search',
            'get_pid_cate',
            'get_product_category',
            'get_product_list',
            'details',
            'get_merchant_index',
            'get_best_product',
            'get_other_product',
            'get_id_cate'
        ];
    }

    /**
     * 分类搜索页面
     * @param Request $request
     * @return \think\response\Json
     */
    public function banner_list($type=1)
    {
        return JsonService::successful(Banner::getBannerList($type));
    }

    /**
     * 分类搜索页面
     * @param Request $request
     * @return \think\response\Json
     */
    public function goods_search()
    {
        list($keyword) = UtilService::getMore([['keyword',0]],null,true);
        return JsonService::successful(StoreProduct::getSearchStorePage($keyword,$this->uid));
    }

    /**
     * 惠农产品首页，获取店铺列表
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_merchant_index(){
        $merInfo = StoreMerchant::getMerchantIndex($this->userInfo['uid']);

        $banner = GroupDataService::getData('benifit_hot_banner')?:[];//TODO 首页banner图
        return $this->successful(compact('merInfo','banner'));
    }

    /**
     * 分类页面
     * @param Request $request
     * @return \think\response\Json
     */
    public function store1(Request $request)
    {
        $data = UtilService::postMore([['keyword',''],['cid',''],['sid','']],$request);
        $keyword = addslashes($data['keyword']);
        $cid = intval($data['cid']);
        $sid = intval($data['sid']);
        $category = null;
        if($sid) $category = StoreCategory::get($sid);
        if($cid && !$category) $category = StoreCategory::get($cid);
        $data['keyword'] = $keyword;
        $data['cid'] = $cid;
        $data['sid'] = $sid;
        return JsonService::successful($data);
    }
    /**
     * 一级分类
     * @return \think\response\Json
     */
    public function get_pid_cate(){
        $data = StoreCategory::pidByCategory(0,'id,cate_name');//一级分类
        if(Cache::has('one_pid_cate_list'))
            return JsonService::successful(Cache::get('one_pid_cate_list'));
        else{
            Cache::set('one_pid_cate_list',$data);
            return JsonService::successful($data);
        }
    }
    /**
     * 二级分类
     * @param Request $request
     * @return \think\response\Json
     */
    public function get_id_cate(){
        $data['id'] = 0;
        $dataCateA = [];
        $dataCateA[0]['id'] = $data['id'];
        $dataCateA[0]['cate_name'] = '全部商品';
        $dataCateA[0]['pid'] = 0;
        $dataCateE = StoreCategory::pidBySidList($data['id']);//根据一级分类获取二级分类
        if($dataCateE) $dataCateE = $dataCateE->toArray();
        $dataCate = [];
        $dataCate = array_merge_recursive($dataCateA,$dataCateE);
        return JsonService::successful($dataCate);
    }
    /**
     * 分类页面产品
     * @param string $keyword
     * @param int $cId
     * @param int $sId
     * @param string $priceOrder
     * @param string $salesOrder
     * @param int $news
     * @param int $first
     * @param int $limit
     * @return \think\response\Json
     */
    public function get_product_list()
    {
        $data = UtilService::getMore([
            ['sid',0],
            ['cid',0],
            ['mid',0],
            ['keyword',''],
            ['priceOrder',''],
            ['salesOrder',''],
            ['news',0],
            ['benefit',0],
            ['page',0],
            ['limit',0]
        ],$this->request);
        return JsonService::successful(StoreProduct::getProductList($data,$this->uid));
    }

    /**
     * @return:
     * @author Handsome Lin
     * @date 2019/11/20 12:32
     * @Notes:商品详情页
     */
    public function details($id=0){
        if(!$id || !($storeInfo = StoreProduct::getValidProduct($id))) return JsonService::fail('商品不存在或已下架');
        //替换windows服务器下正反斜杠问题导致图片无法显示
        $storeInfo['description'] = preg_replace_callback('#<img.*?src="([^"]*)"[^>]*>#i',function ($imagsSrc){
            return isset($imagsSrc[1]) && isset($imagsSrc[0]) ? str_replace($imagsSrc[1],str_replace('\\','/',$imagsSrc[1]),$imagsSrc[0]): '';
        },$storeInfo['description']);

        $storeInfo['userCollect'] = StoreProductRelation::isProductRelation($id,$this->userInfo['uid'],'collect');

        $trip_result=Db::table('eb_store_trip')->where('id','=',$storeInfo['trip_id'])->find();
        //对行程信息的process数据进行梳理
        $storeInfo['trip_id']=$trip_result;
        list($productAttr,$productValue) = StoreProductAttr::getProductAttrDetail($id);
        setView($this->userInfo['uid'],$id,$storeInfo['cate_id'],'viwe');

        $data['storeInfo'] = StoreProduct::setLevelPrice($storeInfo,$this->uid,true);
        $data['similarity'] = StoreProduct::cateIdBySimilarityProduct($storeInfo['cate_id'],'id,store_name,image,price,sales,ficti',4);
        $data['productAttr'] = $productAttr;
        $data['productValue'] = $productValue;
        $data['priceName']=StoreProduct::getPacketPrice($storeInfo,$productValue);
        $data['reply'] = StoreProductReply::getRecProductReply($storeInfo['id']);
        $data['replyCount'] = StoreProductReply::productValidWhere()->where('product_id',$storeInfo['id'])->count();
        if($data['replyCount']){
            $goodReply=StoreProductReply::productValidWhere()->where('product_id',$storeInfo['id'])->where('product_score',5)->count();
            $data['replyChance']=bcdiv($goodReply,$data['replyCount'],2);
            $data['replyChance']=bcmul($data['replyChance'],100,3);
        }else $data['replyChance']=0;
        $mer_id = StoreProduct::where('id',$storeInfo['id'])->value('mer_id');
        $data['merInfo'] = StoreMerchant::getMerchantInfo($mer_id,$this->userInfo['uid']);
        return JsonService::successful($data);
    }

    /*
     * 获取产品是否收藏
     *
     * */
    public function get_product_collect($product_id=0)
    {
        return JsonService::successful(['userCollect'=>StoreProductRelation::isProductRelation($product_id,$this->userInfo['uid'],'collect')]);
    }
    /**
     * 获取产品评论
     * @param int $productId
     * @return \think\response\Json
     */
    public function get_product_reply($productId = 0){
        if(!$productId) return JsonService::fail('参数错误');
        $replyCount = StoreProductReply::productValidWhere()->where('product_id',$productId)->count();
        $reply = StoreProductReply::getRecProductReply($productId);
        return JsonService::successful(['replyCount'=>$replyCount,'reply'=>$reply]);
    }

    /**
     * 添加点赞
     * @param string $productId
     * @param string $category
     * @return \think\response\Json
     */
    public function like_product($productId = '',$category = 'product'){
        if(!$productId || !is_numeric($productId))  return JsonService::fail('参数错误');
        $res = StoreProductRelation::productRelation($productId,$this->userInfo['uid'],'like',$category);
        if(!$res) return  JsonService::fail(StoreProductRelation::getErrorInfo());
        else return JsonService::successful();
    }

    /**
     * 取消点赞
     * @param string $productId
     * @param string $category
     * @return \think\response\Json
     */
    public function unlike_product($productId = '',$category = 'product'){
        if(!$productId || !is_numeric($productId)) return JsonService::fail('参数错误');
        $res = StoreProductRelation::unProductRelation($productId,$this->userInfo['uid'],'like',$category);
        if(!$res) return JsonService::fail(StoreProductRelation::getErrorInfo());
        else return JsonService::successful();
    }

    /**
     * 添加收藏
     * @param $productId
     * @param string $category
     * @return \think\response\Json
     */
    public function collect_product($productId,$category = 'product'){
        if(!$productId || !is_numeric($productId)) return JsonService::fail('参数错误');
        $res = StoreProductRelation::productRelation($productId,$this->userInfo['uid'],'collect',$category);
        if(!$res) return JsonService::fail(StoreProductRelation::getErrorInfo());
        else return JsonService::successful();
    }

    /**
     * 批量收藏
     * @param string $productId
     * @param string $category
     * @return \think\response\Json
     */
    public function collect_product_all($productId = '',$category = 'product'){
        if($productId == '') return JsonService::fail('参数错误');
        $productIdS = explode(',',$productId);
        $res = StoreProductRelation::productRelationAll($productIdS,$this->userInfo['uid'],'collect',$category);
        if(!$res) return JsonService::fail(StoreProductRelation::getErrorInfo());
        else return JsonService::successful('收藏成功');
    }

    /**
     * 取消收藏
     * @param $productId
     * @param string $category
     * @return \think\response\Json
     */
    public function uncollect_product($productId,$category = 'product'){
        if(!$productId || !is_numeric($productId)) return JsonService::fail('参数错误');
        $res = StoreProductRelation::unProductRelation($productId,$this->userInfo['uid'],'collect',$category);
        if(!$res) return JsonService::fail(StoreProductRelation::getErrorInfo());
        else return JsonService::successful();
    }

    /**
     * 获取收藏产品
     * @param int $first
     * @param int $limit
     * @return \think\response\Json
     */
    public function get_user_collect_product($page = 0,$limit = 8)
    {
        return JsonService::successful(StoreProductRelation::getUserCollectProduct($this->uid,$page,$limit));
    }
    /**
     * 获取收藏产品删除
     * @param int $first
     * @param int $limit
     * @return \think\response\Json
     */
    public function get_user_collect_product_del($pid=0)
    {
        if($pid){
            $list = StoreProductRelation::where('uid',$this->userInfo['uid'])->where('product_id',$pid)->delete();
            return JsonService::successful($list);
        }else
            return JsonService::fail('缺少参数');
    }

    /**
     * 获取订单内的某个产品信息
     * @param string $uni
     * @param string $productId
     * @return \think\response\Json
     */
    public function get_order_product($unique = ''){
        if(!$unique || !StoreOrderCartInfo::be(['unique'=>$unique]) || !($cartInfo = StoreOrderCartInfo::where('unique',$unique)->find())) return JsonService::fail('评价产品不存在!');
        return JsonService::successful($cartInfo);
    }

    /**
     * 获取一级和二级分类
     * @return \think\response\Json
     */
    public function get_product_category()
    {
        return JsonService::successful(StoreCategory::getProductCategory());
    }

    /**
     * 获取产品评论
     * @param string $productId
     * @param int $first
     * @param int $limit
     * @param int $type
     * @return \think\response\Json
     */
    public function product_reply_list($productId = '',$page = 0,$limit = 8, $type = 0)
    {
        if(!$productId || !is_numeric($productId)) return JsonService::fail('参数错误!');
        $list = StoreProductReply::getProductReplyList($productId,(int)$type,$page,$limit);
        return JsonService::successful($list);
    }

   /**
     * @Modify: Mr. Lin
     * @function: 获取好评、中评、差评数和评论总数
     * @instructions: 
     * @param {type} $productId(商品id)
     * @return: JSON
     */
    public function product_reply_count($productId = '')
    {
        if(!$productId) return JsonService::fail('缺少参数');
        return JsonService::successful(StoreProductReply::productReplyCount($productId));
    }

    /**
     * 获取商品属性数据
     * @param string $productId
     * @return \think\response\Json
     */
    public function product_attr_detail($productId = '')
    {
        if(!$productId || !is_numeric($productId)) return JsonService::fail('参数错误!');
        list($productAttr,$productValue) = StoreProductAttr::getProductAttrDetail($productId);
        return JsonService::successful(compact('productAttr','productValue'));

    }

    /*
    * 获取产品海报
    * @param int $id 产品id
    * */
    public function poster($id = 0){
//        if(!$id) return JsonService::fail('参数错误');
//        $productInfo = StoreProduct::getValidProduct($id,'store_name,id,price,image,code_path');
//        if(empty($productInfo)) return JsonService::fail('参数错误');
//        if(strlen($productInfo['code_path'])< 10) {
//            $path = 'public'.DS.'uploads'.DS.'codepath'.DS.'product';
//            $codePath = $path.DS.$productInfo['id'].'.jpg';
//            if(!file_exists($codePath)){
//                if(!is_dir($path)) mkdir($path,0777,true);
//                $res = file_put_contents($codePath,RoutineCode::getPages('pages/goods_details/index?id='.$productInfo['id']));
//            }
//            $res = StoreProduct::edit(['code_path'=>$codePath],$id);
//            if($res) $productInfo['code_path'] = $codePath;
//            else return JsonService::fail('没有查看权限');
//        }
//        $posterPath = createPoster($productInfo);
//        return JsonService::successful($posterPath);
    }

    /**
     * 产品海报二维码
     * @param int $id
     */
    public function product_promotion_code($id = 0){
        if(!$id) return JsonService::fail('参数错误ID不存在');
        $count = StoreProduct::validWhere()->count();
        if(!$count) return JsonService::fail('参数错误');
        $name = $id.'_'.$this->userInfo['uid'].'_'.$this->userInfo['is_promoter'].'_product.jpg';
        $imageInfo = SystemAttachment::getInfo($name,'name');
        $siteUrl = SystemConfigService::get('site_url').DS;
        if(!$imageInfo){
            $data='id='.$id;
            if($this->userInfo['is_promoter'] || SystemConfigService::get('store_brokerage_statu')==2) $data.='&pid='.$this->uid;
            $res = RoutineCode::getPageCode('pages/goods_details/index',$data,280);
            if(!$res) return JsonService::fail('二维码生成失败');
            $imageInfo = UploadService::imageStream($name,$res,'routine/product');
            if(!is_array($imageInfo)) return JsonService::fail($imageInfo);
            if($imageInfo['image_type'] == 1) $remoteImage = UtilService::remoteImage($siteUrl.$imageInfo['dir']);
            else $remoteImage = UtilService::remoteImage($imageInfo['dir']);
            if(!$remoteImage['status']) return JsonService::fail($remoteImage['msg']);
            SystemAttachment::attachmentAdd($imageInfo['name'],$imageInfo['size'],$imageInfo['type'],$imageInfo['dir'],$imageInfo['thumb_path'],1,$imageInfo['image_type'],$imageInfo['time']);
            $urlCode = $imageInfo['dir'];
        }else $urlCode = $imageInfo['att_dir'];
        if($imageInfo['image_type'] == 1) $urlCode = $siteUrl.$urlCode;
        return JsonService::successful($urlCode);
    }

    /**
     * 热门搜索
     */
    public function get_routine_hot_search(){
        $routineHotSearch = GroupDataService::getData('routine_hot_search') ? :[];
        return JsonService::successful($routineHotSearch);
    }

    /**
     * @Modify: Mr. Lin
     * @function: 获得best商品
     * @instructions: 
     * @param {type} 
     * @return: JSON
     */
    public function get_best_product($first = 0,$limit = 8){
        return JsonService::successful(StoreProduct::getBestProduct('*',$limit,$first,$this->uid));
    }
    /**
     * @Modify: Mr. Lin
     * @function: 获得best商品
     * @instructions: 
     * @param {type} 
     * @return: JSON
     */
    public function get_other_product($first = 0,$limit = 8){
        return JsonService::successful(StoreProduct::getOtherProduct('*',$limit,$first,$this->uid));
    }
}