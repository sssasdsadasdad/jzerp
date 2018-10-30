<?php

namespace app\admin\controller;

use think\Controller;
use app\admin\model\Menu;


class AdminController extends Controller
{

    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub


        //判断是否登录，并定义用户ID常量
        defined('UID') or define('UID', $this->isLogin());
		if($this -> isMobile()){
            $this->redirect('mobile/message/index');
		}
        //检测是否有访问权限
        AuthRuleController::checkRule();


        // 如果不是ajax请求，则读取菜单
        if (!$this->request->isAjax()) {
            // 读取顶部菜单
            $this->assign('_top_menus', Menu::getTopMenu(config('top_menu_max'), '_top_menus'));
            // 读取全部顶级菜单
            $this->assign('_top_menus_all', Menu::getTopMenu('', '_top_menus_all'));
            // 获取侧边栏菜单
            $this->assign('_sidebar_menus', Menu::getSidebarMenu());
            // 获取面包屑导航
//          $url = $this->request->module() . $this->request->controller() . $this->request->action();
//          $this -> assign('urls', $url);
        }

    }


    public function isLogin()
    {
        // 判断是否登录
        if ($uid = is_signin()) {
            // 已登录
            return $uid;
        } else {
            //未登录
            $this->redirect('admin/public/signin');
        }
    }
    private function isMobile()
    {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
        {
            return true;
        }
        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset ($_SERVER['HTTP_VIA']))
        {
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset ($_SERVER['HTTP_USER_AGENT']))
        {
            $clientkeywords = array ('nokia',
                'sony',
                'ericsson',
                'mot',
                'samsung',
                'htc',
                'sgh',
                'lg',
                'sharp',
                'sie-',
                'philips',
                'panasonic',
                'alcatel',
                'lenovo',
                'iphone',
                'ipod',
                'blackberry',
                'meizu',
                'android',
                'netfront',
                'symbian',
                'ucweb',
                'windowsce',
                'palm',
                'operamini',
                'operamobi',
                'openwave',
                'nexusone',
                'cldc',
                'midp',
                'wap',
                'mobile'
            );

            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
            {
                return true;
            }
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset ($_SERVER['HTTP_ACCEPT']))
        {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))))
            {
                return true;
            }
        }

        return false;
    }
    //搜索,排序参数
    //  $keys 关键词key['name', '...'],
    //  $sort['create_time', ['name' => 'r_name'], '...'], 排序
    //	区间查询$between ['create_time' => ['s_time', 'e_time', 'time'], ...] , 传入time则代表转为时间戳,可不传入time
    public function page_parm_request($keys = [], $sort = [], $between = [])
    {

        $request = $this->request->get();
        $map = [];
        $query = [];
        $order = [];
        foreach ($keys as $v) {
            $args = '';
            if (is_array($v)) {
                $args = array_keys($v)[0];
                $v = $v[$args];
            }
            $bol = !empty($args);
            $vs = isset($request[$bol ? $args : $v]) ? $request[$bol ? $args : $v] : '';
            $map[$v] = ['LIKE', '%' . $vs . '%'];

            $query = array_merge($query, [($bol ? $args : $v) => $vs]);
        }

        //排序处理
        foreach ($sort as $s) {
            //如果$s是个数组
            if (is_array($s)) {
                $key = array_keys($s)[0];
                $s = explode(' ', $s[$key]);
                //$o 值
                $o = empty($request[$s[0]]) ? isset($s[1]) ? $s[1] : 'desc' : $request[$s[0]];

                if (isset($request['important']) && $request['important'] == $s[0]) {
                    $order = array_merge([$key => $o], $order);

                } else {
                    $order = array_merge($order, [$key => $o]);

                }
                $arr = [$s[0] => $o];
                $query = array_merge($query, $arr);
            } else {
                $s = explode(' ', $s);
                $o = [$s[0] => empty($request[$s[0]]) ? (isset($s[1]) ? $s[1] : 'desc') : $request[$s[0]]];
                if (isset($request['important']) && $request['important'] == $s[0]) {
                    $order = array_merge($o, $order);
                } else {
                    $order = array_merge($order, $o);
                }
                $query = array_merge($query, $o);

            }
        }
        //区间处理

        foreach ($between as $key => $bt) {
            $r1 = isset($request[$bt[0]]) ? $request[$bt[0]] : '';
            $r2 = isset($request[$bt[1]]) ? $request[$bt[1]] : '';
            $query = array_merge($query, [$bt[0] => $r1]);
            $query = array_merge($query, [$bt[1] => $r2]);
            $r1 = $r1 ? (isset($bt[2]) && $bt[2] == 'time' ? strtotime($request[$bt[0]] ) : $request[$bt[0]]) : '';  //第一个参数获取的值
            $r2 = $r2 ? (isset($bt[2]) && $bt[2] == 'time' ? strtotime($request[$bt[1]] . " +1 day") : $request[$bt[1]]) : '';
           
            $bt_arr = [];
            if ($r1 && $r2) {
                $bt_arr = ['BETWEEN', [$r1, $r2]];
            }
            if ($r1 && !$r2) {
                $bt_arr = ['>=', $r1];
            }
            if (!$r1 && $r2) {
                $bt_arr = ['<=', $r2];
            }
            if (!empty($bt_arr)) {
                $map[isset($bt[3]) ? $bt[3] : $key] = $bt_arr;
            }
        }
        $page = isset($request['page']) ? $request['page'] : 1;
        return [
            'map'       => $map,
            'order'     => $order,
            'page_parm' => [
                'page'  => $page,
                'query' => $query
            ]
        ];


    }
}
