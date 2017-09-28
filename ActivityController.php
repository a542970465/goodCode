<?php
/**
 * Created by PhpStorm.
 * User: xp
 * Date: 2017/9/23
 * Time: 上午9:52
 */

namespace App\Http\Controllers\V1\Activity;

use App\Entity\Activity;
use App\Entity\ActivityMember;
use App\Entity\ActivityPrize;
use App\Entity\ActivityPrizeItem;
use App\Entity\ActivityPrizeTemplet;
use App\Entity\ActivityStore;
use App\Entity\Coupon;
use App\Entity\Prize;
use App\Entity\Promotion;
use App\Entity\Store;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    // 推荐活动列表
    public function recommendList(Request $request)
    {
        $u_lon = $request->input('u_lon');
        $u_la = $request->input('u_la');
        $activity = Activity::where('status', Activity::STATUS_SUCCESS)
            ->orderBy('join_member')->get()->toArray();


        $begin_time = time();

        for($i = 0; $i < count($activity); $i++)
        {
            $store = Store::where('id', $activity[$i]['store_id'])->get()->toArray();

            $distance[] = $this->getDistance($u_la, $u_lon, $store[0]['latitude'], $store[0]['longitude']);

            $end_time = strtotime($activity[$i]['e_at']);

            $res = $this->timediff($begin_time, $end_time);

            $data[] =
                [

                    'id'            =>  $activity[$i]['id'],
                    'end_time'      =>  $res['day'] + 1,
                    'join_num'      =>  $activity[$i]['join_member'],
                    'name'          =>  $activity[$i]['name'],
                    'image'         =>  $activity[$i]['image'],
                    'distance'      =>  round($distance[$i]),
                    'store_name'    =>  $activity[$i]['store_name'],
                    'store_id'      =>  $activity[$i]['store_id']

                ];
        }

        return $this->json(1, '成功', $data);

    }

    // 活动介绍
    public function intro(Request $request)
    {

        $time = time();
        $activity = Activity::where('id', $request->input('activity_id'))->first();

        if(!$activity)
        {
            return $this->json(-1,'活动不存在');
        }

        $activity_store = ActivityStore::where('activity_id', $activity->id)
            ->where('status', ActivityPrize::STATUS_SUCCESS)
            ->get()->toArray();

        // 赞助方
        if (!$activity_store) {

            $sponsor_num = 0;
            $sponsor[] =
                [
                    'sponsor_name' => '',
                    'sponsor_avatar' => ''
                ];

        } elseif (count($activity_store)  > 0) {

                $sponsor_num = count($activity_store);

                for ($i = 0; $i < count($activity_store); $i++)
                {
                    $sponsor[] =
                        [
                           'sponsor_name' => $activity_store[$i]['store_name'],
                            'sponsor_avatar' => $activity_store[$i]['store_avatar']
                        ];
                }
        }

        // 剩余时间
        $end_time = strtotime($activity->e_at);


        $res = $this->timediff($time, $end_time);

        $store = Store::query()->where('id', $activity->store_id)->first();

        $data = [

            'activity_image'        =>      $activity->iamge,
            'name'                  =>      $activity->name,
            'end_time'              =>      $res['day'] + 1,
            'join_member'           =>      $activity->join_member,
            'rule_type'             =>      $activity->rule_type,
            'join_type'             =>      $activity->join_type,
            'store_avatar'          =>      $activity->store_avatar,
            'store_name'            =>      $activity->store_name,
            'sponsor_num'           =>      $sponsor_num,
            'sponsor'               =>      $sponsor,
            'region'                =>      $store->area_name . $store->address,

        ];

        return $this->json(1, '', $data);

    }


//     参与活动门店


    public function joinStore(Request $request)
    {

        $u_lon = $request->input('u_lon');
        $u_la = $request->input('u_la');

        $activity = Activity::where('id', $request->input('activity_id'))
            ->first();

        if(!$activity)
        {

            return '没有此活动';
        }

        if ($activity->type == Activity::TYPE_SOLE)
        {
            $store = Store::where('id', $activity->store_id)->first();

            $promotion = Promotion::where('store_id', $activity->store_id)->get()->toArray();

            $distance = $this->getDistance($u_la, $u_lon, $store['latitude'], $store['longitude']);

            if (!$promotion)
            {
                $promotionName = Promotion::NOT_HAVE_PROMOTION;
            } else {

                for ($i = 0; $i < count($promotion); $i++)
                {

                    $promotionName[] = $promotion[$i]['name'];
                }
            }

            $data =
                [
                    'store_image' => $store->image,
                    'store_name' => $activity->store_name,
                    'region' => $store->area_name . $store->address,
                    'promotion' => $promotionName,
                    'distance' => round($distance)

                ];
        }
        elseif ($activity->type == Activity::TYPE_UNION)
        {
            $activity_store = ActivityStore::where('activity_id', $request->input('activity_id'))
                ->get()
                ->toArray();

            if (!$activity_store)
            {
                return '获取参与商店失败';
            }

            for ($i = 0; $i < count($activity_store); $i++)
            {
                $store = Store::where('id', $activity_store[$i]['store_id'])->first();

                $distance = $this->getDistance($u_la, $u_lon, $store->latitude, $store->longitude);

                $promotion = Promotion::where('store_id', $activity_store[$i]['store_id'])->get()->toArray();

                $promotionInfo = array();

                if (!$promotion)
                {
                    $promotionInfo = -1;

                } else {

                    for ($i = 0; $i < count($promotion); $i++)
                    {

                        $promotionInfo[] =
                            [
                                'promotion_name'        =>      $promotion[$i]['name'],
                                'promotion_type'        =>      $promotion[$i]['type'],
                                'promotion_user_type'   =>      $promotion[$i]['user_type'],
                                'promotion_time_info'   =>      $promotion[$i]['time_info'],
                                'promotion_time_type'   =>      $promotion[$i]['time_type'],
                                'promotion_money'       =>      $promotion[$i]['money'],
                                'promotion_min_money'   =>      $promotion[$i]['min_money']
                            ];
                    }
                }

                $data[] =
                    [
                        'store_image' => $store->image,
                        'store_name' => $store->name,
                        'region' => $store->area_name . $store->address,
                        'promotion' => $promotionInfo,
                        'distance' => round($distance)
                    ];
            }

        }

        return $this->json(1, '成功', $data);
    }


    //奖品

    public function prize(Request $request)
    {
        $activity_prize = ActivityPrize::where('activity_id', $request->input('activity_id'))
            ->where('status', ActivityPrize::STATUS_SUCCESS)
            ->get()
            ->toArray();


        if (!$activity_prize) {

            return $this->json(-1, '奖品方案未通过' );
        }

        $activity_prize_templet = ActivityPrizeTemplet::where('activity_id', $request->input('activity_id'))->get()->toArray();

        for ($j = 0; $j < count($activity_prize_templet); $j++)
        {
            $res[] =
                [
                    'level' => $activity_prize_templet[$j]['level'],
                    'number' => $activity_prize_templet[$j]['number']
                ];

        }

        $data = array();
        for ($i = 0; $i < count($activity_prize); $i++)
        {
            $activity_prize_item = ActivityPrizeItem::where('activity_prize_id', $activity_prize[$i]['id'])
                ->get()
                ->toArray();

            for ($j = 0; $j < count($activity_prize_item); $j++)
            {
                $coupon = Coupon::where('id', $activity_prize_item[$j]['coupon_id'])->first();

                $data[]  =
                    [
                        'store_name'        =>      $coupon->store_name,
                        'store_id'          =>      $coupon->store_id,
                        'prize_name'        =>      $activity_prize_item[$j]['name'],
                        'coupon_id'         =>      $activity_prize_item[$j]['coupon_id'],
                        'coupon_name'       =>      $activity_prize_item[$j]['coupon_name'],
                        'level'             =>      $activity_prize_item[$j]['level'],
                        'coupon_type'       =>      $activity_prize_item[$j]['coupon_type'],
                        'coupon_number'     =>      $activity_prize_item[$j]['coupon_number'],
                        'min_money'         =>      $coupon->min_money,
                        'money'             =>      $coupon->money,
                        'is_sync'           =>      $coupon->is_sync,
                        'is_index'          =>      $coupon->is_index

                    ];
            }

        }


            return $this->json(1, 'ok', [$res,$data]);

    }

//     消费排名
//    public function activityRank(Request $request)
//    {
//        $model = ActivityMember::where('activity_id', $request->input('activity_id'));
//
//        $activity_member = $model
//            ->orderBy('use_money', 'desc')
//            ->get()
//            ->toArray();
//
//        $user_id = $this->userid();
//
//        $activity_user = $model->where('user_id', $user_id)->first();
//
//        $use_money = $activity_user->use_money;
//
//        foreach($activity_member as $k => $v)
//        {
//
//            if ($use_money == $v['use_money'])
//            {
//                $data =
//                    [
//                        'rank'    => $k + 1,
//                        'use_money' => $v['use_money']
//                    ];
//            }
//
//        }
//
//        return $this->json(1, 'ok', $data);
//
//    }

// 消费排名

    public function activityRank(Request $request)
    {

        $model = Activity::where('id', $request->input('activity_id'))->first();

        $res = $this->getActivityMemberBase($model);

//        echo '<pre>';
//        var_dump($res->get()->toArray());
//        die();

        // 获取等级有几个
        $activity_prize_templet = ActivityPrizeTemplet::where('activity_id', $request->input('activity_id'));
        $count_level = count($activity_prize_templet->get()->toArray());

        //1.对应的等级有几个名额
        for ($i = 1; $i <= $count_level; $i++)
        {
            $activity_prize_templet = ActivityPrizeTemplet::where('activity_id', $request->input('activity_id'))
                ->where('level', $i)
                ->first()
                ->toArray();

            $level_number[$i] = $activity_prize_templet['number'];
        }

        // 判断活动规则,根据活动规则统计自己的消费金额是单次最高消费还是累计统计消费过的金额

        if ($model->rule_type == Activity::RULE_TYPE_ONE_CONSUME) {

            //活动规则是单次消费
            //计算个人消费
            $user_use_money = ActivityMember::where('user_id', $this->user_id)
            ->orderBy('use_money', 'desc')
            ->first()
            ->use_money;

            //统计总共有几个名额
            $sum = array_sum($level_number);

            //获取第$sum名用户消费的金额,前提:根据所有用户的单次最高消费进行排序
            $res = ActivityMember::groupBy('user_id')
                ->orderBy('use_money')
                ->max('use_money');

               if ($user_use_money > $res[$sum]) {

               	for($i = $count_level; $i >= 1; $i++)
               	{

               		if ($uesr_use_money > $res[$sum - $level_number[$i]]) {


               	} else {

               		// 距离奖品所差的金额
               		// 如果是负数代表已经有获得一等奖的资格了,前端拿到数据判断一下是否为负数即可

               		$diff_money = $res[$sum-$level_number[$i]] - $user_use_money;

               	}

               	}

               } else {
               		
               		// 距离获得奖品所差的金额
               		$diff_money = $res[$sum]['use_money'] - $user_use_money;

               }


        } elseif ($model->rule_type == Activity::RULE_TYPE_COUNT_CONSUME) {

        	//活动规则是累计消费
        	//个人消费就是本活动历史消费
        	$user_use_money = ActivityMember::where('user_id', $this->user_id)
        		->get()
        		->toArray();

        		for ($i = 0; $i < count($user_use_money); $i++)
        		{
        			$user_use_money += $user_use_money[$i]['use_money'];
        		}

        		//获取第$sum名用户累计消费的金额,前提:根据所有用户的累计消费金额进行排序
        		 $res = ActivityMember::groupBy('user_id')
                ->orderBy('use_money')
                ->sum('use_money');

                 if ($user_use_money > $res[$sum]) {

               	for($i = $count_level; $i >= 1; $i++)
               	{

               		if ($uesr_use_money > $res[$sum - $level_number[$i]]) {


               	} else {

               		// 距离奖品所差的金额
               		// 如果是负数代表已经有获得一等奖的资格了,前端拿到数据判断一下是否为负数即可

               		$diff_money = $res[$sum-$level_number[$i]] - $user_use_money;

               	}

               	}

               } else {
               		
               		// 距离获得奖品所差的金额
               		$diff_money = $res[$sum]['use_money'] - $user_use_money;

               }



        }

        return $diff_money,$res[''];
    }

    //参赛记录
    public function activityLog(Request $request)
    {
//        $user_id = $this->userid();

        $user_id = 4;

        $model = ActivityMember::where('user_id', $user_id)
            ->where('activity_id', $request->input('activity_id'));

        $activity_member = $model->get()->toArray();

        if(!$activity_member){

            return $this->json(-1, '没有参赛记录');
        }

        $activity = Activity::where('id', $request->input('activity_id'))->first();

        if ($activity->rule_type == Activity::RULE_TYPE_ONE_CONSUME) {

            $max_money_model = $model->orderBy('use_money', 'desc')->first();

            $res[] =
                [
                    'max_money' => $max_money_model->use_money,
                    'max_money_time' => $max_money_model->created_at
                ];

        } elseif($activity->rule_type == Activity::RULE_TYPE_COUNT_CONSUME) {

            $max_money_model = $model->orderBy('created_at', 'desc')->get()->toArray();

            static $max_money;
            for ($i = 0; $i < count($max_money_model); $i++)
            {
                $max_money += $max_money_model[$i]['use_money'];
            }

            $res[] =
                [
                    'max_money' => $max_money,
                    'max_money_time' => $max_money_model[0]['created_at']
                ];
        }

        for ($i = 0; $i < count($activity_member); $i++)
        {
            $store = Store::where('id', $activity_member[$i]['store_id'])->first();

            $data[] =
                [
                    'store_id'      => $activity_member[$i]['store_id'],
                    'use_money'     => $activity_member[$i]['use_money'],
                    'created_at'    => $activity_member[$i]['created_at'],
                    'store_name'    => $store->store_name,
                    'store_avatar'  => $store->store_avatar,
                ];

        }

        $data = array_merge($res, $data);

        return $this->json(1, '成功', $data);
    }

    // 商家详情
    public function storeActivity(Request $request)
    {
        $store = Store::where('id', $request->input('store_id'))->first();

        $store_id = $store->id;
        $store_name = $store->name;
        $store_image = $store->image;
        $store_region = $store->area_name . $store->address;
        $promotion = Promotion::where('store_id', $store_id)->get()->toArray();

        if (!$promotion) {

            $promotion_name = '-1';
        } else {

            for ($i = 0; $i < count($promotion); $i++)
            {
                $promotionInfo[] =
                    [
                        'promotion_name'        =>      $promotion[$i]['name'],
                        'promotion_type'        =>      $promotion[$i]['type'],
                        'promotion_user_type'   =>      $promotion[$i]['user_type'],
                        'promotion_time_info'   =>      $promotion[$i]['time_info'],
                        'promotion_time_type'   =>      $promotion[$i]['time_type'],
                        'promotion_money'       =>      $promotion[$i]['money'],
                        'promotion_min_money'   =>      $promotion[$i]['min_money']
                    ];
            }
        }




//        return $this->json(1, 'ok', $data);

    }

    // 商家详情-券
    public function storeCoupon(Request $request)
    {
        $model = Coupon::where('store_id', $request->input('store_id'))->get()->toArray();

        for($i = 0; $i < count($model); $i++)
        {
            $data[] =
                [
                    'id' => $model[$i]['id'],
                    'image' => $model[$i]['image'],
                    'name'=>$model[$i]['name'],
                    'min_money' => $model[$i]['min_money'],
                    'allow_day' => $model[$i]['allow_day'],
                ];
        }

        return $this->json(1, 'ok', $data);
    }

    // 商家详情-券-领取

    // 商家图像,商家名字,满多少减多少,有效期0000-00-00 至 0000-00-00,券图像,进入商家店铺
    public function storeCouponAdd(Request $request)
    {
        $model = Coupon::where('id', $request->input('coupon_id'))->first();

        $time = time();

        $data =
            [
                'id' => $model->id,
                'store_avatar' => $model->store_avatar,
                'store_name' => $model->store_name,
                'min_money' => $model->min_money,
                'money' => $model->money,
                'stime' => date('Y-m-d', $time),
                'etime' => date('Y-m-d', $time+$model->allow_day*24*3600),
                'image' => $model->image,
                'store_id' => $model->store_id,
                'coupon_type' => $model->type,
                'coupon_desc' => $model->desc
            ];

        return $this->json(1, 'ok', $data);
    }

    //商家详情 -券-执行领取
    public function storeCouponDoAdd(Request $request)
    {
        $coupon = Coupon::where('id', $request->input('coupon_id'))->first();
        $user   = User::where('id', $this->userid())->first();

        $activity_prize_item = ActivityPrizeItem::where('coupon_id', $coupon->id)->first();

        if (!$activity_prize_item)
        {
            return '';
        }

        $time = time();

        $data =
            [

                'sn'                => $coupon->sn,
                'coupon_name'       => $coupon->name,
                'coupon_id'         => $coupon->id,
                'use_at'            => $coupon->allow_day,
                'use_user_id'       => $user->id,
                'use_user_name'     => $user->name,
                'use_user_avatar'   => $user->avatar,
                'status'            => $coupon->status,
                'allow_at'          => date('Y-m-d', $time),
                'over_at'           => date('Y-m-d', $time+$coupon->allow_day*24*3600),
                'store_id'          => $coupon->store_id,
                'store_name'        => $coupon->store_name,
                'store_avatar'      => $coupon->store_avatar,
                'activity_id'       => $activity_prize_item->activity,

            ];

        return $this->json(1, 'ok', $data);
    }



    //功能：计算两个时间戳之间相差的日时分秒
    //$begin_time  开始时间戳
    //$end_time 结束时间戳
    public function  timediff($begin_time,$end_time)
    {
        if($begin_time < $end_time){
            $starttime = $begin_time;
            $endtime = $end_time;
        }else{
            $starttime = $end_time;
            $endtime = $begin_time;
        }

        //计算天数
        $timediff = $endtime-$starttime;
        $days = intval($timediff/86400);
        //计算小时数
        $remain = $timediff%86400;
        $hours = intval($remain/3600);
        //计算分钟数
        $remain = $remain%3600;
        $mins = intval($remain/60);

        $res = array("day" => $days,"hour" => $hours,"min" => $mins);

        return $res;
    }

    /**
     * 计算两个经纬度之间的距离
     */

    public function getDistance($u_la, $u_lon, $s_la, $s_lon)
    {
        // 将角度转为狐度
        $rad_u_la = deg2rad($u_la); //deg2rad()函数将角度转换为弧度
        $rad_s_la = deg2rad($s_la);
        $rad_u_lon = deg2rad($u_lon);
        $rad_s_lon = deg2rad($s_lon);
        $a = $rad_u_la - $rad_s_la;
        $b = $rad_u_lon - $rad_s_lon;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($rad_u_la) * cos($rad_s_la) * pow(sin($b / 2), 2))) * 6378.137 * 1000;

        return $s;

    }

    /**
     * 根据活动表拼接where条件
     * @param Activity $model
     * @return mixed
     */
    protected function getActivityMemberBase(Activity $model)
    {
        $start = (explode(' ',$model->s_at)[0]).' '.$model->stime;
        $end   = (explode(' ',$model->e_at)[0]).' '.$model->etime;
        //查询用户消费表
        if($model->type == Activity::TYPE_UNION)
        {
            $stores = ActivityStore::where('activity_id',$model->id)->pluck('store_id');
            $stores = $stores->toArray();
            $stores[] = $model->store_id;
            switch($model->join_type)
            {
                case Activity::JOIN_TYPE_ARRIVAL:
                    $rankings = ActivityMember::whereIn('store_id',$stores)->where('activity_id',$model->id)->whereBetween('created_at',[$start,$end]);
                    break;

                case Activity::JOIN_TYPE_USER_TYPE:

                    $rankings =   ActivityMember::whereIn('store_id',$stores)->where('activity_id',$model->id)->whereBetween('created_at',[$start,$end]);
                    break;

                case Activity::JOIN_TYPE_FIXED_TIME:
                    $start = strtotime($model->s_at);
                    $end   = strtotime($model->e_at);
                    if($model->join_time_type)
                    {
                        //获取星期方法
                        $weeks = Date::getWeeks($start,$end,$model->join_time_info);
                        $rankings = ActivityMember::whereIn('store_id',$stores)->where('activity_id',$model->id);
                        foreach($weeks as $v)
                        {
                            $start = $v.' 00:00';
                            $end   = $v.' 23:59';
                            $rankings->orWhere(function($query) use ($start,$end)
                            {
                                $query->where('created_at','>=',$start)
                                    ->where('created_at','<=', $end);
                            });
                        }
                    }
                    break;

                case Activity::JOIN_TYPE_CONSUMPTION:
                    $rankings = ActivityMember::whereIn('store_id',$stores)->where('activity_id',$model->id)->where('use_money','>=',$model->join_min_money);
                    break;
                default:
                    break;
            }
        }
        else
        {
            switch($model->join_type)
            {
                case Activity::JOIN_TYPE_ARRIVAL:
                    $rankings = ActivityMember::where('store_id',$model->store_id)->where('activity_id',$model->id)->whereBetween('created_at',[$start,$end]);
                    break;

                case Activity::JOIN_TYPE_USER_TYPE:
                    $rankings =   ActivityMember::where('store_id',$model->store_id)->where('activity_id',$model->id)->whereBetween('created_at',[$start,$end]);
                    break;
                case Activity::JOIN_TYPE_FIXED_TIME:
                    $start = strtotime($model->s_at);
                    $end   = strtotime($model->e_at);
                    if($model->join_time_type)
                    {
                        //获取星期方法
                        $weeks = Date::getWeeks($start,$end,$model->join_time_info);
                        $rankings = ActivityMember::where('store_id',$model->store_id)->where('activity_id',$model->id);
                        foreach($weeks as $v)
                        {
                            $start = $v.' 00:00';
                            $end   = $v.' 23:59';
                            $rankings->orWhere(function($query) use ($start,$end)
                            {
                                $query->where('created_at','>=',$start)
                                    ->where('created_at','<=', $end);
                            });
                        }
                    }
                    break;

                case Activity::JOIN_TYPE_CONSUMPTION:
                    $rankings = ActivityMember::where('store_id',$model->store_id)->where('activity_id',$model->id)->where('use_money','>=',$model->join_min_money);
                    break;
                default:
                    break;
            }
        }

        //规则类型
        if($model->rule_type == Activity::RULE_TYPE_ONE_CONSUME)
        {
            $rankings->selectRaw('id,store_id,user_id,max(use_money) as use_money,user_name,user_avatar,created_at')->orderByRaw('use_money desc')->groupBy('user_id');
        }
        elseif($model->rule_type == Activity::RULE_TYPE_COUNT_CONSUME)
        {
            $rankings->selectRaw('id,store_id,user_id,sum(use_money) as use_money,user_name,user_avatar,created_at')->orderByRaw('use_money desc')->groupBy('user_id');
        }
        return $rankings;
    }

}