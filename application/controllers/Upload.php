<?php
    /* 
    name:ImgURL上传控制器
    author:xiaoz.me
    QQ:337003006
    */
    defined('BASEPATH') OR exit('No direct script access allowed');

    class Upload extends CI_Controller{
        //声明上传文件路径
        public $upload_path;
        //声明文件相对路径
        public $relative_path;
        public $image_lib;
        //当前时间
        public $date;
        //设置临时目录
        public $temp;
        //用户是否已经登录的属性
        protected $user;
        //构造函数
        public function __construct()
        {
            parent::__construct();
            //设置上传文件路径
            $this->upload_path = FCPATH.'imgs/'.date('Y',time()).'/'.date('m',time()).'/';
            $this->upload_path = str_replace('\\','/',$this->upload_path);
            $this->relative_path = "/imgs/".date('Y',time()).'/'.date('m',time()).'/';
            $this->relative_path = str_replace('\\','/',$this->relative_path);
            $this->temp = FCPATH.'data/temp/';
            //如果文件夹不存在，则创建文件夹
            if(!is_dir($this->upload_path)){
                //递归模式创建目录
                mkdir($this->upload_path,0777,TRUE);
            }
            $this->date = date('Y-m-d H:i',time());
            //加载辅助函数
            $this->load->helper('basic');
            $ip = get_ip();
            //加载基本类
            $this->load->library('basic');
            //加载查询模型
            $this->load->model('query','',TRUE);
            //用户已经登录
            if($this->basic->is_login(FALSE)){
                $this->user = 'admin';
            }
            else{
                $this->user = 'visitor';
                //限制上传数量
                if($this->query->uplimit($ip) === FALSE){
                    $this->error_msg("上传达到上限！");
                }
            }
        }
        //通用上传设置
        protected function config($upload_path = ''){
            //设置上传路径
            if($upload_path == ''){
                $upload_path = $this->upload_path;
            }
            // var_dump();
            $config['upload_path']      = $upload_path;
            $config['allowed_types']    = 'gif|jpg|png|bmp|webp';
            $config['max_size']     = 5120;
            $config['file_ext_tolower'] = TRUE; //文件名转换为小写
            $config['overwrite'] = TRUE;        //覆盖同名文件
            $config['encrypt_name']    = TRUE;         //随机命名图片
            return $config;
        }

        public function localhost(){
            //加载上传的配置选项
            $config = $this->config();
            //加载上传类
            $this->load->library('upload', $config);
            //上传失败
            if ( ! $this->upload->do_upload('file'))
            {
                $msg = $this->upload->display_errors();
                $msg = strip_tags($msg);
                
                $this->error_msg($msg);

            }
            else
            {
                $data = $this->upload->data();
                //加载模型
                $this->load->model('insert','',TRUE);
                $this->load->model('query','',TRUE);
                //计算文件MD5
                $file_name = md5_file($data['full_path']);
                $file_name = substr($file_name,8,16);
                //图片唯一ID
                $imgid = $file_name;
                $file_name = $file_name.$data['file_ext'];
                //新图片完整路径
                $full_path = $this->upload_path.$file_name;
                $full_path = str_replace("\\","/",$full_path);
                //新图片相对路径
                $relative_path = $this->relative_path.$file_name;
                //缩略图相对路径
                $thumbnail_path = $this->relative_path.$imgid.'_thumb'.$data['file_ext'];
                //获取域名
                $domain = $this->query->domain('localhost');
                
                //获取图片URL地址
                $url = $domain.$relative_path;
                //缩略图地址
                $thumbnail_url  = $domain.$thumbnail_path;
                
                //重命名文件
                rename($data['full_path'],$full_path);
                
                //生成缩略图
                $this->load->library('image');
                $this->image->thumbnail($full_path,290,175); 
                
                //查询图片是否上传过
                if($imginfo = $this->query->repeat($imgid)){
                    $id = $imginfo->id;
                    //重组数组
                    $info = array(
                        "code"              =>  200,
                        "id"                =>  $id,
                        "imgid"             =>  $imgid,
                        "relative_path"     =>  $relative_path,
                        "url"               =>  $url,
                        "thumbnail_url"     =>  $thumbnail_url,
                        "width"             =>  $data['image_width'],
                        "height"            =>  $data['image_height']
                    );
                    $this->succeed_msg($info);
                }
                //图片没有上传过
                else{
                    //需要插入到images表的数据
                    $datas = array(
                        "imgid"     =>  $imgid,
                        "path"      =>  $relative_path,
                        "thumb_path"=>  $thumbnail_path,
                        "storage"   =>  "localhost",
                        "ip"        =>  get_ip(),
                        "ua"        =>  get_ua(),
                        "date"      =>  $this->date,
                        "user"      =>  $this->user,
                        "level"     =>  'unknown'
                    );
                    //需要插入到imginfo表的数据
                    $imginfo = array(
                        "imgid"     =>  $imgid,
                        "mime"      =>  $data['file_type'],
                        "width"     =>  $data['image_width'],
                        "height"    =>  $data['image_height'],
                        "ext"       =>  $data['file_ext'],
                        "client_name"   =>  $data['client_name']
                    );
                    
                    //插入数据到img_images表
                    $id = $this->insert->images($datas);
                    $this->insert->imginfo($imginfo);
                    //重组数组
                    $info = array(
                        "code"              =>  200,
                        "id"                =>  $id,
                        "imgid"             =>  $imgid,
                        "relative_path"     =>  $relative_path,
                        "url"               =>  $url,
                        "thumbnail_url"     =>  $thumbnail_url,
                        "width"             =>  $data['image_width'],
                        "height"            =>  $data['image_height']
                    );
                }
                //var_dump($info);
                //exit;
                $this->succeed_msg($info);
            }
        }
        //上传成功返回json
        protected function succeed_msg($data){
            $info = json_encode($data);
            echo $info;
            exit;
        }
        //上传失败返回json
        protected function error_msg($msg){
            $data = array(
                "code"  =>  0,
                "msg"   =>  $msg
            );

            $data = json_encode($data);
            echo $data;
            exit;
        }
        //URL上传
        public function url(){
            $url = @$this->input->post('url',TRUE);
            $url = trim($url);
            //检测用户是否登录
            $this->load->library('basic');
            $this->basic->is_login(TRUE);
            //判断URL是否合法
            if(!filter_var($url, FILTER_VALIDATE_URL)){
                $this->error_msg('不是有效的URL地址！');
            }
            //继续执行
            //获取图片后缀名
            $url_arr = explode('.',$url);
            $ext = strtolower(end($url_arr));


            //判断是否是允许的后缀
            switch($ext){
                case 'png':
                case 'jpg':
                case 'jpeg':
                case 'bmp':
                case 'gif':
                case 'bmp':
                    break;
                default:
                    $this->error_msg('不是有效的图片地址！');
                    exit;
            }
            
            //继续执行
            //下载图片
            $pic_data = $this->basic->dl_pic($url);
            //临时文件路径
            $tmp_name = $this->temp.md5($url);
            //写入临时文件
            file_put_contents($tmp_name,$pic_data);
            //计算文件MD5
            $imgid = md5_file($tmp_name);
            $imgid = substr($imgid,8,16);
            $file_name = $imgid.'.'.$ext;
            //图片相对路径
            $relative_path = $this->relative_path.$file_name;
            $ext = '.'.$ext;
            //查询图片是否已经上传过
            if($this->query->repeat($imgid)){
                //删除临时文件
                unlink($tmp_name);
                $this->error_msg('文件已经上传过！');
                exit;
            }
            //没有上传过继续执行
            //复制图片到上传目录
            $full_path = $this->upload_path.$file_name;
            copy($tmp_name,$full_path);
            //删除临时文件
            unlink($tmp_name);
            //生成缩略图
            $this->load->library('image');
            $this->image->thumbnail($full_path,290,175); 

            //获取图片信息
            $img_info = getimagesize($full_path);
            //缩略图相对地址
            $thumbnail_path = $this->relative_path.$imgid.'_thumb'.$ext;

            //需要插入到images表的数据
            $datas = array(
                "imgid"     =>  $imgid,
                "path"      =>  $relative_path,
                "thumb_path"=>  $thumbnail_path,
                "storage"   =>  "localhost",
                "ip"        =>  get_ip(),
                "ua"        =>  get_ua(),
                "date"      =>  $this->date,
                "user"      =>  $this->user,
                "level"     =>  'unknown'
            );
            //需要插入到imginfo表的数据
            $imginfo = array(
                "imgid"     =>  $imgid,
                "mime"      =>  $img_info['mime'],
                "width"     =>  $img_info[0],
                "height"    =>  $img_info[1],
                "ext"       =>  $ext,
                "client_name"   =>  $file_name
            );
            //加载数据库模型
            $this->load->model('insert','',TRUE);
            //插入数据到img_images表
            $id = $this->insert->images($datas);
            $this->insert->imginfo($imginfo);
            //获取域名
            $domain = $this->query->domain('localhost');  
            //获取图片URL地址
            $url = $domain.$relative_path;
            //返回成功的信息
            $re = array(
                "code"  =>  200,
                "msg"   =>  $url
            );
            $re = json_encode($re);
            echo $re;
        }
        //粘贴上传
        public function parse(){
            $date = date('Y-m-d H:i:s',time());
            //临时文件名
            $tmp_name = get_ip().get_ua().$date;
            $tmp_name = md5($tmp_name);
            //图片临时路径
            $tmp_file = $this->temp.$tmp_name;
            //接接收ase64图片
            $picfile = $_POST['content'];
            $picfile = base64_decode($picfile);
            //echo $picfile;
            //存储图片
            file_put_contents($tmp_file, $picfile);

            //判断图片MIME类型
            if(!mime($tmp_file)){
                unlink($tmp_file);
                $this->error_msg('不允许的文件类型！');
                exit;
            }
            //继续执行
            //计算文件MD5
            $imgid = md5_file($tmp_file);
            $imgid = substr($imgid,8,16);
            //获取文件后缀
            $ext = ext($tmp_file);
            $file_name = $imgid.$ext;
            //图片相对路径
            $relative_path = $this->relative_path.$file_name;
            //图片完整路径
            $full_path = $this->upload_path.$file_name;
            //查询图片是否已经上传过
            if($this->query->repeat($imgid)){
                //删除临时文件
                unlink($tmp_file);
                $this->error_msg('文件已经上传过！');
                exit;
            }
            //没有上传过继续执行
            //复制图片到上传目录
            copy($tmp_file,$full_path);
            $file_name = $imgid.$ext;
            //删除临时文件
            unlink($tmp_file);
            //生成缩略图
            $this->load->library('image');
            $this->image->thumbnail($full_path,290,175);
            //缩略图地址
            $thumbnail_path = $this->relative_path.$imgid.'_thumb.'.$ext;

            //获取图片信息
            $img_info = getimagesize($full_path);

            //需要插入到images表的数据
            $datas = array(
                "imgid"     =>  $imgid,
                "path"      =>  $relative_path,
                "thumb_path"=>  $thumbnail_path,
                "storage"   =>  "localhost",
                "ip"        =>  get_ip(),
                "ua"        =>  get_ua(),
                "date"      =>  $this->date,
                "user"      =>  $this->user,
                "level"     =>  'unknown'
            );
            //需要插入到imginfo表的数据
            $imginfo = array(
                "imgid"     =>  $imgid,
                "mime"      =>  $img_info['mime'],
                "width"     =>  $img_info[0],
                "height"    =>  $img_info[1],
                "ext"       =>  $ext,
                "client_name"   =>  $file_name
            );
            //加载数据库模型
            $this->load->model('insert','',TRUE);
            //插入数据到img_images表
            $id = $this->insert->images($datas);
            $this->insert->imginfo($imginfo);
            //获取域名
            $domain = $this->query->domain('localhost');  
            //获取图片URL地址
            $url = $domain.$relative_path;
            $thumbnail_url = $domain.$this->relative_path.$imgid.'_thumb'.$ext;
            //返回成功的信息
            //重组数组
            $info = array(
                "code"              =>  200,
                "id"                =>  $id,
                "imgid"             =>  $imgid,
                "relative_path"     =>  $relative_path,
                "url"               =>  $url,
                "thumbnail_url"     =>  $thumbnail_url,
                "width"             =>  $img_info[0],
                "height"            =>  $img_info[1]
            );
            $this->succeed_msg($info);
            //echo $re;
        }
    }
?>