<?php

namespace app\mobile\controller;

use app\admin\model\Personnel;
use org\Jssdk;
use think\Cache;
use think\Config;
use think\File;
use think\Image;
use app\admin\model\AdminFilesModel;

/*
 * 用户中心控制器*/

class My extends Base
{
    private $path;

    /**
     * My constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->path = __DIR__ . DS;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        //取得所有数据
        $Lists = \app\admin\model\Personnel::getList(['id' => UID])->toArray();

        $List = $Lists['data'][0];


        $this->assign('a', $List);
        return $this->fetch();
    }

    /**
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 修改个人信息
     */
    public function edit()
    {

        $jssdkObj = new Jssdk(config('AppID'), config('AppSecret'));
        $res = $jssdkObj->getSignPackage();

        $a = \app\admin\model\Personnel::where(['id' => UID])->find();

        $appId = $res['appId'];
        $timestamp = $res['timestamp'];
        $nonceStr = $res['nonceStr'];
        $signature = $res['signature'];


        $this->assign(
            [
                'appId' => $appId,
                'timestamp' => $timestamp,
                'nonceStr' => $nonceStr,
                'signature' => $signature,
                'a'=>$a,
            ]
        );

        return $this->fetch();
    }

    /**
     * @param $name
     * @param $mobile
     * @return PersonnelModel
     * 修改个人信息接口
     */
    public function modification($name, $mobile,$email)
    {
        if (Personnel::where('id', '<>', UID)->where('mobile', $mobile)->select()) {
            return '手机号不能重复';
        }
        if (Personnel::where('id', '<>', UID)->where('email', $email)->select()) {
            return '邮箱不能重复';
        }



        $reuslt = Personnel::where('id', UID)
            ->update(['nickname' => $name, 'mobile' => $mobile,'email'=>$email]);

        return $reuslt;
    }


    /**
     * 考勤
     * @return mixed
     */
    public function my_attendance()
    {
        return $this->fetch();
    }

    /**
     * 渲染修改密码页面
     * @return mixed
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function change_password()
    {
        return $this->fetch();
    }

    /**
     * @param $pass1
     * @param $pass2
     * @param $pass3
     * @return string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @author
     * 修改密码
     */
    public function change_password2($pass1, $pass2, $pass3)
    {
        $a = new Personnel();
        $b = $a->where(['id' => UID])->find();
        $c = $b->password;


        if (password_verify($pass1, $c) || 1) {
            if ($pass3 == $pass2) {
                if ($pass3 == $pass1) {
                    return '修改的密码相对于原密码无任何修改';
                }
                if ($a->where('id', UID)->update(['password' => $a->setPasswordAttr($pass3)])) {
                    return '修改成功!';
                } else {
                    return '修改失败';
                }
            } else {
                return '两次输入密码不一致!';
            }
        } else {
            return '原密码错误!';
        }
    }


    /**
     * 清空系统缓存
     * @author 蔡伟明 <314013107@qq.com>
     */
    public function wipeCache()
    {
        if (!empty(config('cache.type'))) {
            Cache::clear();
            $this->success('清空成功');
        } else {
            $this->error('请在系统设置中选择需要清除的缓存类型');
        }
    }


    /**
     * 作废
     * 获取当天是不是周末以及节假日
     * @return bool|string
     */
    public function getCalendar()
    {
        $data = json_decode($this->get_php_file("Calendar.php"));
        //判断数据是否存在以及是否是当天;
        if (!(isset($data->date) && $data->date == $this->datedir())) {
            //得到当天信息
            $url = "http://api.goseek.cn/Tools/holiday";
            $a = json_decode(file_get_contents($url));
            if ($a->data == 0 || $a->data == 1 || $a->data == 2) {

                $data->data = $a->data;
                $data->date = $this->datedir();
                $this->set_php_file($this->path . "Calendar.php", json_encode($data));
            }
        } else {
            $data = $data->data;
        }
        switch ($data) {
            case 2:
                return "国家法定节假日";
            case 1:
                return "周末";
            case 0:
                session('Personnel_auth.uid');
                return '工作日';
        }
    }

    /**
     * 打卡
     * @param $a
     * @return string
     */
    public function clock_in($a)
    {
        if (json_decode($this->today2Work())->code == '2') {
            return json_encode(['code' => 0, 'msg' => '您今日签到已完成!']);
        } else if ($a > 0) {
            return json_encode(['code' => 0, 'msg' => '未在公司检测范围内!' . ',您离公司还有' . $a . '米。']);
        } else {
            $work = $this->todayWork();
            if ($work) {
                if ($work['off_time']) {
                    return json_encode(['code' => 0, 'msg' => '超过每日打卡上限!']);
                } else {
                    $data = ['off_time' => time()];
                    if (WorksModel::where(['uid' => UID])->update($data)) {
                        return json_encode(['code' => 2, 'msg' => '打卡下班成功']);
                    } else {
                        return json_encode(['code' => 0, 'msg' => '打卡下班失败']);
                    }
                }
            } else {
                $data = ['on_time' => time(), 'uid' => UID];

                if (WorksModel::create($data)) {
                    return json_encode(['code' => 1, 'msg' => '打卡上班成功!']);
                } else {
                    return json_encode(['code' => 0, 'msg' => '打卡上班失败']);
                }
            }
        }

    }

    /**
     * 用户当天打卡情况
     * @return boolean
     */
    protected function todayWork()
    {
        $time = time();
        $start_stime = strtotime(date('Y-m-d 0:0:0', $time)) - 1;
        $end_stime = strtotime(date('Y-m-d 23:59:59', $time)) + 1;

        $work = WorksModel::where("uid = " . UID . " and create_time > $start_stime and create_time < $end_stime")->find();
        return $work;
    }

    /**
     * 用户当天打卡情况
     * 这个方法是为了给前台显示用的
     * @param $serverId
     * @return string
     */
    public function today2Work()
    {
        $time = time();
        $start_stime = strtotime(date('Y-m-d 0:0:0', $time)) - 1;
        $end_stime = strtotime(date('Y-m-d 23:59:59', $time)) + 1;

        $work = WorksModel::where("uid = " . UID . " and create_time > $start_stime and create_time < $end_stime")->find();
        if (is_null($work)) {
            return json_encode(['code' => 1, 'msg' => '上班打卡']);
        } elseif ($work->off_time) {
            return json_encode(['code' => 2, 'msg' => '完成']);
        } elseif ($work->on_time) {
            return json_encode(['code' => 3, 'msg' => '下班打卡']);
        } else {
            return json_encode(['code' => 0, 'msg' => '系统错误']);
        }
    }


    /**
     * 将图片从微信服务器下载到服务器
     * @param $serverId
     * @return string
     */
    public function downLoad($serverId, $id='')
    {
        //获取token
        $jssdkObj = new Jssdk(Config::get('AppID'), config::get('AppSecret'));
        $token = $jssdkObj->getAccessToken();


//        根据微信JS接口上传了图片,会返回上面写的images.serverId（即media_id），填在下面即可
        $str = "https://api.weixin.qq.com/cgi-bin/media/get?access_token=" . $token . "&media_id=" . $serverId;
//
//        //获取微信“获取临时素材”接口返回来的内容（即刚上传的图片）
        $a = file_get_contents($str);

        //file_get_contents获得的响应头信息会存进当前作用域中的$http_response_header中，通过$http_response_header得到文件名；
        $str = $http_response_header[4];
        //$http_response_header[4]中并不是只有文件名，还有长度等信息，所以通过正则匹配文件名
        $pattern = '#"(.*?)"#i';
        preg_match_all($pattern, $str, $matches);
        //得到的文件名；MD5没别的原因，就是为了让文件名变短一点；
        $filename = md5(substr($matches[1][0], 0, strpos($matches[1][0], '.')));

        //还需要得到后缀名；
        $suffix = substr(strrchr($matches[1][0], '.'), 0);

        //判断是否有这个目录；
        is_dir(UPLOADS . $this->datedir() . "/") || mkdir(UPLOADS . $this->datedir() . "/");
//        以读写方式打开一个文件，若没有，则自动创建
        $resource = fopen(UPLOADS . $this->datedir() . "/" . $filename . $suffix, 'w+');

//        将图片内容写入上述新建的文件
        fwrite($resource, $a);
//        关闭资源
        fclose($resource);

        if ($id=='') {
            $id=$filename . $suffix;
        }

        //保存到数据库
        $file = new File(UPLOADS . $this->datedir() . "/" . $filename . $suffix);
        $file->file_name = $this->datedir() . "/" . $filename . $suffix;
        if ($result = $this->saveFile($file, $id, 'mobile')) {
            if ($id == $filename . $suffix) {
                $Personnel = new Personnel;
                $Personnel->save(['avatar'  => $result,],['id' => UID]);
            }
            return $result;
        }
        return false;

    }

    /**
     * 获取当前日期的字符串
     * @return mixed
     *
     */
    public function datedir()
    {
        $str = date("Y-m-d");
        return str_replace('-', '', $str);
    }

    /**
     * 外出考勤
     * jssdk
     * @return mixed
     */
    public function attendance()
    {

        $jssdkObj = new Jssdk(config('AppID'), config('AppSecret'));
        $res = $jssdkObj->getSignPackage();


        //伪装客户
//        $lists = Client::all();
        $lists = [

            ['id' => 1, 'title' => '呵呵','name' => '呵呵'],
            ['id' => 2, 'title' => '嘿嘿','name' => '嘿嘿'],

        ];

        $appId = $res['appId'];
        $timestamp = $res['timestamp'];
        $nonceStr = $res['nonceStr'];
        $signature = $res['signature'];


        $this->assign(
            array(
                'appId' => $appId,
                'timestamp' => $timestamp,
                'nonceStr' => $nonceStr,
                'signature' => $signature,
                'lists' => $lists,
            )
        );
        return $this->fetch('attendance');

    }

    /**
     * 外出考勤保存
     */
    public function attendance_save($wz, $kh, $ms)
    {

        $Personnel = new Attendance();
        $Personnel->uid = UID;
        $Personnel->wz = $wz;
        $Personnel->kh = $kh;
        $Personnel->ms = $ms;
        if ($Personnel->save()) {
            return $Personnel->id;
        } else {
            return false;
        }
    }

    /**
     * 出勤记录
     */
    public function attendance_record()
    {
        $Personnel = Attendance::where('uid', UID)->select();
        $arr = [];

        foreach ($Personnel as $kes => &$u) {
            $client = Client::all($u->kh);
            $u->kh = '';
            foreach ($client as $key => $c) {
                if (count($client) - 1 == $key) {
                    $u->kh .= $c->name;
                } else {
                    $u->kh .= $c->name . ',';
                }
            }
            $u->id = AdminFilesModel::where(['uid' => UID, 'name' => $u->id])->column('id');

            $arr[$kes] = $u->toArray();
        }


        //排序
        $data = array_column($arr, 'create_time');


        array_walk($data, function (&$value, $key) {
            $value = strtotime($value);
        });


        array_multisort($data, SORT_DESC, $arr);


        //jsssdk
        $jssdkObj = new Jssdk(config('AppID'), config('AppSecret'));
        $res = $jssdkObj->getSignPackage();

        $appId = $res['appId'];
        $timestamp = $res['timestamp'];
        $nonceStr = $res['nonceStr'];
        $signature = $res['signature'];

        $this->assign(
            array(
                'appId' => $appId,
                'timestamp' => $timestamp,
                'nonceStr' => $nonceStr,
                'signature' => $signature,
                'data_list' => $arr,
            )
        );

        return $this->fetch('lists');
    }

    /**
     * 保存附件
     * @param string $dir 附件存放的目录
     * @param string $from 来源
     * @param string $module 来自哪个模块
     * @author 蔡伟明 <314013107@qq.com>
     * @return string|\think\response\Json
     */
    private function saveFile($info, $name, $module = '', $dir = 'images')
    {
        // 附件大小限制
        $size_limit = $dir == 'images' ? config('upload_image_size') : config('upload_file_size');
        $size_limit = $size_limit * 1024;
        // 附件类型限制
        $ext_limit = $dir == 'images' ? config('upload_image_ext') : config('upload_file_ext');
        $ext_limit = $ext_limit != '' ? parse_attr($ext_limit) : '';
        // 缩略图参数
        $thumb = '';
        // 水印参数
        $watermark = '';
        if ($info) {
            // 缩略图路径
            $thumb_path_name = '';
            // 图片宽度
            $img_width = '';
            // 图片高度
            $img_height = '';
            if ($dir == 'images') {
                $img = Image::open($info);
                $img_width = $img->width();
                $img_height = $img->height();
                // 水印功能
                if ($watermark == '') {
                    if (config('upload_thumb_water') == 1 && config('upload_thumb_water_pic') > 0) {
                        $this->create_water($info->getRealPath(), config('upload_thumb_water_pic'));
                    }
                } else {
                    if (strtolower($watermark) != 'close') {
                        list($watermark_img, $watermark_pos, $watermark_alpha) = explode('|', $watermark);
                        $this->create_water($info->getRealPath(), $watermark_img, $watermark_pos, $watermark_alpha);
                    }
                }


                // 生成缩略图
                if ($thumb == '') {
                    if (config('upload_image_thumb') != '') {
                        $thumb_path_name = $this->create_thumb($info, $info->getPathInfo()->getfileName(), $info->getFilename());
                    }
                } else {
                    if (strtolower($thumb) != 'close') {
                        list($thumb_size, $thumb_type) = explode('|', $thumb);
                        $thumb_path_name = $this->create_thumb($info, $info->getPathInfo()->getfileName(), $info->getFilename(), $thumb_size, $thumb_type);
                    }
                }
            }


            // 获取附件信息
            $file_info = [
                'uid' => UID,
                'name' => $name,
                'mime' => $info->getMime(),
                'path' => 'uploads/' . $dir . '/' . str_replace('\\', '/', $info->file_name),
                'ext' => $info->getExtension(),
                'size' => $info->getSize(),
                'md5' => $info->hash('md5'),
                'sha1' => $info->hash('sha1'),
                'thumb' => $thumb_path_name,
                'module' => $module,
                'width' => $img_width,
                'height' => $img_height,
            ];

            // 写入数据库
            if ($file_add = AdminFilesModel::create($file_info)) {
                $file_path = PUBLIC_PATH . $file_info['path'];
                return $file_add['id'];
                return json([
                    'code' => 1,
                    'info' => '上传成功',
                    'class' => 'success',
                    'id' => $file_add['id'],
                    'path' => $file_path
                ]);

            } else {
                return false;
                return json(['code' => 0, 'class' => 'danger', 'info' => '上传失败']);
            }
        } else {
            return false;
            return json(['code' => 0, 'class' => 'danger', 'info' => $file->getError()]);
        }
    }

    /**
     * 获取文件中的内容
     * @param $filename
     * @return string
     */
    private function get_php_file($filename)
    {
        return trim(substr(file_get_contents($this->path . $filename), 0));
    }

    /**
     * 将内容写入文件
     * @param $filename
     * @param $content
     */
    private function set_php_file($filename, $content)
    {
        $fp = fopen($filename, "w");
        fwrite($fp, $content);
        fclose($fp);
    }

}

